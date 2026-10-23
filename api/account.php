<?php
// Player accounts for AR Duel. Each player's save (cards, decks, coins, character progress, profile)
// is kept on the server, so they can carry on from any browser after logging in.
// Accounts are files in server/data/users (blocked from the web, never touched by deploys).
//
// Names and emails are unique (ignoring capital letters). A new player confirms their email with a link,
// and a confirmed email lets them reset a forgotten password.
//
//   POST ?action=register {name, password, email} -> {token, name, rev: 0, state: null}   (sends the confirmation link)
//   POST ?action=login    {name, password}       -> {token, name, rev, state}
//   POST ?action=load     {token}                -> {name, rev, state, email, verified}
//   GET  ?action=verify&u=NAME&c=CODE            the link in the confirmation email
//   POST ?action=setEmail {token, email}         add or change the email (sends a new link)
//   POST ?action=resend   {token}                send the confirmation link again
//   POST ?action=forgot   {who}                  name or email: sends a password reset link to a confirmed email
//   POST ?action=reset    {name, code, password} set a new password from that link
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
const EMAILS = USERS . '/_emails.json'; // email (lower case) -> player name

function baseUrl(): string
{
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ((($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . "://$host" . rtrim(dirname(dirname((string)$_SERVER['SCRIPT_NAME'])), '/') . '/';
}
// Email goes out through a real mailbox when server/config.php has 'smtp' settings (much more likely to reach
// Gmail and others), otherwise through PHP's mail():
//   'smtp' => ['host' => 'mail.example.com', 'port' => 465, 'user' => 'noreply@example.com', 'pass' => '...']
function sendMail(string $to, string $subject, string $text): bool
{
    $cfg = is_file(__DIR__ . '/../server/config.php') ? (require __DIR__ . '/../server/config.php') : [];
    $host = preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $smtp = is_array($cfg['smtp'] ?? null) ? $cfg['smtp'] : null;
    $from = (string)($smtp['user'] ?? '') ?: "noreply@$host";
    if ($smtp && !empty($smtp['host'])) {
        return smtpSend($smtp, $from, $to, $subject, $text);
    }
    $headers = "From: AR Duel <$from>\r\nReply-To: $from\r\nContent-Type: text/plain; charset=utf-8\r\n";
    return @mail($to, $subject, $text, $headers, "-f$from");
}
// A small SMTP client: SSL on port 465, or STARTTLS on 587/25, with AUTH LOGIN
function smtpSend(array $c, string $from, string $to, string $subject, string $text): bool
{
    $port = (int)($c['port'] ?? 465);
    $fp = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . $c['host'] . ":$port", $errno, $err, 15);
    if (!$fp) {
        error_log("AR Duel mail: cannot connect to {$c['host']}:$port ($err)");
        return false;
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) { $out = ''; while (($line = fgets($fp, 1024)) !== false) { $out .= $line; if (isset($line[3]) && $line[3] === ' ') break; } return $out; };
    $cmd = function (string $line, string $want) use ($fp, $read) { fwrite($fp, $line . "\r\n"); $r = $read(); if (strncmp($r, $want, 3) !== 0) throw new RuntimeException(trim($r)); return $r; };
    try {
        $r = $read(); if (strncmp($r, '220', 3) !== 0) throw new RuntimeException(trim($r));
        $me = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $cmd("EHLO $me", '250');
        if ($port !== 465) {
            $cmd('STARTTLS', '220');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) || throw new RuntimeException('TLS failed');
            $cmd("EHLO $me", '250');
        }
        $cmd('AUTH LOGIN', '334'); $cmd(base64_encode((string)$c['user']), '334'); $cmd(base64_encode((string)$c['pass']), '235');
        $cmd("MAIL FROM:<$from>", '250'); $cmd("RCPT TO:<$to>", '25'); $cmd('DATA', '354');
        $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $text));
        $msg = "From: AR Duel <$from>\r\nTo: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . "@$me>\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$body\r\n.";
        $cmd($msg, '250');
        fwrite($fp, "QUIT\r\n");
        return true;
    } catch (Throwable $e) {
        error_log('AR Duel mail: ' . $e->getMessage());
        return false;
    } finally {
        fclose($fp);
    }
}
function sendConfirmation(array $u): bool
{
    $link = baseUrl() . 'api/account.php?action=verify&u=' . rawurlencode($u['name']) . '&c=' . $u['vcode'];
    return sendMail($u['email'], 'Confirm your AR Duel email', "Hi {$u['name']},\n\nTap this link to confirm your email for AR Duel:\n$link\n\nWith a confirmed email you can reset your password if you forget it.\nIf you didn't create this account, ignore this email.\n");
}
// Claim an email for a player, or fail if another player has it. Returns the normalised email.
function claimEmail(string $email, string $name, string $old = ''): string
{
    $email = strtolower(trim($email));
    filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 120 || fail(400, 'Enter a valid email address.');
    is_dir(USERS) || @mkdir(USERS, 0755, true);
    $h = fopen(EMAILS, 'c+');
    flock($h, LOCK_EX);
    $map = json_decode((string)stream_get_contents($h), true) ?: [];
    if (isset($map[$email]) && strtolower($map[$email]) !== strtolower($name)) {
        flock($h, LOCK_UN);
        fclose($h);
        fail(409, 'That email is already used by another duelist.');
    }
    if ($old !== '' && $old !== $email && strtolower($map[$old] ?? '') === strtolower($name)) {
        unset($map[$old]);
    }
    $map[$email] = $name;
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($map));
    flock($h, LOCK_UN);
    fclose($h);
    return $email;
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
    // names are unique ignoring capital letters: the file name is the lower-case name
    is_file(userFile($name)) && fail(409, 'That name is taken. Try another.');
    $email = claimEmail((string)($in['email'] ?? ''), $name);
    $u = ['name' => $name, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'created' => time(), 'seen' => time(), 'rev' => 0, 'state' => null, 'tokens' => [],
        'email' => $email, 'verified' => false, 'vcode' => bin2hex(random_bytes(16))];
    $token = newToken($u);
    // x mode: fails if someone took the name at the same moment
    $h = @fopen(userFile($name), 'x') ?: fail(409, 'That name is taken. Try another.');
    fwrite($h, json_encode($u));
    fclose($h);
    $sent = sendConfirmation($u);
    echo json_encode(['token' => $token, 'name' => $name, 'rev' => 0, 'state' => null, 'mailed' => $sent]);
    exit;
}

if ($action === 'verify') {
    $n = (string)($_GET['u'] ?? '');
    $ok = preg_match('/^[A-Za-z0-9_]{3,20}$/', $n) && is_file(userFile($n)) && withUser($n, function (array $u) {
        if (!empty($u['vcode']) && hash_equals($u['vcode'], (string)($_GET['c'] ?? ''))) {
            $u['verified'] = true;
            unset($u['vcode']);
            return ['save' => $u, 'reply' => [true]];
        }
        return ['reply' => [!empty($u['verified'])]];
    })[0];
    header('Location: ' . baseUrl() . '?verified=' . ($ok ? '1' : '0'), true, 302);
    exit;
}

if ($action === 'forgot') {
    // the same answer either way, so nobody can find out which names or emails exist
    $who = strtolower(trim((string)($in['who'] ?? '')));
    $map = is_file(EMAILS) ? (json_decode((string)file_get_contents(EMAILS), true) ?: []) : [];
    $n = $map[$who] ?? $who;
    if (preg_match('/^[A-Za-z0-9_]{3,20}$/', $n) && is_file(userFile($n))) {
        withUser($n, function (array $u) {
            if (empty($u['email']) || empty($u['verified']) || !empty($u['banned'])) {
                return ['reply' => []];
            }
            $code = bin2hex(random_bytes(16));
            $u['reset'] = ['h' => hash('sha256', $code), 'until' => time() + 3600];
            sendMail($u['email'], 'Reset your AR Duel password', "Hi {$u['name']},\n\nTap this link within an hour to choose a new password:\n" . baseUrl() . '?reset=' . rawurlencode($u['name']) . ':' . $code . "\n\nIf you didn't ask for this, ignore this email; your password stays the same.\n");
            return ['save' => $u, 'reply' => []];
        });
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'reset') {
    strlen($password) >= 6 || fail(400, 'Use a password of at least 6 characters.');
    preg_match('/^[A-Za-z0-9_]{3,20}$/', $name) && is_file(userFile($name)) || fail(400, 'This reset link is not valid. Ask for a new one.');
    echo json_encode(withUser($name, function (array $u) use ($in, $password) {
        $r = $u['reset'] ?? null;
        ($r && $r['until'] > time() && hash_equals($r['h'], hash('sha256', (string)($in['code'] ?? '')))) || fail(400, 'This reset link is not valid or has expired. Ask for a new one.');
        $u['hash'] = password_hash($password, PASSWORD_DEFAULT);
        unset($u['reset']);
        $u['tokens'] = []; // logged out everywhere
        return ['save' => $u, 'reply' => ['ok' => true, 'name' => $u['name']]];
    }));
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
        return ['save' => $u, 'reply' => ['name' => $u['name'], 'rev' => $u['rev'], 'state' => $u['state'], 'email' => $u['email'] ?? '', 'verified' => !empty($u['verified'])]];
    }));
    exit;
}

if ($action === 'setEmail') {
    echo json_encode(withUser($name, function (array $u) use ($hash, $in) {
        checkToken($u, $hash);
        $u['email'] = claimEmail((string)($in['email'] ?? ''), $u['name'], (string)($u['email'] ?? ''));
        $u['verified'] = false;
        $u['vcode'] = bin2hex(random_bytes(16));
        $sent = sendConfirmation($u);
        return ['save' => $u, 'reply' => ['email' => $u['email'], 'verified' => false, 'mailed' => $sent]];
    }));
    exit;
}

if ($action === 'resend') {
    echo json_encode(withUser($name, function (array $u) use ($hash) {
        checkToken($u, $hash);
        !empty($u['email']) || fail(400, 'Add an email first.');
        !empty($u['verified']) && fail(400, 'Your email is already confirmed.');
        $u['vcode'] ??= bin2hex(random_bytes(16));
        $sent = sendConfirmation($u);
        return ['save' => $u, 'reply' => ['mailed' => $sent]];
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
