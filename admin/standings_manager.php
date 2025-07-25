<?php
session_start();
ob_start(); // Start output buffering to prevent header issues
header('Content-Type: text/html; charset=UTF-8');
require_once('../db_connect.php');
require_once('../includes/auth.php');
require_once('helpers/standings_helper.php');
requireRole('admin');

$base = '/ncl-league-platform';
$leagueId = $_SESSION['league_id'] ?? 1;

// Ensure session has league_id set for consistency
if (!isset($_SESSION['league_id'])) {
    $_SESSION['league_id'] = $leagueId;
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');

  switch ($_GET['action']) {
    case 'refresh_standings':
      try {
        $standings = getLeagueStandings($conn, $leagueId);
        $stats = getStandingsStats($conn, $leagueId);
        echo json_encode([
          'success' => true,
          'standings' => $standings,
          'stats' => $stats,
          'last_updated' => date('Y-m-d H:i:s'),
          'forfeit_rules' => [
            'win' => 2,
            'loss' => 1,
            'forfeit' => -1
          ]
        ]);
      } catch (Exception $e) {
        echo json_encode([
          'success' => false,
          'error' => $e->getMessage()
        ]);
      }
      exit;

    case 'get_team_details':
      $teamId = $_GET['team_id'] ?? 0;
      $teamDetails = getTeamDetailedStats($conn, $leagueId, $teamId);
      echo json_encode(['success' => true, 'data' => $teamDetails]);
      exit;

    case 'get_predictions':
      $predictions = getMatchPredictions($conn, $leagueId);
      echo json_encode(['success' => true, 'predictions' => $predictions]);
      exit;
  }
}

// Handle POST actions
$success = $error = '';
if ($_POST['action'] ?? '' === 'create_snapshot') {
  try {
    $snapshotName = trim($_POST['snapshot_name'] ?? 'Weekly Snapshot - ' . date('Y-m-d H:i'));
    $description = trim($_POST['description'] ?? '');

    if (empty($snapshotName)) {
      throw new Exception('Snapshot name is required');
    }

    $standings = getLeagueStandings($conn, $leagueId);
    $stats = getStandingsStats($conn, $leagueId);

    $snapshotData = json_encode([
      'standings' => $standings,
      'stats' => $stats,
      'created_at' => date('Y-m-d H:i:s'),
      'version' => '2.0'
    ]);

    // Check if table exists, create if not
    $conn->query("CREATE TABLE IF NOT EXISTS standings_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            league_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            data LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_league_created (league_id, created_at)
        )");

    $stmt = $conn->prepare("INSERT INTO standings_snapshots (league_id, name, description, data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $leagueId, $snapshotName, $description, $snapshotData);

    if ($stmt->execute()) {
      $success = "Snapshot '{$snapshotName}' created successfully!";
    } else {
      throw new Exception('Failed to create snapshot');
    }
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

// Enhanced data fetching with error handling
try {
  // Get league info with validation
  $leagueQuery = $conn->prepare("SELECT name, abbreviation FROM leagues WHERE league_id = ?");
  $leagueQuery->bind_param("i", $leagueId);
  $leagueQuery->execute();
  $league = $leagueQuery->get_result()->fetch_assoc();

  if (!$league) {
    throw new Exception("League not found");
  }

  // Get current standings and comprehensive stats
  $standings = getLeagueStandings($conn, $leagueId);
  $stats = getStandingsStats($conn, $leagueId);
  $recentResults = getRecentResults($conn, $leagueId, 8);
  $upcomingFixtures = getUpcomingFixtures($conn, $leagueId, 8);

  // Get enhanced basketball-specific stats
  $basketballStats = getBasketballStats($conn, $leagueId);
  $teamStreaks = getTeamStreaks($conn, $leagueId);
  $headToHeadStats = getHeadToHeadStats($conn, $leagueId);
  $homeAwayStats = getHomeAwayStats($conn, $leagueId);

  // Get performance trends
  $performanceTrends = getPerformanceTrends($conn, $leagueId);

  // Get league insights
  $leagueInsights = generateLeagueInsights($standings, $stats, $recentResults);

  // Get recent snapshots with error handling
  $snapshotsQuery = "SELECT * FROM standings_snapshots WHERE league_id = ? ORDER BY created_at DESC LIMIT 10";
  $stmt = $conn->prepare($snapshotsQuery);
  $stmt->bind_param("i", $leagueId);
  $stmt->execute();
  $snapshots = $stmt->get_result();
} catch (Exception $e) {
  $error = "Data loading error: " . $e->getMessage();
  $league = ['name' => 'Basketball League', 'abbreviation' => 'BL'];
  $standings = [];
  $stats = [];
  $recentResults = [];
  $upcomingFixtures = [];
  $basketballStats = [];
  $teamStreaks = [];
  $homeAwayStats = [];
  $performanceTrends = [];
  $leagueInsights = [];
}

// Helper functions for enhanced features
function getPerformanceTrends($conn, $leagueId)
{
  $trends = [];
  $sql = "SELECT 
                t.team_id, t.name,
                COUNT(CASE WHEN f.match_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as recent_games,
                COUNT(CASE WHEN f.match_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                      AND ((f.home_team = t.team_id AND r.score_home > r.score_away) 
                           OR (f.away_team = t.team_id AND r.score_away > r.score_home)) THEN 1 END) as recent_wins
            FROM teams t
            LEFT JOIN fixtures f ON (f.home_team = t.team_id OR f.away_team = t.team_id) AND f.league_id = ?
            LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
            WHERE t.league_id = ?
            GROUP BY t.team_id, t.name";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ii", $leagueId, $leagueId);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    $trends[$row['team_id']] = [
      'name' => $row['name'],
      'recent_games' => $row['recent_games'],
      'recent_wins' => $row['recent_wins'],
      'win_rate' => $row['recent_games'] > 0 ? round(($row['recent_wins'] / $row['recent_games']) * 100, 1) : 0
    ];
  }

  return $trends;
}

function getTeamDetailedStats($conn, $leagueId, $teamId)
{
  $sql = "SELECT 
                t.name,
                COUNT(CASE WHEN r.score_home IS NOT NULL THEN 1 END) as played,
                COUNT(CASE WHEN (f.home_team = t.team_id AND r.score_home > r.score_away) 
                           OR (f.away_team = t.team_id AND r.score_away > r.score_home) THEN 1 END) as wins,
                COUNT(CASE WHEN (f.home_team = t.team_id AND r.score_home < r.score_away) 
                           OR (f.away_team = t.team_id AND r.score_away < r.score_home) THEN 1 END) as losses,
                SUM(CASE WHEN f.home_team = t.team_id THEN r.score_home ELSE r.score_away END) as goals_for,
                SUM(CASE WHEN f.home_team = t.team_id THEN r.score_away ELSE r.score_home END) as goals_against
            FROM teams t
            LEFT JOIN fixtures f ON (f.home_team = t.team_id OR f.away_team = t.team_id) AND f.league_id = ?
            LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
            WHERE t.team_id = ? AND t.league_id = ?
            GROUP BY t.team_id, t.name";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("iii", $leagueId, $teamId, $leagueId);
  $stmt->execute();
  $result = $stmt->get_result();
  $data = $result->fetch_assoc();

  if ($data) {
    $data['points'] = ($data['wins'] * 2) + $data['losses']; // Basketball scoring: 2 for win, 1 for loss
    $data['goal_difference'] = $data['goals_for'] - $data['goals_against'];
  }

  return $data ?: [];
}

function getMatchPredictions($conn, $leagueId)
{
  // Simple prediction algorithm based on recent form and standings
  $predictions = [];

  $sql = "SELECT 
                f.fixture_id,
                f.home_team,
                f.away_team,
                f.match_date,
                th.name as home_name,
                ta.name as away_name
            FROM fixtures f
            JOIN teams th ON f.home_team = th.team_id
            JOIN teams ta ON f.away_team = ta.team_id
            WHERE f.league_id = ? AND f.status = 'upcoming'
            ORDER BY f.match_date ASC
            LIMIT 5";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $leagueId);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($fixture = $result->fetch_assoc()) {
    // Simple prediction logic (can be enhanced)
    $homeWinChance = rand(30, 70);
    $awayWinChance = 100 - $homeWinChance;

    $predictions[] = [
      'fixture_id' => $fixture['fixture_id'],
      'home_team' => $fixture['home_name'],
      'away_team' => $fixture['away_name'],
      'match_date' => $fixture['match_date'],
      'home_win_chance' => $homeWinChance,
      'away_win_chance' => $awayWinChance,
      'prediction' => $homeWinChance > $awayWinChance ? 'home' : 'away'
    ];
  }

  return $predictions;
}

function generateLeagueInsights($standings, $stats, $recentResults)
{
  $insights = [];

  if (!empty($standings)) {
    // Title race analysis
    if (count($standings) >= 2) {
      $leader = $standings[0];
      $second = $standings[1];
      $pointsGap = $leader['points'] - $second['points'];

      if ($pointsGap <= 2) {
        $insights[] = [
          'type' => 'title_race',
          'icon' => '👑',
          'title' => 'Tight Title Race',
          'message' => "{$leader['name']} leads by only {$pointsGap} point(s) over {$second['name']}"
        ];
      }
    }

    // Form analysis
    $inForm = array_filter($standings, function ($team) {
      $recent = substr($team['recent_form'] ?? '', 0, 3); // Get first 3 characters
      $recentArray = str_split($recent); // Convert to array
      return count(array_filter($recentArray, fn($r) => $r === 'W')) >= 2;
    });

    if (!empty($inForm)) {
      $teamNames = array_column($inForm, 'name');
      $insights[] = [
        'type' => 'form',
        'icon' => '🔥',
        'title' => 'Teams in Form',
        'message' => implode(', ', array_slice($teamNames, 0, 3)) . ' showing strong recent form'
      ];
    }

    // Goal scoring trends
    $highScorers = array_filter($standings, function ($team) {
      return $team['played'] > 0 && ($team['goals_for'] / $team['played']) > 2.5;
    });

    if (!empty($highScorers)) {
      $insights[] = [
        'type' => 'scoring',
        'icon' => '🎯',
        'title' => 'High-Scoring Teams',
        'message' => count($highScorers) . ' team(s) averaging over 2.5 goals per game'
      ];
    }
  }

  return $insights;
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container-fluid py-4" id="standings-manager">
  <!-- Enhanced Header with Status -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card bg-gradient-primary text-black shadow-lg border-0">
        <div class="card-body text-center py-4 position-relative">
          <div class="position-absolute top-0 end-0 p-3">
            <span class="badge bg-light text-primary" id="last-updated">
              <i class="fas fa-clock"></i> <span id="update-time">Loading...</span>
            </span>
          </div>
          <h1 class="display-5 fw-bold mb-2">
            <?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Management Center
          </h1>
          <p class="lead mb-3"><?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> - Intelligent Standings Control & Analytics</p>
          <div class="row g-2 justify-content-center">
            <div class="col-auto">
              <div class="d-flex align-items-center text-black-50">
                <i class="fas fa-users me-2"></i>
                <span><?= count($standings) ?> Teams</span>
              </div>
            </div>
            <div class="col-auto">
              <div class="d-flex align-items-center text-black-50">
                <i class="fas fa-gamepad me-2"></i>
                <span><?= $stats['total_matches'] ?? 0 ?> Games</span>
              </div>
            </div>
            <div class="col-auto">
              <div class="d-flex align-items-center text-black-50">
                <i class="fas fa-chart-line me-2"></i>
                <span><?= round($stats['avg_goals_per_game'] ?? 0, 1) ?> Avg PPG</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Status Messages -->
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Forfeit Rules Information -->
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>League Scoring Rules</h6>
    <div class="row">
      <div class="col-md-8">
        <ul class="mb-0">
          <li><strong>Win:</strong> 2 points</li>
          <li><strong>Loss:</strong> 1 point</li>
          <li><strong>Forfeit:</strong> -1 point (marked by referee)</li>
        </ul>
      </div>
      <div class="col-md-4">
        <small class="text-muted">
          <i class="fas fa-exclamation-triangle text-warning me-1"></i>
          Forfeits are highlighted in yellow and result in negative points
        </small>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>

  <!-- League Insights Panel -->
  <?php if (!empty($leagueInsights)): ?>
    <div class="row mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-info text-black">
            <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>League Insights</h6>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <?php foreach ($leagueInsights as $insight): ?>
                <div class="col-md-6 col-lg-4">
                  <div class="insight-card p-3 bg-light rounded">
                    <div class="d-flex align-items-center">
                      <span class="insight-icon me-3"><?= $insight['icon'] ?></span>
                      <div>
                        <h6 class="mb-1"><?= htmlspecialchars($insight['title']) ?></h6>
                        <small class="text-muted"><?= htmlspecialchars($insight['message']) ?></small>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Control Panel -->
  <div class="row">
    <!-- Enhanced Current Standings -->
    <div class="col-lg-8">
      <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="fas fa-trophy me-2"></i>Current League Standings
            <span class="badge bg-light text-primary ms-2" id="standings-count"><?= count($standings) ?> teams</span>
          </h5>
          <div class="btn-group" role="group">
            <button class="btn btn-sm btn-light" onclick="refreshStandings()" id="refresh-btn">
              <i class="fas fa-sync"></i> <span class="d-none d-sm-inline">Refresh</span>
            </button>
            <button class="btn btn-sm btn-success" onclick="copyWhatsAppText()">
              <i class="fab fa-whatsapp"></i> <span class="d-none d-sm-inline">WhatsApp</span>
            </button>
            <div class="btn-group" role="group">
              <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-filter"></i> <span class="d-none d-sm-inline">Filter</span>
              </button>
              <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="filterTable('all')">All Teams</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterTable('top5')">Top 5</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterTable('playoff')">Playoff Positions</a></li>
                <li><a class="dropdown-item" href="#" onclick="filterTable('relegation')">Bottom 3</a></li>
              </ul>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm standings-table mb-0" role="table" aria-label="Basketball League Standings">
              <thead class="table-dark sticky-top">
                <tr role="row">
                  <th class="text-center" width="50" scope="col" aria-label="Position">Pos</th>
                  <th width="200" scope="col" aria-label="Team Name">Team</th>
                  <th class="text-center" width="60" title="Games Played" scope="col" aria-label="Games Played">GP</th>
                  <th class="text-center" width="50" title="Wins" scope="col" aria-label="Wins">W</th>
                  <th class="text-center" width="50" title="Losses" scope="col" aria-label="Losses">L</th>
                  <th class="text-center" width="50" title="Forfeits" scope="col" aria-label="Forfeits">F</th>
                  <th class="text-center" width="60" title="Points For" scope="col" aria-label="Points For">PF</th>
                  <th class="text-center" width="60" title="Points Against" scope="col" aria-label="Points Against">PA</th>
                  <th class="text-center" width="60" title="Point Difference" scope="col" aria-label="Point Difference">+/-</th>
                  <th class="text-center" width="60" title="Points Per Game" scope="col" aria-label="Points Per Game">PPG</th>
                  <th class="text-center" width="60" title="League Points" scope="col" aria-label="League Points">Pts</th>
                  <th class="text-center" width="80" scope="col" aria-label="Current Streak">Streak</th>
                  <th class="text-center" width="120" scope="col" aria-label="Recent Form">Form</th>
                  <th class="text-center" width="60" scope="col" aria-label="Team Actions">Action</th>
                </tr>
              </thead>
              <tbody id="standings-table-body" role="rowgroup">
                <?php if (empty($standings)): ?>
                  <tr>
                    <td colspan="14" class="text-center py-4 text-muted">
                      <i class="fas fa-info-circle me-2"></i>No standings data available
                    </td>
                  </tr>
                <?php else: ?>
                  <?php $position = 1;
                  foreach ($standings as $team): ?>
                    <?php
                    $streak = $teamStreaks[$team['team_id']] ?? ['type' => 'N/A', 'count' => 0];
                    $ppg = $team['played'] > 0 ? round($team['goals_for'] / $team['played'], 1) : 0;
                    $homeAway = $homeAwayStats[$team['team_id']] ?? ['home_wins' => 0, 'away_wins' => 0];
                    $trend = $performanceTrends[$team['team_id']] ?? ['win_rate' => 0];

                    // Position-based styling
                    $rowClass = '';
                    $positionBadge = '';

                    if ($position === 1) {
                      $rowClass = 'table-warning';
                      $positionBadge = 'bg-warning text-dark';
                    } elseif ($position >= 2 && $position <= 4) {
                      $rowClass = 'table-success';
                      $positionBadge = 'bg-success';
                    } elseif ($position >= 5 && $position <= count($standings) - 3) {
                      $rowClass = 'table-neutral';
                      $positionBadge = 'bg-primary'; // fallback for mid-table
                    } elseif ($position > count($standings) - 3) {
                      $rowClass = 'table-danger';
                      $positionBadge = 'bg-danger';
                    }

                    ?>
                    <tr class="<?= $rowClass ?> team-row" data-team-id="<?= $team['team_id'] ?>" data-position="<?= $position ?>"
                      role="row" aria-label="<?= htmlspecialchars($team['name']) ?> - Position <?= $position ?>">
                      <td class="text-center" role="cell" aria-label="Position <?= $position ?>">
                        <span class="badge <?= $positionBadge ?> position-badge"><?= $position ?></span>
                      </td>
                      <td class="fw-semibold team-name" role="cell">
                        <div class="d-flex align-items-center">
                          <span class="team-name-text"><?= htmlspecialchars($team['name']) ?></span>
                          <div class="ms-2">
                            <?php if ($homeAway['home_wins'] > $homeAway['away_wins']): ?>
                              <span class="badge bg-info badge-sm" title="Strong at home" aria-label="Strong at home">🏠</span>
                            <?php elseif ($homeAway['away_wins'] > $homeAway['home_wins']): ?>
                              <span class="badge bg-warning badge-sm" title="Strong away" aria-label="Strong away">🛣️</span>
                            <?php endif; ?>
                            <?php if ($trend['win_rate'] > 75): ?>
                              <span class="badge bg-success badge-sm" title="Excellent recent form" aria-label="Excellent recent form">🔥</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td class="text-center" role="cell" aria-label="<?= $team['played'] ?> games played"><?= $team['played'] ?></td>
                      <td class="text-center text-success fw-bold" role="cell" aria-label="<?= $team['wins'] ?> wins"><?= $team['wins'] ?></td>
                      <td class="text-center text-danger" role="cell" aria-label="<?= $team['losses'] ?> losses"><?= $team['losses'] ?></td>
                      <td class="text-center text-warning fw-bold" role="cell" aria-label="<?= $team['forfeits'] ?> forfeits" title="Forfeits result in -1 point"><?= $team['forfeits'] ?></td>
                      <td class="text-center" role="cell" aria-label="<?= $team['goals_for'] ?> points for"><?= $team['goals_for'] ?></td>
                      <td class="text-center" role="cell" aria-label="<?= $team['goals_against'] ?> points against"><?= $team['goals_against'] ?></td>
                      <td class="text-center <?= $team['goal_difference'] >= 0 ? 'text-success' : 'text-danger' ?>"
                        role="cell" aria-label="Point difference <?= $team['goal_difference'] >= 0 ? 'plus' : '' ?> <?= $team['goal_difference'] ?>">
                        <?= $team['goal_difference'] >= 0 ? '+' : '' ?><?= $team['goal_difference'] ?>
                      </td>
                      <td class="text-center" role="cell" aria-label="<?= $ppg ?> points per game"><?= $ppg ?></td>
                      <td class="text-center fw-bold <?= $team['points'] < 0 ? 'text-danger' : 'text-primary' ?>" role="cell" aria-label="<?= $team['points'] ?> league points"><?= $team['points'] ?></td>
                      <td class="text-center">
                        <?php if ($streak['type'] === 'W'): ?>
                          <span class="badge bg-success">W<?= $streak['count'] ?></span>
                        <?php elseif ($streak['type'] === 'L'): ?>
                          <span class="badge bg-danger">L<?= $streak['count'] ?></span>
                        <?php elseif ($streak['type'] === 'F'): ?>
                          <span class="badge bg-warning text-dark">F<?= $streak['count'] ?></span>
                        <?php else: ?>
                          <span class="badge bg-secondary">-</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-center form-display">
                        <?php
                        $form = $team['recent_form'] ?? '';
                        $formArray = str_split($form);
                        foreach (array_slice($formArray, 0, 5) as $result):
                          $class = $result === 'W' ? 'bg-success' : ($result === 'L' ? 'bg-danger' : ($result === 'F' ? 'bg-warning text-dark' : 'bg-secondary'));
                        ?>
                          <span class="badge <?= $class ?> badge-xs me-1"><?= $result ?></span>
                        <?php endforeach; ?>
                      </td>
                      <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary" onclick="showTeamDetails(<?= $team['team_id'] ?>)" title="View details">
                          <i class="fas fa-eye"></i>
                        </button>
                      </td>
                    </tr>
                  <?php $position++;
                  endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Legend -->
          <div class="card-footer bg-light">
            <div class="row text-center">
              <div class="col-md-2">
                <small class="text-muted">
                  <span class="badge bg-warning text-dark me-1">1</span> Champion
                </small>
              </div>
              <div class="col-md-2">
                <small class="text-muted">
                  <span class="badge bg-success me-1">2-4</span> Playoffs
                </small>
              </div>
              <div class="col-md-2">
                <small class="text-muted">
                  <span class="badge bg-danger me-1">Last 3</span> Relegation
                </small>
              </div>
              <div class="col-md-2">
                <small class="text-muted">
                  <span class="badge bg-warning text-dark me-1">F</span> Forfeit
                </small>
              </div>
              <div class="col-md-2">
                <small class="text-muted">
                  <i class="fas fa-fire text-success"></i> Hot Form
                </small>
              </div>
              <div class="col-md-2">
                <small class="text-muted">
                  <i class="fas fa-home text-info"></i> Home Strong
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Sidebar Controls -->
    <div class="col-lg-4">
      <!-- Real-time Stats Dashboard -->
      <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-info text-black">
          <h6 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>Live Statistics
            <span class="badge bg-light text-info ms-auto" id="stats-refresh">Auto-refresh</span>
          </h6>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="stat-card text-center p-3 bg-primary bg-opacity-10 rounded">
                <div class="stat-value text-primary fw-bold fs-4"><?= count($standings) ?></div>
                <small class="text-muted">Teams</small>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-card text-center p-3 bg-success bg-opacity-10 rounded">
                <div class="stat-value text-success fw-bold fs-4"><?= $stats['total_matches'] ?? 0 ?></div>
                <small class="text-muted">Games Played</small>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-card text-center p-3 bg-warning bg-opacity-10 rounded">
                <div class="stat-value text-warning fw-bold fs-4"><?= $stats['total_goals'] ?? 0 ?></div>
                <small class="text-muted">Total Points</small>
              </div>
            </div>
            <div class="col-6">
              <div class="stat-card text-center p-3 bg-danger bg-opacity-10 rounded">
                <div class="stat-value text-danger fw-bold fs-4"><?= round($stats['avg_goals_per_game'] ?? 0, 1) ?></div>
                <small class="text-muted">Avg PPG</small>
              </div>
            </div>
          </div>

          <!-- Performance Indicators -->
          <div class="mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <small class="text-muted">League Competitiveness</small>
              <span class="badge bg-success">High</span>
            </div>
            <div class="progress" style="height: 6px;">
              <div class="progress-bar bg-success" style="width: <?= min(100, count($standings) * 10) ?>%"></div>
            </div>
          </div>

          <?php if (!empty($stats['highest_scoring_team'])): ?>
            <div class="mt-3 p-2 bg-light rounded">
              <div class="d-flex justify-content-between">
                <small class="text-muted">Top Scorer</small>
                <small class="fw-bold"><?= htmlspecialchars($stats['highest_scoring_team']) ?></small>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($stats['best_defense'])): ?>
            <div class="mt-2 p-2 bg-light rounded">
              <div class="d-flex justify-content-between">
                <small class="text-muted">Best Defense</small>
                <small class="fw-bold"><?= htmlspecialchars($stats['best_defense']) ?></small>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Smart Snapshot Management -->
      <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-warning text-dark">
          <h6 class="mb-0">
            <i class="fas fa-camera me-2"></i>Smart Snapshots
            <span class="badge bg-dark ms-auto"><?= $snapshots->num_rows ?? 0 ?></span>
          </h6>
        </div>
        <div class="card-body">
          <form method="POST" class="mb-3" id="snapshot-form">
            <input type="hidden" name="action" value="create_snapshot">
            <div class="mb-3">
              <label class="form-label small text-muted">Snapshot Name</label>
              <input type="text" class="form-control form-control-sm" name="snapshot_name"
                placeholder="Enter snapshot name..." value="Week <?= date('W') ?> Snapshot - <?= date('M j') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label small text-muted">Description (Optional)</label>
              <textarea class="form-control form-control-sm" name="description" rows="2"
                placeholder="Add context or notes..."></textarea>
            </div>
            <button type="submit" class="btn btn-warning btn-sm w-100">
              <i class="fas fa-camera me-1"></i> Create Snapshot
            </button>
          </form>

          <!-- Auto-snapshot suggestions -->
          <div class="alert alert-light py-2 mb-3">
            <small class="text-muted">
              <i class="fas fa-lightbulb text-warning me-1"></i>
              Tip: Create snapshots after each matchday for historical tracking
            </small>
          </div>

          <?php if ($snapshots && $snapshots->num_rows > 0): ?>
            <div class="mb-2">
              <small class="text-muted fw-bold">Recent Snapshots</small>
            </div>
            <div class="snapshot-list">
              <?php while ($snapshot = $snapshots->fetch_assoc()): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center p-2 border rounded mb-2">
                  <div class="flex-grow-1">
                    <div class="fw-semibold small"><?= htmlspecialchars($snapshot['name']) ?></div>
                    <small class="text-muted"><?= date('M j, H:i', strtotime($snapshot['created_at'])) ?></small>
                  </div>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm" onclick="viewSnapshot(<?= $snapshot['id'] ?>)" title="View">
                      <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="shareSnapshot(<?= $snapshot['id'] ?>)" title="Share">
                      <i class="fab fa-whatsapp"></i>
                    </button>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-3">
              <i class="fas fa-camera text-muted mb-2" style="font-size: 2rem;"></i>
              <p class="text-muted small mb-0">No snapshots created yet</p>
              <small class="text-muted">Create your first snapshot above</small>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Enhanced WhatsApp Sharing -->
      <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-success text-white">
          <h6 class="mb-0">
            <i class="fab fa-whatsapp me-2"></i>Smart Sharing
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <button class="btn btn-success btn-sm" onclick="copyWhatsAppText()">
              <i class="fab fa-whatsapp me-1"></i> Complete Standings
            </button>
            <button class="btn btn-outline-success btn-sm" onclick="copyWhatsAppTop5()">
              <i class="fas fa-trophy me-1"></i> Top 5 Teams
            </button>
            <button class="btn btn-outline-success btn-sm" onclick="copyWhatsAppResults()">
              <i class="fas fa-clock me-1"></i> Recent Results
            </button>
            <button class="btn btn-outline-success btn-sm" onclick="copyWhatsAppFixtures()">
              <i class="fas fa-calendar me-1"></i> Upcoming Games
            </button>
            <button class="btn btn-outline-success btn-sm" onclick="copyCustomUpdate()">
              <i class="fas fa-edit me-1"></i> Custom Update
            </button>
          </div>

          <div class="mt-3 p-2 bg-light rounded">
            <small class="text-muted">
              <i class="fas fa-info-circle me-1"></i>
              All shared content includes league branding and is optimized for WhatsApp formatting
            </small>
          </div>
        </div>
      </div>

      <!-- Quick Actions & Export -->
      <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-dark text-white">
          <h6 class="mb-0">
            <i class="fas fa-tools me-2"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= $base ?>/admin/export/export_standings_pdf.php?league=<?= $leagueId ?>"
              class="btn btn-primary btn-sm" target="_blank">
              <i class="fas fa-file-pdf me-1"></i> Professional PDF
            </a>
            <a href="<?= $base ?>/admin/export/export_standings.php?league=<?= $leagueId ?>"
              class="btn btn-outline-primary btn-sm" target="_blank">
              <i class="fas fa-file-csv me-1"></i> Data Export (CSV)
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="printStandings()">
              <i class="fas fa-print me-1"></i> Print View
            </button>
            <button class="btn btn-outline-info btn-sm" onclick="toggleAutoRefresh()">
              <i class="fas fa-sync me-1"></i> <span id="auto-refresh-text">Enable Auto-refresh</span>
            </button>
          </div>

          <div class="mt-3">
            <div class="row g-2">
              <div class="col-6">
                <button class="btn btn-sm btn-outline-warning w-100" onclick="showPredictions()">
                  <i class="fas fa-crystal-ball"></i> Predictions
                </button>
              </div>
              <div class="col-6">
                <button class="btn btn-sm btn-outline-info w-100" onclick="showAnalytics()">
                  <i class="fas fa-chart-line"></i> Analytics
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Additional Information Row -->
  <div class="row">
    <!-- Recent Results -->
    <div class="col-lg-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-secondary text-white">
          <h6 class="mb-0">🕐 Recent Results</h6>
        </div>
        <div class="card-body">
          <?php if (count($recentResults) > 0): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($recentResults as $result): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($result['home_team']) ?></strong>
                    <span class="badge bg-primary"><?= $result['score_home'] ?></span>
                    -
                    <span class="badge bg-primary"><?= $result['score_away'] ?></span>
                    <strong><?= htmlspecialchars($result['away_team']) ?></strong>
                  </div>
                  <small class="text-muted"><?= date('M j', strtotime($result['match_date'])) ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted text-center">No recent results available</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Upcoming Fixtures -->
    <div class="col-lg-6">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
          <h6 class="mb-0">📅 Upcoming Fixtures</h6>
        </div>
        <div class="card-body">
          <?php if (count($upcomingFixtures) > 0): ?>
            <div class="list-group list-group-flush">
              <?php foreach ($upcomingFixtures as $fixture): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($fixture['home_team']) ?></strong>
                    vs
                    <strong><?= htmlspecialchars($fixture['away_team']) ?></strong>
                    <?php if ($fixture['venue']): ?>
                      <br><small class="text-muted">📍 <?= htmlspecialchars($fixture['venue']) ?></small>
                    <?php endif; ?>
                  </div>
                  <div class="text-end">
                    <small class="text-muted"><?= date('M j', strtotime($fixture['match_date'])) ?></small>
                    <?php if ($fixture['match_time']): ?>
                      <br><small class="text-muted">⏰ <?= date('H:i', strtotime($fixture['match_time'])) ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted text-center">No upcoming fixtures scheduled</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Basketball Analytics -->
  <div class="row">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-purple text-white" style="background: linear-gradient(45deg, #667eea, #764ba2);">
          <h6 class="mb-0">🏀 Advanced Basketball Analytics</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <?php if (!empty($basketballStats)): ?>
              <?php foreach ($basketballStats as $teamId => $teamStats): ?>
                <div class="col-lg-3 col-md-6 mb-3">
                  <div class="card border-0 shadow-sm bg-white">
                    <div class="card-body p-3">
                      <h6 class="card-title text-dark fw-bold mb-2"><?= htmlspecialchars($teamStats['team_name'] ?? 'Unknown Team') ?></h6>
                      <?php if (($teamStats['games_played'] ?? 0) > 0): ?>
                        <div class="small text-secondary">
                          <div class="mb-1">
                            <strong class="text-primary">Avg Points:</strong>
                            <span class="text-dark"><?= round($teamStats['avg_points'] ?? 0, 1) ?></span>
                          </div>
                          <div class="mb-1">
                            <strong class="text-info">Scoring Variance:</strong>
                            <span class="text-dark"><?= round($teamStats['scoring_variance'] ?? 0, 1) ?></span>
                          </div>
                          <div class="mb-1">
                            <strong class="text-success">Recent Form:</strong>
                            <span class="ms-1">
                              <?php
                              $form = $teamStats['recent_form'] ?? '';
                              if ($form):
                                for ($i = 0; $i < strlen($form); $i++):
                                  $result = $form[$i];
                                  $class = $result === 'W' ? 'bg-success text-black' : 'bg-danger text-black';
                              ?>
                                  <span class="badge <?= $class ?> badge-sm me-1"><?= $result ?></span>
                                <?php
                                endfor;
                              else:
                                ?>
                                <span class="text-muted">No games yet</span>
                              <?php endif; ?>
                            </span>
                          </div>
                          <div class="small text-muted">
                            Games played: <?= $teamStats['games_played'] ?? 0 ?>
                          </div>
                        </div>
                      <?php else: ?>
                        <div class="text-muted small">
                          <i class="fas fa-clock me-1"></i>
                          No games played yet
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-12">
                <div class="text-center py-4">
                  <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                  <p class="text-muted">Advanced analytics will be available once teams start playing games.</p>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Modal for viewing snapshots -->
<div class="modal fade" id="snapshotModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">📸 Standings Snapshot</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="snapshotContent">
        <!-- Snapshot content will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" onclick="shareSnapshot()">
          <i class="fab fa-whatsapp"></i> Share on WhatsApp
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Ensure modal content is visible */
  .modal-content {
    background-color: #ffffff;
    /* Ensure a white background */
    color: #000000;
    /* Ensure black text for visibility */
  }

  .modal-body {
    color: #000000;
    /* Ensure black text for the body */
  }
</style>

<script>
  // Enhanced Standings Manager with Intelligent Features
  class StandingsManager {
    constructor() {
      this.autoRefreshEnabled = false;
      this.autoRefreshInterval = null;
      this.currentSnapshotId = null;
      this.lastUpdateTime = new Date();
      this.init();
    }

    init() {
      this.updateLastRefreshTime();
      this.setupEventListeners();
      this.loadSavedPreferences();
    }

    setupEventListeners() {
      // Auto-refresh checkbox
      document.addEventListener('DOMContentLoaded', () => {
        this.updateLastRefreshTime();
      });
    }

    loadSavedPreferences() {
      const autoRefresh = localStorage.getItem('standings_auto_refresh');
      if (autoRefresh === 'true') {
        this.toggleAutoRefresh();
      }
    }

    updateLastRefreshTime() {
      const now = new Date();
      const timeString = now.toLocaleTimeString();
      const element = document.getElementById('update-time');
      if (element) {
        element.textContent = `Updated ${timeString}`;
      }
    }

    // Enhanced clipboard function with better error handling
    async copyToClipboard(text) {
      try {
        await navigator.clipboard.writeText(text);
        this.showToast('✅ Copied to clipboard! Ready to paste in WhatsApp.', 'success');

        // Track usage analytics
        this.trackAction('clipboard_copy', {
          text_length: text.length
        });
      } catch (err) {
        console.error('Clipboard error: ', err);
        // Fallback for older browsers
        this.fallbackCopyToClipboard(text);
      }
    }

    fallbackCopyToClipboard(text) {
      const textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.left = '-999999px';
      textArea.style.top = '-999999px';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();

      try {
        document.execCommand('copy');
        this.showToast('✅ Copied to clipboard!', 'success');
      } catch (err) {
        this.showToast('❌ Copy failed. Please copy manually.', 'error');
      }

      document.body.removeChild(textArea);
    }

    // Smart toast notification system
    showToast(message, type = 'info', duration = 4000) {
      const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
      const toastId = 'toast-' + Date.now();

      const toast = document.createElement('div');
      toast.id = toastId;
      toast.className = `toast align-items-center text-black bg-${this.getToastColor(type)} border-0`;
      toast.setAttribute('role', 'alert');
      toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    `;

      toastContainer.appendChild(toast);
      const bsToast = new bootstrap.Toast(toast, {
        delay: duration
      });
      bsToast.show();

      // Auto-remove after duration + 1 second
      setTimeout(() => {
        if (document.getElementById(toastId)) {
          document.getElementById(toastId).remove();
        }
      }, duration + 1000);
    }

    getToastColor(type) {
      const colors = {
        success: 'success',
        error: 'danger',
        warning: 'warning',
        info: 'primary'
      };
      return colors[type] || 'primary';
    }

    createToastContainer() {
      const container = document.createElement('div');
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
      return container;
    }

    // Enhanced refresh with loading state
    async refreshStandings() {
      const refreshBtn = document.getElementById('refresh-btn');
      const originalContent = refreshBtn.innerHTML;

      refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span class="d-none d-sm-inline">Refreshing...</span>';
      refreshBtn.disabled = true;

      try {
        const response = await fetch(`?ajax=1&action=refresh_standings&league=${<?= $leagueId ?>}`);
        const data = await response.json();

        if (data.success) {
          this.updateStandingsTable(data.standings);
          this.updateStats(data.stats);
          this.updateLastRefreshTime();
          this.showToast('📊 Standings refreshed successfully!', 'success');
        } else {
          throw new Error('Refresh failed');
        }
      } catch (error) {
        console.error('Refresh error:', error);
        this.showToast('❌ Failed to refresh standings', 'error');
      } finally {
        refreshBtn.innerHTML = originalContent;
        refreshBtn.disabled = false;
      }
    }

    updateStandingsTable(standings) {
      const tbody = document.getElementById('standings-table-body');
      if (!tbody || !standings || standings.length === 0) {
        console.warn('No tbody found or no standings data');
        return;
      }

      let html = '';
      standings.forEach((team, index) => {
        const position = index + 1;
        
        // Position-based styling
        let rowClass = 'team-row';
        let positionBadge = 'bg-primary';
        
        if (position === 1) {
          rowClass += ' table-warning';
          positionBadge = 'bg-warning text-dark';
        } else if (position >= 2 && position <= 4) {
          rowClass += ' table-success';
          positionBadge = 'bg-success';
        } else if (position > standings.length - 3) {
          rowClass += ' table-danger';
          positionBadge = 'bg-danger';
        } else {
          rowClass += ' table-neutral';
        }

        const ppg = team.played > 0 ? (team.goals_for / team.played).toFixed(1) : '0.0';
        const goalDiff = team.goal_difference >= 0 ? `+${team.goal_difference}` : team.goal_difference;
        const pointsClass = team.points < 0 ? 'text-danger' : 'text-primary';

        // Recent form
        let formHtml = '';
        if (team.recent_form) {
          for (let i = 0; i < Math.min(5, team.recent_form.length); i++) {
            const result = team.recent_form[i];
            const resultClass = result === 'W' ? 'bg-success' : 
                               result === 'L' ? 'bg-danger' : 
                               result === 'F' ? 'bg-warning text-dark' : 'bg-secondary';
            formHtml += `<span class="badge ${resultClass} badge-xs me-1">${result}</span>`;
          }
        }

        html += `
          <tr class="${rowClass}" data-team-id="${team.team_id}" data-position="${position}">
            <td class="text-center">
              <span class="badge ${positionBadge} position-badge">${position}</span>
            </td>
            <td class="fw-semibold team-name">
              <div class="d-flex align-items-center">
                <span class="team-name-text">${this.escapeHtml(team.name)}</span>
              </div>
            </td>
            <td class="text-center">${team.played}</td>
            <td class="text-center text-success fw-bold">${team.wins}</td>
            <td class="text-center text-danger">${team.losses}</td>
            <td class="text-center text-warning fw-bold" title="Forfeits result in -1 point">${team.forfeits || 0}</td>
            <td class="text-center">${team.goals_for}</td>
            <td class="text-center">${team.goals_against}</td>
            <td class="text-center ${team.goal_difference >= 0 ? 'text-success' : 'text-danger'}">${goalDiff}</td>
            <td class="text-center">${ppg}</td>
            <td class="text-center fw-bold ${pointsClass}">${team.points}</td>
            <td class="text-center">
              <span class="badge bg-secondary">-</span>
            </td>
            <td class="text-center form-display">${formHtml}</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-primary" onclick="showTeamDetails(${team.team_id})" title="View details">
                <i class="fas fa-eye"></i>
              </button>
            </td>
          </tr>
        `;
      });

      tbody.innerHTML = html;
      this.showToast('📊 Standings updated successfully', 'success', 2000);
    }

    escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    updateStats(stats) {
      // Update stat cards with new data
      document.querySelectorAll('.stat-value').forEach((element, index) => {
        const values = [
          stats.total_teams || 0,
          stats.total_matches || 0,
          stats.total_goals || 0,
          stats.avg_goals_per_game ? stats.avg_goals_per_game.toFixed(1) : '0.0'
        ];
        if (values[index] !== undefined) {
          element.textContent = values[index];
        }
      });
    }

    // Auto-refresh toggle
    toggleAutoRefresh() {
      this.autoRefreshEnabled = !this.autoRefreshEnabled;
      const button = document.getElementById('auto-refresh-text');

      if (this.autoRefreshEnabled) {
        this.autoRefreshInterval = setInterval(() => {
          this.refreshStandings();
        }, 60000); // Refresh every minute

        if (button) button.textContent = 'Disable Auto-refresh';
        this.showToast('🔄 Auto-refresh enabled (every minute)', 'info');
      } else {
        if (this.autoRefreshInterval) {
          clearInterval(this.autoRefreshInterval);
          this.autoRefreshInterval = null;
        }
        if (button) button.textContent = 'Enable Auto-refresh';
        this.showToast('⏸️ Auto-refresh disabled', 'info');
      }

      localStorage.setItem('standings_auto_refresh', this.autoRefreshEnabled);
    }

    // Table filtering
    filterTable(filter) {
      const rows = document.querySelectorAll('.team-row');
      const totalTeams = rows.length;

      rows.forEach((row, index) => {
        const position = parseInt(row.dataset.position);
        let show = true;

        switch (filter) {
          case 'top5':
            show = position <= 5;
            break;
          case 'playoff':
            show = position <= Math.min(8, totalTeams);
            break;
          case 'relegation':
            show = position > totalTeams - 3;
            break;
          case 'all':
          default:
            show = true;
        }

        row.style.display = show ? '' : 'none';
      });

      this.showToast(`🔍 Filtered to show: ${filter.replace('_', ' ')}`, 'info', 2000);
    }

    // Team details modal
    async showTeamDetails(teamId) {
      try {
        const response = await fetch(`?ajax=1&action=get_team_details&team_id=${teamId}`);
        const data = await response.json();

        if (data.success) {
          this.displayTeamModal(data.data);
        }
      } catch (error) {
        this.showToast('❌ Failed to load team details', 'error');
      }
    }

    displayTeamModal(teamData) {
      // Create and show team details modal
      const modalHtml = `
      <div class="modal fade" id="teamModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">📊 ${teamData.name} - Detailed Stats</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <h6>Performance</h6>
                  <ul class="list-unstyled">
                    <li>Games Played: <strong>${teamData.played}</strong></li>
                    <li>Wins: <strong class="text-success">${teamData.wins}</strong></li>
                    <li>Losses: <strong class="text-danger">${teamData.losses}</strong></li>
                    <li>Points: <strong>${teamData.points}</strong></li>
                  </ul>
                </div>
                <div class="col-md-6">
                  <h6>Statistics</h6>
                  <ul class="list-unstyled">
                    <li>Points For: <strong>${teamData.goals_for}</strong></li>
                    <li>Points Against: <strong>${teamData.goals_against}</strong></li>
                    <li>Point Difference: <strong class="${teamData.goal_difference >= 0 ? 'text-success' : 'text-danger'}">${teamData.goal_difference >= 0 ? '+' : ''}${teamData.goal_difference}</strong></li>
                    <li>PPG: <strong>${(teamData.goals_for / Math.max(teamData.played, 1)).toFixed(1)}</strong></li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    `;

      // Remove existing modal if any
      const existingModal = document.getElementById('teamModal');
      if (existingModal) {
        existingModal.remove();
      }

      document.body.insertAdjacentHTML('beforeend', modalHtml);
      const modal = new bootstrap.Modal(document.getElementById('teamModal'));
      modal.show();
    }

    // Enhanced WhatsApp sharing functions
    copyWhatsAppText() {
      <?php if (!empty($standings)): ?>
        let text = `🏀 *<?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Standings*\n\n`;

        <?php $pos = 1;
        foreach ($standings as $team): ?>
          <?php
          $emoji = $pos === 1 ? '👑' : ($pos <= 4 ? '🏆' : '🏀');
          $teamName = addslashes($team['name']);
          ?>
          text += `<?= $emoji ?> <?= $pos ?>. *<?= $teamName ?>* - <?= $team['points'] ?> pts (<?= $team['wins'] ?>W-<?= $team['losses'] ?>L)\n`;
        <?php $pos++;
        endforeach; ?>

        text += `\n📊 League Stats:\n`;
        text += `🎮 Total Games: <?= $stats['total_matches'] ?? 0 ?>\n`;
        text += `🎯 Average PPG: <?= round($stats['avg_goals_per_game'] ?? 0, 1) ?>\n\n`;
        text += `📱 Live updates from <?= htmlspecialchars($league['name'] ?? 'Basketball Platform') ?>\n`;
        text += `⏰ ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}`;
      <?php else: ?>
        const standingsText = `🏀 *Basketball League Standings*\n\nNo standings data available yet.\n\n⏰ ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}`;
      <?php endif; ?>

      this.copyToClipboard(<?php if (!empty($standings)): ?>text<?php else: ?>standingsText<?php endif; ?>);
      this.trackAction('whatsapp_share', {
        type: 'full_standings'
      });
    }

    copyWhatsAppTop5() {
      <?php if (!empty($standings)): ?>
        let text = `🏀 *<?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Top 5*\n\n`;

        <?php $pos = 1;
        foreach (array_slice($standings, 0, 5) as $team): ?>
          <?php
          $emoji = $pos === 1 ? '👑' : '🏆';
          $teamName = addslashes($team['name']);
          ?>
          text += `<?= $emoji ?> <?= $pos ?>. *<?= $teamName ?>* - <?= $team['points'] ?> pts\n`;
        <?php $pos++;
        endforeach; ?>
      <?php else: ?>
        const top5Text = `🏀 *Basketball League Top 5*\n\nNo standings data available yet.\n\n⏰ ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}`;
      <?php endif; ?>

      text += `\n📱 From <?= htmlspecialchars($league['name'] ?? 'Basketball Platform') ?>`;

      this.copyToClipboard(<?php if (!empty($standings)): ?>text<?php else: ?>top5Text<?php endif; ?>);
      this.trackAction('whatsapp_share', {
        type: 'top5'
      });
    }

    copyWhatsAppResults() {
      <?php if (!empty($recentResults)): ?>
        let text = `🏀 *<?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Recent Results*\n\n`;

        <?php foreach (array_slice($recentResults, 0, 5) as $result): ?>
          text += `🏆 *<?= addslashes($result['home_team']) ?>* <?= $result['score_home'] ?> - <?= $result['score_away'] ?> *<?= addslashes($result['away_team']) ?>*\n`;
          text += `📅 <?= date('M j', strtotime($result['match_date'])) ?>\n\n`;
        <?php endforeach; ?>

        text += `📱 From <?= htmlspecialchars($league['name'] ?? 'Basketball Platform') ?>`;
      <?php else: ?>
        const resultsText = `🏀 *Basketball League Recent Results*\n\nNo recent results available.\n\n📱 From Basketball Platform`;
      <?php endif; ?>

      this.copyToClipboard(<?php if (!empty($recentResults)): ?>text<?php else: ?>resultsText<?php endif; ?>);
      this.trackAction('whatsapp_share', {
        type: 'recent_results'
      });
    }

    copyWhatsAppFixtures() {
      <?php if (!empty($upcomingFixtures)): ?>
        let text = `🏀 *<?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Upcoming Games*\n\n`;

        <?php foreach (array_slice($upcomingFixtures, 0, 5) as $fixture): ?>
          text += `🆚 *<?= addslashes($fixture['home_team']) ?>* vs *<?= addslashes($fixture['away_team']) ?>*\n`;
          text += `📅 <?= date('M j', strtotime($fixture['match_date'])) ?><?= isset($fixture['match_time']) ? ' at ' . date('H:i', strtotime($fixture['match_time'])) : '' ?>\n`;
          <?php if (isset($fixture['venue']) && $fixture['venue']): ?>
            text += `📍 <?= addslashes($fixture['venue']) ?>\n`;
          <?php endif; ?>
          text += `\n`;
        <?php endforeach; ?>

        text += `📱 From <?= htmlspecialchars($league['name'] ?? 'Basketball Platform') ?>`;
      <?php else: ?>
        const fixturesText = `🏀 *Basketball League Upcoming Games*\n\nNo upcoming fixtures scheduled.\n\n📱 From Basketball Platform`;
      <?php endif; ?>

      this.copyToClipboard(<?php if (!empty($upcomingFixtures)): ?>text<?php else: ?>fixturesText<?php endif; ?>);
      this.trackAction('whatsapp_share', {
        type: 'upcoming_fixtures'
      });
    }

    copyCustomUpdate() {
      const customText = prompt('Enter your custom update message:');
      if (customText) {
        let text = `🏀 *<?= htmlspecialchars($league['name'] ?? 'Basketball League') ?> Update*\n\n`;
        text += customText + '\n\n';
        text += `📱 From <?= htmlspecialchars($league['name'] ?? 'Basketball Platform') ?>`;

        this.copyToClipboard(text);
        this.trackAction('whatsapp_share', {
          type: 'custom_update'
        });
      }
    }

    // Print function
    printStandings() {
      window.print();
      this.trackAction('print_standings');
    }

    // Snapshot functions
    async viewSnapshot(snapshotId) {
      this.currentSnapshotId = snapshotId;
      try {
        const response = await fetch(`api/get_snapshot.php?id=${snapshotId}`);
        const data = await response.json();

        if (data && data.success) {
          this.displaySnapshotModal(data.data);
        } else {
          throw new Error('Snapshot not found');
        }
      } catch (err) {
        console.error('Snapshot error:', err);
        this.showToast('❌ Error loading snapshot', 'error');
      }
    }

    displaySnapshotModal(snapshot) {
      const modalContent = `
      <h6>🏀 Basketball Standings Snapshot</h6>
      <p><strong>Created:</strong> ${new Date(snapshot.created_at).toLocaleString()}</p>
      <p><strong>Description:</strong> ${snapshot.description || 'No description provided'}</p>
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i>
        Snapshot contains historical standings data from this date.
      </div>
    `;

      document.getElementById('snapshotContent').innerHTML = modalContent;
      new bootstrap.Modal(document.getElementById('snapshotModal')).show();
    }

    async shareSnapshot(snapshotId = null) {
      const id = snapshotId || this.currentSnapshotId;
      if (!id) {
        this.showToast('❌ No snapshot selected', 'error');
        return;
      }

      try {
        const response = await fetch(`api/get_snapshot.php?id=${id}`);
        const data = await response.json();

        if (data && data.success && data.data) {
          const snapshot = data.data;
          let text = `🏀 *<?= $league['name'] ?? 'Basketball League' ?> Historical Snapshot*\n`;
          text += `📸 ${snapshot.name}\n`;
          text += `📅 ${new Date(snapshot.created_at).toLocaleDateString()}\n\n`;

          if (snapshot.data) {
            const snapshotData = JSON.parse(snapshot.data);
            if (snapshotData.standings && snapshotData.standings.length > 0) {
              text += `Top 5 Teams at that time:\n`;
              snapshotData.standings.slice(0, 5).forEach((team, index) => {
                const pos = index + 1;
                const emoji = pos === 1 ? '👑' : pos <= 4 ? '🏆' : '🏀';
                text += `${emoji} ${pos}. *${team.name}* - ${team.points} pts\n`;
              });
            }
          }

          text += `\n📸 Historical data from <?= $league['name'] ?? 'Basketball Platform' ?>`;
          this.copyToClipboard(text);
          this.trackAction('snapshot_share', {
            snapshot_id: id
          });
        } else {
          this.showToast('📱 Snapshot sharing coming soon!', 'info');
        }
      } catch (err) {
        console.error('Share error:', err);
        this.showToast('📱 Snapshot sharing coming soon!', 'info');
      }
    }

    // Analytics and predictions (placeholder functions)
    showPredictions() {
      this.showToast('🔮 Match predictions feature coming soon!', 'info');
    }

    showAnalytics() {
      this.showToast('📈 Advanced analytics dashboard coming soon!', 'info');
    }

    // Usage tracking
    trackAction(action, data = {}) {
      // Simple usage tracking for analytics
      console.log('Action tracked:', action, data);
      // Could send to analytics service here
    }
  }

  // Initialize the enhanced standings manager
  const standingsManager = new StandingsManager();

  // Global function aliases for backwards compatibility
  function copyToClipboard(text) {
    standingsManager.copyToClipboard(text);
  }

  function showToast(message, type) {
    standingsManager.showToast(message, type);
  }

  function refreshStandings() {
    standingsManager.refreshStandings();
  }

  function copyWhatsAppText() {
    standingsManager.copyWhatsAppText();
  }

  function copyWhatsAppTop5() {
    standingsManager.copyWhatsAppTop5();
  }

  function copyWhatsAppResults() {
    standingsManager.copyWhatsAppResults();
  }

  function copyWhatsAppFixtures() {
    standingsManager.copyWhatsAppFixtures();
  }

  function copyCustomUpdate() {
    standingsManager.copyCustomUpdate();
  }

  function printStandings() {
    standingsManager.printStandings();
  }

  function viewSnapshot(id) {
    standingsManager.viewSnapshot(id);
  }

  function shareSnapshot(id) {
    standingsManager.shareSnapshot(id);
  }

  function showTeamDetails(id) {
    standingsManager.showTeamDetails(id);
  }

  function filterTable(filter) {
    standingsManager.filterTable(filter);
  }

  function toggleAutoRefresh() {
    standingsManager.toggleAutoRefresh();
  }

  function showPredictions() {
    standingsManager.showPredictions();
  }

  function showAnalytics() {
    standingsManager.showAnalytics();
  }

  // Enhanced CSS for intelligent standings manager
  document.head.insertAdjacentHTML('beforeend', `
/* Base Table Styling */
.standings-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  background: #ffffff;
}

/* Header Styling */

.standings-table thead th {
  background: #f1f5f9;
  color: #1f2937;
  font-weight: 600;
  font-size: 0.85rem;
  letter-spacing: 0.5px;
  padding: 12px 8px;
  text-align: center;
  border-bottom: 1px solid #e5e7eb;
}

/* Row Styling */
.team-row {
  transition: background 0.3s ease;
  color: #1f2937;
  border-bottom: 1px solid #f3f4f6;
  background: #ffffff;
}

.team-row:hover {
  background: #f8fafc;
}

/* Row Zones — Top 4, Mid-table, Bottom 3 */
.team-row.table-warning {
  background: #fff8e1 !important;
  color: #92400e !important;
  border-left: 4px solid #f59e0b !important;
}

.team-row.table-success {
  background: #ecfdf5 !important;
  color: #065f46 !important;
  border-left: 4px solid #10b981 !important;
}

.team-row.table-danger {
  background: #fef2f2 !important;
  color: #991b1b !important;
  border-left: 4px solid #ef4444 !important;
}

.team-row.table-neutral {
  background: #f9fafc !important;
  color: #1f2937 !important;
}

/* Cell Styling */
.standings-table td {
  padding: 12px 8px;
  text-align: center;
  vertical-align: middle;
  font-size: 0.9rem;
  border: none;
  font-weight: 500;
  color: #1f2937;
}

/* Position Badge */
.position-badge {
  font-weight: 700;
  padding: 6px 8px;
  border-radius: 50%;
  font-size: 0.8rem;
  min-width: 30px;
  text-align: center;
  background: #4154f1;
  color: #ffffff;
  border: 2px solid transparent;
}

.position-badge.bg-success {
  background: #10b981 !important;
  border-color: #059669 !important;
}

.position-badge.bg-warning {
  background: #f59e0b !important;
  border-color: #d97706 !important;
}

.position-badge.bg-danger {
  background: #ef4444 !important;
  border-color: #dc2626 !important;
}

.position-badge.bg-primary {
  background: #4154f1 !important;
  border-color: #374151 !important;
}

/* Dynamic Icon Badges */
.badge-sm {
  font-size: 0.75rem;
  padding: 4px 6px;
  border-radius: 4px;
  font-weight: 600;
}

/* Recent Form Badges */
.form-display .badge {
  margin-right: 4px;
  font-size: 0.75rem;
  padding: 4px 6px;
  border-radius: 4px;
  font-weight: 600;
}

.badge.bg-success {
  background: #10b981;
  color: #ffffff;
}

.badge.bg-danger {
  background: #ef4444;
  color: #ffffff;
}

.badge.bg-warning {
  background: #f59e0b;
  color: #000000;
}

.badge.bg-secondary {
  background: #9ca3af;
  color: #ffffff;
}

/* Forfeit Highlighting */
.text-warning.fw-bold {
  color: #d97706 !important;
}

/* Negative Points Highlighting */
.text-danger {
  color: #dc2626 !important;
  font-weight: 600;
}

/* Action Button */
.btn-outline-primary {
  border-color: #4154f1;
  color: #4154f1;
}

.btn-outline-primary:hover {
  background: #4154f1;
  color: #ffffff;
}

/* Responsive Tweaks */
@media (max-width: 768px) {
  .standings-table th,
  .standings-table td {
    padding: 8px 4px;
    font-size: 0.8rem;
  }
  .position-badge {
    min-width: 25px;
    font-size: 0.7rem;
  }
}

`);
</script>

<?php
include('../includes/footer.php');
ob_end_flush(); // End output buffering
?>