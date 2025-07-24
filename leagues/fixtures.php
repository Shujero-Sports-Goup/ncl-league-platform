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

<section class="league-hero border-bottom py-5 text-center text-light">
  <div class="container">
    <h2 class="fw-bold display-5 mb-2">Fixtures & Results</h2>
    <p class="text-light small mb-0"><?= htmlspecialchars($leagueName) ?>  Schedule</p>
  </div>
</section>


<div class="container mb-5">
  
  <!-- Upcoming Fixtures Section -->
  <div class="mb-5">
    <div class="d-flex align-items-center mb-4">
      <div class="badge bg-primary me-3 p-2">
        <i class="fas fa-calendar-alt"></i>
      </div>
      <h3 class="mb-0 fw-bold">Upcoming Fixtures</h3>
      <span class="badge bg-warning ms-2"><?= $upcomingResults->num_rows ?> matches</span>
    </div>
    
    <?php if ($upcomingResults->num_rows > 0): ?>
      <div class="table-responsive shadow-sm rounded">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-primary">
            <tr class="text-center">
              <th>Date</th>
              <th>Time</th>
              <th>Match</th>
              <th>Venue</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $upcomingResults->fetch_assoc()): ?>
              <tr class="text-center">
                <td class="fw-medium">
                  <?= date("d M, Y", strtotime($row['match_date'])) ?>
                  <div class="text-muted small">
                    <?= date("l", strtotime($row['match_date'])) ?>
                  </div>
                </td>
                <td>
                  <span class="badge bg-info text-dark fw-bold">
                    <?= date("H:i", strtotime($row['match_time'])) ?>
                  </span>
                </td>
                <td>
                  <div class="fw-semibold mb-1">
                    <?= htmlspecialchars($row['home_team']) ?>
                    <span class="text-muted mx-2">vs</span>
                    <?= htmlspecialchars($row['away_team']) ?>
                  </div>
                </td>
                <td class="text-muted">
                  <i class="fas fa-map-marker-alt me-1"></i>
                  <?= htmlspecialchars($row['venue']) ?>
                </td>
                <td>
                  <span class="badge bg-warning text-dark px-3">Scheduled</span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info text-center">
        <i class="fas fa-info-circle me-2"></i>
        No upcoming fixtures scheduled at this time.
      </div>
    <?php endif; ?>
  </div>

  <!-- Played Fixtures Section -->
  <div class="mb-5">
    <div class="d-flex align-items-center mb-4">
      <div class="badge bg-success me-3 p-2">
        <i class="fas fa-check-circle"></i>
      </div>
      <h3 class="mb-0 fw-bold">Recent Results</h3>
      <span class="badge bg-success ms-2"><?= $playedResults->num_rows ?> completed</span>
    </div>
    
    <?php if ($playedResults->num_rows > 0): ?>
      <div class="accordion" id="playedFixturesAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="playedHeading">
            <button class="accordion-button collapsed" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#playedResults" 
                    aria-expanded="false" aria-controls="playedResults"
                    id="playedAccordionBtn">
              <i class="fas fa-history me-2"></i>
              <span id="accordionBtnText">View All Completed Matches (<?= $playedResults->num_rows ?>)</span>
            </button>
          </h2>
          <div id="playedResults" class="accordion-collapse collapse" 
               aria-labelledby="playedHeading" data-bs-parent="#playedFixturesAccordion">
            <div class="accordion-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                  <thead class="table-success">
                    <tr class="text-center">
                      <th>Date</th>
                      <th>Time</th>
                      <th>Match & Result</th>
                      <th>Venue</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php while ($row = $playedResults->fetch_assoc()): ?>
                      <tr class="text-center">
                        <td class="fw-medium">
                          <?= date("d M, Y", strtotime($row['match_date'])) ?>
                          <div class="text-muted small">
                            <?= date("l", strtotime($row['match_date'])) ?>
                          </div>
                        </td>
                        <td>
                          <span class="badge bg-secondary">
                            <?= date("H:i", strtotime($row['match_time'])) ?>
                          </span>
                        </td>
                        <td>
                          <div class="fw-semibold mb-1">
                            <?= htmlspecialchars($row['home_team']) ?>
                            <span class="text-muted mx-2">vs</span>
                            <?= htmlspecialchars($row['away_team']) ?>
                          </div>
                          <?php if (isset($row['score_home'], $row['score_away'])): ?>
                            <div class="d-flex justify-content-center align-items-center gap-3 mt-2">
                              <span class="badge bg-primary fs-6 fw-bold"><?= $row['score_home'] ?></span>
                              <span class="text-uppercase small text-muted">Final</span>
                              <span class="badge bg-primary fs-6 fw-bold"><?= $row['score_away'] ?></span>
                            </div>
                          <?php else: ?>
                            <div class="text-muted small">Score not available</div>
                          <?php endif; ?>
                        </td>
                        <td class="text-muted">
                          <i class="fas fa-map-marker-alt me-1"></i>
                          <?= htmlspecialchars($row['venue']) ?>
                        </td>
                        <td>
                          <span class="badge bg-success px-3">Completed</span>
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
      <div class="alert alert-secondary text-center">
        <i class="fas fa-calendar-times me-2"></i>
        No completed matches yet.
      </div>
    <?php endif; ?>
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
