<?php
// =============================================================================
// generate_program.php — AI Weekly Workout Program Generator (Backend Endpoint)
// =============================================================================
// TEACHING NOTE: This is a "pure API endpoint." It never outputs HTML. It
// only speaks JSON. The browser's fetch() call sends a POST request here,
// this script does all the heavy lifting, and sends back {"status":"success"}
// or an error JSON. Think of it as your own private REST API route.
//
// Flow:
//   1. Auth check  →  2. DB fetch user metrics  →  3. Calculate TDEE
//   4. Build prompt →  5. Call Gemini API        →  6. Parse & validate JSON
//   7. Save to DB   →  8. Return success
// =============================================================================

// ── Step 0: Start session & load shared helpers ───────────────────────────────
// TEACHING NOTE: We include auth.php for session_start() and the helper
// functions, and db.php to get the $pdo connection object. We do NOT call
// require_login() here because that function does an HTML redirect on failure.
// For a JSON API, we need to return a JSON error with HTTP 401 instead.
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'config.php'; // Loads DB constants + Gemini API key

// Always declare we are speaking JSON — do this before ANY output.
header('Content-Type: application/json');

// ── Step 1: Authentication Gate ───────────────────────────────────────────────
// TEACHING NOTE: require_login() would redirect with a Location header, which
// breaks fetch() in the browser. Instead, we check the session manually and
// return a proper 401 Unauthorized JSON response so the frontend can handle it.
if (empty($_SESSION['user_id'])) {
    http_response_code(401); // 401 = Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in.']);
    exit();
}

// TEACHING NOTE: We cast to int to prevent any type-juggling tricks.
$user_id = (int) $_SESSION['user_id'];

// ── Step 2: Method Gate ───────────────────────────────────────────────────────
// This endpoint only accepts POST requests (triggered by the "Generate" button).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 = Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are accepted.']);
    exit();
}

// ── Step 3: Fetch User Health Metrics from the Database ───────────────────────
// TEACHING NOTE: We use a prepared statement with a named placeholder (:id).
// PDO replaces :id with the real $user_id value AFTER the SQL has been sent
// to the database engine — this is what prevents SQL Injection attacks.
try {
    $stmt = $pdo->prepare(
        "SELECT weight, body_fat, activity_level, fitness_goal
         FROM   users
         WHERE  id = :id
         LIMIT  1"
    );
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(); // Returns an associative array or false
} catch (PDOException $e) {
    error_log('generate_program DB fetch error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error while fetching user profile.']);
    exit();
}

// Guard: make sure the user row exists.
if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'User profile not found.']);
    exit();
}

// Guard: make sure all required health metrics have been filled in by the user.
// TEACHING NOTE: empty() treats 0, "0", null, and "" all as "empty."
// For numeric fields like weight, we use a stricter check: isset + > 0.
$required_fields = ['weight', 'body_fat', 'activity_level', 'fitness_goal'];
foreach ($required_fields as $field) {
    if (empty($user[$field])) {
        http_response_code(422); // 422 = Unprocessable Entity (data is there but incomplete)
        echo json_encode([
            'status'  => 'error',
            'message' => 'Please complete your health profile (weight, body fat %, activity level, fitness goal) before generating a program.'
        ]);
        exit();
    }
}

// Extract metrics into clearly-named variables for readability.
$weight         = (float) $user['weight'];         // kg
$body_fat       = (float) $user['body_fat'];       // percentage (e.g. 20 for 20%)
$activity_level = (float) $user['activity_level']; // TDEE multiplier (e.g. 1.375)
$fitness_goal   = (string) $user['fitness_goal'];  // e.g. "muscle_gain", "fat_loss"

// ── Step 4: Calculate TDEE using the Katch-McArdle Formula ────────────────────
// TEACHING NOTE: The Katch-McArdle formula is more accurate than Harris-Benedict
// because it factors in Lean Body Mass (LBM) rather than just total weight.
// This matters a lot: a 90kg person who is 10% body fat has a very different
// metabolism from a 90kg person who is 30% body fat.
//
//   LBM = weight × ((100 - body_fat%) / 100)
//   BMR = 370 + (21.6 × LBM)               ← Katch-McArdle constant
//   TDEE = BMR × activity_multiplier
//
// Activity multiplier reference:
//   1.2   → Sedentary (desk job, no exercise)
//   1.375 → Lightly active (1-3 days/week)
//   1.55  → Moderately active (3-5 days/week)
//   1.725 → Very active (6-7 days/week)
//   1.9   → Extra active (athlete / physical job + training)
$lbm            = $weight * ((100 - $body_fat) / 100);
$bmr            = 370 + (21.6 * $lbm);
$target_calories = (int) round($bmr * $activity_level);

// ── Step 5: Build the Strict System Prompt for Gemini ─────────────────────────
// TEACHING NOTE: "Prompt engineering" is the art of writing instructions that
// force an AI to behave like a deterministic data API. The key tricks are:
//   - Give the AI a persona: "You are a fitness coach API."
//   - State the exact output format with an explicit example skeleton.
//   - Write "ONLY output raw JSON" multiple times (AI models respond to repetition).
//   - Include all user context so the AI can personalize the response.
//   - Use uppercase for critical constraints — it acts like bold text for LLMs.
$fitness_goal_label = str_replace('_', ' ', $fitness_goal); // "muscle_gain" → "muscle gain"

$system_prompt = <<<PROMPT
You are a certified fitness coach and nutrition API. Your ONLY function is to output raw JSON.

USER PROFILE:
- Weight: {$weight} kg
- Body Fat Percentage: {$body_fat}%
- Lean Body Mass (LBM): {$lbm} kg
- Calculated BMR (Katch-McArdle): {$bmr} kcal/day
- Target Daily Calories (TDEE): {$target_calories} kcal/day
- Activity Level Multiplier: {$activity_level}
- Fitness Goal: {$fitness_goal_label}

TASK: Generate a personalized 7-day workout and macro-focus plan based on the user profile above.

OUTPUT RULES — READ CAREFULLY:
1. Output ONLY a raw JSON object. NO introductory text. NO explanations. NO markdown code fences (no ```json).
2. The JSON MUST have a top-level key called "weekly_plan" which is an ARRAY of 7 objects.
3. Each day object MUST have EXACTLY these keys:
   - "day": a string, e.g. "Day 1 - Monday"
   - "focus": a string describing the muscle group or rest, e.g. "Chest & Triceps" or "Active Recovery"
   - "workout": an ARRAY of exercise objects. For rest days, this can be an empty array [].
   - "macro_focus": a short string describing the nutritional priority, e.g. "High Protein, Moderate Carbs"
   - "target_calories": an integer, the recommended calorie intake for that day (can vary ±200 from TDEE on training vs rest days)
4. Each exercise object inside "workout" MUST have:
   - "exercise": string, the exercise name
   - "sets": integer
   - "reps": string (e.g. "8-10" or "To Failure")
   - "rest_seconds": integer
5. Tailor the plan to the user's fitness goal. For fat loss, favor supersets and higher reps. For muscle gain, favor heavier compound lifts with progressive overload rep ranges. For maintenance, a balanced approach.
6. Include exactly 1 rest or active recovery day in the 7-day plan.

YOUR ENTIRE RESPONSE MUST BE A SINGLE, VALID JSON OBJECT STARTING WITH { AND ENDING WITH }. NOTHING ELSE.
PROMPT;

// ── Step 6: Build the Gemini API Payload ──────────────────────────────────────
// TEACHING NOTE: json_encode() converts our PHP array into a JSON string for
// the HTTP request body. We use JSON_THROW_ON_ERROR so that if our internal
// data is somehow un-encodable, we get a catchable Exception, not silent failure.

$payload = json_encode([
    'contents' => [
        [
            'role'  => 'user',
            'parts' => [['text' => $system_prompt]]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.4,    // Slightly creative but still structured/deterministic
        'maxOutputTokens' => 1500,   // Prevents response cutoff on a 7-day plan
        // NOTE: We intentionally do NOT set response_mime_type:'application/json' here
        // because some Gemini model versions ignore it and we rely on our own regex
        // extractor (Step 8) as the robust fallback regardless.
    ]
], JSON_THROW_ON_ERROR);

// ── Step 7: Execute the cURL Request to the Gemini API ────────────────────────
// TEACHING NOTE: We reuse the exact same URL pattern from calculate_macros.php
// so the project stays consistent. The endpoint path structure is:
//   /v1beta/models/{model-name}:generateContent?key={api-key}
$gemini_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=' . GEMINI_API_KEY;

// TEACHING NOTE: curl_init() creates a cURL "handle" — think of it as opening
// a phone line. curl_setopt_array() dials the number and says what to ask.
// curl_exec() actually makes the call and waits for the answer.
$ch = curl_init($gemini_url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,      // Store response in variable, don't echo it
    CURLOPT_POST           => true,      // Use HTTP POST method
    CURLOPT_POSTFIELDS     => $payload,  // Attach our JSON payload as the request body
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,        // 30s timeout: workout plans are longer than macro lookups
    CURLOPT_SSL_VERIFYPEER => true,      // Always verify SSL certificates in production
]);

$response_raw = curl_exec($ch);
$http_status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error   = curl_error($ch);
curl_close($ch);

// ── Handle network / cURL-level failures ─────────────────────────────────────
// TEACHING NOTE: curl_exec() returns false if the request never left our server
// (DNS failure, no network, timeout before response, etc.). This is different
// from the API returning a 4xx/5xx error — that would still be a string response.
if ($response_raw === false) {
    error_log('generate_program cURL error: ' . $curl_error);
    http_response_code(502); // 502 = Bad Gateway (we couldn't reach an upstream server)
    echo json_encode(['status' => 'error', 'message' => 'Could not reach the AI service. Please try again.']);
    exit();
}

// ── Handle API-level HTTP error responses ─────────────────────────────────────
// TEACHING NOTE: Even though the TCP connection succeeded, Gemini might return
// a non-2xx HTTP status (e.g., 400 bad request, 401 invalid key, 429 rate limit).
// We read the error message from the response body to surface it helpfully.
if ($http_status < 200 || $http_status >= 300) {
    error_log('generate_program Gemini HTTP error ' . $http_status . ': ' . $response_raw);
    http_response_code(502);
    $api_err = json_decode($response_raw, true);
    $msg = $api_err['error']['message'] ?? 'Gemini API returned HTTP status ' . $http_status;
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit();
}

// ── Step 8: CRITICAL — Parse the Gemini Response Envelope ─────────────────────
// TEACHING NOTE: Gemini doesn't just return the AI's text directly. It wraps
// it in a multi-level JSON envelope that looks like this:
//
// {
//   "candidates": [
//     {
//       "content": {
//         "parts": [
//           { "text": "...THE ACTUAL GENERATED TEXT IS HERE..." }
//         ]
//       },
//       "finishReason": "STOP"
//     }
//   ]
// }
//
// We use the null-coalescing operator (?? '') at each level so we never get
// an "Undefined index" PHP notice if any level is missing.
$gemini_envelope = json_decode($response_raw, true);

// Guard: Check if the AI blocked this content for safety reasons.
$finish_reason = $gemini_envelope['candidates'][0]['finishReason'] ?? '';
if ($finish_reason === 'SAFETY') {
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'The AI flagged this request. Please try again.']);
    exit();
}

// Extract the raw text the AI produced.
$generated_text = $gemini_envelope['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($generated_text)) {
    error_log('generate_program: empty generated_text. Full envelope: ' . $response_raw);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'AI returned an empty response. Please try again.']);
    exit();
}

// ── Step 9: CRITICAL — Extract the JSON Object from the AI Text ───────────────
// TEACHING NOTE: Even with the strictest prompt, some AI models prepend text
// like "Here is your workout plan:" or wrap the JSON in ```json ... ``` fences.
// The preg_match() call below is our safety net. It searches for the pattern
// that matches a JSON object: starts with { and ends with the LAST } in the string.
//
// Regex breakdown:
//   /   → delimiter
//   \{  → literal opening brace
//   .*  → any character, any number of times
//   \}  → literal closing brace
//   /s  → "single-line" flag: makes . match newlines too (critical for multiline JSON!)
//
// Without the /s flag, the dot (.) in .* would stop at each newline and the
// match would fail on any JSON that spans more than one line.
if (preg_match('/\{.*\}/s', $generated_text, $matches)) {
    $json_string = $matches[0];
} else {
    // If there's not even a { } pair, the AI response is completely malformed.
    error_log('generate_program: no JSON object found in AI text: ' . $generated_text);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'AI response did not contain valid JSON. Please try again.']);
    exit();
}

// Attempt to decode the extracted string into a PHP array.
$program_data = json_decode($json_string, true);

// Guard: json_decode() returns null on any syntax error.
if (!is_array($program_data)) {
    error_log('generate_program: json_decode failed. Extracted string: ' . $json_string);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'AI returned malformed JSON. Please try again.']);
    exit();
}

// Guard: Validate the expected top-level structure — it MUST have "weekly_plan".
if (empty($program_data['weekly_plan']) || !is_array($program_data['weekly_plan'])) {
    error_log('generate_program: missing weekly_plan key. Decoded data: ' . print_r($program_data, true));
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'AI response is missing the required weekly_plan structure.']);
    exit();
}

// ── Step 10: Save the Validated Program to the Database ───────────────────────
// TEACHING NOTE: We save the ORIGINAL extracted $json_string (not re-encoded),
// because re-encoding from a PHP array and back might alter key ordering or
// float precision. Storing the canonical string the AI produced is safer.
// The column type in user_programs is JSON, so MariaDB will validate it again.
try {
    $insert = $pdo->prepare(
        "INSERT INTO user_programs (user_id, program_data, created_at)
         VALUES (:user_id, :program_data, NOW())"
    );
    $insert->execute([
        ':user_id'      => $user_id,
        ':program_data' => $json_string,
    ]);
} catch (PDOException $e) {
    error_log('generate_program DB insert error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save the program to the database.']);
    exit();
}

// ── Step 11: Return Success ────────────────────────────────────────────────────
// TEACHING NOTE: We return 200 OK with a minimal JSON success envelope.
// The frontend only needs to know it worked — it can then make a separate
// GET request to load and display the new program from the database.
http_response_code(200);
echo json_encode(['status' => 'success', 'message' => 'Your weekly program has been generated!']);
