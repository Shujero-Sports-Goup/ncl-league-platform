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
<section class="league-hero d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 500px; position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('/ncl-league-platform/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-5" data-aos="zoom-in" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if ($hasLeagueLogo): ?>
                    <div class="mb-4">
                        <img src="<?= htmlspecialchars($leagueLogo) ?>"
                             alt="<?= htmlspecialchars($leagueName) ?> Logo"
                             class="league-hero-logo rounded-circle shadow-lg"
                             style="height: 120px; width: 120px; object-fit: cover; 
                                    border: 4px solid rgba(255, 255, 255, 0.3); 
                                    background: white; padding: 10px;" />
                    </div>
                <?php endif; ?>
                
                <h1 class="hero-heading display-2 fw-bold mb-4 text-white"><?= htmlspecialchars($leagueName) ?></h1>
                <p class="lead mb-4 text-white" style="font-size: 1.3rem; max-width: 600px; margin: 0 auto;">
                    Where grassroots passion meets professional execution — powered by Nukta League Management System.
                </p>
                
                <!-- Hero CTA Buttons -->
                <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                    <a href="fixtures.php" 
                       class="btn btn-light btn-lg px-4 py-3 fw-semibold"
                       style="border-radius: 25px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-calendar-alt me-2"></i>View Fixtures
                    </a>
                    <a href="standings.php" 
                       class="btn btn-outline-light btn-lg px-4 py-3 fw-semibold"
                       style="border-radius: 25px; text-transform: uppercase; letter-spacing: 0.5px; border-width: 2px;">
                        <i class="fas fa-trophy me-2"></i>League Standings
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Dashboard Cards -->
<section class="container my-5" style="margin-top: 4rem !important; margin-bottom: 4rem !important;">
    <div class="row justify-content-center mb-5">
        <div class="col-lg-8 text-center">
            <h2 class="fw-bold mb-3" style="color: #0000ff; font-size: 2.5rem;">League Dashboard</h2>
            <p class="text-muted lead">Stay updated with the latest matches, results, and team highlights</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- Next Fixture / Recent Results -->
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card border-0 shadow-lg h-100" 
                 style="border-radius: 20px; background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%); 
                        border: 1px solid rgba(0, 0, 255, 0.1) !important;">
                
                <!-- Card Header -->
                <div class="card-header border-0 text-center" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px 20px 0 0;">
                    <h5 class="card-title text-white mb-0 fw-bold py-2">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?= $hasUpcoming ? 'Upcoming Fixtures' : 'Recent Results' ?>
                    </h5>
                </div>

                <div class="card-body text-center p-4">
                    <?php if ($hasUpcoming): ?>
                        <div id="upcomingCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="6000">
                            <div class="carousel-inner">
                                <?php $i = 0; while ($m = $upcomingRes->fetch_assoc()): ?>
                                    <div class="carousel-item <?= $i===0 ? 'active' : '' ?>">
                                        <div class="fixture-card p-3" 
                                             style="background: rgba(0, 0, 255, 0.05); border-radius: 15px; border-left: 4px solid #0000ff;">
                                            <div class="teams-matchup mb-3">
                                                <h6 class="fw-bold mb-2" style="color: #0000ff; font-size: 1.1rem;">
                                                    <?= htmlspecialchars($m['home']) ?>
                                                    <span class="text-muted mx-2">VS</span>
                                                    <?= htmlspecialchars($m['away']) ?>
                                                </h6>
                                            </div>
                                            
                                            <span class="badge px-3 py-2 mb-3" 
                                                  style="background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; font-size: 0.9rem;">
                                                <i class="fas fa-clock me-1"></i>Upcoming
                                            </span>
                                            
                                            <div class="match-details">
                                                <p class="mb-2 fw-semibold" style="color: #0000ff;">
                                                    <i class="fas fa-calendar me-2"></i>
                                                    <?= date("D, d M Y", strtotime($m['match_date'])) ?>
                                                </p>
                                                <p class="mb-2 fw-semibold" style="color: #0000ff;">
                                                    <i class="fas fa-clock me-2"></i>
                                                    <?= date("H:i", strtotime($m['match_time'])) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= htmlspecialchars($m['venue']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php $i++; endwhile; ?>
                            </div>
                            <?php if ($upcomingRes->num_rows > 1): ?>
                                <button class="carousel-control-prev" type="button"
                                        data-bs-target="#upcomingCarousel" data-bs-slide="prev"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button"
                                        data-bs-target="#upcomingCarousel" data-bs-slide="next"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($recentResults && $recentResults->num_rows): ?>
                        <div id="recentCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php $i = 0; while ($m = $recentResults->fetch_assoc()): ?>
                                    <div class="carousel-item <?= $i===0?'active':'' ?>">
                                        <div class="result-card p-3" 
                                             style="background: rgba(0, 255, 0, 0.05); border-radius: 15px; border-left: 4px solid #28a745;">
                                            <div class="teams-score mb-3">
                                                <h6 class="fw-bold mb-2" style="color: #0000ff; font-size: 1.1rem;">
                                                    <?= htmlspecialchars($m['home']) ?> 
                                                    <span class="badge bg-primary mx-2"><?= $m['score_home'] ?? '-' ?></span>
                                                    <span class="text-muted">:</span>
                                                    <span class="badge bg-primary mx-2"><?= $m['score_away'] ?? '-' ?></span>
                                                    <?= htmlspecialchars($m['away']) ?>
                                                </h6>
                                            </div>
                                            
                                            <span class="badge px-3 py-2 mb-3" 
                                                  style="background: linear-gradient(135deg, #28a745, #20c997); color: white; font-size: 0.9rem;">
                                                <i class="fas fa-check-circle me-1"></i>Played
                                            </span>
                                            
                                            <div class="match-details">
                                                <p class="mb-2 fw-semibold" style="color: #0000ff;">
                                                    <i class="fas fa-calendar me-2"></i>
                                                    <?= date("D, d M Y", strtotime($m['match_date'])) ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= htmlspecialchars($m['venue']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php $i++; endwhile; ?>
                            </div>
                            <?php if ($recentResults->num_rows > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#recentCarousel" data-bs-slide="prev"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#recentCarousel" data-bs-slide="next"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-basketball-ball fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No matches scheduled yet</h5>
                            <p class="text-muted">Check back soon for upcoming fixtures!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Featured Teams Showcase -->
        <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
            <div class="card border-0 shadow-lg h-100" 
                 style="border-radius: 20px; background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%); 
                        border: 1px solid rgba(0, 0, 255, 0.1) !important;">
                
                <!-- Card Header -->
                <div class="card-header border-0 text-center" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px 20px 0 0;">
                    <h5 class="card-title text-white mb-0 fw-bold py-2">
                        <i class="fas fa-users me-2"></i>Featured Teams
                    </h5>
                </div>

                <div class="card-body text-center p-4">
                    <?php if (!empty($slides)): ?>
                        <div id="teamsCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000">
                            <div class="carousel-inner">
                                <?php foreach ($slides as $idx => $set): ?>
                                    <div class="carousel-item <?= $idx===0?'active':'' ?>">
                                        <div class="row justify-content-center g-3">
                                            <?php foreach ($set as $team):
                                                $name      = htmlspecialchars($team['name']);
                                                $initials  = implode('', array_map(fn($w)=>strtoupper($w[0]), explode(' ',$name)));
                                                $hasLogoT  = !empty($team['logo_url']);
                                                $logoPathT = $hasLogoT ? "$base/" . ltrim($team['logo_url'], '/') : '';
                                            ?>
                                                <div class="col-4 d-flex flex-column align-items-center">
                                                    <a href="team_profile.php?team_id=<?= $team['team_id'] ?>" 
                                                       class="text-decoration-none team-showcase-item"
                                                       style="transition: all 0.3s ease;"
                                                       onmouseover="this.style.transform='translateY(-5px)'"
                                                       onmouseout="this.style.transform='translateY(0)'">
                                                        
                                                        <?php if ($hasLogoT): ?>
                                                            <div class="team-logo-container mb-3">
                                                                <img src="<?= htmlspecialchars($logoPathT) ?>"
                                                                     alt="<?= $name ?> Logo"
                                                                     class="rounded-circle shadow-sm"
                                                                     style="width: 80px; height: 80px; object-fit: cover; 
                                                                            border: 3px solid rgba(0, 0, 255, 0.1);
                                                                            transition: all 0.3s ease;"
                                                                     onmouseover="this.style.borderColor='#0000ff'; this.style.transform='scale(1.05)'"
                                                                     onmouseout="this.style.borderColor='rgba(0, 0, 255, 0.1)'; this.style.transform='scale(1)'" />
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="team-avatar mb-3 shadow-sm rounded-circle
                                                                        d-flex align-items-center justify-content-center text-white fw-bold"
                                                                 style="width: 80px; height: 80px; 
                                                                        background: linear-gradient(135deg, #0000ff, #4169E1); 
                                                                        font-size: 1.2rem; border: 3px solid rgba(0, 0, 255, 0.1);
                                                                        transition: all 0.3s ease;"
                                                                 onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 8px 20px rgba(0, 0, 255, 0.3)'"
                                                                 onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 8px rgba(0, 0, 0, 0.1)'">
                                                                <?= $initials ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <small class="d-block fw-semibold" 
                                                               style="color: #0000ff; font-size: 0.85rem; line-height: 1.2;">
                                                            <?= $name ?>
                                                        </small>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($slides) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#teamsCarousel" data-bs-slide="prev"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#teamsCarousel" data-bs-slide="next"
                                        style="filter: invert(1);">
                                    <span class="carousel-control-next-icon"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No teams registered</h5>
                            <p class="text-muted">Teams will appear here once they join the league!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Navigation CTA Section -->
<section style="background: linear-gradient(135deg, rgba(0, 0, 255, 0.05), rgba(0, 0, 255, 0.02)); 
                border-top: 1px solid rgba(0, 0, 255, 0.1); padding: 80px 0;">
    <div class="container text-center" data-aos="fade-up">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <h3 class="fw-bold mb-3" style="color: #0000ff; font-size: 2.2rem;">Navigate the Game</h3>
                <p class="text-muted lead">Explore all aspects of the league with our comprehensive tools and insights</p>
            </div>
        </div>
        
        <div class="row g-4 justify-content-center">
            <!-- Fixtures Card -->
            <div class="col-lg-3 col-md-6">
                <div class="cta-card p-4 h-100" 
                     style="background: white; border-radius: 20px; border: 2px solid rgba(0, 0, 255, 0.1); 
                            transition: all 0.3s ease; text-decoration: none;"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#0000ff'; this.style.boxShadow='0 10px 30px rgba(0, 0, 255, 0.15)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(0, 0, 255, 0.1)'; this.style.boxShadow='none'">
                    <a href="fixtures.php" class="text-decoration-none">
                        <div class="cta-icon mb-3">
                            <i class="fas fa-calendar-alt fa-3x" style="color: #0000ff;"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: #0000ff;">Fixtures</h5>
                        <p class="text-muted small mb-3">View upcoming matches and game schedules</p>
                        <span class="btn btn-sm fw-semibold px-3" 
                              style="background: linear-gradient(135deg, #0000ff, #4169E1); color: white; border-radius: 20px;">
                            View Schedule
                        </span>
                    </a>
                </div>
            </div>

            <!-- Standings Card -->
            <div class="col-lg-3 col-md-6">
                <div class="cta-card p-4 h-100" 
                     style="background: white; border-radius: 20px; border: 2px solid rgba(0, 0, 255, 0.1); 
                            transition: all 0.3s ease; text-decoration: none;"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#0000ff'; this.style.boxShadow='0 10px 30px rgba(0, 0, 255, 0.15)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(0, 0, 255, 0.1)'; this.style.boxShadow='none'">
                    <a href="standings.php" class="text-decoration-none">
                        <div class="cta-icon mb-3">
                            <i class="fas fa-trophy fa-3x" style="color: #0000ff;"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: #0000ff;">Standings</h5>
                        <p class="text-muted small mb-3">Check current league rankings and statistics</p>
                        <span class="btn btn-sm fw-semibold px-3" 
                              style="background: linear-gradient(135deg, #0000ff, #4169E1); color: white; border-radius: 20px;">
                            View Rankings
                        </span>
                    </a>
                </div>
            </div>

            <!-- Teams Card -->
            <div class="col-lg-3 col-md-6">
                <div class="cta-card p-4 h-100" 
                     style="background: white; border-radius: 20px; border: 2px solid rgba(0, 0, 255, 0.1); 
                            transition: all 0.3s ease; text-decoration: none;"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.borderColor='#0000ff'; this.style.boxShadow='0 10px 30px rgba(0, 0, 255, 0.15)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='rgba(0, 0, 255, 0.1)'; this.style.boxShadow='none'">
                    <a href="teams.php" class="text-decoration-none">
                        <div class="cta-icon mb-3">
                            <i class="fas fa-users fa-3x" style="color: #0000ff;"></i>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: #0000ff;">Teams</h5>
                        <p class="text-muted small mb-3">Explore team rosters and player profiles</p>
                        <span class="btn btn-sm fw-semibold px-3" 
                              style="background: linear-gradient(135deg, #0000ff, #4169E1); color: white; border-radius: 20px;">
                            Browse Teams
                        </span>
                    </a>
                </div>
            </div>

            <!-- Quick Stats Card -->
            <div class="col-lg-3 col-md-6">
                <div class="cta-card p-4 h-100" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 20px; color: white;
                            transition: all 0.3s ease;"
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 30px rgba(0, 0, 255, 0.3)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <div class="cta-icon mb-3">
                        <i class="fas fa-chart-line fa-3x text-white"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">League Stats</h5>
                    <p class="text-white-50 small mb-3">Comprehensive league analytics and insights</p>
                    <span class="btn btn-sm btn-light fw-semibold px-3" style="border-radius: 20px; color: #0000ff;">
                        View Analytics
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({ 
        duration: 800, 
        once: true,
        offset: 100,
        easing: 'ease-out-cubic'
    });

    // Enhanced carousel behavior
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth transitions for carousels
        const carousels = document.querySelectorAll('.carousel');
        carousels.forEach(carousel => {
            carousel.addEventListener('slide.bs.carousel', function () {
                // Add any custom slide animations here
            });
        });

        // Add loading animations for cards
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>

<?php include(__DIR__ . '/../includes/footer.php'); ?>
