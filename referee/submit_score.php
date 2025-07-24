<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('referee');

$userId = $_SESSION['user_id'];
$msg    = '';

// 1) Load unique assigned leagues
$leaguesStmt = $conn->prepare("
  SELECT DISTINCT l.league_id, l.name
  FROM referee_assignments ra
  JOIN leagues l ON ra.league_id = l.league_id
  WHERE ra.user_id = ?
  ORDER BY l.name
");
$leaguesStmt->bind_param("i", $userId);
$leaguesStmt->execute();
$leagues = $leaguesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$leaguesStmt->close();

// 2) Determine selected league (GET priority)
$selectedLeagueId = isset($_GET['league_id'])
  ? intval($_GET['league_id'])
  : ($_POST['league_id'] ?? null);

// 3) Handle score submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_score'])) {
  $fixtureId = intval($_POST['fixture_id']);
  $scoreHome = intval($_POST['score_home']);
  $scoreAway = intval($_POST['score_away']);

  try {
    // Insert or update match result
    $stmt = $conn->prepare("
      INSERT INTO match_results (fixture_id, score_home, score_away, submitted_by, submitted_at, match_date)
      SELECT ?, ?, ?, ?, NOW(), f.match_date
      FROM fixtures f
      WHERE f.fixture_id = ?
      ON DUPLICATE KEY UPDATE
        score_home = VALUES(score_home),
        score_away = VALUES(score_away),
        submitted_by = VALUES(submitted_by),
        submitted_at = NOW(),
        cancelled_by_referee = 0,
        cancelled_at = NULL,
        cancelled_reason = NULL
    ");
    
    $stmt->bind_param("iiiii", $fixtureId, $scoreHome, $scoreAway, $userId, $fixtureId);
    
    if ($stmt->execute()) {
      // Update fixture status to 'played'
      $updateStmt = $conn->prepare("UPDATE fixtures SET status = 'played' WHERE fixture_id = ?");
      $updateStmt->bind_param("i", $fixtureId);
      $updateStmt->execute();
      $updateStmt->close();
      
      $msg = "✅ Score recorded successfully.";
    } else {
      $msg = "❌ Error recording score: " . $stmt->error;
    }
    
    $stmt->close();
  } catch (Exception $e) {
    $msg = "❌ Error recording score: " . $e->getMessage();
  }
}

// 4) Handle cancel logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_fixture_id'])) {
  $fid = intval($_POST['cancel_fixture_id']);
  
  try {
    // Mark as cancelled by referee
    $stmt = $conn->prepare("
      UPDATE match_results 
      SET cancelled_by_referee = 1, 
          cancelled_at = NOW(), 
          cancelled_reason = 'Cancelled by referee' 
      WHERE fixture_id = ?
    ");
    $stmt->bind_param("i", $fid);
    
    if ($stmt->execute()) {
      // Set fixture status back to 'upcoming'
      $updateStmt = $conn->prepare("UPDATE fixtures SET status = 'upcoming' WHERE fixture_id = ?");
      $updateStmt->bind_param("i", $fid);
      $updateStmt->execute();
      $updateStmt->close();
      
      $msg = "❌ Result cancelled and fixture set as upcoming.";
    } else {
      $msg = "❌ Error cancelling result: " . $stmt->error;
    }
    
    $stmt->close();
  } catch (Exception $e) {
    $msg = "❌ Error cancelling result: " . $e->getMessage();
  }
}

// 5) Load upcoming fixtures once a league is selected
$fixtures = [];
if ($selectedLeagueId) {
  $fx = $conn->prepare("
    SELECT f.fixture_id, f.match_date, f.match_time,
           t1.name AS home_team, t2.name AS away_team
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    WHERE f.league_id = ? AND f.status = 'upcoming'
    ORDER BY f.match_date ASC, f.match_time ASC
  ");
  $fx->bind_param("i", $selectedLeagueId);
  $fx->execute();
  $fixtures = $fx->get_result();
  $fx->close();
}

// 6) Load recently submitted results for cancel option
$recentResults = [];
if ($selectedLeagueId) {
  $recentStmt = $conn->prepare("
    SELECT mr.fixture_id, f.match_date, f.match_time,
           t1.name AS home_team, t2.name AS away_team, mr.score_home, mr.score_away
    FROM match_results mr
    JOIN fixtures f ON mr.fixture_id = f.fixture_id
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    WHERE f.league_id = ? AND mr.submitted_by = ? AND mr.cancelled_by_referee = 0
    ORDER BY mr.submitted_at DESC
    LIMIT 5
  ");
  $recentStmt->bind_param("ii", $selectedLeagueId, $userId);
  $recentStmt->execute();
  $recentResults = $recentStmt->get_result();
  $recentStmt->close();
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container py-5" style="max-width:720px;">
  <h2 class="mb-4 text-center">📤 Submit Match Score</h2>
  <?php if ($msg): ?>
    <div class="alert alert-success text-center"><?= $msg ?></div>
  <?php endif; ?>

  <!-- League Dropdown (GET) -->
  <form method="GET" class="card mb-4 p-3 shadow-sm bg-white submit-score-card">
    <div class="mb-3">
      <label class="form-label">Select League</label>
      <select name="league_id" class="form-select" onchange="this.form.submit()" required>
        <option value="">— Choose League —</option>
        <?php foreach ($leagues as $l): ?>
          <option value="<?= $l['league_id'] ?>"
            <?= $l['league_id'] == $selectedLeagueId ? 'selected' : '' ?>>
            <?= htmlspecialchars($l['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($leagues)): ?>
        <div class="form-text text-danger">No leagues assigned.</div>
      <?php endif; ?>
    </div>
  </form>

  <!-- Score Form (POST) -->
  <?php if ($selectedLeagueId): ?>
    <form method="POST" class="card p-4 shadow-sm bg-white mb-4 submit-score-card">
      <input type="hidden" name="league_id" value="<?= $selectedLeagueId ?>">
      <input type="hidden" name="submit_score" value="1">

      <?php if ($fixtures->num_rows): ?>
        <div class="mb-3">
          <label class="form-label">Select Fixture</label>
          <select name="fixture_id" class="form-select" required>
            <?php while ($f = $fixtures->fetch_assoc()): ?>
              <option value="<?= $f['fixture_id'] ?>">
                <?= date("d M Y", strtotime($f['match_date'])) ?>
                <?= date("H:i", strtotime($f['match_time'])) ?> —
                <?= htmlspecialchars($f['home_team']) ?> vs <?= htmlspecialchars($f['away_team']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="score-input-inline mb-3 text-center">
          <label class="form-label d-block mb-2">Enter Score</label>
          <input type="number" name="score_home" class="form-control d-inline" min="0" required placeholder="Home">
          <span class="mx-2 fw-bold" style="font-size:1.5rem;">:</span>
          <input type="number" name="score_away" class="form-control d-inline" min="0" required placeholder="Away">
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-success">Submit Result</button>
        </div>
      <?php else: ?>
        <div class="alert alert-info text-center mb-0">
          No upcoming fixtures in this league.
        </div>
      <?php endif; ?>
    </form>
  <?php endif; ?>

  <!-- Recently Submitted Results (with Cancel & Edit) -->
  <?php if ($selectedLeagueId && $recentResults && $recentResults->num_rows): ?>
    <div class="card shadow-sm mb-4 submit-score-card">
      <div class="card-header bg-warning text-dark fw-semibold">
        Recently Submitted Results (Undo / Edit)
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered align-middle mb-0 table-referee-results">
            <thead>
              <tr>
                <th>Date</th>
                <th>Fixture</th>
                <th>Score</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $recentResults->fetch_assoc()): ?>
                <tr>
                  <td><?= date("d M Y", strtotime($r['match_date'])) ?> <?= date("H:i", strtotime($r['match_time'])) ?></td>
                  <td><?= htmlspecialchars($r['home_team']) ?> vs <?= htmlspecialchars($r['away_team']) ?></td>
                  <td><strong><?= $r['score_home'] ?> : <?= $r['score_away'] ?></strong></td>
                  <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this result?');">
                      <input type="hidden" name="league_id" value="<?= $selectedLeagueId ?>">
                      <input type="hidden" name="cancel_fixture_id" value="<?= $r['fixture_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger">❌ Cancel</button>
                    </form>
                    <a href="edit_results.php?fixture_id=<?= $r['fixture_id'] ?>" class="btn btn-sm btn-outline-warning ms-1">
                      ✏️ Edit
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <div class="form-text mt-2 text-muted">
          Cancelling a result will revert the fixture to "upcoming" and mark the log as cancelled.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="text-center">
    <a href="../logout.php" class="btn btn-outline-danger">🚪 Logout</a>
  </div>
</div>

<?php include('../includes/footer.php'); ?>