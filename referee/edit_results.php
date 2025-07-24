<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('referee');

$userId   = $_SESSION['user_id'];
$fixtureId = intval($_GET['fixture_id'] ?? 0);
$feedback = '';

// Ensure referee is assigned
$check = $conn->prepare("
  SELECT f.fixture_id, t1.name AS home_name, t2.name AS away_name,
         mr.score_home, mr.score_away, mr.cancelled_by_referee
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  LEFT JOIN match_results mr ON mr.fixture_id = f.fixture_id
  WHERE f.fixture_id = ? AND mr.submitted_by = ?
");
$check->bind_param("ii", $fixtureId, $userId);
$check->execute();
$res = $check->get_result();
$match = $res->fetch_assoc();

if (!$match) {
  die("<div class='alert alert-danger m-5'>❌ Not authorized or fixture not found.</div>");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['cancel_result'])) {
    $stmt = $conn->prepare("UPDATE match_results SET cancelled_by_referee = 1, cancelled_at = NOW() WHERE fixture_id = ?");
    $stmt->bind_param("i", $fixtureId);
    $stmt->execute();
    $feedback = "✅ Result marked as cancelled.";
  } else {
    $homeScore = intval($_POST['score_home']);
    $awayScore = intval($_POST['score_away']);
    $exists = $conn->query("SELECT 1 FROM match_results WHERE fixture_id = $fixtureId")->num_rows;

    if ($exists) {
      $stmt = $conn->prepare("
        UPDATE match_results 
        SET score_home = ?, score_away = ?, cancelled_by_referee = 0, submitted_at = NOW() 
        WHERE fixture_id = ?
      ");
      $stmt->bind_param("iii", $homeScore, $awayScore, $fixtureId);
    } else {
      $stmt = $conn->prepare("
        INSERT INTO match_results (fixture_id, submitted_by, score_home, score_away, submitted_at) 
        VALUES (?, ?, ?, ?, NOW())
      ");
      $stmt->bind_param("iiii", $fixtureId, $userId, $homeScore, $awayScore);
    }
    $stmt->execute();
    $feedback = "✅ Result updated successfully.";
  }
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container py-5" style="max-width: 600px;">
  <h2 class="text-center mb-4">📝 Edit Match Result</h2>

  <?php if ($feedback): ?>
    <div class="alert alert-info"><?= $feedback ?></div>
  <?php endif; ?>

  <div class="card shadow-sm edit-result-card">
    <div class="card-body">
      <form method="POST" class="mb-3 score-input-inline text-center">
        <h5 class="mb-3">
          <?= htmlspecialchars($match['home_name']) ?> 
          <input type="number" name="score_home" class="form-control d-inline" style="width:70px;display:inline-block;"
                 value="<?= (int) $match['score_home'] ?>" required min="0">
          <span class="mx-2 fw-bold" style="font-size:1.5rem;">:</span>
          <input type="number" name="score_away" class="form-control d-inline" style="width:70px;display:inline-block;"
                 value="<?= (int) $match['score_away'] ?>" required min="0">
          <?= htmlspecialchars($match['away_name']) ?>
        </h5>
        <button class="btn btn-success w-100">💾 Update Result</button>
      </form>

      <?php if ($match['score_home'] !== null && !$match['cancelled_by_referee']): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this result?');">
          <input type="hidden" name="cancel_result" value="1">
          <button class="btn btn-outline-danger w-100">❌ Cancel Result</button>
        </form>
      <?php elseif ($match['cancelled_by_referee']): ?>
        <div class="alert alert-warning text-center mt-3">⚠️ This result was cancelled.</div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="submit_score.php" class="btn btn-outline-secondary">⬅ Back to Fixtures</a>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>