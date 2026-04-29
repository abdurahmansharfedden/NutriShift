<?php
// generate_workout.php
session_start();

require_once 'includes/db.php';
require_once 'config.php';

// Show errors for debugging - remove these two lines once it works!
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Context Query
$stmt = $pdo->prepare('SELECT weight, body_fat, fitness_goal FROM users WHERE id = :uid');
$stmt->execute([':uid' => $user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$weight = $user_stats['weight'] ?? 'unknown';
$body_fat = $user_stats['body_fat'] ?? 'unknown';
$goal = $user_stats['fitness_goal'] ?? 'unknown';
$target_split = isset($_POST['target_split']) ? htmlspecialchars(strip_tags($_POST['target_split'])) : 'Full Body';

$system_instruction = "You are a fitness AI. The user weighs {$weight}kg with {$body_fat}% body fat, goal: {$goal}. Generate a daily workout. The user explicitly requested a [{$target_split}] workout for today. You MUST ONLY select exercises that fit this specific split. Do not include upper body exercises on a lower body day, etc. You MUST respond ONLY with a raw, valid JSON object exactly matching this schema, with no markdown, no backticks, and no extra text: {\"workout_name\": \"String\", \"exercises\": [{\"name\": \"String\", \"sets\": Number, \"reps\": \"String\"}]}.";

// API Call to Gemini 3 Flash Preview
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . GEMINI_API_KEY;

$data = [
    "systemInstruction" => [ // Changed to camelCase for API compatibility
        "parts" => [
            ["text" => $system_instruction]
        ]
    ],
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => "Generate my workout plan."]
            ]
        ]
    ]
];

// Initialize cURL FIRST
$ch = curl_init($url);

// Set options AFTER initialization
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Needed for Localhost
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

// Get info BEFORE closing the handle
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

curl_close($ch);

if ($http_code === 200 && $response) {
    $responseData = json_decode($response, true);
    $ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Clean up potential markdown blocks
    $ai_text = preg_replace('/```json\s*/i', '', $ai_text);
    $ai_text = preg_replace('/```\s*/', '', $ai_text);
    $ai_text = trim($ai_text);
    
    // Validate JSON
    $decoded = json_decode($ai_text, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $insert_stmt = $pdo->prepare('INSERT INTO user_programs (user_id, program_data) VALUES (:uid, :data)');
        $insert_stmt->execute([
            ':uid' => $user_id,
            ':data' => json_encode($decoded) // Re-encode to ensure clean database storage
        ]);
        
        $_SESSION['flash_success'] = "New AI workout generated successfully!";
    } else {
        $_SESSION['flash_error'] = "AI returned invalid JSON: " . json_last_error_msg();
    }
} else {
    $_SESSION['flash_error'] = "Failed to connect: HTTP $http_code | Error: $curl_error";
}

header('Location: user_dashboard.php');
exit();