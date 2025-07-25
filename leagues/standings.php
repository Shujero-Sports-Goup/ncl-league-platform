<?php
session_start();
require_once('../db_connect.php');

// Get current league ID
$leagueId = $_SESSION['league_id'] ?? 1;

// Fetch league name
$leagueRes = $conn->query("SELECT name FROM leagues WHERE league_id = $leagueId");
$leagueName = $leagueRes && $leagueRes->num_rows ? $leagueRes->fetch_assoc()['name'] : 'League';

// Fetch teams
$standings = [];
$teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");

if ($teams) {
  while ($row = $teams->fetch_assoc()) {
    $standings[$row['team_id']] = [
      'name' => $row['name'],
      'played' => 0,
      'wins' => 0,
      'losses' => 0,
      'forfeits' => 0, // New field for forfeit tracking
      'points' => 0
    ];
  }
}

// Fetch fixtures with scores
$sql = "
  SELECT f.home_team, f.away_team, r.score_home, r.score_away
  FROM fixtures f
  LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
  WHERE f.league_id = $leagueId
    AND r.score_home IS NOT NULL
    AND r.score_away IS NOT NULL
";
$results = $conn->query($sql);

// Aggregate standings
if ($results) {
  while ($match = $results->fetch_assoc()) {
    $home = $match['home_team'];
    $away = $match['away_team'];
    $sh   = (int) $match['score_home'];
    $sa   = (int) $match['score_away'];

    if (!isset($standings[$home]) || !isset($standings[$away])) continue;

    $standings[$home]['played']++;
    $standings[$away]['played']++;

    // Check for forfeits (score of 0)
    $homeForfeit = ($sh === 0);
    $awayForfeit = ($sa === 0);

    if ($homeForfeit && $awayForfeit) {
      // Both teams forfeit - both get -1 point
      $standings[$home]['forfeits']++;
      $standings[$away]['forfeits']++;
      $standings[$home]['points'] -= 1;
      $standings[$away]['points'] -= 1;
      $standings[$home]['losses']++;
      $standings[$away]['losses']++;
    } elseif ($homeForfeit) {
      // Home team forfeits - away team wins, home team gets -1 point
      $standings[$away]['wins']++;
      $standings[$away]['points'] += 2;
      $standings[$home]['losses']++;
      $standings[$home]['forfeits']++;
      $standings[$home]['points'] -= 1;
    } elseif ($awayForfeit) {
      // Away team forfeits - home team wins, away team gets -1 point
      $standings[$home]['wins']++;
      $standings[$home]['points'] += 2;
      $standings[$away]['losses']++;
      $standings[$away]['forfeits']++;
      $standings[$away]['points'] -= 1;
    } else {
      // Normal game - no forfeits
      if ($sh > $sa) {
        $standings[$home]['wins']++;
        $standings[$home]['points'] += 2;
        $standings[$away]['losses']++;
        $standings[$away]['points'] += 1;
      } elseif ($sa > $sh) {
        $standings[$away]['wins']++;
        $standings[$away]['points'] += 2;
        $standings[$home]['losses']++;
        $standings[$home]['points'] += 1;
      }
    }
  }
}

// Sort by points descending
usort($standings, fn($a, $b) => $b['points'] <=> $a['points']);

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 350px; position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('/ncl-league-platform/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-5" data-aos="fade-down" data-aos-duration="800" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="hero-heading display-3 fw-bold mb-4 text-white">League Standings</h1>
                <h3 class="text-white fw-semibold mb-3"><?= htmlspecialchars($leagueName) ?></h3>
                <p class="lead mb-4 text-white">Track team performance and championship race standings</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5" style="margin-top: 2rem;">
    <!-- Scoring Rules Card -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg" 
                 style="border-radius: 20px; background: linear-gradient(135deg, rgba(0, 0, 255, 0.05), rgba(0, 0, 255, 0.02)); 
                        border: 1px solid rgba(0, 0, 255, 0.1);">
                <div class="card-header border-0 text-center" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px 20px 0 0;">
                    <h5 class="card-title text-white mb-0 fw-bold py-2">
                        <i class="fas fa-clipboard-list me-2"></i>Scoring Rules
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row text-center">
                        <div class="col-md-4 mb-3">
                            <div class="scoring-rule p-3" style="border-radius: 15px; background: rgba(40, 167, 69, 0.1); border-left: 4px solid #28a745;">
                                <i class="fas fa-trophy fa-2x text-success mb-2"></i>
                                <h6 class="fw-bold" style="color: #28a745;">Win</h6>
                                <span class="fw-bold" style="color: #0000ff; font-size: 1.2rem;">2 Points</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="scoring-rule p-3" style="border-radius: 15px; background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107;">
                                <i class="fas fa-handshake fa-2x text-warning mb-2"></i>
                                <h6 class="fw-bold" style="color: #ffc107;">Loss</h6>
                                <span class="fw-bold" style="color: #0000ff; font-size: 1.2rem;">1 Point</span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="scoring-rule p-3" style="border-radius: 15px; background: rgba(220, 53, 69, 0.1); border-left: 4px solid #dc3545;">
                                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                                <h6 class="fw-bold" style="color: #dc3545;">Forfeit</h6>
                                <span class="fw-bold" style="color: #dc3545; font-size: 1.2rem;">-1 Point</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Forfeit occurs when a team registers a score of 0 points
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Standings Table -->
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card border-0 shadow-lg" 
                 style="border-radius: 20px; background: white; overflow: hidden;">
                
                <!-- Table Header -->
                <div class="card-header border-0 text-center py-4" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1);">
                    <h4 class="card-title text-white mb-0 fw-bold">
                        <i class="fas fa-chart-line me-2"></i>Championship Standings
                    </h4>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead style="background: #f8f9fa; border-bottom: 2px solid #0000ff;">
                                <tr>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 80px;">
                                        <i class="fas fa-medal me-1"></i>Rank
                                    </th>
                                    <th class="py-4 fw-bold" style="color: #0000ff; min-width: 200px;">
                                        <i class="fas fa-users me-1"></i>Team
                                    </th>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 80px;">
                                        <i class="fas fa-gamepad me-1"></i>GP
                                    </th>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 80px;">
                                        <i class="fas fa-trophy me-1"></i>W
                                    </th>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 80px;">
                                        <i class="fas fa-times me-1"></i>L
                                    </th>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 80px;">
                                        <i class="fas fa-ban me-1"></i>F
                                    </th>
                                    <th class="py-4 text-center fw-bold" style="color: #0000ff; width: 100px;">
                                        <i class="fas fa-star me-1"></i>Points
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($standings as $team): ?>
                                    <tr class="standings-row border-0" 
                                        style="<?php 
                                            if ($rank === 1) {
                                                echo 'background: linear-gradient(135deg, rgba(255, 215, 0, 0.15), rgba(255, 215, 0, 0.05)); border-left: 5px solid #ffd700;';
                                            } elseif ($rank <= 4) {
                                                echo 'background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.03)); border-left: 5px solid #28a745;';
                                            } else {
                                                echo 'border-left: 5px solid transparent; background: white;';
                                            }
                                        ?> transition: all 0.3s ease; border-bottom: 1px solid #f0f0f0;"
                                        onmouseover="this.style.backgroundColor='rgba(0, 0, 255, 0.08)'; this.style.transform='translateX(8px)'"
                                        onmouseout="this.style.backgroundColor='<?php 
                                            if ($rank === 1) echo 'rgba(255, 215, 0, 0.08)'; 
                                            elseif ($rank <= 4) echo 'rgba(40, 167, 69, 0.05)'; 
                                            else echo 'white'; 
                                        ?>'; this.style.transform='translateX(0)'">
                                        
                                        <td class="py-4 text-center">
                                            <?php if ($rank === 1): ?>
                                                <div class="position-badge" 
                                                     style="display: inline-flex; align-items: center; justify-content: center; 
                                                            width: 45px; height: 45px; border-radius: 50%; 
                                                            background: linear-gradient(135deg, #ffd700, #ffed4e); 
                                                            color: #000; font-weight: bold; font-size: 1.1rem;
                                                            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);">
                                                    <i class="fas fa-crown"></i>
                                                </div>
                                            <?php elseif ($rank <= 3): ?>
                                                <div class="position-badge" 
                                                     style="display: inline-flex; align-items: center; justify-content: center; 
                                                            width: 40px; height: 40px; border-radius: 50%; 
                                                            background: linear-gradient(135deg, #28a745, #20c997); 
                                                            color: white; font-weight: bold; font-size: 1rem;
                                                            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);">
                                                    <?= $rank ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="position-badge" 
                                                     style="display: inline-flex; align-items: center; justify-content: center; 
                                                            width: 35px; height: 35px; border-radius: 50%; 
                                                            background: linear-gradient(135deg, #6c757d, #8d9196); 
                                                            color: white; font-weight: bold; font-size: 1rem;">
                                                    <?= $rank ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="py-4">
                                            <div class="team-info">
                                                <span class="fw-bold text-dark" style="font-size: 1.1rem;">
                                                    <?= htmlspecialchars($team['name']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <td class="py-4 text-center">
                                            <span class="fw-semibold text-muted" style="font-size: 1rem;"><?= $team['played'] ?></span>
                                        </td>
                                        
                                        <td class="py-4 text-center">
                                            <span class="badge px-3 py-2" 
                                                  style="background: linear-gradient(135deg, #28a745, #20c997); color: white; font-size: 0.9rem;">
                                                <?= $team['wins'] ?>
                                            </span>
                                        </td>
                                        
                                        <td class="py-4 text-center">
                                            <span class="badge px-3 py-2" 
                                                  style="background: linear-gradient(135deg, #ffc107, #ffed4e); color: #000; font-size: 0.9rem;">
                                                <?= $team['losses'] ?>
                                            </span>
                                        </td>
                                        
                                        <td class="py-4 text-center">
                                            <?php if ($team['forfeits'] > 0): ?>
                                                <span class="badge px-3 py-2" 
                                                      style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; font-size: 0.9rem;">
                                                    <?= $team['forfeits'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted fw-semibold">0</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="py-4 text-center">
                                            <div class="points-badge" 
                                                 style="display: inline-flex; align-items: center; justify-content: center; 
                                                        min-width: 50px; height: 40px; border-radius: 20px; 
                                                        <?= $team['points'] < 0 ? 
                                                            'background: linear-gradient(135deg, #dc3545, #c82333);' : 
                                                            'background: linear-gradient(135deg, #0000ff, #4169E1);' ?> 
                                                        color: white; font-weight: bold; font-size: 1.1rem;
                                                        box-shadow: 0 4px 12px <?= $team['points'] < 0 ? 'rgba(220, 53, 69, 0.3)' : 'rgba(0, 0, 255, 0.3)' ?>;">
                                                <?= $team['points'] ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="row justify-content-center mt-5">
        <div class="col-lg-10">
            <div class="card border-0 shadow-sm" 
                 style="background: linear-gradient(135deg, rgba(0, 0, 255, 0.03), rgba(0, 0, 255, 0.01)); 
                        border-radius: 15px; border: 1px solid rgba(0, 0, 255, 0.1);">
                <div class="card-body p-4">
                    <h6 class="text-center mb-4 fw-bold" style="color: #0000ff;">
                        <i class="fas fa-info-circle me-2"></i>Legend & Abbreviations
                    </h6>
                    <div class="row text-center">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="legend-item">
                                <div class="position-badge mb-2 mx-auto" 
                                     style="display: inline-flex; align-items: center; justify-content: center; 
                                            width: 35px; height: 35px; border-radius: 50%; 
                                            background: linear-gradient(135deg, #ffd700, #ffed4e); 
                                            color: #000; font-size: 0.9rem;">
                                    <i class="fas fa-crown"></i>
                                </div>
                                <small class="d-block text-muted fw-semibold">Champion</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="legend-item">
                                <div class="position-badge mb-2 mx-auto" 
                                     style="display: inline-flex; align-items: center; justify-content: center; 
                                            width: 35px; height: 35px; border-radius: 50%; 
                                            background: linear-gradient(135deg, #28a745, #20c997); 
                                            color: white; font-size: 0.9rem;">
                                    2-4
                                </div>
                                <small class="d-block text-muted fw-semibold">Top Teams</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="legend-item">
                                <span class="badge px-3 py-2 mb-2" 
                                      style="background: linear-gradient(135deg, #0000ff, #4169E1); color: white;">
                                    PTS
                                </span>
                                <small class="d-block text-muted fw-semibold">Total Points</small>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="legend-item">
                                <div class="d-flex justify-content-center gap-2 mb-2">
                                    <span class="badge" style="background: #28a745; color: white; font-size: 0.8rem;">W</span>
                                    <span class="badge" style="background: #ffc107; color: #000; font-size: 0.8rem;">L</span>
                                    <span class="badge" style="background: #dc3545; color: white; font-size: 0.8rem;">F</span>
                                </div>
                                <small class="d-block text-muted fw-semibold">Win/Loss/Forfeit</small>
                            </div>
                        </div>
                    </div>
                    <hr style="border-color: rgba(0, 0, 255, 0.2); margin: 1.5rem 0;">
                    <div class="row text-center">
                        <div class="col-12">
                            <small class="text-muted">
                                <strong style="color: #0000ff;">GP:</strong> Games Played | 
                                <strong style="color: #0000ff;">W:</strong> Wins (2 pts) | 
                                <strong style="color: #0000ff;">L:</strong> Losses (1 pt) | 
                                <strong style="color: #0000ff;">F:</strong> Forfeits (-1 pt)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="text-center mt-5">
        <a href="home.php" 
           class="btn btn-lg fw-semibold px-5 py-3"
           style="background: transparent; border: 2px solid #0000ff; color: #0000ff; 
                  border-radius: 30px; text-transform: uppercase; letter-spacing: 1px;
                  transition: all 0.3s ease; text-decoration: none;"
           onmouseover="this.style.background='#0000ff'; this.style.color='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 255, 0.2)'"
           onmouseout="this.style.background='transparent'; this.style.color='#0000ff'; this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <i class="fas fa-arrow-left me-2"></i>
            Back to League Home
        </a>
    </div>
</section>

<!-- AOS Animation and Custom Scripts -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        offset: 100,
        once: true,
        easing: 'ease-out-cubic'
    });

    // Enhanced table interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Add loading animation for table rows
        const rows = document.querySelectorAll('.standings-row');
        rows.forEach((row, index) => {
            row.style.opacity = '0';
            row.style.transform = 'translateY(20px)';
            setTimeout(() => {
                row.style.transition = 'all 0.6s ease';
                row.style.opacity = '1';
                row.style.transform = 'translateY(0)';
            }, index * 100 + 400);
        });

        // Add pulse animation for top 3 positions
        const topRows = document.querySelectorAll('.standings-row:nth-child(-n+3)');
        topRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.animation = 'pulse 0.5s ease-in-out';
            });
            row.addEventListener('mouseleave', function() {
                this.style.animation = '';
            });
        });
    });

    // Add CSS for pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .standings-row {
            position: relative;
        }
        
        .standings-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(90deg, rgba(0, 0, 255, 0.1), transparent);
            transition: width 0.3s ease;
            z-index: -1;
        }
        
        .standings-row:hover::before {
            width: 100%;
        }
    `;
    document.head.appendChild(style);
</script>

<?php include('../includes/footer.php'); ?>