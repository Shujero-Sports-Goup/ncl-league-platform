<?php
session_start();
require_once('../db_connect.php');

// Set base path for assets
$base = '/ncl-league-platform';

// Get current league ID
$leagueId = $_SESSION['league_id'] ?? 1;

// Fetch league name
$leagueRes = $conn->query("SELECT name FROM leagues WHERE league_id = $leagueId");
$leagueName = $leagueRes && $leagueRes->num_rows ? $leagueRes->fetch_assoc()['name'] : 'League';

// Fetch teams with logos
$standings = [];
$teams = $conn->query("SELECT team_id, name, logo_url FROM teams WHERE league_id = $leagueId");

if ($teams) {
    while ($row = $teams->fetch_assoc()) {
        $standings[$row['team_id']] = [
            'name' => $row['name'],
            'logo_url' => $row['logo_url'],
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
  SELECT f.home_team, f.away_team, r.score_home, r.score_away, r.home_forfeit, r.away_forfeit
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
        $homeForfeit = (bool) $match['home_forfeit'];
        $awayForfeit = (bool) $match['away_forfeit'];

        if (!isset($standings[$home]) || !isset($standings[$away])) continue;

        $standings[$home]['played']++;
        $standings[$away]['played']++;

        // Check for forfeits using forfeit flags
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
            // Normal game - no forfeits, determine winner by score
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
            } else {
                // It's a tie - both teams get 1 point
                $standings[$home]['points'] += 1;
                $standings[$away]['points'] += 1;
            }
        }
    }
}

// Sort by points descending
usort($standings, fn($a, $b) => $b['points'] <=> $a['points']);

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Modern Hero Section -->
<section class="position-relative overflow-hidden"
    style="background: linear-gradient(135deg, #0000ff 0%, #001a4d 50%, #0000ff 100%); 
                min-height: 60vh; display: flex; align-items: center;">

    <!-- Animated Background Pattern -->
    <div class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 0;">
        <div style="position: absolute; width: 200px; height: 200px; border-radius: 50%; 
                    background: rgba(255, 255, 255, 0.05); top: -100px; right: -100px; 
                    animation: float 6s ease-in-out infinite;"></div>
        <div style="position: absolute; width: 150px; height: 150px; border-radius: 50%; 
                    background: rgba(255, 255, 255, 0.03); bottom: -75px; left: -75px; 
                    animation: float 8s ease-in-out infinite reverse;"></div>
        <div style="position: absolute; width: 100px; height: 100px; border-radius: 50%; 
                    background: rgba(255, 255, 255, 0.04); top: 50%; right: 20%; 
                    animation: float 7s ease-in-out infinite;"></div>
    </div>

    <!-- Nukta Logo Watermark -->
    <div class="position-absolute w-100 h-100 d-flex align-items-center justify-content-center"
        style="top: 0; left: 0; z-index: 1; opacity: 0.08;">
        <img src="<?= $base ?>/assets/images/nukta-logo.png" alt="Nukta" style="height: 300px; filter: brightness(0) invert(1);">
    </div>

    <div class="container position-relative" style="z-index: 2;" data-aos="fade-up" data-aos-duration="1000">
        <div class="row justify-content-center text-center">
            <div class="col-lg-10">
                <!-- Main Title -->
                <div class="mb-4">
                    <span class="badge px-4 py-2 mb-3"
                        style="background: rgba(255, 255, 255, 0.15); color: white; 
                                 border-radius: 50px; font-size: 0.9rem; letter-spacing: 1px; 
                                 border: 1px solid rgba(255, 255, 255, 0.2);">
                        CHAMPIONSHIP TRACKER
                    </span>
                </div>

                <h1 class="display-2 fw-bold text-white mb-3"
                    style="font-family: 'Inter', sans-serif; letter-spacing: -1px;">
                    League Standings
                </h1>

                <h2 class="h3 text-white mb-4" style="font-weight: 300; opacity: 0.9;">
                    <?= htmlspecialchars($leagueName) ?>
                </h2>

                <p class="lead text-white mb-5" style="opacity: 0.8; max-width: 600px; margin: 0 auto;">
                    Real-time championship standings with comprehensive team performance analytics
                </p>

                <!-- Quick Stats -->
                <div class="row justify-content-center g-4 mt-4">
                    <div class="col-auto">
                        <div class="stat-card p-3"
                            style="background: rgba(255, 255, 255, 0.1); border-radius: 15px; 
                                    backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
                            <div class="text-white fw-bold fs-4"><?= count($standings) ?></div>
                            <div class="text-white-50 small">Teams</div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="stat-card p-3"
                            style="background: rgba(255, 255, 255, 0.1); border-radius: 15px; 
                                    backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
                            <div class="text-white fw-bold fs-4"><?= array_sum(array_column($standings, 'played')) / 2 ?></div>
                            <div class="text-white-50 small">Matches</div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="stat-card p-3"
                            style="background: rgba(255, 255, 255, 0.1); border-radius: 15px; 
                                    backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
                            <div class="text-white fw-bold fs-4"><?= $standings[0]['points'] ?? 0 ?></div>
                            <div class="text-white-50 small">Top Score</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5" style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%);">
    <!-- Championship Table -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <!-- Table Card -->
                <div class="card border-0 shadow-lg"
                    style="border-radius: 24px; overflow: hidden; background: white;">

                    <!-- Card Header with Gradient -->
                    <div class="card-header border-0 position-relative"
                        style="background: linear-gradient(135deg, #0000ff 0%, #0033cc 100%); 
                                padding: 2rem 0;">
                        <div class="container-fluid">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h3 class="text-white fw-bold mb-1" style="font-size: 1.75rem;">
                                        <i class="fas fa-trophy me-3" style="color: #ffd700;"></i>
                                        Championship Standings
                                    </h3>
                                    <p class="text-white-80 mb-0">Live rankings and team performance</p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="d-flex justify-content-end gap-3">
                                        <div class="text-center">
                                            <div class="text-white fw-bold fs-5"><?= date('M d') ?></div>
                                            <div class="text-white small">Updated</div>
                                        </div>
                                        <div class="vr" style="opacity: 0.3;"></div>
                                        <div class="text-center">
                                            <div class="text-white fw-bold fs-5"><?= count($standings) ?></div>
                                            <div class="text-white small">Teams</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modern Table -->
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" style="border-collapse: separate; border-spacing: 0;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th class="border-0 py-4 text-center fw-bold" style="color: #0000ff; width: 100px; border-radius: 0;">
                                        <th class="border-0 py-4 fw-bold text-white" style="min-width: 250px;">
                                            <i class="fas fa-shield-alt me-1"></i>TEAM
                                        </th>
                                        <th class="border-0 py-4 text-center fw-bold text-white" style="width: 100px;">
                                            <i class="fas fa-calendar me-1"></i>GP
                                        </th>
                                        <th class="border-0 py-4 text-center fw-bold text-white" style="width: 100px;">
                                            <i class="fas fa-trophy me-1"></i>W
                                        </th>
                                        <th class="border-0 py-4 text-center fw-bold text-white" style="width: 100px;">
                                            <i class="fas fa-times me-1"></i>L
                                        </th>
                                        <th class="border-0 py-4 text-center fw-bold text-white" style="width: 100px;">
                                            <i class="fas fa-ban me-1"></i>F
                                        </th>
                                        <th class="border-0 py-4 text-center fw-bold text-white" style="width: 120px;">
                                            <i class="fas fa-star me-1"></i>POINTS
                                        </th>

                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1;
                                    foreach ($standings as $team): ?>
                                        <tr class="team-row border-0"
                                            style="<?php
                                                    if ($rank === 1) {
                                                        echo 'background: linear-gradient(90deg, rgba(255, 215, 0, 0.08) 0%, rgba(255, 215, 0, 0.02) 100%);';
                                                    } elseif ($rank <= 3) {
                                                        echo 'background: linear-gradient(90deg, rgba(40, 167, 69, 0.08) 0%, rgba(40, 167, 69, 0.02) 100%);';
                                                    } else {
                                                        echo 'background: white;';
                                                    }
                                                    ?> border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important; 
                                            transition: all 0.3s ease;"
                                            onmouseover="this.style.backgroundColor='rgba(0, 0, 255, 0.04)'; this.style.transform='translateX(4px)'"
                                            onmouseout="this.style.backgroundColor='<?php
                                                                                    if ($rank === 1) echo 'rgba(255, 215, 0, 0.04)';
                                                                                    elseif ($rank <= 3) echo 'rgba(40, 167, 69, 0.04)';
                                                                                    else echo 'white';
                                                                                    ?>'; this.style.transform='translateX(0)'">

                                            <!-- Rank Column -->
                                            <td class="py-4 text-center align-middle">
                                                <?php if ($rank === 1): ?>
                                                    <div class="rank-badge champion"
                                                        style="display: inline-flex; align-items: center; justify-content: center; 
                                                                width: 50px; height: 50px; border-radius: 50%; 
                                                                background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); 
                                                                color: #000; font-weight: bold; font-size: 1.2rem;
                                                                box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
                                                                border: 3px solid #fff;">
                                                        <i class="fas fa-crown"></i>
                                                    </div>
                                                <?php elseif ($rank <= 3): ?>
                                                    <div class="rank-badge top-tier"
                                                        style="display: inline-flex; align-items: center; justify-content: center; 
                                                                width: 45px; height: 45px; border-radius: 50%; 
                                                                background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
                                                                color: white; font-weight: bold; font-size: 1.1rem;
                                                                box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
                                                                border: 2px solid #fff;">
                                                        <?= $rank ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="rank-badge regular"
                                                        style="display: inline-flex; align-items: center; justify-content: center; 
                                                                width: 40px; height: 40px; border-radius: 50%; 
                                                                background: linear-gradient(135deg, #6c757d 0%, #495057 100%); 
                                                                color: white; font-weight: bold; font-size: 1rem;
                                                                border: 1px solid #fff;">
                                                        <?= $rank ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Team Column -->
                                            <td class="py-4 align-middle">
                                                <div class="d-flex align-items-center">
                                                    <!-- Team Logo/Avatar -->
                                                    <div class="team-logo-container me-3"
                                                        style="width: 50px; height: 50px; border-radius: 12px; 
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 255, 0.2);
            transition: all 0.3s ease;">
                                                        <?php if (!empty($team['logo_url'])): ?>
                                                            <?php
                                                            // Handle different logo path formats
                                                            $logoPath = $team['logo_url'];
                                                            if (strpos($logoPath, 'uploads/logos/') === 0) {
                                                                $fullLogoPath = $base . '/' . $logoPath;
                                                                $fileCheckPath = '../' . $logoPath;
                                                            } else {
                                                                $fullLogoPath = $base . '/uploads/logos/' . basename($logoPath);
                                                                $fileCheckPath = '../uploads/logos/' . basename($logoPath);
                                                            }
                                                            ?>
                                                            <?php if (file_exists($fileCheckPath)): ?>
                                                                <img src="<?= htmlspecialchars($fullLogoPath) ?>"
                                                                    alt="<?= htmlspecialchars($team['name']) ?> Logo"
                                                                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                                                            <?php else: ?>
                                                                <?php
                                                                $words = explode(' ', $team['name']);
                                                                $initials = count($words) > 1
                                                                    ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
                                                                    : strtoupper(substr($team['name'], 0, 2));
                                                                ?>
                                                                <div class="team-avatar"
                                                                    style="width: 100%; height: 100%; border-radius: 12px; 
                        background: linear-gradient(135deg, #0000ff, #0033cc); 
                        display: flex; align-items: center; justify-content: center;
                        color: white; font-weight: bold; font-size: 1.2rem;">
                                                                    <?= $initials ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <?php
                                                            $words = explode(' ', $team['name']);
                                                            $initials = count($words) > 1
                                                                ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1))
                                                                : strtoupper(substr($team['name'], 0, 2));
                                                            ?>
                                                            <div class="team-avatar"
                                                                style="width: 100%; height: 100%; border-radius: 12px; 
                    background: linear-gradient(135deg, #0000ff, #0033cc); 
                    display: flex; align-items: center; justify-content: center;
                    color: white; font-weight: bold; font-size: 1.2rem;">
                                                                <?= $initials ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>


                                                    <!-- Team Info -->
                                                    <div>
                                                        <div class="team-name fw-bold" style="color: #1a1a1a; font-size: 1.1rem;">
                                                            <?= htmlspecialchars($team['name']) ?>
                                                        </div>
                                                        <div class="team-stats small text-muted">
                                                            <?php
                                                            $winRate = $team['played'] > 0 ? round(($team['wins'] / $team['played']) * 100) : 0;
                                                            echo "{$winRate}% win rate";
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Stats Columns -->
                                            <td class="py-4 text-center align-middle">
                                                <span class="fw-semibold" style="color: #495057; font-size: 1rem;">
                                                    <?= $team['played'] ?>
                                                </span>
                                            </td>

                                            <td class="py-4 text-center align-middle">
                                                <span class="stat-badge wins"
                                                    style="display: inline-flex; align-items: center; justify-content: center;
                                                             min-width: 35px; height: 35px; border-radius: 8px;
                                                             background: linear-gradient(135deg, #28a745, #20c997); 
                                                             color: white; font-weight: bold; font-size: 0.9rem;">
                                                    <?= $team['wins'] ?>
                                                </span>
                                            </td>

                                            <td class="py-4 text-center align-middle">
                                                <span class="stat-badge losses"
                                                    style="display: inline-flex; align-items: center; justify-content: center;
                                                             min-width: 35px; height: 35px; border-radius: 8px;
                                                             background: linear-gradient(135deg, #ffc107, #ffed4e); 
                                                             color: #000; font-weight: bold; font-size: 0.9rem;">
                                                    <?= $team['losses'] ?>
                                                </span>
                                            </td>

                                            <td class="py-4 text-center align-middle">
                                                <?php if ($team['forfeits'] > 0): ?>
                                                    <span class="stat-badge forfeits"
                                                        style="display: inline-flex; align-items: center; justify-content: center;
                                                                 min-width: 35px; height: 35px; border-radius: 8px;
                                                                 background: linear-gradient(135deg, #dc3545, #c82333); 
                                                                 color: white; font-weight: bold; font-size: 0.9rem;">
                                                        <?= $team['forfeits'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted fw-semibold">—</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="py-4 text-center align-middle">
                                                <div class="points-display"
                                                    style="display: inline-flex; align-items: center; justify-content: center; 
                                                            min-width: 60px; height: 45px; border-radius: 12px; 
                                                            background: linear-gradient(135deg, <?= $team['points'] < 0 ? '#dc3545, #c82333' : '#0000ff, #0033cc' ?>); 
                                                            color: white; font-weight: bold; font-size: 1.2rem;
                                                            box-shadow: 0 4px 12px <?= $team['points'] < 0 ? 'rgba(220, 53, 69, 0.3)' : 'rgba(0, 0, 255, 0.3)' ?>;">
                                                    <?= $team['points'] ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php $rank++;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Scoring Rules & Information Panel -->
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="row g-4">
                
                <!-- 🏆 Scoring System Section -->
                <div class="col-lg-8">
                    <section class="card border-0 shadow-sm h-100 scoring-system">
                        <header class="card-header text-white fw-bold py-2"
                                style="border-radius: 20px 20px 0 0; background: linear-gradient(135deg, #0000ff, #0033cc);">
                            <i class="fas fa-clipboard-list me-2"></i>Scoring System
                        </header>

                        <div class="card-body p-4">
                            <div class="row g-3">
                                <?php
                                $scoring = [
                                    ['label' => 'Win', 'icon' => 'fa-trophy', 'points' => 2, 'color' => '#28a745', 'bg' => 'rgba(40, 167, 69, 0.08)', 'border' => 'rgba(40, 167, 69, 0.2)'],
                                    ['label' => 'Loss', 'icon' => 'fa-handshake', 'points' => 1, 'color' => '#ffc107', 'bg' => 'rgba(255, 193, 7, 0.08)', 'border' => 'rgba(255, 193, 7, 0.2)'],
                                    ['label' => 'Forfeit', 'icon' => 'fa-ban', 'points' => -1, 'color' => '#dc3545', 'bg' => 'rgba(220, 53, 69, 0.08)', 'border' => 'rgba(220, 53, 69, 0.2)'],
                                ];

                                foreach ($scoring as $rule): ?>
                                    <div class="col-md-4">
                                        <div class="scoring-rule p-3 h-100 text-center"
                                             style="border-radius: 16px; background: <?= $rule['bg'] ?>; border: 1px solid <?= $rule['border'] ?>;">
                                            <div class="icon-wrapper mb-3"
                                                 style="width: 50px; height: 50px; margin: auto; border-radius: 50%;
                                                        background: linear-gradient(135deg, <?= $rule['color'] ?>, <?= $rule['color'] ?>AA);
                                                        display: flex; align-items: center; justify-content: center;">
                                                <i class="fas <?= $rule['icon'] ?> fa-lg text-white"></i>
                                            </div>
                                            <h6 class="fw-bold mb-2" style="color: <?= $rule['color'] ?>;"><?= $rule['label'] ?></h6>
                                            <div class="fw-bold" style="color: #0000ff; font-size: 1.5rem;"><?= $rule['points'] ?></div>
                                            <small class="text-muted"><?= abs($rule['points']) === 1 ? 'Point' : 'Points' ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4 p-3"
                                 style="background: rgba(0, 0, 255, 0.05); border-left: 4px solid #0000ff; border-radius: 12px;">
                                <small class="text-muted d-flex align-items-center">
                                    <i class="fas fa-info-circle me-2 text-blue"></i>
                                    <strong>Note:</strong> Forfeits are manually marked by referees during score submission. Teams can legitimately score 0 points.
                                </small>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- 📊 League Stats Section -->
                <div class="col-lg-4">
                    <section class="card border-0 shadow-sm h-100 text-white"
                             style="border-radius: 20px; background: linear-gradient(135deg, #0000ff, #0033cc);">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-white">
                                <i class="fas fa-chart-bar me-2"></i>League Stats
                            </h5>

                            <?php
                            $stats = [
                                'Total Teams' => count($standings),
                                'Matches Played' => array_sum(array_column($standings, 'played')) / 2,
                                'Leader Points' => $standings[0]['points'] ?? 0,
                                'Last Updated' => date('M d, Y')
                            ];

                            foreach ($stats as $label => $value): ?>
                                <div class="stat-item mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><?= $label ?></span>
                                        <span class="fw-bold fs-4"><?= $value ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

            </div>
        </div>
    </div>
</div>


<!-- Navigation -->
<div class="container text-center mt-5 mb-4">
    <a href="home.php"
        class="btn btn-lg fw-semibold px-5 py-3"
        style="background: linear-gradient(135deg, #0000ff, #0033cc); color: white; 
                  border: none; border-radius: 25px; text-transform: uppercase; 
                  letter-spacing: 1px; transition: all 0.3s ease; text-decoration: none;
                  box-shadow: 0 4px 15px rgba(0, 0, 255, 0.2);"
        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 25px rgba(0, 0, 255, 0.3)'"
        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 0, 255, 0.2)'">
        <i class="fas fa-arrow-left me-2"></i>
        Back to League Home
    </a>
</div>

<!-- Enhanced CSS and Animations -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    @keyframes float {

        0%,
        100% {
            transform: translateY(0px);
        }

        50% {
            transform: translateY(-20px);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes shimmer {
        0% {
            background-position: -200px 0;
        }

        100% {
            background-position: 200px 0;
        }
    }

    .team-row {
        position: relative;
        animation: fadeInUp 0.6s ease forwards;
    }

    .team-row:nth-child(1) {
        animation-delay: 0.1s;
    }

    .team-row:nth-child(2) {
        animation-delay: 0.2s;
    }

    .team-row:nth-child(3) {
        animation-delay: 0.3s;
    }

    .team-row:nth-child(4) {
        animation-delay: 0.4s;
    }

    .team-row:nth-child(5) {
        animation-delay: 0.5s;
    }

    .rank-badge.champion {
        animation: float 3s ease-in-out infinite;
    }

    .team-avatar,
    .team-logo-container {
        transition: all 0.3s ease;
    }

    .team-row:hover .team-avatar,
    .team-row:hover .team-logo-container {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 255, 0.3) !important;
    }

    .team-logo-container img {
        transition: all 0.3s ease;
    }

    .team-row:hover .team-logo-container img {
        transform: scale(1.05);
    }

    .stat-badge {
        transition: all 0.3s ease;
    }

    .team-row:hover .stat-badge {
        transform: scale(1.05);
    }

    .points-display {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .team-row:hover .points-display {
        transform: scale(1.1);
    }

    .points-display::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .team-row:hover .points-display::before {
        left: 100%;
    }

    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
    }

    .scoring-rule {
        transition: all 0.3s ease;
    }

    .scoring-rule:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {

        .team-avatar,
        .team-logo-container {
            width: 40px !important;
            height: 40px !important;
        }

        .team-avatar {
            font-size: 1rem !important;
        }

        .rank-badge {
            width: 35px !important;
            height: 35px !important;
            font-size: 0.9rem !important;
        }

        .points-display {
            min-width: 50px !important;
            height: 40px !important;
            font-size: 1rem !important;
        }
    }
</style>

<!-- AOS Animation and Enhanced Scripts -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 1000,
        offset: 100,
        once: true,
        easing: 'ease-out-cubic'
    });

    // Enhanced interactions
    document.addEventListener('DOMContentLoaded', function() {
        // Add counter animation for stats
        const statElements = document.querySelectorAll('.stat-card .fw-bold, .points-display');

        const animateCounter = (element) => {
            const target = parseInt(element.textContent);
            if (isNaN(target)) return;

            let current = 0;
            const increment = target / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 50);
        };

        // Trigger counter animation when elements come into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.hasAttribute('data-animated')) {
                    entry.target.setAttribute('data-animated', 'true');
                    setTimeout(() => animateCounter(entry.target), 200);
                }
            });
        });

        // Observe stat elements
        document.querySelectorAll('.stat-card .fw-bold').forEach(el => {
            observer.observe(el);
        });

        // Add hover effects for team rows
        const teamRows = document.querySelectorAll('.team-row');
        teamRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
                this.style.boxShadow = '0 10px 30px rgba(0, 0, 255, 0.1)';
            });

            row.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
                this.style.boxShadow = 'none';
            });
        });

        // Smooth scroll for navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    });
</script>

<?php include('../includes/footer.php'); ?>