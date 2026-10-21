<?php
// AI Lens and Translate for SmartEyes.
// The browser posts a camera snapshot; this file asks Claude Haiku about it and returns the answer.
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

$languages = ['en' => 'English', 'ms' => 'Malay', 'zh' => 'Simplified Chinese'];
$mode = (string)($input['mode'] ?? 'identify');

if ($mode === 'handwriting') {
    $prompt = "This image shows words written in the air with a fingertip and tracked by a camera, so the strokes are shaky, letters may be joined or uneven, and dots or crosses may be missing.\n"
        . "Work out the most likely intended text (any language). Reply with the text only: no quotes, no explanation, no markdown.\n"
        . "If it is a drawing rather than writing, reply with a few words describing the drawing in square brackets.";
} elseif ($mode === 'translate') {
    $lang = $languages[(string)($input['lang'] ?? 'en')] ?? 'English';
    $prompt = "Read all the text you can see in this photo and translate it into {$lang}.\n"
        . "Reply in exactly this format:\n"
        . "Line 1: the detected source language, for example \"Japanese → {$lang}\".\n"
        . "Then the translation, keeping the original line breaks. Plain text only, no markdown.\n"
        . "If there is no readable text, reply with \"No text found\" on line 1 and one short sentence saying what you see instead.";
} else {
    $prompt = "You are the encyclopedia inside a pair of smart glasses. Identify the main object, place, plant, animal, food, product or landmark in this photo.\n"
        . "Reply in exactly this format:\n"
        . "Line 1: its name only (be specific, e.g. the species or model if you can tell).\n"
        . "Then 2 to 4 short sentences of useful, encyclopedia-style facts about it. Plain text only, no markdown.\n"
        . "If you are unsure, say what it most likely is and how confident you are.";
}

try {
    $client = new Client(apiKey: $key);
    $message = $client->messages->create(
        model: MODEL,
        maxTokens: 1024,
        outputConfig: ['effort' => 'low'],
        messages: [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => $image]],
                ['type' => 'text', 'text' => $prompt],
            ],
        ]],
    );

    if ($message->stopReason === 'refusal') {
        reply(200, ['title' => 'No answer', 'text' => 'The AI declined to describe this photo.']);
    }

    $text = '';
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $text .= $block->text;
        }
    }
    $lines = preg_split('/\R/', trim($text)) ?: [''];
    $title = trim(array_shift($lines), " \t*#\"");
    reply(200, ['title' => $title, 'text' => trim(implode("\n", $lines))]);
} catch (AuthenticationException) {
    reply(502, ['error' => 'The API key on the server was rejected. Check server/config.php.']);
} catch (RateLimitException) {
    reply(429, ['error' => 'The AI is busy right now. Try again in a moment.']);
} catch (APIStatusException $e) {
    reply(502, ['error' => 'The AI service returned an error (' . ($e->type?->value ?? 'unknown') . ').']);
} catch (APIConnectionException) {
    reply(504, ['error' => 'Could not reach the AI service. Try again.']);
}
