<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('referee');

// Set base path for assets
$base = '/ncl-league-platform';

$userId   = $_SESSION['user_id'];
$fixtureId = intval($_GET['fixture_id'] ?? 0);
$feedback = '';

// Ensure referee is assigned and get forfeit flags
$check = $conn->prepare("
  SELECT f.fixture_id, t1.name AS home_name, t2.name AS away_name,
         mr.score_home, mr.score_away, mr.home_forfeit, mr.away_forfeit, mr.cancelled_by_referee
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
  header('Location: submit_score.php');
  exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['cancel_result'])) {
    $stmt = $conn->prepare("UPDATE match_results SET cancelled_by_referee = 1, cancelled_at = NOW() WHERE fixture_id = ?");
    $stmt->bind_param("i", $fixtureId);
    $stmt->execute();
    $feedback = "✅ Result marked as cancelled.";
  } else {
    $homeScore = intval($_POST['score_home'] ?? 0);
    $awayScore = intval($_POST['score_away'] ?? 0);
    $homeForfeit = isset($_POST['home_forfeit']) ? 1 : 0;
    $awayForfeit = isset($_POST['away_forfeit']) ? 1 : 0;
    
    $exists = $conn->query("SELECT 1 FROM match_results WHERE fixture_id = $fixtureId")->num_rows;

    if ($exists) {
      $stmt = $conn->prepare("
        UPDATE match_results 
        SET score_home = ?, score_away = ?, home_forfeit = ?, away_forfeit = ?, 
            cancelled_by_referee = 0, submitted_at = NOW() 
        WHERE fixture_id = ?
      ");
      $stmt->bind_param("iiiii", $homeScore, $awayScore, $homeForfeit, $awayForfeit, $fixtureId);
    } else {
      $stmt = $conn->prepare("
        INSERT INTO match_results (fixture_id, submitted_by, score_home, score_away, home_forfeit, away_forfeit, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
      ");
      $stmt->bind_param("iiiiii", $fixtureId, $userId, $homeScore, $awayScore, $homeForfeit, $awayForfeit);
    }
    $stmt->execute();
    
    if ($homeForfeit && $awayForfeit) {
      $feedback = "✅ Result updated - Double forfeit recorded (both teams penalized).";
    } elseif ($homeForfeit || $awayForfeit) {
      $feedback = "✅ Result updated - Forfeit recorded.";
    } else {
      $feedback = "✅ Result updated successfully.";
    }
  }
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 300px; position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('<?= $base ?>/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-4" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <div class="icon-badge me-3" 
                         style="width: 60px; height: 60px; background: rgba(255, 255, 255, 0.2); 
                                border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-edit fa-2x text-white"></i>
                    </div>
                    <h1 class="hero-heading display-4 fw-bold mb-0 text-white">Edit Match Result</h1>
                </div>
                <p class="lead mb-3 text-white">Modify scores and forfeit status for completed fixtures</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

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
          <input type="number" name="score_home" id="score_home" class="form-control d-inline" style="width:70px;display:inline-block;"
                 value="<?= intval($match['score_home'] ?? 0) ?>" required min="0">
          <span class="mx-2 fw-bold" style="font-size:1.5rem;">:</span>
          <input type="number" name="score_away" id="score_away" class="form-control d-inline" style="width:70px;display:inline-block;"
                 value="<?= intval($match['score_away'] ?? 0) ?>" required min="0">
          <?= htmlspecialchars($match['away_name']) ?>
        </h5>
        
        <!-- Forfeit Checkboxes -->
        <div class="row mb-3">
          <div class="col-6 text-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="home_forfeit" id="home_forfeit"
                     <?= ($match['home_forfeit'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="home_forfeit">
                Home Forfeit
              </label>
            </div>
          </div>
          <div class="col-6 text-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="away_forfeit" id="away_forfeit"
                     <?= ($match['away_forfeit'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="away_forfeit">
                Away Forfeit
              </label>
            </div>
          </div>
        </div>
        
        <!-- Forfeit Info -->
        <div class="alert alert-info">
          <small>
            <strong>Forfeit Rules:</strong><br>
            • Single forfeit: Forfeiting team gets -1 point, opponent gets +2 points<br>
            • Double forfeit: Both teams get -1 point when neither shows up
          </small>
        </div>
        
        <button class="btn btn-success w-100">💾 Update Result</button>
      </form>

      <?php if (($match['score_home'] ?? null) !== null && !($match['cancelled_by_referee'] ?? 0)): ?>
        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this result?');">
          <input type="hidden" name="cancel_result" value="1">
          <button class="btn btn-outline-danger w-100">❌ Cancel Result</button>
        </form>
      <?php elseif ($match['cancelled_by_referee'] ?? 0): ?>
        <div class="alert alert-warning text-center mt-3">⚠️ This result was cancelled.</div>
      <?php endif; ?>

      <div class="text-center mt-4">
        <a href="submit_score.php" class="btn btn-outline-secondary">⬅ Back to Fixtures</a>
      </div>
    </div>
  </div>
</div>

<script>
// Handle forfeit checkbox logic for edit form
document.addEventListener('DOMContentLoaded', function() {
    const homeForfeit = document.getElementById('home_forfeit');
    const awayForfeit = document.getElementById('away_forfeit');
    const homeScore = document.getElementById('score_home');
    const awayScore = document.getElementById('score_away');
    
    if (homeForfeit && awayForfeit && homeScore && awayScore) {
        // Function to handle forfeit logic
        function handleForfeitChange() {
            if (homeForfeit.checked && awayForfeit.checked) {
                // Double forfeit - both teams get 0 points
                homeScore.value = '0';
                awayScore.value = '0';
                homeScore.disabled = true;
                awayScore.disabled = true;
            } else if (homeForfeit.checked) {
                // Home team forfeits
                homeScore.value = '0';
                homeScore.disabled = true;
                awayScore.disabled = false;
                if (!awayScore.value || awayScore.value === '0') {
                    awayScore.value = '20'; // Default win score
                }
            } else if (awayForfeit.checked) {
                // Away team forfeits
                awayScore.value = '0';
                awayScore.disabled = true;
                homeScore.disabled = false;
                if (!homeScore.value || homeScore.value === '0') {
                    homeScore.value = '20'; // Default win score
                }
            } else {
                // No forfeits - enable both inputs
                homeScore.disabled = false;
                awayScore.disabled = false;
            }
        }
        
        // Set initial state based on current checkbox values
        handleForfeitChange();
        
        // Add event listeners
        homeForfeit.addEventListener('change', handleForfeitChange);
        awayForfeit.addEventListener('change', handleForfeitChange);
    }
});
</script>

<?php include('../includes/footer.php'); ?>