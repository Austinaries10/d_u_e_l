<?php
// Card database and card images for AR Duel, from YGOPRODeck (https://ygoprodeck.com/api-guide/).
// Everything fetched is cached on this server (server/cache, which is blocked from the web),
// so each image is downloaded from YGOPRODeck only once, as their guide asks. Searches run on a
// copy of the whole card database kept here and refreshed daily.
//
//   ?action=search&q=dragon          cards whose name or card text contains the words (name matches first), 40 at a time
//        &kind=monster|spell|trap &attribute=LIGHT &race=Dragon &level=8 &archetype=Blue-Eyes
//        &sort=name|atk|def|level|type &offset=40
//   ?action=archetypes               all archetype names
//   ?action=packs                    the booster packs sold in the shop
//   ?action=set&code=LOB             every card in a set, with its rarities in that set
//   ?action=img&set=LOB              a set's pack image
//   ?action=names&n=A|B|C            cards with exactly these names
//   ?action=img&id=46986414          card image  (&size=small or &size=crop for the artwork only)

declare(strict_types=1);

const API = 'https://db.ygoprodeck.com/api/v7/cardinfo.php';
const IMG = 'https://images.ygoprodeck.com/images/';
const CACHE = __DIR__ . '/../server/cache';
const DATA_TTL = 86400; // card data is refreshed after a day

function fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message]);
    exit;
}

function fetchUrl(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'SmartEyes-ARDuel/1.0 (+https://smarteyes.cck8.my)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return is_string($body) && ($code === 200 || $code === 400) ? $body : null;
}

function cacheDir(string $sub): string
{
    $dir = CACHE . '/' . $sub;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

// Only the fields the game uses
function trimCard(array $c): array
{
    $type = (string)($c['type'] ?? '');
    $kind = str_contains($type, 'Spell') ? 'spell' : (str_contains($type, 'Trap') ? 'trap' : 'monster');
    return [
        'id' => (int)$c['id'],
        'name' => (string)$c['name'],
        'kind' => $kind,
        'attribute' => (string)($c['attribute'] ?? ''),
        'level' => isset($c['level']) ? (int)$c['level'] : (isset($c['linkval']) ? (int)$c['linkval'] : null),
        'atk' => isset($c['atk']) && (int)$c['atk'] >= 0 ? (int)$c['atk'] : null, // -1 means "?"
        'def' => isset($c['def']) && (int)$c['def'] >= 0 ? (int)$c['def'] : null,
        'race' => (string)($c['race'] ?? ''),
        'archetype' => (string)($c['archetype'] ?? ''),
        'type' => trim(($kind === 'monster' ? ($c['race'] ?? '') . ' / ' : ($c['race'] ?? '') . ' ') . preg_replace('/ (Monster|Card)$/', '', $type)),
        'summary' => trim((string)($c['desc'] ?? '')),
    ];
}

// Real booster sets sold in the shop, by set code
const PACKS = ['LOB', 'MRD', 'SRL', 'PSV', 'LON', 'LOD', 'PGD', 'MFC', 'DCR', 'IOC', 'AST', 'SOD', 'RDS', 'FET', 'TLM', 'CRV', 'EEN', 'SOI', 'EOJ', 'POTD', 'CDIP',
    'STON', 'FOTB', 'TAEV', 'GLAS', 'PTDN', 'LODT', 'TDGS', 'CSOC', 'DLG1', 'CRMS', 'RGBT', 'ANPR', 'SOVR', 'ABPF', 'TSHD', 'DREV', 'STBL', 'STOR', 'EXVC', 'GENF',
    'PHSW', 'ORCS', 'GAOV', 'REDU', 'ABYR', 'CBLZ', 'LTGY', 'NUMH', 'JOTL', 'SHSP', 'LVAL', 'PRIO', 'DUEA', 'NECH', 'SECE', 'THSF', 'CROS', 'CORE', 'HSRD', 'DOCS',
    'BOSH', 'WIRA', 'SHVI', 'TDIL', 'DRL3', 'INOV', 'RATE', 'FUEN', 'DUSA', 'MACR', 'PEVO', 'BLLR', 'COTD', 'CIBR', 'SPWA', 'EXFO', 'FLOD', 'DASA', 'BLRR', 'SHVA',
    'HISU', 'SAST', 'DANE', 'BLHR', 'RIRA', 'FIGA', 'CHIM', 'MYFI', 'IGAS', 'DUOV', 'SESL', 'ETCO', 'BLAR', 'ROTD', 'PHRA', 'GEIM', 'BLVO', 'ANGU', 'LIOV', 'KICO',
    'DAMA', 'BROL', 'GRCR', 'BACH', 'DIFO', 'POTE', 'TAMA', 'DABL', 'MAMA', 'BLCR', 'AMDE', 'PHHY', 'MAZE', 'CYAC', 'WISU', 'BLMR', 'DUNE', 'AGOV', 'VASM', 'MZMI',
    'PHNI', 'LEDE', 'BLTR', 'INFO', 'ROTA', 'CRBR', 'SUDA', 'MZTM', 'ALIN', 'JUSH', 'DOOD', 'PHRE', 'BPRO', 'MZMU', 'BLZD', 'CORI', 'MAMO', 'BETB'];
// Starter decks, one of which is given to a new player
const STARTERS = ['SDY', 'SDK', 'SDJ', 'SDP'];

// All sets in the database (name, code, date), cached for a day
function allSets(): array
{
    $file = cacheDir('data') . '/cardsets.json';
    if (!is_file($file) || filemtime($file) < time() - DATA_TTL) {
        $body = fetchUrl('https://db.ygoprodeck.com/api/v7/cardsets.php') ?? fail(502, 'Could not reach the card database. Try again.');
        $sets = [];
        foreach (json_decode($body, true) ?: [] as $x) {
            $code = (string)($x['set_code'] ?? '');
            $name = html_entity_decode((string)($x['set_name'] ?? ''), ENT_QUOTES);
            // the original release, not a later special or anniversary edition with the same code
            if ($code === '' || isset($sets[$code]) || preg_match('/Anniversary|Special Edition/i', $name)) {
                continue;
            }
            $sets[$code] = ['code' => $code, 'name' => $name, 'date' => (string)($x['tcg_date'] ?? '')];
        }
        @file_put_contents($file, json_encode($sets), LOCK_EX);
    }
    return json_decode((string)file_get_contents($file), true) ?: [];
}

// The whole card database, trimmed, sorted by name, kept as a PHP file so OPcache holds it in memory.
// Rebuilt once a day by whichever request comes first; others keep using the old copy meanwhile.
const DB_FILE = CACHE . '/data/db.php';
function cardDb(): array
{
    $fresh = fn() => is_file(DB_FILE) && filemtime(DB_FILE) > time() - DATA_TTL;
    if (!$fresh()) {
        $lock = fopen(cacheDir('data') . '/db.lock', 'c');
        if ($lock && flock($lock, is_file(DB_FILE) ? LOCK_EX | LOCK_NB : LOCK_EX)) {
            clearstatcache();
            if (!$fresh() && !buildDb() && is_file(DB_FILE)) {
                touch(DB_FILE, time() - DATA_TTL + 3600); // the database is down: try again in an hour
            }
            flock($lock, LOCK_UN);
        }
    }
    is_file(DB_FILE) || fail(502, 'Could not reach the card database. Try again.');
    return require DB_FILE;
}
function buildDb(): bool
{
    @set_time_limit(120);
    $cards = [];
    for ($offset = 0, $total = 1; $offset < $total; $offset += 2000) {
        $json = json_decode((string)fetchUrl(API . '?' . http_build_query(['num' => 2000, 'offset' => $offset, 'sort' => 'id'])), true);
        if (!is_array($json['data'] ?? null)) {
            return false;
        }
        foreach ($json['data'] as $c) {
            $cards[] = trimCard($c);
        }
        $total = (int)($json['meta']['total_rows'] ?? 0);
        unset($json);
    }
    if (count($cards) < 10000) {
        return false;
    }
    usort($cards, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    $tmp = DB_FILE . '.tmp';
    if (!file_put_contents($tmp, '<?php return ' . var_export($cards, true) . ';') || !rename($tmp, DB_FILE)) {
        return false;
    }
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(DB_FILE, true);
    }
    return true;
}

// Fetch (or read from the cache) one API query: the trimmed cards and the total number of matches
function query(string $query): array
{
    $file = cacheDir('data') . '/' . md5('v2' . $query) . '.json'; // v2: cards carry race and archetype
    if (is_file($file) && filemtime($file) > time() - DATA_TTL) {
        return json_decode((string)file_get_contents($file), true) ?: ['cards' => [], 'total' => 0];
    }
    $body = fetchUrl(API . '?' . $query);
    if ($body === null) {
        fail(502, 'Could not reach the card database. Try again.');
    }
    $json = json_decode($body, true);
    $list = array_map('trimCard', is_array($json['data'] ?? null) ? $json['data'] : []);
    $result = ['cards' => $list, 'total' => (int)($json['meta']['total_rows'] ?? count($list))];
    @file_put_contents($file, json_encode($result), LOCK_EX);
    return $result;
}
function cards(string $query): array { return query($query)['cards']; }

$action = (string)($_GET['action'] ?? '');

if ($action === 'img') {
    $id = (string)($_GET['id'] ?? '');
    $size = (string)($_GET['size'] ?? '');
    if (!isset($_GET['set']) && !preg_match('/^\d{1,10}$/', $id)) {
        fail(400, 'Bad card id.');
    }
    $folder = ['small' => 'cards_small', 'crop' => 'cards_cropped'][$size] ?? 'cards';
    if (isset($_GET['set'])) {
        preg_match('/^[A-Z0-9]{2,5}$/', (string)$_GET['set']) || fail(400, 'Bad set code.');
        [$folder, $id] = ['sets', (string)$_GET['set']];
    }
    $file = cacheDir('img/' . $folder) . "/$id.jpg";
    if (!is_file($file)) {
        $body = fetchUrl(IMG . "$folder/$id.jpg");
        if ($body === null || strlen($body) < 1000 || substr($body, 0, 2) !== "\xFF\xD8") {
            fail(404, 'No image for that card.');
        }
        @file_put_contents($file, $body, LOCK_EX);
    }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($file);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if ($action === 'search') {
    $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
    if ($q !== '' && (mb_strlen($q) < 2 || mb_strlen($q) > 60)) {
        fail(400, 'Type at least 2 letters.');
    }
    $kind = (string)($_GET['kind'] ?? '');
    $attribute = strtoupper((string)($_GET['attribute'] ?? ''));
    $race = (string)($_GET['race'] ?? '');
    $level = (string)($_GET['level'] ?? '');
    $archetype = (string)($_GET['archetype'] ?? '');
    if ($q === '' && $kind === '' && $attribute === '' && $race === '' && $level === '' && $archetype === '') {
        fail(400, 'Type a name or choose a filter.');
    }
    // cards whose name has the words come first, then those whose card text has them
    $byName = $byText = [];
    foreach (cardDb() as $c) {
        if (($kind !== '' && $c['kind'] !== $kind) || ($attribute !== '' && $c['attribute'] !== $attribute) || ($race !== '' && $c['race'] !== $race)
            || ($level !== '' && $c['level'] !== (int)$level) || ($archetype !== '' && $c['archetype'] !== $archetype)) {
            continue;
        }
        if ($q === '' || str_contains(mb_strtolower($c['name']), $q)) {
            $byName[] = $c;
        } elseif (str_contains(mb_strtolower($c['summary']), $q)) {
            $byText[] = $c + ['inText' => true];
        }
    }
    $list = array_merge($byName, $byText);
    $sort = (string)($_GET['sort'] ?? 'name');
    $kindOrder = ['monster' => 0, 'spell' => 1, 'trap' => 2];
    if ($sort === 'type') {
        usort($list, fn($a, $b) => $kindOrder[$a['kind']] <=> $kindOrder[$b['kind']] ?: strcasecmp($a['name'], $b['name']));
    } elseif (in_array($sort, ['atk', 'def', 'level'], true)) {
        usort($list, fn($a, $b) => ($b[$sort] ?? -1) <=> ($a[$sort] ?? -1) ?: strcasecmp($a['name'], $b['name']));
    }
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    echo json_encode(['cards' => array_slice($list, $offset, 40), 'total' => count($list), 'offset' => $offset]);
    exit;
}

if ($action === 'archetypes') {
    $file = cacheDir('data') . '/archetypes.json';
    if (!is_file($file) || filemtime($file) < time() - DATA_TTL) {
        $body = fetchUrl('https://db.ygoprodeck.com/api/v7/archetypes.php') ?? fail(502, 'Could not reach the card database. Try again.');
        $names = array_values(array_filter(array_map(fn($a) => trim((string)($a['archetype_name'] ?? '')), json_decode($body, true) ?: []), fn($n) => $n !== '' && !str_contains($n, '|')));
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        @file_put_contents($file, json_encode($names), LOCK_EX);
    }
    echo json_encode(['archetypes' => json_decode((string)file_get_contents($file), true) ?: []]);
    exit;
}

if ($action === 'packs') {
    $sets = allSets();
    $packs = array_values(array_filter(array_map(fn($c) => $sets[$c] ?? null, PACKS)));
    usort($packs, fn($a, $b) => strcmp($b['date'], $a['date']));
    echo json_encode(['packs' => $packs, 'starters' => STARTERS]);
    exit;
}

if ($action === 'set') {
    $code = (string)($_GET['code'] ?? '');
    in_array($code, PACKS, true) || in_array($code, STARTERS, true) || fail(400, 'Unknown pack.');
    $set = allSets()[$code] ?? fail(404, 'Unknown pack.');
    $file = cacheDir('data') . "/set-$code.json";
    if (!is_file($file) || filemtime($file) < time() - DATA_TTL) {
        $body = fetchUrl(API . '?' . http_build_query(['cardset' => $set['name']])) ?? fail(502, 'Could not reach the card database. Try again.');
        $cards = [];
        foreach ((json_decode($body, true)['data'] ?? []) as $c) {
            // the rarities this card was printed in, in this set only
            $rarities = [];
            foreach ($c['card_sets'] ?? [] as $p) {
                if (html_entity_decode((string)$p['set_name'], ENT_QUOTES) === $set['name'] && str_starts_with((string)$p['set_code'], $code)) {
                    $rarities[(string)$p['set_rarity']] = true;
                }
            }
            if ($rarities) {
                $cards[] = trimCard($c) + ['rarities' => array_keys($rarities)];
            }
        }
        @file_put_contents($file, json_encode(['set' => $set, 'cards' => $cards]), LOCK_EX);
    }
    echo file_get_contents($file);
    exit;
}

if ($action === 'names') {
    $names = array_slice(array_filter(array_map('trim', explode('|', (string)($_GET['n'] ?? '')))), 0, 60);
    if (!$names) {
        fail(400, 'No card names given.');
    }
    echo json_encode(['cards' => cards(http_build_query(['name' => implode('|', $names)]))]);
    exit;
}

fail(400, 'Unknown action.');
