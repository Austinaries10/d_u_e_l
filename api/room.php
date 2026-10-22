<?php
// Online duel rooms for AR Duel. A room is an ordered list of moves; each phone posts its own moves
// and polls for the other's. Rooms are files in server/cache/rooms (blocked from the web) and expire after a day.
//
//   POST ?action=create  {name}               -> {code, token, seat: 0}
//   POST ?action=join    {code, name}         -> {code, token, seat: 1}
//   POST ?action=send    {code, token, msg}   -> {seq}
//   GET  ?action=poll&code=&token=&after=N    -> {msgs: [{seq, seat, msg}], players: [names], more}

declare(strict_types=1);

const ROOMS = __DIR__ . '/../server/cache/rooms';
const ROOM_TTL = 86400;
const MAX_MSGS = 4000;
const MAX_MSG = 200000; // bytes; the first message carries the whole deck
const PAGE = 300;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

// Run $fn on the room's data with the file locked; whatever $fn returns as 'save' is written back
function withRoom(string $code, callable $fn): array
{
    preg_match('/^[A-Z2-9]{5}$/', $code) || fail(404, 'No room with that code.');
    $file = ROOMS . "/$code.json";
    is_file($file) || fail(404, 'No room with that code. Check the code, or the room may have expired.');
    $h = fopen($file, 'c+') ?: fail(500, 'Could not open the room.');
    flock($h, LOCK_EX);
    $room = json_decode((string)stream_get_contents($h), true);
    if (!is_array($room) || $room['created'] < time() - ROOM_TTL) {
        flock($h, LOCK_UN);
        fclose($h);
        fail(404, 'This room has expired. Create a new one.');
    }
    $out = $fn($room);
    if (isset($out['save'])) {
        ftruncate($h, 0);
        rewind($h);
        fwrite($h, json_encode($out['save']));
        fflush($h);
    }
    flock($h, LOCK_UN);
    fclose($h);
    return $out['reply'];
}
function seatOf(array $room, string $token): int
{
    foreach ($room['players'] as $seat => $p) {
        if (hash_equals($p['token'], $token)) {
            return $seat;
        }
    }
    fail(403, 'You are not in this room.');
}
$cleanName = fn($n) => mb_substr(trim(preg_replace('/[\x00-\x1F]/u', '', (string)$n)), 0, 20) ?: 'Duelist';

$action = (string)($_GET['action'] ?? '');
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];

if ($action === 'create') {
    is_dir(ROOMS) || @mkdir(ROOMS, 0755, true);
    // clear out expired rooms now and then
    if (random_int(1, 20) === 1) {
        foreach (glob(ROOMS . '/*.json') ?: [] as $f) {
            if (filemtime($f) < time() - ROOM_TTL) {
                @unlink($f);
            }
        }
    }
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $letters[random_int(0, strlen($letters) - 1)];
        }
        $file = ROOMS . "/$code.json";
    } while (is_file($file));
    $token = bin2hex(random_bytes(16));
    $room = ['created' => time(), 'players' => [['name' => $cleanName($in['name'] ?? ''), 'token' => $token]], 'msgs' => []];
    file_put_contents($file, json_encode($room), LOCK_EX) || fail(500, 'Could not create the room.');
    echo json_encode(['code' => $code, 'token' => $token, 'seat' => 0]);
    exit;
}

if ($action === 'join') {
    $code = strtoupper(trim((string)($in['code'] ?? '')));
    $token = bin2hex(random_bytes(16));
    echo json_encode(withRoom($code, function (array $room) use ($token, $in, $cleanName, $code) {
        count($room['players']) < 2 || fail(409, 'This room already has two players.');
        $room['players'][] = ['name' => $cleanName($in['name'] ?? ''), 'token' => $token];
        return ['save' => $room, 'reply' => ['code' => $code, 'token' => $token, 'seat' => 1]];
    }));
    exit;
}

if ($action === 'send') {
    $msg = $in['msg'] ?? null;
    is_array($msg) && isset($msg['t']) || fail(400, 'Bad move.');
    strlen(json_encode($msg)) <= MAX_MSG || fail(413, 'That move is too large.');
    echo json_encode(withRoom((string)($in['code'] ?? ''), function (array $room) use ($in, $msg) {
        $seat = seatOf($room, (string)($in['token'] ?? ''));
        count($room['msgs']) < MAX_MSGS || fail(409, 'This room is full. Start a new one.');
        $seq = count($room['msgs']) + 1;
        $room['msgs'][] = ['seq' => $seq, 'seat' => $seat, 'msg' => $msg];
        return ['save' => $room, 'reply' => ['seq' => $seq]];
    }));
    exit;
}

if ($action === 'poll') {
    $after = max(0, (int)($_GET['after'] ?? 0));
    echo json_encode(withRoom((string)($_GET['code'] ?? ''), function (array $room) use ($after) {
        seatOf($room, (string)($_GET['token'] ?? ''));
        $msgs = array_slice($room['msgs'], $after, PAGE);
        return ['reply' => ['msgs' => $msgs, 'players' => array_column($room['players'], 'name'), 'more' => count($room['msgs']) > $after + PAGE]];
    }));
    exit;
}

fail(400, 'Unknown action.');
