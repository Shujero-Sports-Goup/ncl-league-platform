<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

$msg = '';
$success = false;

// Handle assignment on form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId  = intval($_POST['manager_id'] ?? 0);
    $teamIds = $_POST['teams'] ?? [];

    if ($userId && !empty($teamIds)) {
        // Remove existing assignments for this manager
        $stmt = $conn->prepare("DELETE FROM team_managers WHERE user_id = ?");
        if (!$stmt) {
            die("❌ Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        // Assign new teams
        $stmt = $conn->prepare("INSERT INTO team_managers (user_id, team_id) VALUES (?, ?)");
        foreach ($teamIds as $teamId) {
            $stmt->bind_param("ii", $userId, $teamId);
            $stmt->execute();
        }

        $success = true;
        $msg = "✅ Teams successfully assigned to manager.";
    } else {
        $msg = "❌ Please select a manager and at least one team.";
    }
}

// Get managers
$managers = $conn->query("SELECT user_id, name FROM users WHERE role = 'manager' ORDER BY name");

// Get teams
$teams = $conn->query("SELECT team_id, name FROM teams ORDER BY name");

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container py-5" style="max-width: 700px;">
    <h2 class="text-center mb-4">🧑‍💼 Assign Manager to Teams</h2>

    <?php if ($msg): ?>
        <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?> text-center">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm">
        <div class="mb-3">
            <label class="form-label">Select Manager</label>
            <select name="manager_id" class="form-select" required>
                <option value="">-- Select Manager --</option>
                <?php while ($m = $managers->fetch_assoc()): ?>
                    <option value="<?= $m['user_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-4">
            <label class="form-label">Assign Teams</label>
            <select name="teams[]" class="form-select" multiple required>
                <?php while ($t = $teams->fetch_assoc()): ?>
                    <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">💾 Assign Teams</button>
        </div>
    </form>

    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-outline-secondary">⬅ Back to Admin Dashboard</a>
    </div>
</section>

<?php include('../includes/footer.php'); ?>