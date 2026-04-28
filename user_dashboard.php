<?php
// =============================================================================
// user_dashboard.php — User Dashboard (Cycles + Food Logs)
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';

// TEACHING NOTE: require_login() (defined in auth.php) checks $_SESSION['user_id'].
// If the session is empty, it calls header('Location: index.php') and exit().
// Any page that should be private starts with this one call.
require_login();

$uid = (int)$_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────────────────────
// FETCH: All cycles belonging to this user (newest first)
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: We use a SELECT with WHERE user_id = :uid so each user sees
// ONLY their own data. ORDER BY created_at DESC puts newest cycles first.
$cycles_stmt = $pdo->prepare(
    'SELECT id, cycle_name, start_time, end_time, target_calories
       FROM cycles
      WHERE user_id = :uid
      ORDER BY created_at DESC'
);
$cycles_stmt->execute([':uid' => $uid]);
$cycles = $cycles_stmt->fetchAll(); // fetchAll() returns all matching rows as an array

// ─────────────────────────────────────────────────────────────────────────────
// DETERMINE: Which cycle is currently selected for the food log view
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: We read the selected cycle from the URL query string (?cycle=5).
// If none provided, default to the first cycle in the list (index 0).
// (int) casts to integer — never trust raw $_GET values.
$selected_cycle_id = isset($_GET['cycle']) ? (int)$_GET['cycle'] : 0;
if (!$selected_cycle_id && !empty($cycles)) {
    $selected_cycle_id = (int)$cycles[0]['id'];
}

// ─────────────────────────────────────────────────────────────────────────────
// FETCH: Food logs for the selected cycle
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: This is the KEY query of NutriShift.
// We join food_logs to cycles and filter by:
//   1. The specific cycle_id   — shows only meals in this cycle
//   2. c.user_id = :uid        — ownership check (IDOR protection)
//   3. fl.logged_at BETWEEN c.start_time AND c.end_time
//      — This is WHY we use DATETIME instead of DATE.
//      The BETWEEN clause works across midnight boundaries, e.g.
//      a cycle from 2024-11-01 18:00 to 2024-11-02 04:00 correctly
//      captures a meal logged at 2024-11-02 01:30 (2 AM).
$meals = [];
$selected_cycle = null;
$totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];

if ($selected_cycle_id) {

    // Fetch the cycle's metadata for display
    $sc_stmt = $pdo->prepare(
        'SELECT id, cycle_name, start_time, end_time, target_calories
           FROM cycles
          WHERE id = :cid AND user_id = :uid'
    );
    $sc_stmt->execute([':cid' => $selected_cycle_id, ':uid' => $uid]);
    $selected_cycle = $sc_stmt->fetch();

    if ($selected_cycle) {
        // Fetch meals logged WITHIN this cycle's time window
        // SQL COMMENT: BETWEEN is inclusive on both ends.
        // Because start_time and end_time are DATETIME (not DATE), a cycle
        // running from 2024-11-01 22:00 to 2024-11-02 06:00 correctly
        // captures entries at 2024-11-02 02:00 — no midnight reset needed.
        $meals_stmt = $pdo->prepare(
            'SELECT fl.id, fl.food_name, fl.calories, fl.protein, fl.carbs, fl.fat, fl.logged_at
               FROM food_logs fl
               JOIN cycles c ON fl.cycle_id = c.id
              WHERE fl.cycle_id = :cid
                AND c.user_id  = :uid
                AND fl.logged_at BETWEEN c.start_time AND c.end_time
              ORDER BY fl.logged_at ASC'
        );
        $meals_stmt->execute([':cid' => $selected_cycle_id, ':uid' => $uid]);
        $meals = $meals_stmt->fetchAll();

        // TEACHING NOTE: array_sum() + array_column() is a neat PHP combo.
        // array_column($meals, 'calories') extracts all 'calories' values into
        // a flat array, and array_sum() adds them all up. No manual loop needed!
        $totals['calories'] = array_sum(array_column($meals, 'calories'));
        $totals['protein']  = array_sum(array_column($meals, 'protein'));
        $totals['carbs']    = array_sum(array_column($meals, 'carbs'));
        $totals['fat']      = array_sum(array_column($meals, 'fat'));
    }
}

// Calculate calorie progress percentage
$target_cal  = $selected_cycle ? (int)$selected_cycle['target_calories'] : 2000;
$cal_pct     = $target_cal > 0 ? round(($totals['calories'] / $target_cal) * 100, 1) : 0;
$cal_remain  = max(0, $target_cal - $totals['calories']);

// ─────────────────────────────────────────────────────────────────────────────
// FLASH MESSAGES
// ─────────────────────────────────────────────────────────────────────────────
$success_msg = get_flash('success');
$error_msg   = get_flash('error');

$page_title = 'My Dashboard';
require_once 'includes/header.php';
?>

<div class="dashboard">

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>👋 Welcome back, <?= sanitize($_SESSION['username']) ?>!</h1>
        <p>Manage your biological cycles and track your nutrition below.</p>
    </div>

    <!-- Flash Messages -->
    <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= sanitize($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">⚠️ <?= sanitize($error_msg) ?></div>
    <?php endif; ?>

    <!-- ─── STATS STRIP ─── -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Total Cycles</span>
            <span class="stat-value stat-accent"><?= count($cycles) ?></span>
        </div>
        <?php if ($selected_cycle): ?>
        <div class="stat-card">
            <span class="stat-label">Calories In</span>
            <span class="stat-value <?= $cal_pct > 100 ? 'stat-danger' : 'stat-accent' ?>"><?= number_format($totals['calories'], 0) ?></span>
            <span class="stat-unit">/ <?= number_format($target_cal) ?> kcal target</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Remaining</span>
            <span class="stat-value <?= $cal_pct > 100 ? 'stat-danger' : '' ?>"><?= number_format($cal_remain) ?></span>
            <span class="stat-unit">kcal left</span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Meals Logged</span>
            <span class="stat-value"><?= count($meals) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── TWO-COLUMN LAYOUT ─── -->
    <div class="two-col">

        <!-- LEFT: Cycle List + Add Cycle -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🔄 My Cycles</h2>
                    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-cycle')" id="btn-open-add-cycle">+ New Cycle</button>
                </div>

                <?php if (empty($cycles)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🌙</div>
                        <p>No cycles yet. Create your first biological cycle!</p>
                    </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Window</th>
                                <th>Target</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cycles as $cyc): ?>
                            <tr class="<?= $cyc['id'] == $selected_cycle_id ? 'selected-row' : '' ?>">
                                <td>
                                    <a href="user_dashboard.php?cycle=<?= $cyc['id'] ?>" style="font-weight:600;color:var(--text-heading)">
                                        <?= sanitize($cyc['cycle_name']) ?>
                                    </a>
                                </td>
                                <td class="text-small text-muted">
                                    <?= date('d M, H:i', strtotime($cyc['start_time'])) ?><br>
                                    → <?= date('d M, H:i', strtotime($cyc['end_time'])) ?>
                                </td>
                                <td class="text-small"><?= number_format($cyc['target_calories']) ?> kcal</td>
                                <td>
                                    <div class="td-actions">
                                        <button class="btn btn-warning btn-sm btn-edit-cycle"
                                            data-id="<?= $cyc['id'] ?>"
                                            data-name="<?= sanitize($cyc['cycle_name']) ?>"
                                            data-start="<?= date('Y-m-d\TH:i', strtotime($cyc['start_time'])) ?>"
                                            data-end="<?= date('Y-m-d\TH:i', strtotime($cyc['end_time'])) ?>"
                                            data-calories="<?= (int)$cyc['target_calories'] ?>">
                                            ✏️ Edit
                                        </button>
                                        <!-- TEACHING NOTE: We use a <form> with POST for delete actions,
                                             NOT a <a href> link. GET requests should never modify data.
                                             The JS confirm-delete class triggers a confirmation dialog. -->
                                        <form method="POST" action="process_meal.php" style="display:inline">
                                            <input type="hidden" name="action"   value="delete_cycle">
                                            <input type="hidden" name="cycle_id" value="<?= $cyc['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                                data-confirm-msg="Delete cycle &quot;<?= sanitize($cyc['cycle_name']) ?>&quot; and ALL its food logs?">
                                                🗑 Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Selected Cycle Detail + Food Logs -->
        <div>
            <?php if ($selected_cycle): ?>

            <!-- Calorie Progress Card -->
            <div class="card mb-2">
                <div class="card-header">
                    <h2 class="card-title">📊 <?= sanitize($selected_cycle['cycle_name']) ?></h2>
                    <span class="badge <?= $cal_pct > 100 ? 'badge-red' : 'badge-green' ?>">
                        <?= $cal_pct ?>%
                    </span>
                </div>
                <p class="text-small text-muted">
                    🕐 <?= date('d M Y, H:i', strtotime($selected_cycle['start_time'])) ?>
                    &rarr; <?= date('d M Y, H:i', strtotime($selected_cycle['end_time'])) ?>
                </p>
                <div class="progress-wrap">
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" data-pct="<?= $cal_pct ?>"></div>
                    </div>
                    <div class="progress-label">
                        <span><?= number_format($totals['calories'], 1) ?> kcal consumed</span>
                        <span>Target: <?= number_format($target_cal) ?> kcal</span>
                    </div>
                </div>
                <!-- Macros -->
                <div class="macro-grid mt-2">
                    <div class="macro-item">
                        <div class="macro-label">Protein</div>
                        <div class="macro-val"><?= number_format($totals['protein'], 1) ?></div>
                        <div class="macro-unit">g</div>
                    </div>
                    <div class="macro-item">
                        <div class="macro-label">Carbs</div>
                        <div class="macro-val"><?= number_format($totals['carbs'], 1) ?></div>
                        <div class="macro-unit">g</div>
                    </div>
                    <div class="macro-item">
                        <div class="macro-label">Fat</div>
                        <div class="macro-val"><?= number_format($totals['fat'], 1) ?></div>
                        <div class="macro-unit">g</div>
                    </div>
                </div>
            </div>

            <!-- Food Log Table -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">🍽 Food Log</h2>
                    <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-meal')" id="btn-open-add-meal">+ Log Food</button>
                </div>

                <?php if (empty($meals)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🥗</div>
                        <p>No meals logged yet. Hit "+ Log Food" to start!</p>
                    </div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Food</th>
                                <th>Cal</th>
                                <th>P/C/F (g)</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($meals as $meal): ?>
                            <tr>
                                <td style="font-weight:500"><?= sanitize($meal['food_name']) ?></td>
                                <td><?= number_format($meal['calories'], 0) ?></td>
                                <td class="text-small text-muted">
                                    <?= number_format($meal['protein'],1) ?> /
                                    <?= number_format($meal['carbs'],1) ?> /
                                    <?= number_format($meal['fat'],1) ?>
                                </td>
                                <td class="text-small text-muted"><?= date('H:i', strtotime($meal['logged_at'])) ?></td>
                                <td>
                                    <div class="td-actions">
                                        <button class="btn btn-warning btn-sm btn-edit-meal"
                                            data-id="<?= $meal['id'] ?>"
                                            data-name="<?= sanitize($meal['food_name']) ?>"
                                            data-calories="<?= $meal['calories'] ?>"
                                            data-protein="<?= $meal['protein'] ?>"
                                            data-carbs="<?= $meal['carbs'] ?>"
                                            data-fat="<?= $meal['fat'] ?>">
                                            ✏️
                                        </button>
                                        <form method="POST" action="process_meal.php" style="display:inline">
                                            <input type="hidden" name="action"  value="delete_meal">
                                            <input type="hidden" name="meal_id" value="<?= $meal['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                                data-confirm-msg="Delete &quot;<?= sanitize($meal['food_name']) ?>&quot;?">
                                                🗑
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-icon">🔄</div>
                        <p>Select a cycle on the left to see its food log, or create your first cycle.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div><!-- /right col -->

    </div><!-- /two-col -->
</div><!-- /dashboard -->


<!-- ═══════════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════════ -->

<!-- Modal: Add Cycle -->
<div class="modal-overlay" id="modal-add-cycle" role="dialog" aria-modal="true" aria-labelledby="modal-add-cycle-title">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-add-cycle-title">🔄 New Biological Cycle</h3>
            <button class="modal-close" onclick="closeModal('modal-add-cycle')" aria-label="Close">×</button>
        </div>
        <form method="POST" action="process_meal.php">
            <input type="hidden" name="action" value="add_cycle">
            <div class="form-group">
                <label for="cycle_name">Cycle Name</label>
                <input type="text" id="cycle_name" name="cycle_name" placeholder="e.g. Night Shift Nov 1" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="start_time">Start Date & Time</label>
                    <input type="datetime-local" id="start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="end_time">End Date & Time</label>
                    <input type="datetime-local" id="end_time" name="end_time" required>
                </div>
            </div>
            <div class="form-group">
                <label for="target_calories">Calorie Target (kcal)</label>
                <input type="number" id="target_calories" name="target_calories" value="2000" min="100" max="20000" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="add-cycle-submit">Create Cycle</button>
        </form>
    </div>
</div>

<!-- Modal: Edit Cycle -->
<div class="modal-overlay" id="modal-edit-cycle" role="dialog" aria-modal="true" aria-labelledby="modal-edit-cycle-title">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-edit-cycle-title">✏️ Edit Cycle</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-cycle')" aria-label="Close">×</button>
        </div>
        <form method="POST" action="process_meal.php">
            <input type="hidden" name="action"   value="edit_cycle">
            <input type="hidden" name="cycle_id" id="edit_cycle_id">
            <div class="form-group">
                <label for="edit_cycle_name">Cycle Name</label>
                <input type="text" id="edit_cycle_name" name="cycle_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_cycle_start">Start Date & Time</label>
                    <input type="datetime-local" id="edit_cycle_start" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="edit_cycle_end">End Date & Time</label>
                    <input type="datetime-local" id="edit_cycle_end" name="end_time" required>
                </div>
            </div>
            <div class="form-group">
                <label for="edit_target_calories">Calorie Target (kcal)</label>
                <input type="number" id="edit_target_calories" name="target_calories" min="100" max="20000" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="edit-cycle-submit">Save Changes</button>
        </form>
    </div>
</div>

<!-- Modal: Add Meal -->
<div class="modal-overlay" id="modal-add-meal" role="dialog" aria-modal="true" aria-labelledby="modal-add-meal-title">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-add-meal-title">🍽 Log Food Item</h3>
            <button class="modal-close" onclick="closeModal('modal-add-meal')" aria-label="Close">×</button>
        </div>
        <form method="POST" action="process_meal.php">
            <input type="hidden" name="action"   value="add_meal">
            <input type="hidden" name="cycle_id" value="<?= $selected_cycle_id ?>">
            <div class="form-group">
                <label for="food_name">Food Name</label>
                <input type="text" id="food_name" name="food_name" placeholder="e.g. Grilled Chicken Breast" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="calories">Calories (kcal)</label>
                    <input type="number" id="calories" name="calories" value="0" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="protein">Protein (g)</label>
                    <input type="number" id="protein" name="protein" value="0" min="0" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="carbs">Carbs (g)</label>
                    <input type="number" id="carbs" name="carbs" value="0" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="fat">Fat (g)</label>
                    <input type="number" id="fat" name="fat" value="0" min="0" step="0.01">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="add-meal-submit">Log Meal</button>
        </form>
    </div>
</div>

<!-- Modal: Edit Meal -->
<div class="modal-overlay" id="modal-edit-meal" role="dialog" aria-modal="true" aria-labelledby="modal-edit-meal-title">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-edit-meal-title">✏️ Edit Meal</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-meal')" aria-label="Close">×</button>
        </div>
        <form method="POST" action="process_meal.php">
            <input type="hidden" name="action"  value="edit_meal">
            <input type="hidden" name="meal_id" id="edit_meal_id">
            <div class="form-group">
                <label for="edit_meal_name">Food Name</label>
                <input type="text" id="edit_meal_name" name="food_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_meal_cal">Calories (kcal)</label>
                    <input type="number" id="edit_meal_cal" name="calories" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="edit_meal_protein">Protein (g)</label>
                    <input type="number" id="edit_meal_protein" name="protein" min="0" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_meal_carbs">Carbs (g)</label>
                    <input type="number" id="edit_meal_carbs" name="carbs" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label for="edit_meal_fat">Fat (g)</label>
                    <input type="number" id="edit_meal_fat" name="fat" min="0" step="0.01">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-full" id="edit-meal-submit">Save Changes</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
