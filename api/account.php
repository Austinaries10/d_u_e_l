<?php
// Player accounts for AR Duel. Each player's save (cards, decks, coins, character progress, profile)
// is kept on the server, so they can carry on from any browser after logging in.
// Accounts are files in server/data/users (blocked from the web, never touched by deploys).
//
//   POST ?action=register {name, password}       -> {token, name, rev: 0, state: null}
//   POST ?action=login    {name, password}       -> {token, name, rev, state}
//   POST ?action=load     {token}                -> {name, rev, state}
//   POST ?action=save     {token, rev, state}    -> {rev}   (409 with the server's save if it changed elsewhere)
//   POST ?action=logout   {token}

declare(strict_types=1);

const USERS = __DIR__ . '/../server/data/users';
const MAX_STATE = 3000000; // bytes

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $status, string $message, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(['error' => $message] + $extra);
    exit;
}
function userFile(string $name): string
{
    return USERS . '/' . strtolower($name) . '.json';
}
// Run $fn on a player's file with it locked; return ['save' => data] to write it back
function withUser(string $name, callable $fn): array
{
    $file = userFile($name);
    is_file($file) || fail(404, 'No player with that name.');
    $h = fopen($file, 'c+') ?: fail(500, 'Could not open the account.');
    flock($h, LOCK_EX);
    $u = json_decode((string)stream_get_contents($h), true) ?: fail(500, 'The account file is damaged.');
    $out = $fn($u);
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
// "name:secret" tokens; the secret's hash is kept in the player's file
function tokenUser(string $token): array
{
    [$name, $secret] = array_pad(explode(':', $token, 2), 2, '');
    preg_match('/^[A-Za-z0-9_]{3,20}$/', $name) && $secret !== '' || fail(401, 'Please log in again.');
    return [$name, hash('sha256', $secret)];
}
function newToken(array &$u): string
{
    $secret = bin2hex(random_bytes(24));
    $u['tokens'] = array_slice(array_merge([['h' => hash('sha256', $secret), 'at' => time()]], $u['tokens'] ?? []), 0, 10);
    return $u['name'] . ':' . $secret;
}
function checkToken(array $u, string $hash): void
{
    empty($u['banned']) || fail(401, 'This account has been suspended.');
    foreach ($u['tokens'] ?? [] as $t) {
        if (hash_equals($t['h'], $hash)) {
            return;
        }
    }
    fail(401, 'Please log in again.');
}

$action = (string)($_GET['action'] ?? '');
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$name = trim((string)($in['name'] ?? ''));
$password = (string)($in['password'] ?? '');

if ($action === 'register') {
    preg_match('/^[A-Za-z0-9_]{3,20}$/', $name) || fail(400, 'Use 3–20 letters, numbers or _ for your name.');
    strlen($password) >= 6 || fail(400, 'Use a password of at least 6 characters.');
    is_dir(USERS) || @mkdir(USERS, 0755, true);
    $u = ['name' => $name, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'created' => time(), 'seen' => time(), 'rev' => 0, 'state' => null, 'tokens' => []];
    $token = newToken($u);
    // x mode: fails if the name is already taken
    $h = @fopen(userFile($name), 'x') ?: fail(409, 'That name is taken. Try another.');
    fwrite($h, json_encode($u));
    fclose($h);
    echo json_encode(['token' => $token, 'name' => $name, 'rev' => 0, 'state' => null]);
    exit;
}

if ($action === 'login') {
    is_file(userFile($name)) || (sleep(1) && false) || fail(403, 'Wrong name or password.');
    echo json_encode(withUser($name, function (array $u) use ($password) {
        if (!password_verify($password, $u['hash'])) {
            sleep(1); // slows down guessing
            fail(403, 'Wrong name or password.');
        }
        if (!empty($u['banned'])) {
            fail(403, 'This account has been suspended.');
        }
        $token = newToken($u);
        $u['seen'] = time();
        return ['save' => $u, 'reply' => ['token' => $token, 'name' => $u['name'], 'rev' => $u['rev'], 'state' => $u['state']]];
    }));
    exit;
}

[$name, $hash] = tokenUser((string)($in['token'] ?? ''));

if ($action === 'load') {
    echo json_encode(withUser($name, function (array $u) use ($hash) {
        checkToken($u, $hash);
        $u['seen'] = time();
        return ['save' => $u, 'reply' => ['name' => $u['name'], 'rev' => $u['rev'], 'state' => $u['state']]];
    }));
    exit;
}

if ($action === 'save') {
    $state = $in['state'] ?? null;
    is_array($state) || fail(400, 'Nothing to save.');
    strlen(json_encode($state)) <= MAX_STATE || fail(413, 'Your save is too large.');
    echo json_encode(withUser($name, function (array $u) use ($hash, $state, $in) {
        checkToken($u, $hash);
        // saved from another device, or changed by the game master, since this browser last loaded it
        if ((int)($in['rev'] ?? -1) !== (int)$u['rev']) {
            fail(409, 'Your save changed somewhere else.', ['rev' => $u['rev'], 'state' => $u['state']]);
        }
        $u['state'] = $state;
        $u['rev']++;
        $u['seen'] = time();
        return ['save' => $u, 'reply' => ['rev' => $u['rev']]];
    }));
    exit;
}

if ($action === 'logout') {
    echo json_encode(withUser($name, function (array $u) use ($hash) {
        $u['tokens'] = array_values(array_filter($u['tokens'] ?? [], fn($t) => !hash_equals($t['h'], $hash)));
        return ['save' => $u, 'reply' => ['ok' => true]];
    }));
    exit;
}

fail(400, 'Unknown action.');
