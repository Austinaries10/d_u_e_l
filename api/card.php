<?php
// Card scanner for AR Duel.
// The browser posts a photo of a trading card; Claude Haiku reads it and returns the card's details as JSON.
// The API key stays on the server (server/config.php or the ANTHROPIC_API_KEY environment variable).

declare(strict_types=1);

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const MODEL = 'claude-haiku-5-5';
const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
const RATE_LIMIT = 30;          // requests per IP address...
const RATE_WINDOW = 600;        // ...per 10 minutes

function reply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// If PHP stops with a fatal error, still answer with JSON the app can show
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Server error: ' . $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')'], JSON_UNESCAPED_UNICODE);
    }
});
set_exception_handler(function (Throwable $t): void {
    reply(500, ['error' => 'Server error: ' . get_class($t) . ': ' . $t->getMessage()]);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(405, ['error' => 'Send a POST request.']);
}

// Only accept requests from pages on this same site
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originHost = parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
if ($origin !== '' && strcasecmp($originHost, $_SERVER['HTTP_HOST'] ?? '') !== 0) {
    reply(403, ['error' => 'Requests from other websites are not allowed.']);
}

$autoload = __DIR__ . '/../server/vendor/autoload.php';
if (!is_file($autoload)) {
    reply(500, ['error' => 'The AI library is missing on the server. Upload server-vendor.zip into the server folder and extract it.']);
}
require __DIR__ . '/compat.php';
require $autoload;

function apiKey(): string
{
    $key = getenv('ANTHROPIC_API_KEY') ?: '';
    if ($key !== '') {
        return $key;
    }
    $candidates = [
        __DIR__ . '/../server/config.php',
        dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) . '/smarteyes-config.php',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $cfg = include $file;
            if (is_array($cfg) && !empty($cfg['anthropic_api_key'])) {
                return (string)$cfg['anthropic_api_key'];
            }
        }
    }
    return '';
}

// Simple per-IP limit so a leaked page URL cannot run up the bill
function rateLimited(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/smarteyes_rl_' . md5($ip) . '.json';
    $now = time();
    $hits = [];
    if (is_file($file)) {
        $hits = json_decode((string)@file_get_contents($file), true) ?: [];
    }
    $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - RATE_WINDOW));
    if (count($hits) >= RATE_LIMIT) {
        return true;
    }
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return false;
}

$key = apiKey();
if ($key === '') {
    reply(503, ['error' => 'AI is not set up yet. Add your API key to server/config.php on the hosting.']);
}
if (rateLimited()) {
    reply(429, ['error' => 'Too many requests. Wait a few minutes and try again.']);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    reply(400, ['error' => 'The request was not valid JSON.']);
}

$image = (string)($input['image'] ?? '');
$image = preg_replace('#^data:image/jpeg;base64,#', '', $image) ?? '';
if ($image === '' || strlen($image) > MAX_IMAGE_BYTES * 4 / 3 || base64_decode($image, true) === false) {
    reply(400, ['error' => 'The photo is missing or too large.']);
}

$prompt = "This photo should show one trading card from a collectible card game, held up to a phone camera.\n"
    . "Read the card and reply with a single JSON object only (no markdown, no other text) with these keys:\n"
    . "name (string, the card's name exactly as printed), kind (\"monster\", \"spell\" or \"trap\"), "
    . "attribute (for monsters: DARK, LIGHT, FIRE, WATER, EARTH, WIND or DIVINE; otherwise empty string), "
    . "level (integer level, rank or link rating, or null), atk (integer or null), def (integer or null; null for link monsters), "
    . "type (short, e.g. \"Spellcaster / Effect\" or \"Quick-Play Spell\"), "
    . "summary (what the card does in plain words, at most 140 characters).\n"
    . "If ATK or DEF is printed as ?, use null. If you cannot see a card clearly, reply {\"error\": \"No card found. Hold one card flat, in good light, filling most of the frame.\"}.";

try {
    $client = new Client(apiKey: $key);
    $message = $client->messages->create(
        model: MODEL,
        maxTokens: 1024,
        outputConfig: ['effort' => 'medium'],
        messages: [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => $image]],
                ['type' => 'text', 'text' => $prompt],
            ],
        ]],
    );

    if ($message->stopReason === 'refusal') {
        reply(200, ['error' => 'The AI could not read this card.']);
    }

    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }
    // take the JSON object out of the reply, even if it came wrapped in other text
    if (!preg_match('/\{.*\}/s', $text, $m) || !is_array($card = json_decode($m[0], true))) {
        reply(200, ['error' => 'Could not read the card. Try again with the card filling the frame.']);
    }
    if (!empty($card['error'])) {
        reply(200, ['error' => (string)$card['error']]);
    }
    $int = fn($v) => is_numeric($v) ? (int)$v : null;
    $kind = in_array($card['kind'] ?? '', ['monster', 'spell', 'trap'], true) ? $card['kind'] : 'monster';
    reply(200, ['card' => [
        'name' => mb_substr(trim((string)($card['name'] ?? 'Unknown card')), 0, 80),
        'kind' => $kind,
        'attribute' => strtoupper(trim((string)($card['attribute'] ?? ''))),
        'level' => $int($card['level'] ?? null),
        'atk' => $int($card['atk'] ?? null),
        'def' => $int($card['def'] ?? null),
        'type' => mb_substr(trim((string)($card['type'] ?? '')), 0, 60),
        'summary' => mb_substr(trim((string)($card['summary'] ?? '')), 0, 200),
    ]]);
} catch (AuthenticationException) {
    reply(502, ['error' => 'The API key on the server was rejected. Check server/config.php.']);
} catch (RateLimitException) {
    reply(429, ['error' => 'The AI is busy right now. Try again in a moment.']);
} catch (APIStatusException $e) {
    reply(502, ['error' => 'The AI service returned an error (' . ($e->type?->value ?? 'unknown') . ').']);
} catch (APIConnectionException) {
    reply(504, ['error' => 'Could not reach the AI service. Try again.']);
}
