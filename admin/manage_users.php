<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

$msg = '';
$success = false;

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $name     = trim($_POST['name'] ?? '');
  $role     = $_POST['role'] ?? '';
  $password = $_POST['password'] ?? '';
  $leagueId = intval($_POST['league_id'] ?? 0);
  $assignedTeams = $_POST['teams'] ?? [];

  if (!$username || !$name || !$role || !$password || !$leagueId) {
    $msg = "❌ All fields are required.";
  } else {
    // Check for duplicate username
    $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $exists = $check->get_result()->fetch_row()[0];

    if ($exists > 0) {
      $msg = "❌ Username <strong>$username</strong> already exists.";
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users (username, password, name, role, league_id) VALUES (?, ?, ?, ?, ?)");
      $stmt->bind_param("ssssi", $username, $hash, $name, $role, $leagueId);
      $success = $stmt->execute();
      $msg = $success ? "✅ User <strong>$name</strong> created." : "❌ Error: " . $stmt->error;

      // Assign teams if manager
      if ($success && $role === 'manager' && !empty($assignedTeams)) {
        $userId = $conn->insert_id;
        $teamStmt = $conn->prepare("INSERT INTO team_managers (user_id, team_id) VALUES (?, ?)");
        foreach ($assignedTeams as $teamId) {
          $teamStmt->bind_param("ii", $userId, $teamId);
          $teamStmt->execute();
        }
      }
    }
  }
}

// Fetch leagues and teams
$leagues = $conn->query("SELECT league_id, abbreviation FROM leagues ORDER BY abbreviation");
$teams = $conn->query("SELECT team_id, name FROM teams ORDER BY name");

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container py-5" style="max-width: 600px;">
  <h2 class="text-center mb-4">👤 Create New User</h2>

  <?php if ($msg): ?>
    <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Role</label>
      <select name="role" class="form-select" id="roleSelect" required>
        <option value="">Select role</option>
        <option value="admin">Admin</option>
        <option value="manager">Team Manager</option>
        <option value="referee">Referee</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">League</label>
      <select name="league_id" class="form-select" required>
        <option value="">Select league</option>
        <?php while ($l = $leagues->fetch_assoc()): ?>
          <option value="<?= $l['league_id'] ?>"><?= htmlspecialchars($l['abbreviation']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <!-- Only shown if role = manager -->
    <div class="mb-4" id="teamWrapper" style="display: none;">
      <label class="form-label">Assign Teams</label>
      <select name="teams[]" class="form-select" multiple>
        <?php while ($t = $teams->fetch_assoc()): ?>
          <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
        <?php endwhile; ?>
      </select>
      <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple</small>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-accent">Create User</button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-primary">⬅ Back to Dashboard</a>
  </div>
</section>

<script>
  const roleSelect = document.getElementById('roleSelect');
  const teamWrapper = document.getElementById('teamWrapper');

  roleSelect.addEventListener('change', () => {
    teamWrapper.style.display = roleSelect.value === 'manager' ? 'block' : 'none';
  });
</script>

<?php include('../includes/footer.php'); ?>
