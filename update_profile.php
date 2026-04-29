<?php
session_start();

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// 3. Grab the data from the form
$user_id = $_SESSION['user_id'];
$height = $_POST['height'] ?? null;
$weight = $_POST['weight'] ?? null;
$body_fat = $_POST['body_fat'] ?? null;
$activity_level = $_POST['activity_level'] ?? 1.2;
$fitness_goal = $_POST['fitness_goal'] ?? 'maintain';

// 4. Update the Database
$sql = "UPDATE users 
        SET height = ?, weight = ?, body_fat = ?, activity_level = ?, fitness_goal = ? 
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$success = $stmt->execute([$height, $weight, $body_fat, $activity_level, $fitness_goal, $user_id]);

// 5. Send them back to the dashboard
if ($success) {
    // You can pass a success parameter in the URL if you want to show a toast notification later
    header("Location: profile.php?success=1");
    exit();
} else {
    echo "Something went wrong updating your profile.";
}