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

<section class="container py-5">
  <h2 class="text-center mb-4"><?= htmlspecialchars($leagueName) ?> – Standings</h2>

  <!-- Forfeit Rules Notice -->
  <div class="alert alert-info mb-4">
    <h6 class="alert-heading">📋 Scoring Rules:</h6>
    <ul class="mb-0">
      <li><strong>Win:</strong> 2 points</li>
      <li><strong>Loss:</strong> 1 point</li>
      <li><strong>Forfeit:</strong> -1 point (score of 0)</li>
    </ul>
  </div>

  <div class="table-responsive">
    <table class="table table-standings table-hover text-center shadow-sm">
      <thead class="table-light">
        <tr>
          <th>Rank</th>
          <th>Team</th>
          <th>GP</th>
          <th>W</th>
          <th>L</th>
          <th>F</th> <!-- Forfeit column -->
          <th>Pts</th>
        </tr>
      </thead>
      <tbody>
        <?php $rank = 1; foreach ($standings as $team): ?>
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

  <div class="text-center mt-4">
    <a href="home.php" class="btn btn-accent">⬅ Back to League Home</a>
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
</style>

<?php include('../includes/footer.php'); ?>