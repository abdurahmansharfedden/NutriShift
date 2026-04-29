<?php
// chat_handler.php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once 'includes/db.php';
require_once 'config.php';

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);
$user_message = trim($input['message'] ?? '');

if (empty($user_message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty message']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Context Query
$stmt = $pdo->prepare('SELECT weight, height, body_fat, fitness_goal, activity_level FROM users WHERE id = :uid');
$stmt->execute([':uid' => $user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Workout Query
$prog_stmt = $pdo->prepare('SELECT program_data FROM user_programs WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
$prog_stmt->execute([':uid' => $user_id]);
$latest_prog = $prog_stmt->fetchColumn();

// Format stats
$weight = $user_stats['weight'] ?? 'unknown';
$body_fat = $user_stats['body_fat'] ?? 'unknown';
$goal = $user_stats['fitness_goal'] ?? 'unknown';
$activity = $user_stats['activity_level'] ?? 'unknown';

// System Prompt Construction
$system_instruction = "You are an expert fitness and nutrition AI coach for NutriShift. Respond concisely. The user's current stats are: Weight: {$weight}kg, Body Fat: {$body_fat}%, Goal: {$goal}, Activity Level: {$activity}. Base your advice on these exact metrics.";

// Include the workout data if it exists to give the AI context
if ($latest_prog) {
    $system_instruction .= "\nUser's current workout plan context: " . $latest_prog;
}

// API Call
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . GEMINI_API_KEY;

$data = [
    "system_instruction" => [
        "parts" => [
            ["text" => $system_instruction]
        ]
    ],
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $user_message]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    http_response_code(500);
    $curl_error = curl_error($ch);
    
    // Send back EXACTLY what went wrong
    echo json_encode([
        'error' => 'API Connection Failed',
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'gemini_response' => $response
    ]);
    exit();
}

$responseData = json_decode($response, true);
$ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'Unavailable.';

// Return JSON
echo json_encode(['response' => trim($ai_text)]);
exit();
