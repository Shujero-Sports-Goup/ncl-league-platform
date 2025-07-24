<?php
session_start();
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../db_connect.php');
requireRole('admin');

$leagueId  = $_SESSION['league_id'] ?? 1;
$fixtureId = intval($_GET['id'] ?? $_POST['fixture_id'] ?? 0);
$msg       = '';
$success   = false;

// Fetch teams & venues
$teams  = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");
$venues = $conn->query("SELECT venue_id, name FROM venues");
$teamList = $teams->fetch_all(MYSQLI_ASSOC);

// On POST: validate & update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $home  = intval($_POST['home_team'] ?? 0);
  $away  = intval($_POST['away_team'] ?? 0);
  $date  = $_POST['match_date'] ?? '';
  $time  = $_POST['match_time'] ?? '';
  $venue = trim($_POST['venue'] ?? '');

  if ($home === $away) {
    $msg = "❌ Home and away teams must differ.";
  } elseif (!$date || !$time || strtotime("$date $time") < time()) {
    $msg = "❌ Date/time must be in the future.";
  } elseif (empty($venue)) {
    $msg = "❌ Venue is required.";
  } else {
    $stmt = $conn->prepare("
      UPDATE fixtures 
      SET home_team=?, away_team=?, match_date=?, match_time=?, venue=?
      WHERE fixture_id=? AND league_id=?
    ");
    $stmt->bind_param(
      "iisssii",
      $home, $away, $date, $time, $venue,
      $fixtureId, $leagueId
    );
    $success = $stmt->execute();
    $msg = $success
      ? "✅ Fixture #{$fixtureId} updated successfully."
      : "❌ Update error: " . $stmt->error;
  }
}

// On GET or after POST, fetch current fixture data
$fixture = $conn
  ->prepare("SELECT home_team, away_team, match_date, match_time, venue FROM fixtures WHERE fixture_id=? AND league_id=?")
  ;
$fixture->bind_param("ii", $fixtureId, $leagueId);
$fixture->execute();
$fixtureData = $fixture->get_result()->fetch_assoc() ?: [];
$fixture->close();

include('../includes/header.php');
include('../includes/navbar.php');
?>
<section class="container py-5">
  <h2 class="text-center mb-4">✏️ Edit Fixture #<?= $fixtureId ?></h2>
  <?php if ($msg): ?>
    <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <form id="editFixtureForm" method="POST" class="card p-4 shadow-sm" style="max-width:700px; margin:auto;">
    <input type="hidden" name="fixture_id" value="<?= $fixtureId ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label for="home_team" class="form-label">Home Team</label>
        <select id="home_team" name="home_team" class="form-select" required>
          <option value="">Select…</option>
          <?php foreach ($teamList as $t): ?>
            <option 
              value="<?= $t['team_id'] ?>" 
              <?= $t['team_id']==($fixtureData['home_team']??0)?'selected':''?>
            >
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="away_team" class="form-label">Away Team</label>
        <select id="away_team" name="away_team" class="form-select" required>
          <option value="">Select…</option>
          <?php foreach ($teamList as $t): ?>
            <option 
              value="<?= $t['team_id'] ?>" 
              <?= $t['team_id']==($fixtureData['away_team']??0)?'selected':''?>
            >
              <?= htmlspecialchars($t['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="match_date" class="form-label">Match Date</label>
        <input 
          type="date" id="match_date" name="match_date" 
          class="form-control" 
          min="<?= date('Y-m-d') ?>"
          value="<?= htmlspecialchars($fixtureData['match_date'] ?? '') ?>"
          required
        >
      </div>
      <div class="col-md-6">
        <label for="match_time" class="form-label">Match Time</label>
        <input 
          type="time" id="match_time" name="match_time" 
          class="form-control" 
          value="<?= htmlspecialchars($fixtureData['match_time'] ?? '') ?>"
          required
        >
      </div>
      <div class="col-12">
        <label for="venue" class="form-label">Venue</label>
        <select id="venue" name="venue" class="form-select" required>
          <option value="">Select…</option>
          <?php while ($v = $venues->fetch_assoc()): ?>
            <option 
              value="<?= htmlspecialchars($v['name']) ?>" 
              <?= $v['name']==($fixtureData['venue']??'')?'selected':''?>
            >
              <?= htmlspecialchars($v['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>

    <div class="mt-4 text-center">
      <button type="submit" class="btn btn-accent px-5">
        Update Fixture
      </button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-primary">
      ⬅ Back to Dashboard
    </a>
  </div>
</section>

<?php include('../includes/footer.php'); ?>
