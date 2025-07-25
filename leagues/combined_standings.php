<?php
session_start();
require_once('../db_connect.php');

// Get both NCL and WEL leagues
$nclLeagueId = 1;
$welLeagueId = 2;

// Fetch league names
$leaguesRes = $conn->query("SELECT league_id, name, abbreviation FROM leagues WHERE league_id IN ($nclLeagueId, $welLeagueId)");
$leagues = [];
while ($row = $leaguesRes->fetch_assoc()) {
    $leagues[$row['league_id']] = $row;
}

// Function to calculate standings for a league
function calculateLeagueStandings($conn, $leagueId) {
    $standings = [];
    
    // Fetch teams
    $teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");
    if ($teams) {
        while ($row = $teams->fetch_assoc()) {
            $standings[$row['team_id']] = [
                'name' => $row['name'],
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'forfeits' => 0,
                'points' => 0
            ];
        }
    }
    
    // Fetch fixtures with scores and forfeit flags
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
            $sh = (int) $match['score_home'];
            $sa = (int) $match['score_away'];
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
    
    return $standings;
}

$nclStandings = calculateLeagueStandings($conn, $nclLeagueId);
$welStandings = calculateLeagueStandings($conn, $welLeagueId);

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container py-5">
    <h2 class="text-center mb-4">Combined League Standings - NCL & WEL</h2>

    <!-- Forfeit Rules Notice -->
    <div class="alert alert-info mb-4">
        <h6 class="alert-heading">📋 Scoring Rules:</h6>
        <ul class="mb-0">
            <li><strong>Win:</strong> 2 points</li>
            <li><strong>Loss:</strong> 1 point</li>
            <li><strong>Forfeit:</strong> -1 point (marked by referee)</li>
        </ul>
    </div>

    <div class="row">
        <!-- NCL Standings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-primary">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-trophy"></i> 
                        <?= htmlspecialchars($leagues[$nclLeagueId]['name']) ?> (<?= htmlspecialchars($leagues[$nclLeagueId]['abbreviation']) ?>)
                    </h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-standings table-hover text-center mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Team</th>
                                    <th>GP</th>
                                    <th>W</th>
                                    <th>L</th>
                                    <th>F</th>
                                    <th>Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($nclStandings as $team): ?>
                                    <tr class="<?= $rank === 1 ? 'table-gold' : ($rank <= 4 ? 'table-highlight' : '') ?>">
                                        <td><strong><?= $rank++ ?></strong></td>
                                        <td class="text-start fw-semibold"><?= htmlspecialchars($team['name']) ?></td>
                                        <td><?= $team['played'] ?></td>
                                        <td><?= $team['wins'] ?></td>
                                        <td><?= $team['losses'] ?></td>
                                        <td class="<?= $team['forfeits'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= $team['forfeits'] ?></td>
                                        <td class="fw-bold <?= $team['points'] < 0 ? 'text-danger' : '' ?>"><?= $team['points'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- WEL Standings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-trophy"></i> 
                        <?= htmlspecialchars($leagues[$welLeagueId]['name']) ?> (<?= htmlspecialchars($leagues[$welLeagueId]['abbreviation']) ?>)
                    </h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-standings table-hover text-center mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Team</th>
                                    <th>GP</th>
                                    <th>W</th>
                                    <th>L</th>
                                    <th>F</th>
                                    <th>Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($welStandings as $team): ?>
                                    <tr class="<?= $rank === 1 ? 'table-gold' : ($rank <= 4 ? 'table-highlight' : '') ?>">
                                        <td><strong><?= $rank++ ?></strong></td>
                                        <td class="text-start fw-semibold"><?= htmlspecialchars($team['name']) ?></td>
                                        <td><?= $team['played'] ?></td>
                                        <td><?= $team['wins'] ?></td>
                                        <td><?= $team['losses'] ?></td>
                                        <td class="<?= $team['forfeits'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= $team['forfeits'] ?></td>
                                        <td class="fw-bold <?= $team['points'] < 0 ? 'text-danger' : '' ?>"><?= $team['points'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">NCL Statistics</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-box">
                                <h3 class="text-primary"><?= count($nclStandings) ?></h3>
                                <small class="text-muted">Teams</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <h3 class="text-primary"><?= array_sum(array_column($nclStandings, 'played')) / 2 ?></h3>
                                <small class="text-muted">Games Played</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">WEL Statistics</h5>
                    <div class="row">
                        <div class="col-6">
                            <div class="stat-box">
                                <h3 class="text-success"><?= count($welStandings) ?></h3>
                                <small class="text-muted">Teams</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-box">
                                <h3 class="text-success"><?= array_sum(array_column($welStandings, 'played')) / 2 ?></h3>
                                <small class="text-muted">Games Played</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="../leagues/home.php" class="btn btn-outline-primary me-2">⬅ Back to League Home</a>
        <a href="../admin/fixture_generator.php" class="btn btn-primary">📅 Manage Fixtures</a>
    </div>
</section>

<style>
.table-gold {
    background-color: #fff3cd !important;
    border-left: 4px solid #ffc107;
}

.table-highlight {
    background-color: #e8f5e8 !important;
    border-left: 4px solid #28a745;
}

.text-danger {
    color: #dc3545 !important;
}

.alert-info {
    background-color: #cce7ff;
    border-color: #b3d9ff;
    color: #004085;
}

.stat-box {
    padding: 10px;
}

.card {
    transition: transform 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
}
</style>

<?php include('../includes/footer.php'); ?>
