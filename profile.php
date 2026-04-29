<?php
// profile.php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];

// Fetch current user data to pre-fill the form
$stmt = $pdo->prepare("SELECT height, weight, body_fat, activity_level, fitness_goal FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$height = $user_data['height'] ?? '';
$weight = $user_data['weight'] ?? '';
$body_fat = $user_data['body_fat'] ?? '';
$activity_level = $user_data['activity_level'] ?? '1.2';
$fitness_goal = $user_data['fitness_goal'] ?? 'maintenance'; 

$page_title = 'My Profile';
require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>⚙️ Profile Settings</h1>
        <p>Update your body metrics and fitness goals here.</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Profile updated successfully!</div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto;">
        <div class="card-header">
            <h2 class="card-title">Update Metrics</h2>
        </div>
        <form method="POST" action="update_profile.php">
            <div class="form-group">
                <label for="weight">Weight (kg)</label>
                <input type="number" step="0.1" id="weight" name="weight" value="<?= htmlspecialchars((string)$weight) ?>" required>
            </div>
            
            <div class="form-group">
                <label for="height">Height (cm)</label>
                <input type="number" step="0.1" id="height" name="height" value="<?= htmlspecialchars((string)$height) ?>" required>
            </div>

            <div class="form-group">
                <label for="body_fat">Body Fat (%)</label>
                <input type="number" step="0.1" id="body_fat" name="body_fat" value="<?= htmlspecialchars((string)$body_fat) ?>" required>
            </div>

            <div class="form-group">
                <label for="fitness_goal">Fitness Goal</label>
                <select name="fitness_goal" class="form-control" required>
                    <option value="lose" <?= ($user['fitness_goal'] ?? '') === 'lose' ? 'selected' : '' ?>>Fat Loss</option>
                    <option value="gain" <?= ($user['fitness_goal'] ?? '') === 'gain' ? 'selected' : '' ?>>Muscle Gain</option>
                    <option value="maintain" <?= ($user['fitness_goal'] ?? '') === 'maintain' ? 'selected' : '' ?>>Maintenance</option>
                </select>
            </div>

            <div class="form-group">
                <label for="activity_level">Activity Level</label>
                <select name="activity_level" class="form-control" required>
                    <option value="1.2" <?= ($user['activity_level'] ?? '') == '1.2' ? 'selected' : '' ?>>Sedentary (1.2)</option>
                    <option value="1.375" <?= ($user['activity_level'] ?? '') == '1.375' ? 'selected' : '' ?>>Lightly Active (1.375)</option>
                    <option value="1.55" <?= ($user['activity_level'] ?? '') == '1.55' ? 'selected' : '' ?>>Moderately Active (1.55)</option>
                    <option value="1.725" <?= ($user['activity_level'] ?? '') == '1.725' ? 'selected' : '' ?>>Very Active (1.725)</option>
                    <option value="1.9" <?= ($user['activity_level'] ?? '') == '1.9' ? 'selected' : '' ?>>Extra Active (1.9)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Save Changes</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
