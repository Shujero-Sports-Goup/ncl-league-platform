<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../db_connect.php');

// Fetch dynamic league abbreviation
$leagueName = 'Nukta League Management'; // Default value
if (!empty($_SESSION['league_id'])) {
  $stmt = $conn->prepare("SELECT abbreviation FROM leagues WHERE league_id = ?");
  $stmt->bind_param("i", $_SESSION['league_id']);
  $stmt->execute();
  $stmt->bind_result($abbr);
  if ($stmt->fetch()) {
    $leagueName = htmlspecialchars($abbr) . ' | Nukta';
  }
  $stmt->close();
}
?>
<nav class="navbar navbar-expand-md bg-dark border-bottom border-accent shadow-sm sticky-top">
  <div class="container-fluid px-3 py-2 d-flex align-items-center justify-content-between flex-wrap">

    <!-- Branding -->
    <a class="navbar-brand d-flex align-items-center" href="/ncl-league-platform/index.php">
      <img src="/ncl-league-platform/assets/images/nukta-logo.png" alt="Nukta" style="height: 35px; background: white; padding: 4px; border-radius: 8px;">
    </a>

    <!-- Mobile Toggle -->
    <button class="navbar-toggler text-accent border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nclNavbar" aria-controls="nclNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="fs-2">☰</span>
    </button>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Navigation Menu -->
    <div class="collapse navbar-collapse mt-3 mt-md-0" id="nclNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-md-0 gap-2">
        <?php if (!empty($_SESSION['role'])): ?>
          <?php switch ($_SESSION['role']) {
            case 'admin': ?>
              <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/admin/dashboard.php">Dashboard</a></li>
              <?php break;
            case 'manager': ?>
              <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/manager/panel.php">Team Panel</a></li>
              <?php break;
            case 'referee': ?>
              <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/referee/submit_score.php">Submit Score</a></li>
              <?php break;
          } ?>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/leagues/fixtures.php">Fixtures</a></li>
          <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/leagues/standings.php">Standings</a></li>
          <li class="nav-item"><a class="nav-link text-light" href="/ncl-league-platform/leagues/teams.php">Teams</a></li>
        <?php endif; ?>
      </ul>

      <!-- User Info -->
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($_SESSION['name'])): ?>
          <span class="text-light small">
            👤 <?= htmlspecialchars($_SESSION['name']) ?> <em>(<?= ucfirst(htmlspecialchars($_SESSION['role'])) ?>)</em>
          </span>
          <a class="btn btn-sm btn-outline-light" href="/ncl-league-platform/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-sm btn-outline-light me-2" href="mailto:info@nukta.pro">Contact Us</a>
          <a class="btn btn-sm btn-accent" href="/ncl-league-platform/login.php">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
