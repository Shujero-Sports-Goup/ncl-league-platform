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
                        <i class="fas fa-clipboard-check fa-2x text-white"></i>
                    </div>
                    <h1 class="hero-heading display-4 fw-bold mb-0 text-white">Match Score Submission</h1>
                </div>
                <p class="lead mb-3 text-white">Submit and manage match results for your assigned leagues</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<div class="container py-5" style="background: white; margin-top: 2rem;">
  
  <!-- Success/Error Message -->
  <?php if ($msg): ?>
    <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px;">
      <div class="card-body text-center py-4">
        <div class="alert border-0 mb-0" 
             style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05)); 
                    color: #28a745; border-radius: 15px; font-size: 1.1rem;">
          <?= $msg ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- League Selection Card -->
  <div class="card border-0 shadow-lg mb-4" 
       style="border-radius: 20px; overflow: hidden;" data-aos="fade-up">
    
    <div class="card-header border-0 d-flex align-items-center py-4" 
         style="background: linear-gradient(135deg, #0000ff, #4169E1);">
      <div class="icon-badge me-3" 
           style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                  border-radius: 50%; display: flex; align-items: center; justify-content: center;">
        <i class="fas fa-futbol fa-lg text-white"></i>
      </div>
      <div>
        <h4 class="mb-0 fw-bold text-white">Select League</h4>
        <small class="text-white-50">Choose the league to manage fixtures</small>
      </div>
    </div>
    
    <div class="card-body p-4" style="background: white;">
      <form method="GET">
        <div class="mb-0">
          <label class="form-label fw-bold" style="color: #333;">
            <i class="fas fa-trophy me-2" style="color: #0000ff;"></i>Available Leagues
          </label>
          <select name="league_id" class="form-select py-3" 
                  style="border: 2px solid #e9ecef; border-radius: 15px; font-size: 1rem;
                         transition: all 0.3s ease;"
                  onchange="this.form.submit()" 
                  onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                  onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'"
                  required>
            <option value="">— Choose League —</option>
            <?php foreach ($leagues as $l): ?>
              <option value="<?= $l['league_id'] ?>"
                <?= $l['league_id'] == $selectedLeagueId ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($leagues)): ?>
            <div class="mt-3 p-3 text-center" 
                 style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05)); 
                        color: #dc3545; border-radius: 15px;">
              <i class="fas fa-exclamation-triangle me-2"></i>No leagues assigned to your account.
            </div>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Score Submission Form -->
  <?php if ($selectedLeagueId): ?>
    <div class="card border-0 shadow-lg mb-4" 
         style="border-radius: 20px; overflow: hidden;" data-aos="fade-up">
      
      <div class="card-header border-0 d-flex align-items-center py-4" 
           style="background: linear-gradient(135deg, #17a2b8, #20c997);">
        <div class="icon-badge me-3" 
             style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                    border-radius: 50%; display: flex; align-items: center; justify-content: center;">
          <i class="fas fa-plus-circle fa-lg text-white"></i>
        </div>
        <div>
          <h4 class="mb-0 fw-bold text-white">Submit Match Result</h4>
          <small class="text-white-50">Enter the final score for completed fixtures</small>
        </div>
      </div>
      
      <div class="card-body p-4" style="background: white;">
        <form method="POST">
          <input type="hidden" name="league_id" value="<?= $selectedLeagueId ?>">
          <input type="hidden" name="submit_score" value="1">

          <?php if ($fixtures->num_rows): ?>
            <!-- Fixture Selection -->
            <div class="mb-4">
              <label class="form-label fw-bold" style="color: #333;">
                <i class="fas fa-calendar-check me-2" style="color: #0000ff;"></i>Select Fixture
              </label>
              <select name="fixture_id" class="form-select py-3" 
                      style="border: 2px solid #e9ecef; border-radius: 15px; font-size: 1rem;
                             transition: all 0.3s ease;"
                      onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                      onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'"
                      required>
                <?php while ($f = $fixtures->fetch_assoc()): ?>
                  <option value="<?= $f['fixture_id'] ?>">
                    <?= date("d M Y", strtotime($f['match_date'])) ?>
                    <?= date("H:i", strtotime($f['match_time'])) ?> —
                    <?= htmlspecialchars($f['home_team']) ?> vs <?= htmlspecialchars($f['away_team']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            
            <!-- Score Input -->
            <div class="mb-4">
              <label class="form-label fw-bold d-block text-center mb-3" style="color: #333;">
                <i class="fas fa-trophy me-2" style="color: #0000ff;"></i>Enter Final Score
              </label>
              <div class="d-flex align-items-center justify-content-center gap-3">
                <div class="text-center">
                  <label class="form-label small fw-bold text-muted">HOME</label>
                  <input type="number" name="score_home" 
                         class="form-control text-center fw-bold" 
                         style="width: 80px; height: 60px; font-size: 1.5rem; border: 2px solid #e9ecef; 
                                border-radius: 15px; transition: all 0.3s ease;"
                         min="0" max="999" required placeholder="0"
                         onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                         onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'">
                </div>
                <div class="text-center">
                  <div class="fw-bold" style="font-size: 2rem; color: #0000ff; margin-top: 25px;">:</div>
                </div>
                <div class="text-center">
                  <label class="form-label small fw-bold text-muted">AWAY</label>
                  <input type="number" name="score_away" 
                         class="form-control text-center fw-bold" 
                         style="width: 80px; height: 60px; font-size: 1.5rem; border: 2px solid #e9ecef; 
                                border-radius: 15px; transition: all 0.3s ease;"
                         min="0" max="999" required placeholder="0"
                         onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                         onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'">
                </div>
              </div>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid">
              <button type="submit" class="btn py-3 fw-bold" 
                      style="background: linear-gradient(135deg, #28a745, #20c997); 
                             color: white; border: none; border-radius: 15px; 
                             font-size: 1.1rem; transition: all 0.3s ease;
                             box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);"
                      onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(40, 167, 69, 0.4)'"
                      onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(40, 167, 69, 0.3)'">
                <i class="fas fa-check-circle me-2"></i>Submit Result
              </button>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
              <h5 class="text-muted mb-2">No Upcoming Fixtures</h5>
              <p class="text-muted">There are no upcoming fixtures in this league that need score submission.</p>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <!-- Recently Submitted Results -->
  <?php if ($selectedLeagueId && $recentResults && $recentResults->num_rows): ?>
    <div class="card border-0 shadow-lg mb-4" 
         style="border-radius: 20px; overflow: hidden;" data-aos="fade-up">
      
      <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
           style="background: linear-gradient(135deg, #ffc107, #ffed4e);">
        <div class="d-flex align-items-center">
          <div class="icon-badge me-3" 
               style="width: 50px; height: 50px; background: rgba(0, 0, 0, 0.1); 
                      border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-history fa-lg" style="color: #333;"></i>
          </div>
          <div>
            <h4 class="mb-0 fw-bold" style="color: #333;">Recent Submissions</h4>
            <small style="color: rgba(0, 0, 0, 0.6);">Manage your submitted results</small>
          </div>
        </div>
        <span class="badge px-4 py-2 fw-bold" 
              style="background: rgba(0, 0, 0, 0.1); color: #333; border-radius: 20px; font-size: 1rem;">
          <?= $recentResults->num_rows ?> results
        </span>
      </div>
      
      <div class="card-body p-0" style="background: white;">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead style="background: linear-gradient(135deg, #0000ff, #4169E1);">
              <tr class="text-center">
                <th class="py-4 fw-bold" style="color: white; border: none;">
                  <i class="fas fa-calendar me-1"></i>Date & Time
                </th>
                <th class="py-4 fw-bold" style="color: white; border: none;">
                  <i class="fas fa-vs me-1"></i>Fixture
                </th>
                <th class="py-4 fw-bold" style="color: white; border: none;">
                  <i class="fas fa-trophy me-1"></i>Score
                </th>
                <th class="py-4 fw-bold" style="color: white; border: none;">
                  <i class="fas fa-cogs me-1"></i>Actions
                </th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $recentResults->fetch_assoc()): ?>
                <tr class="text-center" 
                    style="border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                    onmouseover="this.style.backgroundColor='rgba(255, 193, 7, 0.05)'; this.style.transform='translateX(5px)'"
                    onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                  <td class="py-4">
                    <div class="fw-bold" style="color: #0000ff;">
                      <?= date("d M Y", strtotime($r['match_date'])) ?>
                    </div>
                    <small class="text-muted"><?= date("H:i", strtotime($r['match_time'])) ?></small>
                  </td>
                  <td class="py-4">
                    <div class="fw-bold" style="color: #333;">
                      <?= htmlspecialchars($r['home_team']) ?> 
                      <span class="text-muted mx-2">vs</span> 
                      <?= htmlspecialchars($r['away_team']) ?>
                    </div>
                  </td>
                  <td class="py-4">
                    <div class="d-flex justify-content-center align-items-center gap-2">
                      <span class="score-badge fw-bold px-3 py-2" 
                            style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                   color: white; border-radius: 10px; min-width: 40px;">
                        <?= $r['score_home'] ?>
                      </span>
                      <span class="text-muted">-</span>
                      <span class="score-badge fw-bold px-3 py-2" 
                            style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                   color: white; border-radius: 10px; min-width: 40px;">
                        <?= $r['score_away'] ?>
                      </span>
                    </div>
                  </td>
                  <td class="py-4">
                    <div class="d-flex justify-content-center gap-2">
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this result? The fixture will be set back to upcoming status.');">
                        <input type="hidden" name="league_id" value="<?= $selectedLeagueId ?>">
                        <input type="hidden" name="cancel_fixture_id" value="<?= $r['fixture_id'] ?>">
                        <button class="btn btn-sm px-3 py-2" 
                                style="background: linear-gradient(135deg, #dc3545, #c82333); 
                                       color: white; border: none; border-radius: 10px; transition: all 0.3s ease;"
                                onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(220, 53, 69, 0.3)'"
                                onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                          <i class="fas fa-times me-1"></i>Cancel
                        </button>
                      </form>
                      <a href="edit_results.php?fixture_id=<?= $r['fixture_id'] ?>" 
                         class="btn btn-sm px-3 py-2" 
                         style="background: linear-gradient(135deg, #ffc107, #ffed4e); 
                                color: #333; border: none; border-radius: 10px; transition: all 0.3s ease; text-decoration: none;"
                         onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 8px rgba(255, 193, 7, 0.3)'"
                         onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="fas fa-edit me-1"></i>Edit
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer border-0 py-3" style="background: rgba(255, 193, 7, 0.05);">
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Cancelling a result will revert the fixture to "upcoming" status and mark the submission as cancelled.
          </small>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Navigation -->
  <div class="text-center mt-5">
    <a href="../logout.php" class="btn px-5 py-3 fw-bold" 
       style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; 
              border-radius: 50px; border: none; box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3); 
              transition: all 0.3s ease; text-decoration: none;"
       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 35px rgba(220, 53, 69, 0.4)'"
       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(220, 53, 69, 0.3)'">
      <i class="fas fa-sign-out-alt me-2"></i>Logout
    </a>
  </div>
</div>

<?php include('../includes/footer.php'); ?>