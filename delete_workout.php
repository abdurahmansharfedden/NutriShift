<?php
// delete_workout.php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = (int)$_SESSION['user_id'];

if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM user_programs WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $id, ':uid' => $user_id]);
    $_SESSION['flash_success'] = "Workout plan deleted.";
}

header('Location: user_dashboard.php');
exit();
