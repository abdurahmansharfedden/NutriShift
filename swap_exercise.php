<?php
// swap_exercise.php
session_start();
require_once 'includes/db.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$old_ex = isset($_GET['old_ex']) ? trim($_GET['old_ex']) : '';
$user_id = (int)$_SESSION['user_id'];

if ($id <= 0 || empty($old_ex)) {
    header('Location: user_dashboard.php');
    exit();
}

// Context: Fetch user stats
$stmt = $pdo->prepare('SELECT weight, body_fat, fitness_goal FROM users WHERE id = :uid');
$stmt->execute([':uid' => $user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$weight = $user_stats['weight'] ?? 'unknown';
$body_fat = $user_stats['body_fat'] ?? 'unknown';
$goal = $user_stats['fitness_goal'] ?? 'unknown';

// Fetch existing program_data
$prog_stmt = $pdo->prepare('SELECT program_data FROM user_programs WHERE id = :id AND user_id = :uid');
$prog_stmt->execute([':id' => $id, ':uid' => $user_id]);
$prog_row = $prog_stmt->fetch(PDO::FETCH_ASSOC);

if (!$prog_row) {
    header('Location: user_dashboard.php');
    exit();
}

$program_data = json_decode($prog_row['program_data'], true);
if (!$program_data || !isset($program_data['exercises'])) {
    header('Location: user_dashboard.php');
    exit();
}

// AI Call: ask Gemini
$system_instruction = "You are a fitness AI. The user weighs {$weight}kg with {$body_fat}% body fat, goal: {$goal}. Suggest ONE replacement exercise for '{$old_ex}' that fits the same muscle group/split. You MUST return ONLY a raw JSON object: {\"name\": \"Exercise\", \"sets\": 3, \"reps\": \"10\"} with no markdown, no backticks, and no extra text.";

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . GEMINI_API_KEY;

$data = [
    "system_instruction" => [
        "parts" => [["text" => $system_instruction]]
    ],
    "contents" => [
        [
            "role" => "user",
            "parts" => [["text" => "Replace {$old_ex}"]]
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

if ($http_code === 200 && $response) {
    $responseData = json_decode($response, true);
    $ai_text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    // Clean up potential markdown blocks if AI ignored the prompt
    $ai_text = preg_replace('/```json\s*/i', '', $ai_text);
    $ai_text = preg_replace('/```\s*/', '', $ai_text);
    $ai_text = trim($ai_text);
    
    $new_ex = json_decode($ai_text, true);
    
    if (json_last_error() === JSON_ERROR_NONE && isset($new_ex['name'])) {
        // Update Logic
        foreach ($program_data['exercises'] as $index => $ex) {
            if ($ex['name'] === $old_ex) {
                $program_data['exercises'][$index] = $new_ex;
                break;
            }
        }
        
        $update_stmt = $pdo->prepare('UPDATE user_programs SET program_data = :data WHERE id = :id AND user_id = :uid');
        $update_stmt->execute([
            ':data' => json_encode($program_data),
            ':id' => $id,
            ':uid' => $user_id
        ]);
        
        $_SESSION['flash_success'] = "Exercise '{$old_ex}' swapped with '{$new_ex['name']}'!";
    } else {
        $_SESSION['flash_error'] = "AI failed to generate a valid replacement.";
    }
} else {
    $_SESSION['flash_error'] = "Failed to connect to AI Coach.";
}

header('Location: user_dashboard.php');
exit();
