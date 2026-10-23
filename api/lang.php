<?php
// Card names and texts in other languages, from the YGOPro community's translated card databases
// (mycard/ygopro-database: the same card numbers as the English database). Cached in server/cache/data for a week.

declare(strict_types=1);

const LANGS = ['ja' => 'ja-JP', 'zh' => 'zh-CN'];
const LANG_URL = 'https://raw.githubusercontent.com/mycard/ygopro-database/master/locales/';

// ?lang=ja|zh, or '' for English
function langParam(): string
{
    $l = (string)($_GET['lang'] ?? '');
    return isset(LANGS[$l]) ? $l : '';
}
// A file of that language's database ('cards.cdb' or 'strings.conf'), downloaded when missing or a week old
function langFile(string $lang, string $name): ?string
{
    $dir = __DIR__ . '/../server/cache/data';
    is_dir($dir) || @mkdir($dir, 0755, true);
    $file = "$dir/" . $lang . '-' . $name;
    if (!is_file($file) || filemtime($file) < time() - 604800) {
        $ch = curl_init(LANG_URL . LANGS[$lang] . '/' . $name);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'ARDuel/1.0']);
        $body = curl_exec($ch);
        $ok = is_string($body) && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && strlen($body) > 1000;
        curl_close($ch);
        if ($ok) {
            file_put_contents("$file.tmp", $body) && rename("$file.tmp", $file);
        } elseif (is_file($file)) {
            touch($file);
        }
    }
    return is_file($file) ? $file : null;
}
// id => ['name', 'desc', 'strings' => [16]] for these card ids (or every card when $ids is null)
function langTexts(string $lang, ?array $ids): array
{
    if ($lang === '' || ($ids !== null && !$ids) || !($cdb = langFile($lang, 'cards.cdb'))) {
        return [];
    }
    $db = new PDO("sqlite:$cdb", null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $sql = 'SELECT id, name, desc, ' . implode(', ', array_map(fn($i) => "str$i", range(1, 16))) . ' FROM texts'
        . ($ids === null ? '' : ' WHERE id IN (' . implode(',', array_map('intval', $ids)) . ')');
    $out = [];
    foreach ($db->query($sql, PDO::FETCH_ASSOC) as $r) {
        $out[(int)$r['id']] = ['name' => (string)$r['name'], 'desc' => (string)$r['desc'], 'strings' => array_map(fn($i) => (string)($r["str$i"] ?? ''), range(1, 16))];
    }
    return $out;
}
