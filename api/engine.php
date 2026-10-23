<?php
// Data for the duel rules engine (EDOPro's ocgcore, running in the browser).
// Card data and texts come from Project Ignis' card database, card scripts from their CardScripts
// repository, and system texts from their strings.conf. Everything is cached in server/cache.
//
//   ?action=cards&codes=46986414,55144522   engine data, name, text and effect strings for each card
//   ?action=script&name=c55144522.lua        a card script, or a base script such as utility.lua
//   ?action=base                             all base scripts (constant.lua, utility.lua and what they load), as JSON
//   ?action=scripts&codes=46986414,55144522  the scripts of these cards, as JSON (one request instead of one per card)
//   ?action=strings                          system, victory and archetype texts

declare(strict_types=1);

const CACHE = __DIR__ . '/../server/cache';
const CDB_URL = 'https://raw.githubusercontent.com/ProjectIgnis/BabelCDB/master/cards.cdb';
const SCRIPTS = 'https://raw.githubusercontent.com/ProjectIgnis/CardScripts/master/';
const STRINGS_URL = 'https://raw.githubusercontent.com/ProjectIgnis/Distribution/master/config/strings.conf';
const WEEK = 604800;

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
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'ARDuel/1.0 (+https://duel.cck8.my)']);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return is_string($body) && $code === 200 ? $body : null;
}
function dir_(string $sub): string
{
    $d = CACHE . "/$sub";
    is_dir($d) || @mkdir($d, 0755, true);
    return $d;
}
// A cached download, refreshed after $ttl; if the refresh fails the old copy is kept
function cached(string $file, string $url, int $ttl): ?string
{
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        return $file;
    }
    $body = fetchUrl($url);
    if ($body !== null) {
        file_put_contents("$file.tmp", $body) && rename("$file.tmp", $file);
    } elseif (is_file($file)) {
        touch($file);
    }
    return is_file($file) ? $file : null;
}

// A script from the cache, downloaded first when missing or a week old ('' when the card has none)
function scriptFile(string $name, ?string $body = null, bool $fetched = false): string
{
    $file = dir_('scripts') . "/$name";
    if (!$fetched && is_file($file) && filemtime($file) >= time() - WEEK) {
        return $file;
    }
    if (!$fetched) {
        foreach (scriptUrls($name) as $url) {
            if (($body = fetchUrl($url)) !== null) {
                break;
            }
        }
    }
    if ($body !== null) {
        file_put_contents($file, $body);
    } elseif (!is_file($file)) {
        file_put_contents($file, ''); // no script: a normal monster, or a card without one
    } else {
        touch($file);
    }
    return $file;
}
// card scripts live in official/ (or pre-release/), base scripts at the top of the repository
function scriptUrls(string $name): array
{
    return array_map(fn($d) => SCRIPTS . $d . $name, preg_match('/^c\d+\.lua$/', $name) ? ['official/', 'pre-release/', ''] : ['', 'official/']);
}
// Download the missing scripts all at once (from official/ first, then the other folders for any not found)
function prefetch(array $names): void
{
    $need = array_values(array_filter($names, fn($n) => !is_file(dir_('scripts') . "/$n") || filemtime(dir_('scripts') . "/$n") < time() - WEEK));
    for ($try = 0; $need && $try < 3; $try++) {
        $mh = curl_multi_init();
        $hs = [];
        foreach ($need as $n) {
            $url = scriptUrls($n)[$try] ?? null;
            if ($url === null) {
                continue;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'ARDuel/1.0 (+https://duel.cck8.my)']);
            curl_multi_add_handle($mh, $ch);
            $hs[$n] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $running);
            $running && curl_multi_select($mh, 1.0);
        } while ($running && $st === CURLM_OK);
        $left = [];
        foreach ($hs as $n => $ch) {
            $body = curl_multi_getcontent($ch);
            if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && is_string($body)) {
                scriptFile($n, $body, true);
            } else {
                $left[] = $n;
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        $need = $left;
    }
    foreach ($need as $n) {
        scriptFile($n, null, true); // not found anywhere: remember that it has no script
    }
}
function sendJson(array $data): never
{
    header('Content-Type: application/json; charset=utf-8');
    if (str_contains((string)($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') && function_exists('gzencode') && !ini_get('zlib.output_compression')) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        echo gzencode(json_encode($data), 6);
    } else {
        echo json_encode($data);
    }
    exit;
}

$action = (string)($_GET['action'] ?? '');

if ($action === 'base') {
    // constant.lua and utility.lua, plus every base script they load (followed through LoadScript calls)
    header('Cache-Control: public, max-age=86400');
    $out = [];
    $todo = ['constant.lua', 'utility.lua'];
    while ($todo) {
        $n = array_shift($todo);
        if (isset($out[$n]) || !preg_match('/^[a-z0-9_]{1,60}\.lua$/', $n)) {
            continue;
        }
        $out[$n] = (string)file_get_contents(scriptFile($n));
        preg_match_all('/LoadScript\("([a-z0-9_]+\.lua)"\)/', $out[$n], $m);
        array_push($todo, ...$m[1]);
    }
    sendJson(['scripts' => $out]);
}

if ($action === 'scripts') {
    header('Cache-Control: public, max-age=86400');
    $codes = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['codes'] ?? '')))))), 0, 200);
    $names = array_map(fn($c) => "c$c.lua", $codes);
    prefetch($names);
    $out = [];
    foreach ($names as $n) {
        $out[$n] = (string)file_get_contents(scriptFile($n));
    }
    sendJson(['scripts' => $out]);
}

if ($action === 'cards') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    $codes = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['codes'] ?? '')))))), 0, 400);
    $codes || fail(400, 'No card codes.');
    class_exists('PDO') && in_array('sqlite', PDO::getAvailableDrivers(), true) || fail(500, 'This server has no SQLite support.');
    $cdb = cached(dir_('data') . '/cards.cdb', CDB_URL, WEEK) ?? fail(502, 'Could not reach the card database. Try again.');
    $db = new PDO("sqlite:$cdb", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $q = $db->prepare('SELECT d.id, d.alias, d.setcode, d.type, d.atk, d.def, d.level, d.race, d.attribute, t.name, t.desc, '
        . implode(', ', array_map(fn($i) => "t.str$i", range(1, 16))) . ' FROM datas d JOIN texts t ON t.id = d.id WHERE d.id IN (' . implode(',', $codes) . ')');
    $q->execute();
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $setcode = (int)$r['setcode'];
        $level = (int)$r['level'];
        $type = (int)$r['type'];
        $out[(int)$r['id']] = [
            'code' => (int)$r['id'], 'alias' => (int)$r['alias'],
            'setcodes' => array_values(array_filter([$setcode & 0xffff, ($setcode >> 16) & 0xffff, ($setcode >> 32) & 0xffff, ($setcode >> 48) & 0xffff])),
            'type' => $type, 'level' => $level & 0xff, 'lscale' => ($level >> 24) & 0xff, 'rscale' => ($level >> 16) & 0xff,
            'attribute' => (int)$r['attribute'], 'race' => (string)$r['race'], 'attack' => (int)$r['atk'],
            'defense' => $type & 0x4000000 ? 0 : (int)$r['def'], 'link_marker' => $type & 0x4000000 ? (int)$r['def'] : 0,
            'name' => (string)$r['name'], 'desc' => (string)$r['desc'],
            'strings' => array_map(fn($i) => (string)($r["str$i"] ?? ''), range(1, 16)),
        ];
    }
    echo json_encode(['cards' => (object)$out]);
    exit;
}

if ($action === 'script') {
    $name = (string)($_GET['name'] ?? '');
    preg_match('/^[a-z0-9_]{1,60}\.lua$/', $name) || fail(400, 'Bad script name.');
    $file = scriptFile($name);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    readfile($file);
    exit;
}

if ($action === 'strings') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    $json = dir_('data') . '/strings.json';
    if (!is_file($json) || filemtime($json) < time() - WEEK) {
        $conf = cached(dir_('data') . '/strings.conf', STRINGS_URL, WEEK) ?? fail(502, 'Could not load the game texts. Try again.');
        $out = ['system' => [], 'victory' => [], 'counter' => [], 'setname' => []];
        foreach (file($conf, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^!(system|victory|counter|setname) (0x[0-9a-fA-F]+|\d+) (.*)$/', $line, $m)) {
                $out[$m[1]][(string)intval($m[2], 0)] = rtrim($m[3]);
            }
        }
        file_put_contents($json, json_encode($out));
    }
    readfile($json);
    exit;
}

fail(400, 'Unknown action.');
