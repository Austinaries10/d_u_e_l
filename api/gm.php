<?php
// Game master settings for AR Duel: the Forbidden & Limited list, which booster packs are on sale,
// and limited-time pack events. Kept in server/data/gm.json (blocked from the web, not touched by deploys).
// The GM password is 'gm_password' in server/config.php.
//
//   GET  ?action=config                    public settings, as the game uses them
//   POST ?action=load     {password}       everything, for the GM page
//   POST ?action=save     {password, config}
//   POST ?action=official {password}       the current official TCG Forbidden & Limited list from YGOPRODeck
//   POST ?action=players  {password, q}    players (name, coins, cards, last seen), newest activity first
//   POST ?action=player   {password, name} one player's account and save
//   POST ?action=updatePlayer {password, name, state?, banned?, newPassword?}
//                                          change a player's save; their game reloads it the next time it syncs

declare(strict_types=1);

const DATA = __DIR__ . '/../server/data';
const GM_FILE = DATA . '/gm.json';
const STATUS = ['Forbidden' => 0, 'Limited' => 1, 'Semi-Limited' => 2];
const USERS = __DIR__ . '/../server/data/users';

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
    // the shop: which packs are sold, at what price, between which times (blank start/end = always)
    $shop = [];
    foreach ((array)($c['shop'] ?? []) as $l) {
        if (!preg_match('/^[A-Z0-9-]{2,8}$/', (string)($l['code'] ?? ''))) {
            continue;
        }
        $start = ($l['start'] ?? '') !== '' ? (strtotime((string)$l['start']) ?: fail(400, 'A shop pack has a bad start time.')) : null;
        $end = ($l['end'] ?? '') !== '' ? (strtotime((string)$l['end']) ?: fail(400, 'A shop pack has a bad end time.')) : null;
        if ($start && $end && $end <= $start) {
            fail(400, "The pack {$l['code']} must end after it starts.");
        }
        $shop[] = ['code' => (string)$l['code'], 'price' => max(0, min(100000, (int)($l['price'] ?? 100))), 'start' => $start ? gmdate('c', $start) : '', 'end' => $end ? gmdate('c', $end) : '', 'enabled' => !empty($l['enabled'])];
    }
    // starter decks sold whole in the shop: on or off, and their price
    $starters = [];
    foreach ((array)($c['starterShop'] ?? []) as $l) {
        if (preg_match('/^[A-Z0-9]{2,8}$/', (string)($l['code'] ?? ''))) {
            $starters[] = ['code' => (string)$l['code'], 'price' => max(0, min(100000, (int)($l['price'] ?? 800))), 'enabled' => !empty($l['enabled'])];
        }
    }
    // rewarded ads: watch an ad for coins (off until the GM picks a network and enters its ID)
    $a = (array)($c['ads'] ?? []);
    $prov = in_array($a['provider'] ?? '', ['test', 'adinplay', 'gamedistribution', 'h5'], true) ? $a['provider'] : 'test';
    $ads = ['enabled' => !empty($a['enabled']), 'provider' => $prov, 'id' => preg_replace('/[^A-Za-z0-9_.\/-]/', '', (string)($a['id'] ?? '')),
        'coins' => max(1, min(10000, (int)($a['coins'] ?? 100))), 'perDay' => max(1, min(50, (int)($a['perDay'] ?? 5))), 'gap' => max(0, min(3600, (int)($a['gap'] ?? 60)))];
    return [
        'ads' => $ads,
        'shop' => $shop,
        'starterShop' => $starters,
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
    echo json_encode(['banlist' => $c['banlist'], 'disabledPacks' => $c['disabledPacks'], 'events' => $events, 'ads' => $c['ads'] ?? null, 'now' => gmdate('c', $now)]);
    exit;
}

if ($action === 'load') {
    requireGm($in);
    $c = gmConfig();
    // until the GM saves a shop of their own, show the default lineup the game is selling
    $c['shop'] ??= array_map(fn($code) => ['code' => $code, 'price' => 100, 'start' => '', 'end' => '', 'enabled' => true], ['LOB', 'MRD', 'SRL', 'PSV', 'LON', 'LOD', 'PGD', 'MFC']);
    echo json_encode($c + ['now' => gmdate('c')]);
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
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'ARDuel/1.0 (+https://duel.cck8.my)']);
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

if ($action === 'players') {
    requireGm($in);
    $q = strtolower(trim((string)($in['q'] ?? '')));
    $out = [];
    foreach (glob(USERS . '/*.json') ?: [] as $f) {
        if (basename($f)[0] === '_' || ($q !== '' && !str_contains(basename($f, '.json'), $q))) { // _emails.json is the email index
            continue;
        }
        $u = json_decode((string)file_get_contents($f), true) ?: [];
        $cards = $u['state']['cards'] ?? [];
        $out[] = ['name' => $u['name'] ?? basename($f, '.json'), 'created' => $u['created'] ?? 0, 'seen' => $u['seen'] ?? 0, 'banned' => !empty($u['banned']),
            'coins' => $u['state']['wallet']['coins'] ?? 0, 'unique' => is_array($cards) ? count($cards) : 0,
            'total' => is_array($cards) ? array_sum(array_map(fn($e) => (int)(is_array($e) && array_is_list($e) ? ($e[0] ?? 0) : ($e['n'] ?? 0)), $cards)) : 0, // [copies, rarity], or an older { card, n, r }
            'wins' => array_sum(array_map('intval', (array)($u['state']['charWins'] ?? [])))];
    }
    usort($out, fn($a, $b) => $b['seen'] <=> $a['seen']);
    echo json_encode(['players' => array_slice($out, 0, 300), 'count' => count($out)]);
    exit;
}

// Lock a player's file, let $fn change it, write it back
function editPlayer(string $name, callable $fn): array
{
    preg_match('/^[A-Za-z0-9_]{3,20}$/', $name) || fail(400, 'Bad player name.');
    $file = USERS . '/' . strtolower($name) . '.json';
    is_file($file) || fail(404, 'No player with that name.');
    $h = fopen($file, 'c+');
    flock($h, LOCK_EX);
    $u = json_decode((string)stream_get_contents($h), true) ?: fail(500, 'The account file is damaged.');
    $u = $fn($u);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($u));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $u;
}

if ($action === 'player') {
    requireGm($in);
    $u = editPlayer((string)($in['name'] ?? ''), fn($u) => $u);
    echo json_encode(['name' => $u['name'], 'created' => $u['created'] ?? 0, 'seen' => $u['seen'] ?? 0, 'banned' => !empty($u['banned']), 'rev' => $u['rev'] ?? 0, 'state' => $u['state'] ?? null,
        'email' => $u['email'] ?? '', 'verified' => !empty($u['verified'])]);
    exit;
}

if ($action === 'updatePlayer') {
    requireGm($in);
    $u = editPlayer((string)($in['name'] ?? ''), function (array $u) use ($in) {
        if (isset($in['state']) && is_array($in['state'])) {
            strlen(json_encode($in['state'])) <= 3000000 || fail(413, 'That save is too large.');
            $u['state'] = $in['state'];
            $u['rev'] = (int)($u['rev'] ?? 0) + 1; // the player's game sees a newer save and reloads it
        }
        if (isset($in['banned'])) {
            $u['banned'] = (bool)$in['banned'];
            if ($u['banned']) {
                $u['tokens'] = [];
            }
        }
        if (($in['newPassword'] ?? '') !== '') {
            strlen((string)$in['newPassword']) >= 6 || fail(400, 'Use a password of at least 6 characters.');
            $u['hash'] = password_hash((string)$in['newPassword'], PASSWORD_DEFAULT);
            $u['tokens'] = [];
        }
        return $u;
    });
    echo json_encode(['ok' => true, 'rev' => $u['rev'] ?? 0]);
    exit;
}

fail(400, 'Unknown action.');
