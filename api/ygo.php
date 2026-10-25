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
//   ?action=packs                    the packs on sale in the shop right now (chosen by the GM), and the starter decks
//   ?action=set&code=LOB             every card in a set, with its rarities in that set
//   ?action=set&event=ID             the cards of a limited pack event, only while the event is on
//   ?action=sets                     every set in the database (for the GM page)
//   ?action=img&set=LOB              a set's pack image
//   ?action=face&name=Seto_Kaiba     a character portrait: server/data/faces/Seto_Kaiba.(jpg|png|webp) if the GM put one
//                                    there, otherwise the main image of the character's Yugipedia page (fetched once, cached)
//   ?action=names&n=A|B|C            cards with exactly these names
//   ?action=img&id=46986414          card image  (&size=small or &size=crop for the artwork only)

declare(strict_types=1);

const API = 'https://db.ygoprodeck.com/api/v7/cardinfo.php';
const IMG = 'https://images.ygoprodeck.com/images/';
require __DIR__ . '/lang.php'; // &lang=ja|zh for card names and texts in Japanese or Chinese
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
        CURLOPT_USERAGENT => 'ARDuel/1.0 (+https://duel.cck8.my)',
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

// Booster sets suggested to the GM for the shop (the GM can list any set; see gm.html)
const PACKS = ['LOB', 'MRD', 'SRL', 'PSV', 'LON', 'LOD', 'PGD', 'MFC', 'DCR', 'IOC', 'AST', 'SOD', 'RDS', 'FET', 'TLM', 'CRV', 'EEN', 'SOI', 'EOJ', 'POTD', 'CDIP',
    'STON', 'FOTB', 'TAEV', 'GLAS', 'PTDN', 'LODT', 'TDGS', 'CSOC', 'DLG1', 'CRMS', 'RGBT', 'ANPR', 'SOVR', 'ABPF', 'TSHD', 'DREV', 'STBL', 'STOR', 'EXVC', 'GENF',
    'PHSW', 'ORCS', 'GAOV', 'REDU', 'ABYR', 'CBLZ', 'LTGY', 'NUMH', 'JOTL', 'SHSP', 'LVAL', 'PRIO', 'DUEA', 'NECH', 'SECE', 'THSF', 'CROS', 'CORE', 'HSRD', 'DOCS',
    'BOSH', 'WIRA', 'SHVI', 'TDIL', 'DRL3', 'INOV', 'RATE', 'FUEN', 'DUSA', 'MACR', 'PEVO', 'BLLR', 'COTD', 'CIBR', 'SPWA', 'EXFO', 'FLOD', 'DASA', 'BLRR', 'SHVA',
    'HISU', 'SAST', 'DANE', 'BLHR', 'RIRA', 'FIGA', 'CHIM', 'MYFI', 'IGAS', 'DUOV', 'SESL', 'ETCO', 'BLAR', 'ROTD', 'PHRA', 'GEIM', 'BLVO', 'ANGU', 'LIOV', 'KICO',
    'DAMA', 'BROL', 'GRCR', 'BACH', 'DIFO', 'POTE', 'TAMA', 'DABL', 'MAMA', 'BLCR', 'AMDE', 'PHHY', 'MAZE', 'CYAC', 'WISU', 'BLMR', 'DUNE', 'AGOV', 'VASM', 'MZMI',
    'PHNI', 'LEDE', 'BLTR', 'INFO', 'ROTA', 'CRBR', 'SUDA', 'MZTM', 'ALIN', 'JUSH', 'DOOD', 'PHRE', 'BPRO', 'MZMU', 'BLZD', 'CORI', 'MAMO', 'BETB'];
// Starter decks without a main character's name, one of which is given to a new player
const STARTERS = ['YSD', '5DS1', '5DS2', '5DS3', 'YS11', 'YS12', 'YS17', 'YS18']; // all real 40+ card starter decks (YS14 in the card database is a 15-card pack)
const STARTER_PRICE = 800; // a whole starter deck in the shop (a 9-card pack is 100)
// Every real starter deck the Shop can sell whole (the GM chooses which are on sale and their prices)
const SHOP_STARTERS = ['SDY', 'SDK', 'SDJ', 'SDP', 'SYE', 'SKE', 'YSD', 'YSDJ', 'YSDS', '5DS1', '5DS2', '5DS3', 'YS11', 'YS12', 'YSKR', 'YSYR', 'YS15', 'YS16', 'YS17', 'YS18'];
// The starter decks on sale: the GM's list, or all of them at the default price until the GM sets one
function starterListings(array $gm): array
{
    $byCode = [];
    foreach ((array)($gm['starterShop'] ?? []) as $l) {
        $byCode[(string)($l['code'] ?? '')] = $l;
    }
    $out = [];
    foreach (SHOP_STARTERS as $code) {
        $l = $byCode[$code] ?? ['code' => $code, 'price' => STARTER_PRICE, 'enabled' => true];
        if (!empty($l['enabled'])) {
            $out[] = ['code' => $code, 'price' => max(0, (int)($l['price'] ?? STARTER_PRICE))];
        }
    }
    return $out;
}
// Japanese-only packs in the shop's "Japan only" tab: all on sale unless the GM switches one off; premium packs cost more
function ocgDefaultPrice(string $code): int
{
    return preg_match('/^JP\d/', $code) ? 300 : (preg_match('/^J(20LC|HAC|LGB|MB1|PAC|PGB|QC|RCPG|SSB|AC)/', $code) ? 200 : 100);
}
function ocgListings(array $gm): array
{
    $byCode = [];
    foreach ((array)($gm['ocgShop'] ?? []) as $l) $byCode[(string)($l['code'] ?? '')] = $l;
    $out = [];
    foreach (array_keys(ocgSets()) as $code) {
        $l = $byCode[$code] ?? ['enabled' => true, 'price' => ocgDefaultPrice($code)];
        if (!empty($l['enabled'])) $out[] = ['code' => $code, 'price' => max(0, (int)($l['price'] ?? ocgDefaultPrice($code)))];
    }
    return $out;
}
// What the shop sells until the GM chooses: the first booster packs, 100 coins each
const DEFAULT_SHOP = ['LOB', 'MRD', 'SRL', 'PSV', 'LON', 'LOD', 'PGD', 'MFC'];
// The GM's shop listings that are on sale now or coming soon: [{code, price, start, end, enabled}]
function shopListings(array $gm, bool $onlyNow): array
{
    $list = isset($gm['shop']) && is_array($gm['shop']) ? $gm['shop'] : array_map(fn($c) => ['code' => $c, 'price' => 100, 'start' => '', 'end' => '', 'enabled' => true], DEFAULT_SHOP);
    $now = time();
    return array_values(array_filter($list, fn($l) => !empty($l['enabled']) && (($l['end'] ?? '') === '' || strtotime($l['end']) > $now)
        && (!$onlyNow || ($l['start'] ?? '') === '' || strtotime($l['start']) <= $now)));
}
function gmSettings(): array
{
    $f = __DIR__ . '/../server/data/gm.json';
    return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
}

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
    return (json_decode((string)file_get_contents($file), true) ?: []) + ocgSets();
}
// Japanese-only (OCG) packs: data/ocg-sets.json; their cards come from Yugipedia (see ocgCards)
function ocgSets(): array
{
    static $sets = null;
    if ($sets === null) {
        $sets = [];
        foreach (json_decode((string)@file_get_contents(__DIR__ . '/../data/ocg-sets.json'), true) ?: [] as $code => $x) {
            if (is_array($x)) $sets[$code] = ['code' => $code, 'name' => $x['title'], 'date' => $x['date'], 'ocg' => true];
        }
    }
    return $sets;
}
// Yugipedia's rarity names and abbreviations, as the TCG rarities the game knows
function ocgRarity(string $r): string
{
    $r = trim(html_entity_decode($r, ENT_QUOTES));
    $abbr = ['C' => 'Common', 'N' => 'Common', 'NR' => 'Common', 'NPR' => 'Common', 'R' => 'Rare', 'SR' => 'Super Rare', 'UR' => 'Ultra Rare', 'UPR' => 'Ultra Rare', 'SE' => 'Secret Rare', 'ScR' => 'Secret Rare',
        'UtR' => 'Ultimate Rare', 'UL' => 'Ultimate Rare', 'GR' => 'Ghost Rare', 'HR' => 'Ghost Rare', 'HGR' => 'Ghost Rare', 'CR' => "Collector's Rare", 'PScR' => 'Platinum Secret Rare', 'PSE' => 'Secret Rare', 'ESR' => 'Secret Rare',
        '20ScR' => 'Secret Rare', 'QCScR' => 'Quarter Century Secret Rare', 'QCSE' => 'Quarter Century Secret Rare', 'StR' => 'Starlight Rare', 'GUR' => 'Ultra Rare', 'GScR' => 'Secret Rare', 'PGR' => 'Ultra Rare', 'MLR' => 'Ultra Rare', 'MLUR' => 'Ultra Rare', 'MLSR' => 'Super Rare', 'GSR' => 'Secret Rare', 'KC' => 'Ultra Rare', 'KCR' => 'Ultra Rare', 'P' => 'Super Rare', 'PR' => 'Super Rare'];
    if (isset($abbr[$r])) return $abbr[$r];
    $r = preg_replace('/\s*Parallel\b/i', '', $r);
    if (preg_match('/^(Normal|Common|Short Print)/i', $r)) return 'Common';
    if (preg_match('/Gold|Millennium|Kaiba/i', $r)) return 'Ultra Rare';
    foreach (['Quarter Century Secret Rare', 'Platinum Secret Rare', 'Starlight Rare', "Collector's Rare", 'Ghost Rare', 'Ultimate Rare', 'Secret Rare', 'Ultra Rare', 'Super Rare', 'Rare'] as $known) {
        if (stripos($r, $known) !== false) return $known;
    }
    if (preg_match('/Holographic/i', $r)) return 'Ghost Rare';
    if (preg_match('/Gold|Millennium|Kaiba/i', $r)) return 'Ultra Rare';
    return 'Super Rare';
}
// A Japanese pack's cards, with their rarities in it, read from Yugipedia's card list and matched to the card database by name
function ocgCards(array $set): array
{
    $json = json_decode((string)fetchUrl('https://yugipedia.com/api.php?' . http_build_query(['action' => 'query', 'prop' => 'revisions', 'rvprop' => 'content', 'rvslots' => 'main', 'redirects' => 1,
        'titles' => "Set Card Lists:{$set['name']} (OCG-JP)", 'format' => 'json'])), true) ?: fail(502, 'Could not reach the card list. Try again.');
    $text = (string)((current($json['query']['pages'] ?? []) ?: [])['revisions'][0]['slots']['main']['*'] ?? '');
    $text !== '' || fail(404, 'No card list for that pack.');
    $byName = [];
    foreach (cardDb() as $c) $byName[mb_strtolower($c['name'])] ??= $c;
    $found = [];
    preg_match_all('/\{\{Set list\s*\|(.*?)\n\}\}/s', $text, $blocks);
    foreach ($blocks[1] as $block) {
        $lines = explode("\n", $block);
        $head = array_shift($lines);
        preg_match('/rarities\s*=\s*([^|]*)/', $head, $m);
        $default = array_values(array_filter(array_map('trim', explode(',', $m[1] ?? 'Common'))));
        $noCode = str_contains($head, 'noabbr'); // (older lists have no card numbers: "name; rarity")
        foreach ($lines as $line) {
            $f = array_map('trim', explode(';', $line));
            if ($noCode) array_unshift($f, '');
            if (count($f) < 2 || $f[1] === '' || str_starts_with($f[1], '{') || str_starts_with($f[1], '<')) continue;
            $name = html_entity_decode(preg_replace('/\s*\/\/.*$/', '', $f[1]), ENT_QUOTES);
            $c = $byName[mb_strtolower($name)] ?? $byName[mb_strtolower(preg_replace('/\s*\([^)]*\)\s*$/', '', $name))] ?? null;
            if (!$c) continue; // a card that never came out in English
            $rar = ($f[2] ?? '') !== '' ? array_map('trim', explode(',', $f[2])) : ($default ?: ['Common']);
            $e = $found[$c['id']] ?? $c + ['rarities' => []];
            foreach ($rar as $r) if ($r !== '') $e['rarities'][] = ocgRarity($r);
            $e['rarities'] = array_values(array_unique($e['rarities']));
            $found[$c['id']] = $e;
        }
    }
    $found || fail(404, 'No cards found for that pack.');
    return array_values($found);
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
    $file = cacheDir('img/' . $folder) . "/$id" . ($folder === 'sets' ? '-v3' : '') . '.jpg'; // v3: pack art may come from Yugipedia
    if (!is_file($file)) {
        $body = fetchUrl(IMG . "$folder/$id.jpg");
        if ($folder === 'sets') {
            // pack art: keep Yugipedia's picture of the pack instead when it is larger (older packs especially)
            $name = allSets()[$id]['name'] ?? null;
            $json = $name ? json_decode((string)fetchUrl('https://yugipedia.com/api.php?' . http_build_query(['action' => 'query', 'redirects' => 1, 'titles' => $name, 'prop' => 'pageimages', 'piprop' => 'original', 'format' => 'json'])), true) : null;
            $src = (string)(current($json['query']['pages'] ?? []) ?: [])['original']['source'] ?? '';
            $alt = $src !== '' ? fetchUrl($src) : null;
            $width = fn($b) => $b !== null && ($i = @getimagesizefromstring($b)) ? $i[0] : 0;
            if ($alt !== null && $width($alt) > $width($body)) {
                $body = $alt;
            }
            // originals can be huge: shrink to 600px wide when GD is there
            if ($width($body) > 600 && function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($body))) {
                $small = imagescale($im, 600);
                ob_start(); imagejpeg($small, null, 86); $body = (string)ob_get_clean();
            }
        }
        if ($body === null || strlen($body) < 1000 || !@getimagesizefromstring($body)) {
            fail(404, 'No image for that card.');
        }
        @file_put_contents($file, $body, LOCK_EX);
    }
    header('Content-Type: ' . ((@getimagesize($file)['mime'] ?? '') ?: 'image/jpeg'));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($file);
    exit;
}

if ($action === 'face') {
    $name = (string)($_GET['name'] ?? '');
    preg_match('/^[\p{L}0-9_.\'-]{2,60}$/u', $name) || fail(400, 'Bad name.');
    $send = function (string $file, string $type) {
        header("Content-Type: $type");
        header('Cache-Control: public, max-age=604800');
        readfile($file);
        exit;
    };
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $ext => $type) {
        $own = __DIR__ . "/../server/data/faces/$name.$ext";
        if (is_file($own)) {
            $send($own, $type);
        }
    }
    $file = cacheDir('img/faces') . '/' . md5($name);
    if (!is_file($file)) {
        $json = json_decode((string)fetchUrl('https://yugipedia.com/api.php?' . http_build_query(['action' => 'query', 'redirects' => 1, 'titles' => str_replace('_', ' ', $name), 'prop' => 'pageimages', 'pithumbsize' => 500, 'format' => 'json'])), true);
        $page = current($json['query']['pages'] ?? []) ?: [];
        $src = (string)($page['thumbnail']['source'] ?? '') ?: fail(404, 'No picture for that character.');
        $body = fetchUrl($src);
        $type = $body === null ? '' : (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        in_array($type, ['image/jpeg', 'image/png', 'image/webp'], true) || fail(404, 'No picture for that character.');
        @file_put_contents($file, $body, LOCK_EX);
        @file_put_contents("$file.type", $type, LOCK_EX);
    }
    $send($file, (string)@file_get_contents("$file.type") ?: 'image/png');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

if ($action === 'search') {
    $q = mb_strtolower(trim((string)($_GET['q'] ?? '')));
    $lang = langParam();
    // Chinese and Japanese words can be a single character
    if ($q !== '' && ((mb_strlen($q) < 2 && !preg_match('/[^\x00-\x7F]/', $q)) || mb_strlen($q) > 60)) {
        fail(400, 'Type at least 2 letters.');
    }
    $tr = $lang ? langTexts($lang, null) : [];
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
        $t = $tr[$c['id']] ?? null;
        if ($t) {
            $c += ['tname' => $t['name'], 'tdesc' => $t['desc']];
        }
        if ($q === '' || str_contains(mb_strtolower($c['name']), $q) || ($t && str_contains(mb_strtolower($t['name']), $q))) {
            $byName[] = $c;
        } elseif (str_contains(mb_strtolower($c['summary']), $q) || ($t && str_contains(mb_strtolower($t['desc']), $q))) {
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
    header('Cache-Control: no-store');
    $sets = allSets();
    $packs = [];
    foreach (shopListings(gmSettings(), false) as $l) {
        if (isset($sets[$l['code']])) {
            $packs[] = $sets[$l['code']] + ['price' => (int)$l['price'], 'start' => (string)($l['start'] ?? ''), 'end' => (string)($l['end'] ?? '')];
        }
    }
    // the real starter decks are sold whole in the shop, for more than a pack
    $decks = array_values(array_map(fn($l) => $sets[$l['code']] + ['price' => $l['price']], array_filter(starterListings(gmSettings()), fn($l) => isset($sets[$l['code']]))));
    $ocg = array_values(array_map(fn($l) => $sets[$l['code']] + ['price' => $l['price']], ocgListings(gmSettings())));
    echo json_encode(['packs' => $packs, 'ocg' => $ocg, 'starters' => STARTERS, 'decks' => $decks, 'suggested' => PACKS, 'now' => gmdate('c')]);
    exit;
}

if ($action === 'sets') {
    echo json_encode(['sets' => array_values(allSets())]);
    exit;
}

if ($action === 'set') {
    $gm = gmSettings();
    $code = (string)($_GET['code'] ?? '');
    if (isset($_GET['event'])) {
        // a limited pack: only while the GM has it switched on and inside its time frame
        $ev = current(array_filter($gm['events'] ?? [], fn($e) => $e['id'] === $_GET['event'])) ?: fail(404, 'That event pack is not on sale.');
        ($ev['enabled'] && strtotime($ev['start']) <= time() && strtotime($ev['end']) > time()) || fail(403, 'That event pack is not on sale right now.');
        if (!$ev['set']) {
            echo json_encode(['set' => ['code' => '', 'name' => $ev['name'], 'date' => substr($ev['start'], 0, 10)], 'cards' => $ev['cards']]);
            exit;
        }
        $code = $ev['set'];
    } else {
        // a new player's starter deck, a starter deck on sale, or a pack the GM has on sale right now
        in_array($code, STARTERS, true) || in_array($code, array_column(starterListings($gm), 'code'), true)
            || in_array($code, array_column(shopListings($gm, true), 'code'), true) || in_array($code, array_column(ocgListings($gm), 'code'), true) || fail(403, 'That pack is not on sale right now.');
    }
    $set = allSets()[$code] ?? fail(404, 'Unknown pack.');
    $file = cacheDir('data') . "/set-$code.json";
    if (!empty($set['ocg']) && (!is_file($file) || filemtime($file) < time() - DATA_TTL)) {
        @file_put_contents($file, json_encode(['set' => $set, 'cards' => ocgCards($set)]), LOCK_EX);
    }
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

// cards by number: ?action=byid&ids=1,2,3 -> {cards: [...]} (a player's collection is saved as numbers only)
if ($action === 'byid') {
    header('Cache-Control: public, max-age=86400');
    $want = array_flip(array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))))), 0, 400));
    $out = [];
    foreach (cardDb() as $c) {
        if (isset($want[$c['id']])) {
            $out[] = $c;
            unset($want[$c['id']]);
        }
    }
    // numbers not in the trimmed database (alternate artworks): ask the card database itself
    if ($want) {
        foreach (cards(http_build_query(['id' => implode(',', array_keys($want))])) as $c) {
            $out[] = $c;
        }
    }
    echo json_encode(['cards' => $out]);
    exit;
}

// card names and texts in another language: ?action=tr&lang=ja&ids=1,2,3 -> {id: {name, desc}}
if ($action === 'tr') {
    header('Cache-Control: public, max-age=86400');
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? '')))))), 0, 300);
    $out = [];
    foreach (langTexts(langParam(), $ids) as $id => $t) {
        $out[$id] = ['name' => $t['name'], 'desc' => $t['desc']];
    }
    echo json_encode(['tr' => (object)$out]);
    exit;
}

// Cards that go with one card, for building a deck around it: cards that name it, its archetype, the cards it names,
// and monsters of its type and attribute; plus well-known staples to fill the deck
//   ?action=related&id=ID -> {seed, related: [card + score], staples: [card]}
const STAPLES = ['Monster Reborn', 'Dark Hole', 'Mystical Space Typhoon', 'Swords of Revealing Light', 'Book of Moon', 'Pot of Duality',
    'Upstart Goblin', 'Raigeki', 'Heavy Storm', 'Change of Heart', 'Mirror Force', 'Call of the Haunted', 'Torrential Tribute',
    'Bottomless Trap Hole', 'Solemn Judgment', 'Magic Cylinder', 'Sakuretsu Armor', 'Compulsory Evacuation Device', 'Dimensional Prison',
    'Trap Hole', 'Fissure', 'Shrink', 'Enemy Controller', 'Forbidden Lance', 'Effect Veiler', 'Ash Blossom & Joyous Spring',
    'Sangan', 'Man-Eater Bug', 'Breaker the Magical Warrior', 'Exiled Force', 'Cyber Dragon', 'D.D. Warrior Lady'];
if ($action === 'related') {
    $id = (int)($_GET['id'] ?? 0);
    $db = cardDb(); $seed = null;
    foreach ($db as $c) if ($c['id'] === $id) { $seed = $c; break; }
    $seed || fail(404, 'Card not found.');
    $name = mb_strtolower($seed['name']); $arch = $seed['archetype'] ?? '';
    preg_match_all('/"([^"]{3,60})"/', $seed['summary'] ?? '', $mm);
    $named = array_map('mb_strtolower', $mm[1]);
    $out = [];
    foreach ($db as $c) {
        if ($c['id'] === $id) continue;
        $sum = mb_strtolower($c['summary'] ?? ''); $cn = mb_strtolower($c['name']); $score = 0;
        if (str_contains($sum, '"' . $name . '"')) $score += 6;                       // it names this card
        if (in_array($cn, $named, true)) $score += 5;                                 // this card names it
        if ($arch !== '' && ($c['archetype'] ?? '') === $arch) $score += 4;           // same archetype
        if ($arch !== '' && str_contains($sum, '"' . mb_strtolower($arch) . '"')) $score += 3; // supports the archetype
        foreach ($named as $n) if ($n !== $cn && str_contains($sum, '"' . $n . '"')) { $score += 2; break; }
        if ($seed['kind'] === 'monster' && $c['kind'] === 'monster' && $c['race'] === $seed['race'] && $c['attribute'] === $seed['attribute']) $score += 1;
        if ($score > 0) $out[] = $c + ['score' => $score];
    }
    usort($out, fn($a, $b) => $b['score'] <=> $a['score'] ?: ($b['atk'] ?? 0) <=> ($a['atk'] ?? 0));
    $byName = []; foreach ($db as $c) $byName[mb_strtolower($c['name'])] ??= $c;
    $staples = array_values(array_filter(array_map(fn($n) => $byName[mb_strtolower($n)] ?? null, STAPLES)));
    echo json_encode(['seed' => $seed, 'related' => array_slice($out, 0, 90), 'staples' => $staples]);
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
