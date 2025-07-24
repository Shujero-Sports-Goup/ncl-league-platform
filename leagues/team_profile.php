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
  <title><?= htmlspecialchars($teamName) ?> — Profile</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="<?= $base ?>/assets/css/style.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" />
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= $base ?>/index.php"> League Home</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/leagues/teams.php">Teams</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/leagues/fixtures.php">Fixtures</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>/leagues/standings.php">Standings</a></li>
      </ul>
      <ul class="navbar-nav">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- 🎖 Hero Section -->
<div class="hero-banner-teams d-flex flex-column justify-content-center align-items-center text-center" data-aos="fade-down">
  <?php if ($hasLogo): ?>
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= $teamName ?> Logo"
         class="team-image mb-3 shadow rounded-circle border border-light"
         style="width: 120px; height: 120px; object-fit: cover;" />
  <?php else: ?>
    <div class="team-avatar mb-3 d-flex align-items-center justify-content-center text-white fw-bold shadow rounded-circle"
         style="width: 120px; height: 120px; background-color: #6c757d; font-size: 2rem;">
      <?= $initials ?>
    </div>
  <?php endif; ?>
  <h1 class="hero-heading"><?= htmlspecialchars($teamName) ?></h1>
  <p class="hero-subtext">Coach: <span class="coach-name"><?= htmlspecialchars($coach) ?></span></p>
</div>

<div class="container my-5">
  <ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bio">Team</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#roster">Roster</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#schedule">Schedule</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#match-history">Match History</button></li>
  </ul>

  <div class="tab-content mb-5">
    <!-- Team Bio -->
    <div class="tab-pane fade show active" id="bio">
      <div class="card shadow-soft mb-4" data-aos="fade-up">
        <div class="card-body">
          <h4 class="text-primary fw-bold mb-2">Team Biography</h4>
          <p><?= nl2br(htmlspecialchars($bio)) ?></p>
        </div>
      </div>
    </div>

    <!-- Roster -->
    <div class="tab-pane fade" id="roster">
      <div class="card shadow-soft mb-4" data-aos="fade-up">
        <div class="card-header bg-primary text-white">Player Roster</div>
        <div class="card-body p-0">
          <?php if ($roster->num_rows): ?>
            <ul class="list-group list-group-flush">
              <?php while ($p = $roster->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between">
                  <?= htmlspecialchars($p['name']) ?>
                  <span class="badge bg-secondary"><?= htmlspecialchars($p['position'] ?: '—') ?></span>
                </li>
              <?php endwhile; ?>
            </ul>
          <?php else: ?>
            <div class="p-3 text-muted text-center">No players registered.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Fixtures -->
    <div class="tab-pane fade" id="schedule">
      <div class="card shadow-soft mb-4" data-aos="fade-up">
        <div class="card-header bg-secondary text-white">
          Upcoming Fixtures
        </div>
        <div class="card-body p-0">
          <?php if ($schedule->num_rows): ?>
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Fixture</th>
                  <th>Venue</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($m = $schedule->fetch_assoc()): ?>
                  <tr>
                    <td><?= date("d M Y", strtotime($m['match_date'])) ?></td>
                    <td><?= date("H:i", strtotime($m['match_time'])) ?></td>
                    <td>
                      <?= htmlspecialchars($m['home_team']) ?> vs <?= htmlspecialchars($m['away_team']) ?>
                    </td>
                    <td><?= htmlspecialchars($m['venue']) ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="p-3 text-muted text-center">
              No upcoming fixtures scheduled.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 📚 Match History -->
    <div class="tab-pane fade" id="match-history">
      <div class="card shadow-soft mb-4" data-aos="fade-up">
        <div class="card-header bg-success text-white">Match History</div>
        <div class="card-body p-0"> <?php if ($results->num_rows): ?> <table class="table table-hover table-standings mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Match</th>
                  <th>Score</th>
                  <th>Venue</th>
                </tr>
              </thead>
              <tbody> <?php while ($m = $results->fetch_assoc()):
                                        $isHome = ($m['home_team'] === $teamName);
                                        $score = "{$m['score_home']} - {$m['score_away']}";
                                        $fixture = "{$m['home_team']} vs {$m['away_team']}";
                    ?>
                <tr>
                  <td><?= date("d M Y", strtotime($m['match_date'])) ?></td>
                  <td><?= htmlspecialchars($fixture) ?></td>
                  <td class="fw-bold"><?= $score ?></td>
                  <td><?= htmlspecialchars($m['venue']) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table> <?php else: ?> <div class="p-3 text-muted text-center">No past match results available yet.</div> <?php endif; ?> </div>
      </div>
    </div>

  </div>

  <!-- 🔙 Back Link -->
  <div class="text-center" data-aos="fade-in"> <a href="<?= $base ?>/leagues/teams.php" class="btn btn-outline-accent mt-3">← Back to Teams</a> </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({
      duration: 800,
      once: true
    });
  </script>
</body>

</html>