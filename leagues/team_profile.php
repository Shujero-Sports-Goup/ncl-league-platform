<?php
session_start();
require_once(__DIR__ . '/../db_connect.php');
$base = '/ncl-league-platform';

// Validate team ID
if (!empty($_GET['team_id']) && is_numeric($_GET['team_id'])) {
  $teamId = (int)$_GET['team_id'];
} else {
  die("<div class='alert alert-danger m-4'>❌ Team ID missing or invalid.</div>");
}

// Fetch team details
$stmt = $conn->prepare("SELECT name, coach_name, description, logo_url FROM teams WHERE team_id = ?");
$stmt->bind_param("i", $teamId);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$team) die("<div class='alert alert-danger m-4'>❌ Team not found.</div>");

$teamName = $team['name'];
$coach    = $team['coach_name'] ?: '—';
$bio      = $team['description'] ?: 'No biography available.';
$hasLogo  = !empty($team['logo_url']);
$logoUrl  = $hasLogo ? "$base/" . ltrim($team['logo_url'], '/') : '';
$initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $teamName)));

// Roster
$players = $conn->prepare("SELECT name, position FROM players WHERE team_id = ? ORDER BY name");
$players->bind_param("i", $teamId);
$players->execute();
$roster = $players->get_result();
$players->close();

// Upcoming fixtures
$fixtures = $conn->prepare("
  SELECT f.match_date, f.match_time, f.venue,
         t1.name AS home_team, t2.name AS away_team
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  WHERE (f.home_team = ? OR f.away_team = ?) AND f.status = 'upcoming'
  ORDER BY f.match_date, f.match_time
");
$fixtures->bind_param("ii", $teamId, $teamId);
$fixtures->execute();
$schedule = $fixtures->get_result();
$fixtures->close();

// Match history
$history = $conn->prepare("
  SELECT f.match_date, f.venue, t1.name AS home_team, t2.name AS away_team,
         mr.score_home, mr.score_away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  LEFT JOIN match_results mr ON f.fixture_id = mr.fixture_id
  WHERE (f.home_team = ? OR f.away_team = ?) AND f.status = 'played' AND mr.cancelled_by_referee = 0
  ORDER BY f.match_date DESC
");
$history->bind_param("ii", $teamId, $teamId);
$history->execute();
$results = $history->get_result();
$history->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($teamName) ?> — Nukta League Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="<?= $base ?>/assets/css/nukta-theme.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" />
</head>
<body>

<nav class="navbar navbar-expand-lg shadow-lg" 
     style="background: linear-gradient(135deg, #0000ff, #4169E1);">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center text-white" href="<?= $base ?>/index.php">
      <img src="<?= $base ?>/assets/images/nukta-logo.png" alt="Nukta" height="30" class="me-2">
      Nukta League Management
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link text-white" href="<?= $base ?>/leagues/teams.php"><i class="fas fa-users me-1"></i>Teams</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="<?= $base ?>/leagues/fixtures.php"><i class="fas fa-calendar me-1"></i>Fixtures</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="<?= $base ?>/leagues/standings.php"><i class="fas fa-trophy me-1"></i>Standings</a></li>
      </ul>
      <ul class="navbar-nav">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link text-white" href="<?= $base ?>/logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link text-white" href="<?= $base ?>/login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 400px; position: relative; overflow: hidden;" data-aos="fade-down">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('<?= $base ?>/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-5" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($hasLogo): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= $teamName ?> Logo"
                         class="team-image mb-4 shadow-lg rounded-circle border border-light"
                         style="width: 150px; height: 150px; object-fit: cover;" data-aos="zoom-in" />
                <?php else: ?>
                    <div class="team-avatar mb-4 d-flex align-items-center justify-content-center text-white fw-bold shadow-lg rounded-circle mx-auto"
                         style="width: 150px; height: 150px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1)); 
                                font-size: 3rem; border: 3px solid rgba(255, 255, 255, 0.3);" data-aos="zoom-in">
                        <?= $initials ?>
                    </div>
                <?php endif; ?>
                <h1 class="hero-heading display-3 fw-bold mb-4 text-white"><?= htmlspecialchars($teamName) ?></h1>
                <div class="d-flex align-items-center justify-content-center mb-4">
                    <i class="fas fa-user-tie fa-lg me-3" style="color: rgba(255, 255, 255, 0.8);"></i>
                    <p class="hero-subtext lead mb-0 text-white">Coach: <span class="fw-bold"><?= htmlspecialchars($coach) ?></span></p>
                </div>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<div class="container py-5" style="background: white; margin-top: 2rem;">
  
  <!-- Modern Tab Navigation -->
  <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px; overflow: hidden;">
    <div class="card-header border-0 py-4" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
      <ul class="nav nav-pills justify-content-center mb-0" role="tablist" style="gap: 10px;">
        <li class="nav-item">
          <button class="nav-link active px-4 py-3 fw-bold" 
                  style="border-radius: 15px; background: linear-gradient(135deg, #0000ff, #4169E1); 
                         color: white; border: none; transition: all 0.3s ease;"
                  data-bs-toggle="tab" data-bs-target="#bio">
            <i class="fas fa-info-circle me-2"></i>Team Info
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link px-4 py-3 fw-bold" 
                  style="border-radius: 15px; background: transparent; color: #0000ff; 
                         border: 2px solid #0000ff; transition: all 0.3s ease;"
                  data-bs-toggle="tab" data-bs-target="#roster">
            <i class="fas fa-users me-2"></i>Roster
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link px-4 py-3 fw-bold" 
                  style="border-radius: 15px; background: transparent; color: #0000ff; 
                         border: 2px solid #0000ff; transition: all 0.3s ease;"
                  data-bs-toggle="tab" data-bs-target="#schedule">
            <i class="fas fa-calendar me-2"></i>Schedule
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link px-4 py-3 fw-bold" 
                  style="border-radius: 15px; background: transparent; color: #0000ff; 
                         border: 2px solid #0000ff; transition: all 0.3s ease;"
                  data-bs-toggle="tab" data-bs-target="#match-history">
            <i class="fas fa-history me-2"></i>Match History
          </button>
        </li>
      </ul>
    </div>
  </div>

  <div class="tab-content">
    <!-- Team Bio -->
    <div class="tab-pane fade show active" id="bio">
      <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px;" data-aos="fade-up">
        <div class="card-header border-0 d-flex align-items-center py-4" 
             style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px 20px 0 0;">
          <div class="icon-badge me-3" 
               style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                      border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-info-circle fa-lg text-white"></i>
          </div>
          <div>
            <h4 class="mb-0 fw-bold text-white">Team Biography</h4>
            <small class="text-white-50">About <?= htmlspecialchars($teamName) ?></small>
          </div>
        </div>
        <div class="card-body p-4" style="background: white;">
          <div class="bio-content" style="line-height: 1.8; color: #333; font-size: 1.1rem;">
            <?= nl2br(htmlspecialchars($bio)) ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Roster -->
    <div class="tab-pane fade" id="roster">
      <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px;" data-aos="fade-up">
        <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
             style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px 20px 0 0;">
          <div class="d-flex align-items-center">
            <div class="icon-badge me-3" 
                 style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                        border-radius: 50%; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-users fa-lg text-white"></i>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-white">Player Roster</h4>
              <small class="text-white-50">Team members</small>
            </div>
          </div>
          <span class="badge px-4 py-2 fw-bold" 
                style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 20px; font-size: 1rem;">
            <?= $roster->num_rows ?> players
          </span>
        </div>
        <div class="card-body p-0" style="background: white;">
          <?php if ($roster->num_rows): ?>
            <div class="list-group list-group-flush">
              <?php while ($p = $roster->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center border-0 py-4 px-4"
                     style="transition: all 0.3s ease;"
                     onmouseover="this.style.backgroundColor='rgba(0, 0, 255, 0.05)'; this.style.transform='translateX(5px)'"
                     onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                  <div class="d-flex align-items-center">
                    <div class="player-avatar me-3 d-flex align-items-center justify-content-center fw-bold"
                         style="width: 40px; height: 40px; background: linear-gradient(135deg, #0000ff, #4169E1); 
                                color: white; border-radius: 50%; font-size: 0.9rem;">
                      <?= strtoupper(substr($p['name'], 0, 1)) ?>
                    </div>
                    <div>
                      <div class="fw-bold" style="color: #333;"><?= htmlspecialchars($p['name']) ?></div>
                    </div>
                  </div>
                  <span class="badge px-3 py-2 fw-bold" 
                        style="background: linear-gradient(135deg, #17a2b8, #20c997); color: white; font-size: 0.9rem;">
                    <?= htmlspecialchars($p['position'] ?: 'Player') ?>
                  </span>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-users fa-3x text-muted mb-3"></i>
              <h5 class="text-muted mb-2">No Players Registered</h5>
              <p class="text-muted">Players will appear here once they're added to the roster!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Fixtures -->
    <div class="tab-pane fade" id="schedule">
      <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px;" data-aos="fade-up">
        <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
             style="background: linear-gradient(135deg, #17a2b8, #20c997); border-radius: 20px 20px 0 0;">
          <div class="d-flex align-items-center">
            <div class="icon-badge me-3" 
                 style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                        border-radius: 50%; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-calendar-alt fa-lg text-white"></i>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-white">Upcoming Fixtures</h4>
              <small class="text-white-50">Scheduled matches</small>
            </div>
          </div>
          <span class="badge px-4 py-2 fw-bold" 
                style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 20px; font-size: 1rem;">
            <?= $schedule->num_rows ?> matches
          </span>
        </div>
        <div class="card-body p-0" style="background: white;">
          <?php if ($schedule->num_rows): ?>
            <div class="table-responsive">
              <table class="table mb-0 align-middle">
                <thead style="background: linear-gradient(135deg, #0000ff, #4169E1);">
                  <tr class="text-center">
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-calendar me-1"></i>Date
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-clock me-1"></i>Time
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-vs me-1"></i>Fixture
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-map-marker-alt me-1"></i>Venue
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($m = $schedule->fetch_assoc()): ?>
                    <tr class="text-center" 
                        style="border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                        onmouseover="this.style.backgroundColor='rgba(23, 162, 184, 0.05)'; this.style.transform='translateX(5px)'"
                        onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                      <td class="py-4">
                        <div class="fw-bold" style="color: #0000ff;">
                          <?= date("d M Y", strtotime($m['match_date'])) ?>
                        </div>
                      </td>
                      <td class="py-4">
                        <span class="badge px-3 py-2 fw-bold" 
                              style="background: linear-gradient(135deg, #17a2b8, #20c997); color: white;">
                          <?= date("H:i", strtotime($m['match_time'])) ?>
                        </span>
                      </td>
                      <td class="py-4">
                        <div class="fw-bold" style="color: #333;">
                          <?= htmlspecialchars($m['home_team']) ?> 
                          <span class="text-muted mx-2">vs</span> 
                          <?= htmlspecialchars($m['away_team']) ?>
                        </div>
                      </td>
                      <td class="py-4">
                        <small class="text-muted">
                          <i class="fas fa-map-marker-alt me-1" style="color: #0000ff;"></i>
                          <?= htmlspecialchars($m['venue']) ?>
                        </small>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
              <h5 class="text-muted mb-2">No Upcoming Fixtures</h5>
              <p class="text-muted">Check back soon for new scheduled matches!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Match History -->
    <div class="tab-pane fade" id="match-history">
      <div class="card border-0 shadow-lg mb-4" style="border-radius: 20px;" data-aos="fade-up">
        <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
             style="background: linear-gradient(135deg, #28a745, #20c997); border-radius: 20px 20px 0 0;">
          <div class="d-flex align-items-center">
            <div class="icon-badge me-3" 
                 style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                        border-radius: 50%; display: flex; align-items: center; justify-content: center;">
              <i class="fas fa-history fa-lg text-white"></i>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-white">Match History</h4>
              <small class="text-white-50">Past results</small>
            </div>
          </div>
          <span class="badge px-4 py-2 fw-bold" 
                style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 20px; font-size: 1rem;">
            <?= $results->num_rows ?> matches
          </span>
        </div>
        <div class="card-body p-0" style="background: white;">
          <?php if ($results->num_rows): ?>
            <div class="table-responsive">
              <table class="table mb-0 align-middle">
                <thead style="background: linear-gradient(135deg, #0000ff, #4169E1);">
                  <tr class="text-center">
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-calendar me-1"></i>Date
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-vs me-1"></i>Match
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-trophy me-1"></i>Score
                    </th>
                    <th class="py-4 fw-bold" style="color: white; border: none;">
                      <i class="fas fa-map-marker-alt me-1"></i>Venue
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($m = $results->fetch_assoc()):
                    $isHome = ($m['home_team'] === $teamName);
                    $score = "{$m['score_home']} - {$m['score_away']}";
                    $fixture = "{$m['home_team']} vs {$m['away_team']}";
                    ?>
                    <tr class="text-center" 
                        style="border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                        onmouseover="this.style.backgroundColor='rgba(40, 167, 69, 0.05)'; this.style.transform='translateX(5px)'"
                        onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                      <td class="py-4">
                        <div class="fw-bold" style="color: #0000ff;">
                          <?= date("d M Y", strtotime($m['match_date'])) ?>
                        </div>
                      </td>
                      <td class="py-4">
                        <div class="fw-bold" style="color: #333;">
                          <?= htmlspecialchars($fixture) ?>
                        </div>
                      </td>
                      <td class="py-4">
                        <div class="d-flex justify-content-center align-items-center gap-2">
                          <span class="score-badge fw-bold px-3 py-2" 
                                style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                       color: white; border-radius: 10px; min-width: 40px;">
                            <?= $m['score_home'] ?>
                          </span>
                          <span class="text-muted">-</span>
                          <span class="score-badge fw-bold px-3 py-2" 
                                style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                       color: white; border-radius: 10px; min-width: 40px;">
                            <?= $m['score_away'] ?>
                          </span>
                        </div>
                      </td>
                      <td class="py-4">
                        <small class="text-muted">
                          <i class="fas fa-map-marker-alt me-1" style="color: #0000ff;"></i>
                          <?= htmlspecialchars($m['venue']) ?>
                        </small>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fas fa-history fa-3x text-muted mb-3"></i>
              <h5 class="text-muted mb-2">No Match History</h5>
              <p class="text-muted">Past match results will appear here once games are played!</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Navigation -->
  <div class="text-center mt-5" data-aos="fade-in">
    <a href="<?= $base ?>/leagues/teams.php" class="btn px-5 py-3 fw-bold" 
       style="background: linear-gradient(135deg, #0000ff, #4169E1); color: white; 
              border-radius: 50px; border: none; box-shadow: 0 8px 25px rgba(0, 0, 255, 0.3); 
              transition: all 0.3s ease;"
       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 35px rgba(0, 0, 255, 0.4)'"
       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(0, 0, 255, 0.3)'">
      <i class="fas fa-arrow-left me-2"></i>Back to Teams
    </a>
  </div>
</div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({
      duration: 800,
      once: true
    });
    
    // Enhanced tab navigation
    document.addEventListener('DOMContentLoaded', function() {
      const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
      
      tabButtons.forEach(button => {
        button.addEventListener('click', function() {
          // Reset all buttons
          tabButtons.forEach(btn => {
            btn.style.background = 'transparent';
            btn.style.color = '#0000ff';
            btn.style.border = '2px solid #0000ff';
          });
          
          // Style active button
          this.style.background = 'linear-gradient(135deg, #0000ff, #4169E1)';
          this.style.color = 'white';
          this.style.border = '2px solid transparent';
        });
        
        // Hover effects
        button.addEventListener('mouseenter', function() {
          if (!this.classList.contains('active')) {
            this.style.background = 'rgba(0, 0, 255, 0.1)';
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 5px 15px rgba(0, 0, 255, 0.2)';
          }
        });
        
        button.addEventListener('mouseleave', function() {
          if (!this.classList.contains('active')) {
            this.style.background = 'transparent';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
          }
        });
      });
    });
  </script>

<style>
.nav-pills .nav-link {
  transition: all 0.3s ease !important;
}

.nav-pills .nav-link:hover {
  background: rgba(0, 0, 255, 0.1) !important;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0, 0, 255, 0.2);
}

.nav-pills .nav-link.active {
  background: linear-gradient(135deg, #0000ff, #4169E1) !important;
  color: white !important;
  border: 2px solid transparent !important;
}

.table tbody tr:hover {
  background-color: rgba(0, 0, 255, 0.05) !important;
  transform: translateX(5px);
}
</style>
</body>

</html>