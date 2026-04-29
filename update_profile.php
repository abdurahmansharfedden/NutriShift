<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);


// update_profile.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];
$height = $_POST['height'] ?? null;
$weight = $_POST['weight'] ?? null;
$body_fat = $_POST['body_fat'] ?? null;
$activity_level = $_POST['activity_level'] ?? 1.2;
$fitness_goal = $_POST['fitness_goal'] ?? 'maintenance';

// Step B: Fetch the old weight to see if it changed
$stmt = $pdo->prepare("SELECT weight FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$old_user = $stmt->fetch(PDO::FETCH_ASSOC);
$old_weight = $old_user ? $old_user['weight'] : null;

// Step A: Update users table with all form metrics
$sql = "UPDATE users 
        SET height = ?, weight = ?, body_fat = ?, activity_level = ?, fitness_goal = ? 
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$success = $stmt->execute([$height, $weight, $body_fat, $activity_level, $fitness_goal, $user_id]);

// Step B: Insert into weight_logs if weight has changed
if ($success && $weight !== null && (float)$weight !== (float)$old_weight) {
    $weight_sql = "INSERT INTO weight_logs (user_id, weight, logged_at) VALUES (?, ?, NOW())";
    $weight_stmt = $pdo->prepare($weight_sql);
    $weight_stmt->execute([$user_id, $weight]);
}

// Step C: Redirect back to profile.php with a success message
if ($success) {
    header("Location: profile.php?success=1");
    exit();
} else {
    echo "Something went wrong updating your profile.";
}