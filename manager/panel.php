<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('manager');

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// Fetch teams this manager handles, including description
$stmt = $conn->prepare("
  SELECT 
    t.team_id, 
    t.name         AS team_name,
    t.description, 
    l.abbreviation AS league_abbr
  FROM team_managers tm
  JOIN teams t   ON tm.team_id   = t.team_id
  JOIN leagues l ON t.league_id  = l.league_id
  WHERE tm.user_id = ?
  ORDER BY t.name
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$teamList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container py-5">
  <h2 class="text-center mb-4">👔 Team Manager Panel</h2>
  <p class="text-center mb-5">Welcome, <strong><?= htmlspecialchars($userName) ?></strong></p>

  <?php if (empty($teamList)): ?>
    <div class="alert alert-warning text-center">
      ⚠️ You are not currently assigned to any teams.
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 g-4">
      <?php foreach ($teamList as $team): ?>
        <div class="col">
          <div class="card h-100 shadow-sm">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">
                <?= htmlspecialchars($team['team_name']) ?>
                <span class="badge bg-secondary ms-2"><?= htmlspecialchars($team['league_abbr']) ?></span>
              </h5>

              <?php if (!empty($team['description'])): ?>
                <p class="text-muted small mb-3">
                  <?= nl2br(htmlspecialchars($team['description'])) ?>
                </p>
              <?php endif; ?>

              <p class="card-text">Manage your roster or view upcoming fixtures.</p>

              <div class="mt-auto">
                <div class="d-grid gap-2">
                  <!-- Manage Roster -->
                  <a href="add_players.php?team_id=<?= $team['team_id'] ?>" class="btn btn-primary">
                    🏀 Manage Roster
                  </a>
                  <!-- View Fixtures -->
                  <a href="../leagues/fixtures.php?team_id=<?= $team['team_id'] ?>" class="btn btn-outline-primary">
                    📅 View Fixtures
                  </a>
                  <!-- View Public Profile -->
                  <a href="../leagues/team_profile.php?team_id=<?= $team['team_id'] ?>" class="btn btn-outline-secondary">
                    👁️ View Profile
                  </a>
                  <!-- Edit Team Info -->
                  <a href="edit_team.php?team_id=<?= $team['team_id'] ?>" class="btn btn-outline-warning">
                    ✏️ Edit Team Info
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="text-center mt-5">
    <a href="../logout.php" class="btn btn-outline-danger">🚪 Logout</a>
  </div>
</section>

<?php include('../includes/footer.php'); ?>
