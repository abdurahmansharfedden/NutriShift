<?php
// =============================================================================
// progress.php — Weight Progress Tracking Chart
// =============================================================================
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login();

$user_id = (int)$_SESSION['user_id'];

// 1. Fetch historical weight data
$stmt = $pdo->prepare("SELECT weight, DATE(logged_at) as log_date FROM weight_logs WHERE user_id = :uid ORDER BY logged_at ASC");
$stmt->execute([':uid' => $user_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$dataPoints = [];
foreach ($logs as $log) {
    $labels[] = $log['log_date'];
    $dataPoints[] = (float)$log['weight'];
}

$page_title = 'Progress Tracking';
require_once 'includes/header.php';
?>

<div class="dashboard">
    <div class="dashboard-header">
        <h1>📈 Your Progress</h1>
        <p>Track your weight changes over time.</p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <div class="card-header">
            <h2 class="card-title">Weight History (kg)</h2>
        </div>
        
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="empty-icon">⚖️</div>
                <p>No weight data logged yet. Update your profile to see your chart!</p>
            </div>
        <?php else: ?>
            <div style="position: relative; height: 400px; width: 100%;">
                <canvas id="weightChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($logs)): ?>
<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('weightChart').getContext('2d');
    
    // Inject PHP arrays into JS
    const chartLabels = <?= json_encode($labels) ?>;
    const chartData = <?= json_encode($dataPoints) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Weight (kg)',
                data: chartData,
                borderColor: '#8b5cf6', // Purple accent
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderWidth: 3,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4 // Smooth curves
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#e2e8f0' } // Light text
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#334155',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.05)', borderColor: 'transparent' },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    grid: { color: 'rgba(255,255,255,0.05)', borderColor: 'transparent' },
                    ticks: { color: '#94a3b8' },
                    beginAtZero: false
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
