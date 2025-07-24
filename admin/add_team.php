<?php
require_once('../db_connect.php');
require_once('../includes/auth.php');
requireRole('admin');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $teamName = trim($_POST['team_name']);
  $coachName = trim($_POST['coach_name']);
  $leagueId = intval($_POST['league_id']);

  $stmt = $conn->prepare("INSERT INTO teams (name, coach_name, league_id) VALUES (?, ?, ?)");
  $stmt->bind_param("ssi", $teamName, $coachName, $leagueId);

  $success = $stmt->execute();
  $msg = $success ? "✅ Team <strong>$teamName</strong> added successfully!" : "❌ Error: " . $stmt->error;
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container py-5" style="max-width: 600px;">
  <h2 class="text-center mb-4">➕ Add New Team</h2>

  <?php if (isset($msg)): ?>
    <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="league" class="form-label">League</label>
      <select name="league_id" id="league" class="form-select" required>
        <option value="">Select a league</option>
        <?php
        $leagues = $conn->query("SELECT league_id, abbreviation FROM leagues");
        while ($row = $leagues->fetch_assoc()) {
          echo "<option value='{$row['league_id']}'>{$row['abbreviation']}</option>";
        }
        ?>
      </select>
    </div>

    <div class="mb-3">
      <label for="team_name" class="form-label">Team Name</label>
      <input type="text" name="team_name" id="team_name" class="form-control" required>
    </div>

    <div class="mb-4">
      <label for="coach_name" class="form-label">Coach Name</label>
      <input type="text" name="coach_name" id="coach_name" class="form-control">
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-accent">Add Team</button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-primary">⬅ Back to Dashboard</a>
  </div>
</section>

<?php include('../includes/footer.php'); ?>
