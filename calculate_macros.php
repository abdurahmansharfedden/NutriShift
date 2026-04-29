<?php
// =============================================================================
// calculate_macros.php — Secure Gemini API Middleman
// =============================================================================
// TEACHING NOTE: This file is a "backend endpoint." The browser never calls
// the Gemini API directly (that would expose your API key to anyone who opens
// DevTools). Instead, the browser calls THIS file, which holds the key safely
// on the server, calls Gemini, and forwards only the result back.
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'config.php';

// ── Security gates ─────────────────────────────────────────────────────────
// TEACHING NOTE: We enforce three things before doing any real work:
//   1. The user must be logged in (no anonymous API abuse).
//   2. The request must be POST (not a browser address-bar GET).
//   3. We set the response type to JSON so the browser knows what to parse.
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 = Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are accepted.']);
    exit();
}

// ── API Key ────────────────────────────────────────────────────────────────
// TEACHING NOTE: In a real production project you would load this from an
// environment variable (getenv('GEMINI_KEY')) or a config file that sits
// OUTSIDE the web root and is never committed to Git.
// For this assignment, defining it here as a constant is perfectly fine.

// ── Read & validate input ──────────────────────────────────────────────────
// TEACHING NOTE: file_get_contents('php://input') reads the raw HTTP request
// body. We use this instead of $_POST because our JS sends JSON, not a form.
// json_decode(..., true) converts the JSON string into a PHP associative array.
$body = json_decode(file_get_contents('php://input'), true);
$meal_text = trim($body['meal_text'] ?? '');

if (empty($meal_text)) {
    http_response_code(400); // 400 = Bad Request
    echo json_encode(['error' => 'meal_text field is required.']);
    exit();
}

// Limit input length to prevent token abuse / runaway costs
if (strlen($meal_text) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Description too long (max 500 characters).']);
    exit();
}

// ── Build the Gemini API payload ───────────────────────────────────────────
// TEACHING NOTE: We use a "bossy" prompt to force the AI to behave like a 
// pure data API and ignore its conversational "chatty" personality.

$full_prompt = "ACT AS A NUTRITION DATA API. 
INPUT: '" . $meal_text . "'
JSON OUTPUT ONLY: {\"calories\":0, \"protein\":0, \"carbs\":0, \"fat\":0}";

$payload = json_encode([
    'contents' => [
        [
            'role'  => 'user',
            'parts' => [['text' => $full_prompt]]
        ]
    ],
    'generationConfig' => [
        'temperature'        => 0.1,
        'maxOutputTokens'    => 1000,
        'response_mime_type' => 'application/json'
    ]
]);

// ── Call the Gemini API with cURL ──────────────────────────────────────────
// TEACHING NOTE: cURL (Client URL) is PHP's built-in HTTP client. It lets
// us make outbound HTTP requests FROM the server. The steps are always:
//   1. curl_init()      — create a new cURL "handle" (resource)
//   2. curl_setopt()    — configure what to do (URL, method, headers, body)
//   3. curl_exec()      — actually send the request and get the response
//   4. curl_getinfo()   — check the HTTP status code of the response
//   5. curl_close()     — release the handle and its memory
$gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=' . GEMINI_API_KEY;

$ch = curl_init($gemini_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,       // Return response as a string (don't echo it)
    CURLOPT_POST           => true,       // This is a POST request
    CURLOPT_POSTFIELDS     => $payload,   // The JSON body we built above
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json', // Tell Gemini we're sending JSON
        'Accept: application/json',       // Tell Gemini we expect JSON back
    ],
    CURLOPT_TIMEOUT        => 15,         // Give up after 15 seconds
    CURLOPT_SSL_VERIFYPEER => true,       // Always verify SSL in production!
]);

$response_raw  = curl_exec($ch);
$http_status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error    = curl_error($ch);
curl_close($ch);

// ── Handle cURL / network errors ───────────────────────────────────────────
// TEACHING NOTE: curl_exec() returns false if the request never reached the
// server (no internet, DNS failure, timeout, etc.). curl_error() gives the reason.
if ($response_raw === false) {
    http_response_code(502); // 502 = Bad Gateway (our server couldn't reach upstream)
    echo json_encode(['error' => 'Could not reach Gemini API: ' . $curl_error]);
    exit();
}

// TEACHING NOTE: A non-2xx status code means the API itself returned an error
// (e.g., 400 bad request, 401 invalid key, 429 rate limit, 500 server error).
if ($http_status < 200 || $http_status >= 300) {
    http_response_code(502);
    $api_err = json_decode($response_raw, true);
    $msg = $api_err['error']['message'] ?? 'Gemini API returned HTTP ' . $http_status;
    echo json_encode(['error' => $msg]);
    exit();
}

// ── Parse the Gemini response ──────────────────────────────────────────────
// TEACHING NOTE: Gemini wraps the model's text inside a nested structure.
// The actual generated text lives at:
//   $gemini['candidates'][0]['content']['parts'][0]['text']
// We safely navigate this with the null coalescing operator (??) at each level.
$gemini = json_decode($response_raw, true);

// 1. Check for Safety Blocks
if (isset($gemini['candidates'][0]['finishReason']) && $gemini['candidates'][0]['finishReason'] === 'SAFETY') {
    http_response_code(502);
    echo json_encode(['error' => 'The AI blocked this request for safety reasons. Try rephrasing.']);
    exit();
}

$generated_text = $gemini['candidates'][0]['content']['parts'][0]['text'] ?? '';

// 2. The "Search & Rescue" Regex
// This hunts for the { } even if the AI says "Here is the JSON: { ... }"
if (preg_match('/\{.*\}/s', $generated_text, $matches)) {
    $cleaned = $matches[0];
} else {
    $cleaned = $generated_text;
}

$macros = json_decode($cleaned, true);

if (!is_array($macros)) {
    http_response_code(502);
    echo json_encode([
        'error' => 'AI structure invalid.',
        'debug_full_response' => $response_raw // THIS SHOWS EVERYTHING FROM GOOGLE
    ]);
    exit();
}

// Success!
echo json_encode(['status' => 'ok', 'macros' => $macros]);




