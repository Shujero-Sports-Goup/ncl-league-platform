<?php
session_start();
require_once(__DIR__ . '/../db_connect.php');

// Base URL of your app
$base = '/ncl-league-platform';

// 1. League details
$leagueId   = $_SESSION['league_id'] ?? 1;
$stmt       = $conn->prepare("SELECT name, logo_url FROM leagues WHERE league_id = ?");
$stmt->bind_param("i", $leagueId);
$stmt->execute();
$league     = $stmt->get_result()->fetch_assoc();
$stmt->close();

$leagueName    = $league['name'] ?? 'League';
$hasLeagueLogo = !empty($league['logo_url']);
if ($hasLeagueLogo) {
  // build root-relative path to uploaded logo
  $leagueLogo = $base . '/' . ltrim($league['logo_url'], '/');
}

// 2. All upcoming fixtures (instead of LIMIT 1)
$upcoming = $conn->prepare("
  SELECT f.match_date, f.match_time, f.venue,
         t1.name AS home, t2.name AS away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  WHERE f.league_id = ? AND f.status = 'upcoming'
  ORDER BY f.match_date, f.match_time
");
$upcoming->bind_param("i", $leagueId);
$upcoming->execute();
$upcomingRes    = $upcoming->get_result();
$hasUpcoming    = $upcomingRes && $upcomingRes->num_rows > 0;
$upcoming->close();

// 3. Recent results (only if no upcoming)
$recentResults = null;
if (! $hasUpcoming) {
  $recent = $conn->prepare("
    SELECT f.match_date, f.venue,
           t1.name AS home, t2.name AS away,
           mr.score_home, mr.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    LEFT JOIN match_results mr ON mr.fixture_id = f.fixture_id
    WHERE f.league_id = ? AND f.status = 'played' AND mr.cancelled_by_referee = 0
    ORDER BY f.match_date DESC
    LIMIT 5
  ");
  $recent->bind_param("i", $leagueId);
  $recent->execute();
  $recentResults = $recent->get_result();
  $recent->close();
}

// 4. Featured teams
$teamsStmt = $conn->prepare("SELECT team_id, name, logo_url FROM teams WHERE league_id = ? ORDER BY RAND()");
$teamsStmt->bind_param("i", $leagueId);
$teamsStmt->execute();
$allTeams = $teamsStmt->get_result();
$teamsStmt->close();

// chunk into slides of 3
$teamChunks = [];
while ($t = $allTeams->fetch_assoc()) {
  $teamChunks[] = $t;
}
$slides = array_chunk($teamChunks, 3);

include(__DIR__ . '/../includes/header.php');
include(__DIR__ . '/../includes/navbar.php');
?>

<!-- Hero Banner -->
<section class="league-hero d-flex align-items-center text-white text-center" style="background:#222;">
  <div class="container py-5" data-aos="zoom-in">
    <?php if ($hasLeagueLogo): ?>
      <img src="<?= htmlspecialchars($leagueLogo) ?>"
           alt="<?= htmlspecialchars($leagueName) ?> Logo"
           class="league-hero-logo mb-3"
           style="height:100px; object-fit:contain;" />
    <?php endif; ?>
    <h1 class="hero-heading display-4 fw-bold mb-2"><?= htmlspecialchars($leagueName) ?></h1>
    <p class="lead mb-0">
      Where grassroots passion meets professional execution — powered by purpose and the people.
    </p>
  </div>
</section>

<!-- Dashboard Cards -->
<section class="container my-5">
  <div class="row g-4">

    <!-- Next Fixture / Recent Results -->
    <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
      <div class="card border-0 shadow-lg h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-primary mb-4">Upcoming Fixtures</h5>
          <?php if ($hasUpcoming): ?>
            <div id="upcomingCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">
              <div class="carousel-inner">
                <?php $i = 0; while ($m = $upcomingRes->fetch_assoc()): ?>
                  <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
                    <strong><?= htmlspecialchars($m['home']) ?> vs <?= htmlspecialchars($m['away']) ?></strong><br>
                    <span class="badge bg-warning text-dark my-2">Upcoming</span><br>
                    <?= date("D, d M Y", strtotime($m['match_date'])) ?>
                    &ndash; <?= date("H:i", strtotime($m['match_time'])) ?><br>
                    <small class="text-muted"><?= htmlspecialchars($m['venue']) ?></small>
                  </div>
                <?php $i++; endwhile; ?>
              </div>
              <?php if ($upcomingRes->num_rows > 1): ?>
                <button class="carousel-control-prev" type="button"
                        data-bs-target="#upcomingCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button"
                        data-bs-target="#upcomingCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
              <?php endif; ?>
            </div>
          <?php elseif ($recentResults && $recentResults->num_rows): ?>
            <div id="recentCarousel" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner">
                <?php $i = 0; while ($m = $recentResults->fetch_assoc()): ?>
                  <div class="carousel-item <?= $i===0?'active':'' ?>">
                    <strong>
                      <?= htmlspecialchars($m['home']) ?> 
                      <?= $m['score_home'] ?? '-' ?> 
                      : <?= $m['score_away'] ?? '-' ?> 
                      <?= htmlspecialchars($m['away']) ?>
                    </strong><br>
                    <span class="badge bg-success my-2">Played</span><br>
                    <?= date("D, d M Y", strtotime($m['match_date'])) ?><br>
                    <small class="text-muted"><?= htmlspecialchars($m['venue']) ?></small>
                  </div>
                <?php $i++; endwhile; ?>
              </div>
              <?php if ($recentResults->num_rows > 1): ?>
                <button class="carousel-control-prev" type="button" data-bs-target="#recentCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#recentCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
              <?php endif; ?>
            </div>

          <?php else: ?>
            <p class="text-muted">No matches scheduled yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Featured Teams Showcase -->
    <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
      <div class="card border-0 shadow-lg h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-primary mb-4">Featured Teams Showcase</h5>
          <div id="teamsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
            <div class="carousel-inner">
              <?php foreach ($slides as $idx => $set): ?>
                <div class="carousel-item <?= $idx===0?'active':'' ?>">
                  <div class="row justify-content-center gx-3">
                    <?php foreach ($set as $team):
                      $name      = htmlspecialchars($team['name']);
                      $initials  = implode('', array_map(fn($w)=>strtoupper($w[0]), explode(' ',$name)));
                      $hasLogoT  = !empty($team['logo_url']);
                      $logoPathT = $hasLogoT ? "$base/" . ltrim($team['logo_url'], '/') : '';
                    ?>
                      <div class="col-4 d-flex flex-column align-items-center">
                        <a href="team_profile.php?team_id=<?= $team['team_id'] ?>" class="text-decoration-none">
                          <?php if ($hasLogoT): ?>
                            <img src="<?= htmlspecialchars($logoPathT) ?>"
                                 alt="<?= $name ?> Logo"
                                 class="mb-2 rounded-circle shadow"
                                 style="width:80px; height:80px; object-fit:cover;" />
                          <?php else: ?>
                            <div class="team-avatar mb-2 shadow rounded-circle
                                        d-flex align-items-center justify-content-center text-white fw-bold"
                                 style="width:80px; height:80px; background:#6c757d; font-size:1.2rem;">
                              <?= $initials ?>
                            </div>
                          <?php endif; ?>
                          <small class="d-block text-muted"><?= $name ?></small>
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if (count($slides) > 1): ?>
              <button class="carousel-control-prev" type="button" data-bs-target="#teamsCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
              </button>
              <button class="carousel-control-next" type="button" data-bs-target="#teamsCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- CTA Links -->
<section class="bg-light py-5 border-top">
  <div class="container text-center" data-aos="fade-up">
    <h5 class="text-muted mb-4">Navigate the Game</h5>
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <a href="fixtures.php" class="btn btn-outline-accent px-4">Fixtures</a>
      <a href="standings.php" class="btn btn-accent px-4">Standings</a>
      <a href="teams.php" class="btn btn-outline-accent px-4">Teams</a>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({ duration: 800, once: true });
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
