<?php
// =============================================================================
// process_meal.php — Backend Endpoint for Cycle & Food Log CRUD
// =============================================================================
// TEACHING NOTE: This file handles ALL form submissions that create, update, or
// delete cycles and food logs. It is never visited directly in the browser —
// HTML forms POST to it, it processes the data, then REDIRECTS back to the
// dashboard. This pattern is called POST/Redirect/GET (PRG) and it prevents
// the browser from re-submitting the form if the user hits "Refresh."
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';

require_login(); // Protect this endpoint — unauthenticated users go to index.php

// TEACHING NOTE: We read the action name from the POST body, defaulting to ''
// if it doesn't exist. All routing below is based on this one variable.
$action = $_POST['action'] ?? '';

// Helper: redirect back to the dashboard with a flash message
function redirect_dashboard(string $type, string $msg): void {
    flash($type, $msg);
    header('Location: user_dashboard.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: add_cycle — INSERT a new Biological Cycle for this user
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'add_cycle') {

    $name       = trim($_POST['cycle_name']      ?? '');
    $start      = trim($_POST['start_time']      ?? '');
    $end        = trim($_POST['end_time']         ?? '');
    $target_cal = (int)($_POST['target_calories'] ?? 2000);

    if (empty($name) || empty($start) || empty($end)) {
        redirect_dashboard('error', 'Cycle name and time range are required.');
    }

    if ($target_cal < 100 || $target_cal > 20000) {
        redirect_dashboard('error', 'Target calories must be between 100 and 20,000.');
    }

    // TEACHING NOTE: strtotime() converts a datetime string like "2024-11-01T18:00"
    // into a Unix timestamp (seconds since 1970-01-01). We use this to compare
    // the two datetimes. If start >= end we reject it (cycles can span midnight,
    // but start must be before end on the same calendar path OR end must be next day).
    // We allow end < start only when the date part differs (overnight cycles).
    $start_ts = strtotime($start);
    $end_ts   = strtotime($end);

    if ($start_ts === false || $end_ts === false) {
        redirect_dashboard('error', 'Invalid date/time format.');
    }

    if ($end_ts <= $start_ts) {
        redirect_dashboard('error', 'End time must be after start time. For overnight cycles, set the end date to the next day.');
    }

    // TEACHING NOTE: $_SESSION['user_id'] was set during login. We pull it here
    // to ensure this cycle is linked to the CURRENTLY logged-in user — never
    // trust a hidden form field for the user_id (that can be forged!).
    $stmt = $pdo->prepare(
        'INSERT INTO cycles (user_id, cycle_name, start_time, end_time, target_calories)
         VALUES (:uid, :name, :start, :end, :cal)'
    );
    $stmt->execute([
        ':uid'   => $_SESSION['user_id'],
        ':name'  => $name,
        ':start' => $start,
        ':end'   => $end,
        ':cal'   => $target_cal,
    ]);

    redirect_dashboard('success', "Cycle \"" . htmlspecialchars($name, ENT_QUOTES) . "\" created successfully!");
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: edit_cycle — UPDATE an existing cycle
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit_cycle') {

    $cycle_id   = (int)($_POST['cycle_id']       ?? 0);
    $name       = trim($_POST['cycle_name']       ?? '');
    $start      = trim($_POST['start_time']       ?? '');
    $end        = trim($_POST['end_time']         ?? '');
    $target_cal = (int)($_POST['target_calories'] ?? 2000);

    if (!$cycle_id || empty($name) || empty($start) || empty($end)) {
        redirect_dashboard('error', 'All cycle fields are required.');
    }

    // TEACHING NOTE: SECURITY CHECK — we verify that the cycle being edited
    // belongs to the logged-in user. Without this check, user A could edit
    // user B's cycle by simply guessing the cycle ID. This is called an
    // Insecure Direct Object Reference (IDOR) vulnerability — one of the most
    // common web security bugs. We always add "AND user_id = :uid" to
    // UPDATE/DELETE queries for user-owned resources.
    $stmt = $pdo->prepare(
        'UPDATE cycles
            SET cycle_name = :name, start_time = :start, end_time = :end, target_calories = :cal
          WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        ':name'  => $name,
        ':start' => $start,
        ':end'   => $end,
        ':cal'   => $target_cal,
        ':id'    => $cycle_id,
        ':uid'   => $_SESSION['user_id'],
    ]);

    redirect_dashboard('success', 'Cycle updated successfully!');
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: delete_cycle — DELETE a cycle and all its food logs (CASCADE handles it)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_cycle') {

    $cycle_id = (int)($_POST['cycle_id'] ?? 0);
    if (!$cycle_id) {
        redirect_dashboard('error', 'Invalid cycle.');
    }

    // Security: "AND user_id = :uid" ensures users can only delete their own cycles.
    $stmt = $pdo->prepare('DELETE FROM cycles WHERE id = :id AND user_id = :uid');
    $stmt->execute([':id' => $cycle_id, ':uid' => $_SESSION['user_id']]);

    // TEACHING NOTE: rowCount() returns how many rows were affected by the last
    // query. If it is 0, either the cycle didn't exist or it belonged to another
    // user — both cases are handled gracefully here.
    if ($stmt->rowCount() === 0) {
        redirect_dashboard('error', 'Cycle not found or access denied.');
    }

    redirect_dashboard('success', 'Cycle and all its food logs deleted.');
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: add_meal — INSERT a new food log entry for a cycle
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'add_meal') {

    $cycle_id  = (int)($_POST['cycle_id']  ?? 0);
    $food_name = trim($_POST['food_name']  ?? '');
    $calories  = (float)($_POST['calories']?? 0);
    $protein   = (float)($_POST['protein'] ?? 0);
    $carbs     = (float)($_POST['carbs']   ?? 0);
    $fat       = (float)($_POST['fat']     ?? 0);

    if (!$cycle_id || empty($food_name)) {
        redirect_dashboard('error', 'Select a cycle and enter a food name.');
    }

    if ($calories < 0 || $protein < 0 || $carbs < 0 || $fat < 0) {
        redirect_dashboard('error', 'Nutritional values cannot be negative.');
    }

    // TEACHING NOTE: We verify the cycle belongs to this user BEFORE inserting.
    // Never trust a cycle_id from a form field alone — verify ownership in the DB.
    $stmt = $pdo->prepare('SELECT id FROM cycles WHERE id = :cid AND user_id = :uid');
    $stmt->execute([':cid' => $cycle_id, ':uid' => $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        redirect_dashboard('error', 'Invalid cycle or access denied.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO food_logs (cycle_id, food_name, calories, protein, carbs, fat)
         VALUES (:cid, :name, :cal, :prot, :carb, :fat)'
    );
    $stmt->execute([
        ':cid'  => $cycle_id,
        ':name' => $food_name,
        ':cal'  => $calories,
        ':prot' => $protein,
        ':carb' => $carbs,
        ':fat'  => $fat,
    ]);

    redirect_dashboard('success', "\"" . htmlspecialchars($food_name, ENT_QUOTES) . "\" logged successfully!");
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: edit_meal — UPDATE an existing food log entry
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit_meal') {

    $meal_id   = (int)($_POST['meal_id']   ?? 0);
    $food_name = trim($_POST['food_name']  ?? '');
    $calories  = (float)($_POST['calories']?? 0);
    $protein   = (float)($_POST['protein'] ?? 0);
    $carbs     = (float)($_POST['carbs']   ?? 0);
    $fat       = (float)($_POST['fat']     ?? 0);

    if (!$meal_id || empty($food_name)) {
        redirect_dashboard('error', 'Meal ID and name are required.');
    }

    // TEACHING NOTE: We use a JOIN here to verify ownership across two tables:
    // food_logs belongs to cycles, and cycles belongs to the current user.
    // This "chain of ownership" check prevents cross-user data tampering.
    $stmt = $pdo->prepare(
        'UPDATE food_logs fl
           JOIN cycles c ON fl.cycle_id = c.id
            SET fl.food_name = :name, fl.calories = :cal, fl.protein = :prot,
                fl.carbs = :carb, fl.fat = :fat
          WHERE fl.id = :mid AND c.user_id = :uid'
    );
    $stmt->execute([
        ':name' => $food_name,
        ':cal'  => $calories,
        ':prot' => $protein,
        ':carb' => $carbs,
        ':fat'  => $fat,
        ':mid'  => $meal_id,
        ':uid'  => $_SESSION['user_id'],
    ]);

    redirect_dashboard('success', 'Meal updated successfully!');
}

// ─────────────────────────────────────────────────────────────────────────────
// ACTION: delete_meal — DELETE one food log entry
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete_meal') {

    $meal_id = (int)($_POST['meal_id'] ?? 0);
    if (!$meal_id) {
        redirect_dashboard('error', 'Invalid meal.');
    }

    // Security: JOIN with cycles to confirm the meal belongs to this user's cycle.
    $stmt = $pdo->prepare(
        'DELETE fl FROM food_logs fl
           JOIN cycles c ON fl.cycle_id = c.id
          WHERE fl.id = :mid AND c.user_id = :uid'
    );
    $stmt->execute([':mid' => $meal_id, ':uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        redirect_dashboard('error', 'Meal not found or access denied.');
    }

    redirect_dashboard('success', 'Meal deleted.');
}

// If no valid action was matched, just redirect home.
redirect_dashboard('error', 'Unknown action.');
