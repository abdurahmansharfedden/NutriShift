<?php
// =============================================================================
// admin_dashboard.php — Admin Panel (Manage All Users & All Cycles)
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';

// TEACHING NOTE: require_role('admin') first calls require_login() internally,
// then checks $_SESSION['role'] === 'admin'. Regular users hitting this URL
// get bounced to user_dashboard.php automatically. Admins proceed.
require_role('admin');

// ─────────────────────────────────────────────────────────────────────────────
// HANDLE ADMIN ACTIONS (POST)
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $admin_action = $_POST['admin_action'] ?? '';

    // ── DELETE USER ──────────────────────────────────────────────────────────
    if ($admin_action === 'delete_user') {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);

        // TEACHING NOTE: An admin should not be able to delete themselves,
        // which would lock everyone out. We compare the target ID against the
        // currently logged-in admin's session ID as a safety check.
        if ($target_uid === (int)$_SESSION['user_id']) {
            flash('error', 'You cannot delete your own account.');
        } elseif ($target_uid > 0) {
            // ON DELETE CASCADE in the DB schema will also remove all cycles
            // and food_logs that belong to this user automatically.
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $target_uid]);
            flash('success', 'User deleted (along with all their cycles and logs).');
        }
    }

    // ── EDIT USER ─────────────────────────────────────────────────────────────
    if ($admin_action === 'edit_user') {
        $target_uid = (int)($_POST['target_user_id'] ?? 0);
        $username   = trim($_POST['edit_username']   ?? '');
        $email      = trim($_POST['edit_email']      ?? '');
        $role       = $_POST['edit_role']            ?? 'user';

        // TEACHING NOTE: in_array() checks whether $role is one of the allowed
        // values. This prevents someone from POSTing role=superadmin or anything
        // unexpected. Always whitelist allowed values for enum-like fields.
        if (!in_array($role, ['admin', 'user'], true)) {
            $role = 'user';
        }

        if (!$target_uid || empty($username) || empty($email)) {
            flash('error', 'All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Invalid email format.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET username = :u, email = :e, role = :r WHERE id = :id'
            );
            $stmt->execute([':u' => $username, ':e' => $email, ':r' => $role, ':id' => $target_uid]);
            flash('success', 'User updated successfully.');
        }
    }

    // ── ADMIN DELETE CYCLE ────────────────────────────────────────────────────
    if ($admin_action === 'delete_cycle') {
        $cycle_id = (int)($_POST['cycle_id'] ?? 0);
        if ($cycle_id) {
            // TEACHING NOTE: Admins can delete ANY cycle (no user_id filter).
            // This is intentional — admins manage all data.
            $stmt = $pdo->prepare('DELETE FROM cycles WHERE id = :id');
            $stmt->execute([':id' => $cycle_id]);
            flash('success', 'Cycle deleted.');
        }
    }

    header('Location: admin_dashboard.php');
    exit();
}

// ─────────────────────────────────────────────────────────────────────────────
// FETCH ALL USERS
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: The admin sees ALL users. No WHERE clause filters by user_id.
// We alias COUNT(c.id) AS cycle_count using a LEFT JOIN so we can show
// how many cycles each user has in the same query — avoiding N+1 queries
// (where you'd run a separate query for every user in a loop).
$users_stmt = $pdo->query(
    'SELECT u.id, u.username, u.email, u.role, u.created_at,
            COUNT(c.id) AS cycle_count
       FROM users u
       LEFT JOIN cycles c ON c.user_id = u.id
      GROUP BY u.id
      ORDER BY u.created_at DESC'
);
$users = $users_stmt->fetchAll();

// ─────────────────────────────────────────────────────────────────────────────
// FETCH ALL CYCLES (with owner username via JOIN)
// ─────────────────────────────────────────────────────────────────────────────
// TEACHING NOTE: INNER JOIN links each cycle row to its owner row in users.
// This is more efficient than fetching cycles then doing a second query per cycle.
// We also count meals per cycle using another LEFT JOIN + COUNT.
$cycles_stmt = $pdo->query(
    'SELECT c.id, c.cycle_name, c.start_time, c.end_time, c.target_calories,
            u.username AS owner,
            COUNT(fl.id) AS meal_count
       FROM cycles c
       JOIN  users u ON u.id = c.user_id
       LEFT JOIN food_logs fl ON fl.cycle_id = c.id
      GROUP BY c.id
      ORDER BY c.created_at DESC'
);
$all_cycles = $cycles_stmt->fetchAll();

// Flash messages
$success_msg = get_flash('success');
$error_msg   = get_flash('error');

$page_title = 'Admin Panel';
require_once 'includes/header.php';
?>

<div class="dashboard">

    <div class="dashboard-header">
        <h1>🛡️ Admin Dashboard</h1>
        <p>Full oversight of all users, cycles, and logs across the platform.</p>
    </div>

    <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?= sanitize($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error">⚠️ <?= sanitize($error_msg) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Total Users</span>
            <span class="stat-value stat-accent"><?= count($users) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Cycles</span>
            <span class="stat-value"><?= count($all_cycles) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Admins</span>
            <span class="stat-value stat-warn"><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></span>
        </div>
    </div>

    <!-- ─── USERS TABLE ─── -->
    <div class="card mb-2" style="margin-bottom:1.5rem">
        <div class="card-header">
            <h2 class="card-title">👥 All Users</h2>
            <span class="badge badge-blue"><?= count($users) ?> registered</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Cycles</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td class="text-muted text-small">#<?= $u['id'] ?></td>
                        <td style="font-weight:600"><?= sanitize($u['username']) ?></td>
                        <td class="text-small"><?= sanitize($u['email']) ?></td>
                        <td><span class="role-tag role-<?= sanitize($u['role']) ?>"><?= sanitize($u['role']) ?></span></td>
                        <td><?= (int)$u['cycle_count'] ?></td>
                        <td class="text-small text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="td-actions">
                                <button class="btn btn-warning btn-sm btn-edit-user"
                                    data-id="<?= $u['id'] ?>"
                                    data-username="<?= sanitize($u['username']) ?>"
                                    data-email="<?= sanitize($u['email']) ?>"
                                    data-role="<?= sanitize($u['role']) ?>">
                                    ✏️ Edit
                                </button>

                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                <form method="POST" action="admin_dashboard.php" style="display:inline">
                                    <input type="hidden" name="admin_action"   value="delete_user">
                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                        data-confirm-msg="Permanently delete user &quot;<?= sanitize($u['username']) ?>&quot; and ALL their data?">
                                        🗑 Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted text-small">(You)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ─── ALL CYCLES TABLE ─── -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">🔄 All Cycles</h2>
            <span class="badge badge-blue"><?= count($all_cycles) ?> total</span>
        </div>

        <?php if (empty($all_cycles)): ?>
            <div class="empty-state">
                <div class="empty-icon">🌙</div>
                <p>No cycles have been created yet.</p>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cycle Name</th>
                        <th>Owner</th>
                        <th>Window</th>
                        <th>Target Cal</th>
                        <th>Meals</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_cycles as $cyc): ?>
                    <tr>
                        <td class="text-muted text-small">#<?= $cyc['id'] ?></td>
                        <td style="font-weight:500"><?= sanitize($cyc['cycle_name']) ?></td>
                        <td><?= sanitize($cyc['owner']) ?></td>
                        <td class="text-small text-muted">
                            <?= date('d M, H:i', strtotime($cyc['start_time'])) ?>
                            &rarr; <?= date('d M, H:i', strtotime($cyc['end_time'])) ?>
                        </td>
                        <td><?= number_format($cyc['target_calories']) ?> kcal</td>
                        <td><?= (int)$cyc['meal_count'] ?></td>
                        <td>
                            <form method="POST" action="admin_dashboard.php" style="display:inline">
                                <input type="hidden" name="admin_action" value="delete_cycle">
                                <input type="hidden" name="cycle_id"    value="<?= $cyc['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                    data-confirm-msg="Delete cycle &quot;<?= sanitize($cyc['cycle_name']) ?>&quot; owned by &quot;<?= sanitize($cyc['owner']) ?>&quot;?">
                                    🗑 Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /dashboard -->


<!-- ═══════════════════════════════════════════════════════════════
     Modal: Edit User
     ═══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-edit-user" role="dialog" aria-modal="true" aria-labelledby="modal-edit-user-title">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="modal-edit-user-title">✏️ Edit User</h3>
            <button class="modal-close" onclick="closeModal('modal-edit-user')" aria-label="Close">×</button>
        </div>
        <form method="POST" action="admin_dashboard.php">
            <input type="hidden" name="admin_action"   value="edit_user">
            <input type="hidden" name="target_user_id" id="edit_user_id">

            <div class="form-group">
                <label for="edit_user_username">Username</label>
                <input type="text" id="edit_user_username" name="edit_username" required>
            </div>
            <div class="form-group">
                <label for="edit_user_email">Email</label>
                <input type="email" id="edit_user_email" name="edit_email" required>
            </div>
            <div class="form-group">
                <label for="edit_user_role">Role</label>
                <select id="edit_user_role" name="edit_role">
                    <option value="user">user</option>
                    <option value="admin">admin</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-full" id="edit-user-submit">Save Changes</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
