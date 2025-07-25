<?php
session_start();
require_once('../db_connect.php');

$leagueId = $_SESSION['league_id'] ?? 1;
$league = $conn->query("SELECT name FROM leagues WHERE league_id = $leagueId")->fetch_assoc();
$leagueName = $league['name'] ?? 'League';

// Get upcoming fixtures
$upcomingSql = "
  SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
         t1.name AS home_team, t2.name AS away_team,
         r.score_home, r.score_away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
  WHERE f.league_id = $leagueId AND f.status = 'upcoming'
  ORDER BY f.match_date, f.match_time
";
$upcomingResults = $conn->query($upcomingSql);

// Get played fixtures
$playedSql = "
  SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
         t1.name AS home_team, t2.name AS away_team,
         r.score_home, r.score_away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
  WHERE f.league_id = $leagueId AND f.status = 'played'
  ORDER BY f.match_date DESC, f.match_time DESC
";
$playedResults = $conn->query($playedSql);

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 400px; position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('../assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-5" data-aos="fade-down" data-aos-duration="800" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="hero-heading display-3 fw-bold mb-4 text-white">Fixtures & Results</h1>
                <h3 class="text-white fw-semibold mb-3"><?= htmlspecialchars($leagueName) ?></h3>
                <p class="lead mb-4 text-white">Complete schedule of matches and game results</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<div class="container py-5" style="margin-top: 2rem;">
  
  <!-- Upcoming Fixtures Section -->
  <div class="mb-5">
    <div class="card border-0 shadow-lg" 
         style="border-radius: 20px; background: white; overflow: hidden;">
      
      <!-- Section Header -->
      <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
           style="background: linear-gradient(135deg, #0000ff, #4169E1);">
        <div class="d-flex align-items-center">
          <div class="icon-badge me-3" 
               style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                      border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-calendar-alt fa-lg text-white"></i>
          </div>
          <div>
            <h3 class="mb-0 fw-bold text-white">Upcoming Fixtures</h3>
            <small class="text-white-50">Scheduled matches</small>
          </div>
        </div>
        <span class="badge px-4 py-2 fw-bold" 
              style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 20px; font-size: 1rem;">
          <?= $upcomingResults->num_rows ?> matches
        </span>
      </div>
      
      <div class="card-body p-0">
        <?php if ($upcomingResults->num_rows > 0): ?>
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
                    <i class="fas fa-vs me-1"></i>Match
                  </th>
                  <th class="py-4 fw-bold" style="color: white; border: none;">
                    <i class="fas fa-map-marker-alt me-1"></i>Venue
                  </th>
                  <th class="py-4 fw-bold" style="color: white; border: none;">
                    <i class="fas fa-info-circle me-1"></i>Status
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php while ($row = $upcomingResults->fetch_assoc()): ?>
                  <tr class="text-center fixture-row" 
                      style="border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                      onmouseover="this.style.backgroundColor='rgba(0, 0, 255, 0.05)'; this.style.transform='translateX(5px)'"
                      onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                    <td class="py-4">
                      <div class="date-info">
                        <div class="fw-bold" style="color: #0000ff; font-size: 1.1rem;">
                          <?= date("d M", strtotime($row['match_date'])) ?>
                        </div>
                        <small class="text-muted"><?= date("l", strtotime($row['match_date'])) ?></small>
                      </div>
                    </td>
                    <td class="py-4">
                      <span class="badge px-3 py-2 fw-bold" 
                            style="background: linear-gradient(135deg, #17a2b8, #20c997); color: white; font-size: 0.9rem;">
                        <?= date("H:i", strtotime($row['match_time'])) ?>
                      </span>
                    </td>
                    <td class="py-4">
                      <div class="match-info" style="min-width: 250px;">
                        <div class="fw-bold mb-2" style="color: #333; font-size: 1.1rem;">
                          <?= htmlspecialchars($row['home_team']) ?>
                          <span class="text-muted mx-3" style="font-size: 0.9rem;">VS</span>
                          <?= htmlspecialchars($row['away_team']) ?>
                        </div>
                      </div>
                    </td>
                    <td class="py-4">
                      <small class="text-muted">
                        <i class="fas fa-map-marker-alt me-1" style="color: #0000ff;"></i>
                        <?= htmlspecialchars($row['venue']) ?>
                      </small>
                    </td>
                    <td class="py-4">
                      <span class="badge px-3 py-2 fw-bold" 
                            style="background: linear-gradient(135deg, #ffc107, #ffed4e); color: #000; font-size: 0.9rem;">
                        <i class="fas fa-clock me-1"></i>Scheduled
                      </span>
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

  <!-- Recent Results Section -->
  <div class="mb-5">
    <div class="card border-0 shadow-lg" 
         style="border-radius: 20px; background: white; overflow: hidden;">
      
      <!-- Section Header -->
      <div class="card-header border-0 d-flex align-items-center justify-content-between py-4" 
           style="background: linear-gradient(135deg, #28a745, #20c997);">
        <div class="d-flex align-items-center">
          <div class="icon-badge me-3" 
               style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); 
                      border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-check-circle fa-lg text-white"></i>
          </div>
          <div>
            <h3 class="mb-0 fw-bold text-white">Recent Results</h3>
            <small class="text-white-50">Completed matches</small>
          </div>
        </div>
        <span class="badge px-4 py-2 fw-bold" 
              style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 20px; font-size: 1rem;">
          <?= $playedResults->num_rows ?> completed
        </span>
      </div>
      
      <div class="card-body p-0">
        <?php if ($playedResults->num_rows > 0): ?>
          <div class="accordion" id="playedFixturesAccordion">
            <div class="accordion-item border-0">
              <h2 class="accordion-header" id="playedHeading">
                <button class="accordion-button collapsed border-0 fw-bold py-4" 
                        style="background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05)); 
                               color: #0000ff; font-size: 1.1rem;"
                        type="button" data-bs-toggle="collapse" data-bs-target="#playedResults" 
                        aria-expanded="false" aria-controls="playedResults" id="playedAccordionBtn">
                  <i class="fas fa-history me-3" style="color: #28a745;"></i>
                  <span id="accordionBtnText">View All Completed Matches (<?= $playedResults->num_rows ?>)</span>
                </button>
              </h2>
              <div id="playedResults" class="accordion-collapse collapse" 
                   aria-labelledby="playedHeading" data-bs-parent="#playedFixturesAccordion">
                <div class="accordion-body p-0">
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
                            <i class="fas fa-trophy me-1"></i>Match & Result
                          </th>
                          <th class="py-4 fw-bold" style="color: white; border: none;">
                            <i class="fas fa-map-marker-alt me-1"></i>Venue
                          </th>
                          <th class="py-4 fw-bold" style="color: white; border: none;">
                            <i class="fas fa-check-circle me-1"></i>Status
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while ($row = $playedResults->fetch_assoc()): ?>
                          <tr class="text-center result-row" 
                              style="border-bottom: 1px solid #f0f0f0; transition: all 0.3s ease;"
                              onmouseover="this.style.backgroundColor='rgba(40, 167, 69, 0.05)'; this.style.transform='translateX(5px)'"
                              onmouseout="this.style.backgroundColor='white'; this.style.transform='translateX(0)'">
                            <td class="py-4">
                              <div class="date-info">
                                <div class="fw-bold" style="color: #0000ff; font-size: 1.1rem;">
                                  <?= date("d M", strtotime($row['match_date'])) ?>
                                </div>
                                <small class="text-muted"><?= date("l", strtotime($row['match_date'])) ?></small>
                              </div>
                            </td>
                            <td class="py-4">
                              <span class="badge px-3 py-2" 
                                    style="background: #6c757d; color: white; font-size: 0.9rem;">
                                <?= date("H:i", strtotime($row['match_time'])) ?>
                              </span>
                            </td>
                            <td class="py-4">
                              <div class="match-result" style="min-width: 300px;">
                                <div class="fw-bold mb-2" style="color: #333; font-size: 1.1rem;">
                                  <?= htmlspecialchars($row['home_team']) ?>
                                  <span class="text-muted mx-3" style="font-size: 0.9rem;">VS</span>
                                  <?= htmlspecialchars($row['away_team']) ?>
                                </div>
                                <?php if (isset($row['score_home'], $row['score_away'])): ?>
                                  <div class="d-flex justify-content-center align-items-center gap-3 mt-3">
                                    <span class="score-badge fw-bold px-3 py-2" 
                                          style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                                 color: white; border-radius: 10px; font-size: 1.1rem; min-width: 45px;">
                                      <?= $row['score_home'] ?>
                                    </span>
                                    <span class="text-uppercase small fw-bold" style="color: #28a745;">FINAL</span>
                                    <span class="score-badge fw-bold px-3 py-2" 
                                          style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                                 color: white; border-radius: 10px; font-size: 1.1rem; min-width: 45px;">
                                      <?= $row['score_away'] ?>
                                    </span>
                                  </div>
                                <?php else: ?>
                                  <div class="text-muted small mt-2">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Score not available
                                  </div>
                                <?php endif; ?>
                              </div>
                            </td>
                            <td class="py-4">
                              <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1" style="color: #0000ff;"></i>
                                <?= htmlspecialchars($row['venue']) ?>
                              </small>
                            </td>
                            <td class="py-4">
                              <span class="badge px-3 py-2 fw-bold" 
                                    style="background: linear-gradient(135deg, #28a745, #20c997); color: white; font-size: 0.9rem;">
                                <i class="fas fa-check me-1"></i>Completed
                              </span>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="fas fa-history fa-3x text-muted mb-3"></i>
            <h5 class="text-muted mb-2">No Completed Matches</h5>
            <p class="text-muted">Match results will appear here once games are played!</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="text-center mt-5">
    <a href="home.php" class="btn btn-outline-primary px-4">
      <i class="fas fa-arrow-left me-2"></i>Back to League Home
    </a>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<style>
.accordion-button:not(.collapsed) {
  background-color: #d1e7dd;
  color: #0f5132;
}

.accordion-button:focus {
  box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const accordionButton = document.getElementById('playedAccordionBtn');
  const accordionText = document.getElementById('accordionBtnText');
  const playedResultsCollapse = document.getElementById('playedResults');
  const matchCount = <?= $playedResults->num_rows ?>;
  
  console.log('Accordion elements:', { accordionButton, accordionText, playedResultsCollapse });
  
  if (accordionButton && playedResultsCollapse && accordionText) {
    
    // Handle click events directly
    accordionButton.addEventListener('click', function() {
      console.log('Button clicked');
      setTimeout(function() {
        if (playedResultsCollapse.classList.contains('show')) {
          accordionText.textContent = `Hide Completed Matches (${matchCount})`;
          console.log('Text updated to Hide');
        } else {
          accordionText.textContent = `View All Completed Matches (${matchCount})`;
          console.log('Text updated to View');
        }
      }, 100);
    });
    
    // Alternative: Use Bootstrap events if available
    if (typeof bootstrap !== 'undefined') {
      playedResultsCollapse.addEventListener('shown.bs.collapse', function() {
        accordionText.textContent = `Hide Completed Matches (${matchCount})`;
        console.log('Shown event - text updated to Hide');
      });
      
      playedResultsCollapse.addEventListener('hidden.bs.collapse', function() {
        accordionText.textContent = `View All Completed Matches (${matchCount})`;
        console.log('Hidden event - text updated to View');
      });
    }
    
    // Fallback: Monitor class changes
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
          if (playedResultsCollapse.classList.contains('show')) {
            accordionText.textContent = `Hide Completed Matches (${matchCount})`;
          } else {
            accordionText.textContent = `View All Completed Matches (${matchCount})`;
          }
        }
      });
    });
    
    observer.observe(playedResultsCollapse, {
      attributes: true,
      attributeFilter: ['class']
    });
    
  } else {
    console.error('One or more accordion elements not found');
  }
});
</script>
