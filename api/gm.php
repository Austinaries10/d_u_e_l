<?php
// Game master settings for AR Duel: the Forbidden & Limited list, which booster packs are on sale,
// and limited-time pack events. Kept in server/data/gm.json (blocked from the web, not touched by deploys).
// The GM password is 'gm_password' in server/config.php.
//
//   GET  ?action=config                    public settings, as the game uses them
//   POST ?action=load     {password}       everything, for the GM page
//   POST ?action=save     {password, config}
//   POST ?action=official {password}       the current official TCG Forbidden & Limited list from YGOPRODeck

declare(strict_types=1);

const DATA = __DIR__ . '/../server/data';
const GM_FILE = DATA . '/gm.json';
const STATUS = ['Forbidden' => 0, 'Limited' => 1, 'Semi-Limited' => 2];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

function gmConfig(): array
{
    $c = is_file(GM_FILE) ? json_decode((string)file_get_contents(GM_FILE), true) : null;
    return (is_array($c) ? $c : []) + ['banlist' => ['name' => '', 'cards' => []], 'disabledPacks' => [], 'events' => [], 'updated' => 0];
}

// Event fields the game needs; the cards of a custom event are served by api/ygo.php when a pack is opened
function publicEvent(array $e): array
{
    return [
        'id' => $e['id'], 'name' => $e['name'], 'about' => $e['about'], 'start' => $e['start'], 'end' => $e['end'], 'price' => $e['price'],
        'limit' => $e['limit'], 'set' => $e['set'], 'image' => $e['image'], 'cards' => count($e['cards'] ?? []),
    ];
}

function requireGm(array $in): void
{
    $cfg = is_file(__DIR__ . '/../server/config.php') ? include __DIR__ . '/../server/config.php' : [];
    $password = is_array($cfg) ? (string)($cfg['gm_password'] ?? '') : '';
    if ($password === '') {
        fail(503, "GM is not set up yet. Add 'gm_password' => 'your password' to server/config.php on the hosting.");
    }
    if (!hash_equals($password, (string)($in['password'] ?? ''))) {
        sleep(1); // slows down guessing
        fail(403, 'Wrong GM password.');
    }
}

// Check and tidy a config sent by the GM page
function clean(array $c): array
{
    $cards = [];
    foreach ((array)($c['banlist']['cards'] ?? []) as $name => $n) {
        $name = trim((string)$name);
        if ($name !== '' && in_array($n, [0, 1, 2], true)) {
            $cards[$name] = $n;
        }
    }
    ksort($cards, SORT_NATURAL | SORT_FLAG_CASE);
    $events = [];
    foreach ((array)($c['events'] ?? []) as $e) {
        $start = strtotime((string)($e['start'] ?? '')) ?: fail(400, 'Every event needs a start time.');
        $end = strtotime((string)($e['end'] ?? '')) ?: fail(400, 'Every event needs an end time.');
        $end > $start || fail(400, 'The event "' . mb_substr((string)($e['name'] ?? ''), 0, 60) . '" must end after it starts.');
        $set = preg_match('/^[A-Z0-9-]{2,8}$/', (string)($e['set'] ?? '')) ? (string)$e['set'] : null;
        $list = [];
        foreach ((array)($e['cards'] ?? []) as $card) {
            if (isset($card['id'], $card['name'], $card['kind']) && is_array($card['rarities'] ?? null) && $card['rarities']) {
                $list[] = $card;
            }
        }
        $set || count($list) >= 5 || fail(400, 'A custom event pack needs at least 5 cards, or choose a real set.');
        $events[] = [
            'id' => preg_match('/^[a-z0-9]{6,16}$/', (string)($e['id'] ?? '')) ? $e['id'] : bin2hex(random_bytes(5)),
            'name' => mb_substr(trim((string)($e['name'] ?? '')), 0, 60) ?: fail(400, 'Every event needs a name.'),
            'about' => mb_substr(trim((string)($e['about'] ?? '')), 0, 300),
            'start' => gmdate('c', $start), 'end' => gmdate('c', $end),
            'enabled' => !empty($e['enabled']),
            'price' => max(0, min(100000, (int)($e['price'] ?? 100))),
            'limit' => max(0, min(1000, (int)($e['limit'] ?? 0))),
            'set' => $set, 'image' => isset($e['image']) && preg_match('/^\d{1,10}$/', (string)$e['image']) ? (int)$e['image'] : null,
            'cards' => $set ? [] : $list,
        ];
    }
    return [
        'banlist' => ['name' => mb_substr(trim((string)($c['banlist']['name'] ?? '')), 0, 60), 'cards' => (object)$cards],
        'disabledPacks' => array_values(array_filter((array)($c['disabledPacks'] ?? []), fn($x) => is_string($x) && preg_match('/^[A-Z0-9]{2,6}$/', $x))),
        'events' => $events,
        'updated' => time(),
    ];
}

$action = (string)($_GET['action'] ?? '');
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];

if ($action === 'config') {
    $c = gmConfig();
    $now = time();
    // events that are switched on and have not ended yet (upcoming ones show as "coming soon")
    $events = array_values(array_map('publicEvent', array_filter($c['events'], fn($e) => $e['enabled'] && strtotime($e['end']) > $now)));
    echo json_encode(['banlist' => $c['banlist'], 'disabledPacks' => $c['disabledPacks'], 'events' => $events, 'now' => gmdate('c', $now)]);
    exit;
}

if ($action === 'load') {
    requireGm($in);
    echo json_encode(gmConfig() + ['now' => gmdate('c')]);
    exit;
}

if ($action === 'save') {
    requireGm($in);
    $c = clean(is_array($in['config'] ?? null) ? $in['config'] : fail(400, 'Nothing to save.'));
    is_dir(DATA) || @mkdir(DATA, 0755, true);
    $tmp = GM_FILE . '.tmp';
    (file_put_contents($tmp, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) && rename($tmp, GM_FILE)) || fail(500, 'Could not save. Check that server/data can be written.');
    echo json_encode(['ok' => true, 'config' => $c]);
    exit;
}

if ($action === 'official') {
    requireGm($in);
    $ch = curl_init('https://db.ygoprodeck.com/api/v7/cardinfo.php?banlist=tcg');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'SmartEyes-ARDuel/1.0 (+https://smarteyes.cck8.my)']);
    $json = json_decode((string)curl_exec($ch), true);
    curl_close($ch);
    is_array($json['data'] ?? null) || fail(502, 'Could not reach the card database. Try again.');
    $cards = [];
    foreach ($json['data'] as $c) {
        $s = STATUS[$c['banlist_info']['ban_tcg'] ?? ''] ?? null;
        if ($s !== null) {
            $cards[html_entity_decode((string)$c['name'], ENT_QUOTES)] = $s;
        }
    }
    echo json_encode(['cards' => $cards]);
    exit;
}

fail(400, 'Unknown action.');
