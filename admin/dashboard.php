<?php
session_start();
require_once('../db_connect.php');
require_once('../includes/auth.php');
require_once('../includes/audit_trail.php');
requireRole('admin');

$base = '/ncl-league-platform';

// Handle search and filtering
$searchTerm = $_GET['search'] ?? '';
$searchType = $_GET['type'] ?? 'all';
$leagueFilter = $_GET['league_filter'] ?? '';

if (isset($_GET['league'])) {
  $_SESSION['league_id'] = intval($_GET['league']);
  header("Location: dashboard.php");
  exit;
}

if (!isset($_SESSION['league_id'])) {
  $res = $conn->query("SELECT league_id FROM leagues ORDER BY league_id LIMIT 1");
  $_SESSION['league_id'] = $res->num_rows ? (int) $res->fetch_assoc()['league_id'] : 0;
}
$leagueId = (int) $_SESSION['league_id'];

$leagues = $conn->query("SELECT league_id, name, abbreviation, logo_url FROM leagues ORDER BY abbreviation");

if (!$leagueId) {
  include('../includes/header.php');
  echo '<div class="container py-5 text-center"><h2>No leagues found</h2></div>';
  include('../includes/footer.php');
  exit;
}

$stmt = $conn->prepare("SELECT name, logo_url FROM leagues WHERE league_id = ?");
$stmt->bind_param("i", $leagueId);
$stmt->execute();
$stmt->bind_result($leagueName, $logoUrl);
$stmt->fetch();
$stmt->close();

// Get comprehensive stats
$totalTeams = $conn->query("SELECT COUNT(*) as total FROM teams")->fetch_assoc()['total'];
$totalFixtures = $conn->query("SELECT COUNT(*) as total FROM fixtures")->fetch_assoc()['total'];
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$totalPlayers = $conn->query("SELECT COUNT(*) as total FROM players")->fetch_assoc()['total'];

$teamCount = $conn->query("SELECT COUNT(*) as total FROM teams WHERE league_id = $leagueId")->fetch_assoc()['total'];
$fixtureCount = $conn->query("SELECT COUNT(*) as total FROM fixtures WHERE league_id = $leagueId")->fetch_assoc()['total'];
$userCount = $conn->query("SELECT COUNT(*) as total FROM users WHERE league_id = $leagueId")->fetch_assoc()['total'];
$playerCount = $conn->query("SELECT COUNT(DISTINCT p.player_id) as total FROM players p JOIN teams t ON p.team_id = t.team_id WHERE t.league_id = $leagueId")->fetch_assoc()['total'];

// Get security audit data
$audit = new SecurityAuditTrail($conn);
$securityStats = $audit->getSecurityStats(7); // Last 7 days
$recentSecurityEvents = $audit->getRecentEvents(10, ['severity' => 'HIGH']); // Recent high severity events
$unresolvedEvents = $audit->getUnresolvedSecurityEvents();

// Search functionality
$searchResults = [];
if ($searchTerm) {
    $searchWhere = $leagueFilter ? "AND l.league_id = " . intval($leagueFilter) : "";
    
    if ($searchType === 'all' || $searchType === 'teams') {
        $teamQuery = "SELECT 'team' as type, t.team_id as id, t.name as title, 
                      CONCAT(t.description, ' | ', l.abbreviation, ' League') as description,
                      t.logo_url as image, l.name as league_name
                      FROM teams t 
                      JOIN leagues l ON t.league_id = l.league_id 
                      WHERE t.name LIKE ? $searchWhere";
        $stmt = $conn->prepare($teamQuery);
        $searchPattern = '%' . $searchTerm . '%';
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
        $stmt->close();
    }
    
    if ($searchType === 'all' || $searchType === 'fixtures') {
        $fixtureQuery = "SELECT 'fixture' as type, f.fixture_id as id, 
                         CONCAT(t1.name, ' vs ', t2.name) as title,
                         CONCAT('Date: ', f.match_date, ' | Time: ', f.match_time, ' | Status: ', f.status) as description,
                         '' as image, l.name as league_name
                         FROM fixtures f
                         JOIN teams t1 ON f.home_team = t1.team_id
                         JOIN teams t2 ON f.away_team = t2.team_id
                         JOIN leagues l ON f.league_id = l.league_id
                         WHERE (t1.name LIKE ? OR t2.name LIKE ?) $searchWhere";
        $stmt = $conn->prepare($fixtureQuery);
        $stmt->bind_param("ss", $searchPattern, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
        $stmt->close();
    }
    
    if ($searchType === 'all' || $searchType === 'players') {
        $playerQuery = "SELECT 'player' as type, p.player_id as id, p.name as title,
                        CONCAT('Team: ', t.name, ' | Position: ', COALESCE(p.position, 'N/A')) as description,
                        '' as image, l.name as league_name
                        FROM players p
                        JOIN teams t ON p.team_id = t.team_id
                        JOIN leagues l ON t.league_id = l.league_id
                        WHERE p.name LIKE ? $searchWhere";
        $stmt = $conn->prepare($playerQuery);
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
        $stmt->close();
    }
    
    if ($searchType === 'all' || $searchType === 'users') {
        $userQuery = "SELECT 'user' as type, u.user_id as id, u.username as title,
                      CONCAT('Name: ', COALESCE(u.name, 'N/A'), ' | Role: ', u.role) as description,
                      '' as image, COALESCE(l.name, 'No League') as league_name
                      FROM users u
                      LEFT JOIN leagues l ON u.league_id = l.league_id
                      WHERE (u.username LIKE ? OR u.name LIKE ?) $searchWhere";
        $stmt = $conn->prepare($userQuery);
        $stmt->bind_param("ss", $searchPattern, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
        $stmt->close();
    }
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<style>
body {
  background-color: #f8f9fa !important;
  color: #333 !important;
}
</style>

<section class="admin-dashboard-bg">
<div class="container-fluid py-4">
  <!-- Super Admin Header -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card admin-header text-white shadow-lg">
        <div class="card-body text-center py-5">
          <div class="mb-3">
            <img src="<?= $base ?>/assets/images/nukta-logo.png" alt="Nukta Sports Management" style="height: 80px; background: rgba(255,255,255,0.95); padding: 12px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
          </div>
          <h1 class="display-4 fw-bold mb-3">Nukta Admin Command Center</h1>
          <p class="lead mb-0">Complete control over the Nukta League Management System</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="row mb-4 g-4">
    <div class="col-md-3">
      <div class="card admin-stats-card">
        <div class="card-body text-center">
          <i class="fas fa-users admin-action-icon mb-3"></i>
          <div class="admin-stats-number"><?= $totalUsers ?></div>
          <div class="admin-stats-label">Total Users</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stats-card">
        <div class="card-body text-center">
          <i class="fas fa-shield-alt admin-action-icon mb-3"></i>
          <div class="admin-stats-number"><?= $totalTeams ?></div>
          <div class="admin-stats-label">Total Teams</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stats-card">
        <div class="card-body text-center">
          <i class="fas fa-calendar admin-action-icon mb-3"></i>
          <div class="admin-stats-number"><?= $totalFixtures ?></div>
          <div class="admin-stats-label">Total Fixtures</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stats-card">
        <div class="card-body text-center">
          <i class="fas fa-users-cog admin-action-icon mb-3"></i>
          <div class="admin-stats-number"><?= $totalPlayers ?></div>
          <div class="admin-stats-label">Total Players</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Comprehensive Search Panel -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent-hover) 100%); color: white;">
          <h5 class="mb-0">🔍 Universal Search & Filter</h5>
        </div>
        <div class="card-body bg-light">
          <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label fw-semibold">🔎 Search Term</label>
              <input type="text" name="search" class="form-control form-control-lg" 
                     placeholder="Search teams, players, fixtures, users by name..." 
                     value="<?= htmlspecialchars($searchTerm) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">📂 Search Type</label>
              <select name="type" class="form-select form-select-lg">
                <option value="all" <?= $searchType === 'all' ? 'selected' : '' ?>> All Categories</option>
                <option value="teams" <?= $searchType === 'teams' ? 'selected' : '' ?>> Teams Only</option>
                <option value="players" <?= $searchType === 'players' ? 'selected' : '' ?>> Players Only</option>
                <option value="fixtures" <?= $searchType === 'fixtures' ? 'selected' : '' ?>> Fixtures Only</option>
                <option value="users" <?= $searchType === 'users' ? 'selected' : '' ?>> Users Only</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold"> League Filter</label>
              <select name="league_filter" class="form-select form-select-lg">
                <option value="">All Leagues</option>
                <?php 
                $leagues->data_seek(0);
                while ($l = $leagues->fetch_assoc()): 
                ?>
                  <option value="<?= $l['league_id'] ?>" <?= $leagueFilter == $l['league_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['abbreviation']) ?> - <?= htmlspecialchars($l['name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-2">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                  🚀 Search
                </button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Clear</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Action Center -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent-hover) 100%); color: white;">
          <h5 class="mb-0">⚡ Quick Actions</h5>
        </div>
        <div class="card-body bg-white">
          <div class="row g-4">
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-plus admin-action-icon"></i>
                  <h6 class="admin-action-title">Add Team</h6>
                  <a href="<?= $base ?>/admin/add_team.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-calendar-plus admin-action-icon"></i>
                  <h6 class="admin-action-title">Schedule Match</h6>
                  <a href="<?= $base ?>/admin/create_fixture.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-users-cog admin-action-icon"></i>
                  <h6 class="admin-action-title">Manage Users</h6>
                  <a href="<?= $base ?>/admin/manage_users.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-trophy admin-action-icon"></i>
                  <h6 class="admin-action-title">Manage Leagues</h6>
                  <a href="<?= $base ?>/admin/manage_leagues.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-shield-alt admin-action-icon"></i>
                  <h6 class="admin-action-title">Security Center</h6>
                  <a href="<?= $base ?>/admin/security_dashboard.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-chart-bar admin-action-icon"></i>
                  <h6 class="admin-action-title">Standings</h6>
                  <a href="<?= $base ?>/admin/standings_manager.php" class="btn btn-primary btn-sm">Go</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Additional Actions -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent-hover) 100%); color: white;">
          <h5 class="mb-0">🎯 Management Tools</h5>
        </div>
        <div class="card-body bg-white">
          <div class="row g-4">
            <div class="col-md-3">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-file-alt admin-action-icon"></i>
                  <h6 class="admin-action-title">Registration</h6>
                  <p class="admin-action-description">Manage team registrations</p>
                  <a href="<?= $base ?>/admin/nukta_registration_manager.php" class="btn btn-primary">Access</a>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-calendar admin-action-icon"></i>
                  <h6 class="admin-action-title">Fixtures</h6>
                  <p class="admin-action-description">Manage all fixtures</p>
                  <a href="manage_fixtures.php" class="btn btn-primary">Access</a>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-cogs admin-action-icon"></i>
                  <h6 class="admin-action-title">Fixture Manager</h6>
                  <p class="admin-action-description">Advanced fixture tools</p>
                  <a href="fixture_manager.php" class="btn btn-primary">Access</a>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card admin-action-card">
                <div class="card-body">
                  <i class="fas fa-chart-line admin-action-icon"></i>
                  <h6 class="admin-action-title">Analytics</h6>
                  <p class="admin-action-description">View system analytics</p>
                  <a href="#" class="btn btn-primary">Coming Soon</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Results -->
  <?php if ($searchTerm && !empty($searchResults)): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent-hover) 100%); color: white;">
          <h5 class="mb-0">🎯 Search Results (<?= count($searchResults) ?> found)</h5>
        </div>
        <div class="card-body p-0 bg-white">
          <div class="table-responsive">
            <table class="table table-hover mb-0 search-results-table">
              <thead style="background: linear-gradient(135deg, var(--primary) 0%, var(--accent-hover) 100%); color: white;">
                <tr>
                  <th width="10%">Type</th>
                  <th width="25%">Name/Title</th>
                  <th width="35%">Details</th>
                  <th width="15%">League</th>
                  <th width="15%">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($searchResults as $result): ?>
                <tr>
                  <td>
                    <span class="badge bg-<?= 
                      $result['type'] === 'team' ? 'primary' : 
                      ($result['type'] === 'player' ? 'success' : 
                      ($result['type'] === 'fixture' ? 'warning' : 'info')) 
                    ?>">
                      <?= ucfirst($result['type']) ?>
                    </span>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($result['title']) ?></strong>
                  </td>
                  <td>
                    <small class="text-muted"><?= htmlspecialchars($result['description']) ?></small>
                  </td>
                  <td>
                    <small><?= htmlspecialchars($result['league_name']) ?></small>
                  </td>
                  <td>
                    <?php if ($result['type'] === 'team'): ?>
                      <a href="<?= $base ?>/admin/edit_team.php?team_id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-primary action-btn">✏️ Edit</a>
                      <a href="<?= $base ?>/admin/add_players.php?team_id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-success action-btn">👥 Roster</a>
                    <?php elseif ($result['type'] === 'fixture'): ?>
                      <a href="<?= $base ?>/admin/edit_fixture.php?fixture_id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-warning action-btn">📅 Edit</a>
                    <?php elseif ($result['type'] === 'user'): ?>
                      <a href="<?= $base ?>/admin/manage_users.php?user_id=<?= $result['id'] ?>" class="btn btn-sm btn-outline-info action-btn">👤 Manage</a>
                    <?php elseif ($result['type'] === 'player'): ?>
                      <button class="btn btn-sm btn-outline-success action-btn" onclick="viewPlayer(<?= $result['id'] ?>)">👁️ View</button>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php elseif ($searchTerm): ?>
  <div class="row mb-4">
    <div class="col-12">
      <div class="alert alert-warning">
        <h6>🔍 No Results Found</h6>
        <p class="mb-0">No matches found for "<?= htmlspecialchars($searchTerm) ?>". Try different keywords or check your filters.</p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- League Selector -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body text-center">
          <form method="GET" class="d-flex flex-column align-items-center">
            <?php
              $initialLogo = !empty($logoUrl)
                ? $base . '/' . ltrim($logoUrl, '/')
                : $base . '/assets/default_league.png';
            ?>
            <img id="leagueLogoPreview"
              src="<?= $initialLogo ?>"
              alt="League Logo"
              class="mb-3"
              style="height: 80px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);" />

            <label for="league-select" class="form-label fw-semibold mb-2">🏆 Current League Context:</label>

            <select id="league-select"
              name="league"
              onchange="this.form.submit()"
              class="form-select w-auto text-center shadow-sm">
              <?php 
              $leagues->data_seek(0);
              while ($l = $leagues->fetch_assoc()):
                $selected = $l['league_id'] == $leagueId ? 'selected' : '';
                // compute full URL or fallback default
                $optLogo = !empty($l['logo_url'])
                  ? $base . '/' . ltrim($l['logo_url'], '/')
                  : $base . '/assets/default_league.png';
              ?>
                <option 
                  value="<?= $l['league_id'] ?>" 
                  data-logo="<?= $optLogo ?>" 
                  <?= $selected ?>>
                  <?= $l['abbreviation'] ?> – <?= $l['name'] ?>
                </option>
              <?php endwhile; ?>
            </select>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Global Statistics Dashboard -->
  <div class="row mb-4">
    <div class="col-12">
      <h5 class="text-center mb-4">📊 Platform Statistics</h5>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card bg-gradient-primary text-white shadow">
        <div class="card-body text-center">
          <div class="display-6">🏀</div>
          <h4><?= $totalTeams ?></h4>
          <p class="mb-0">Total Teams</p>
          <small>(<?= $teamCount ?> in current league)</small>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card bg-gradient-success text-white shadow">
        <div class="card-body text-center">
          <div class="display-6">�</div>
          <h4><?= $totalPlayers ?></h4>
          <p class="mb-0">Total Players</p>
          <small>(<?= $playerCount ?> in current league)</small>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card bg-gradient-warning text-white shadow">
        <div class="card-body text-center">
          <div class="display-6">�</div>
          <h4><?= $totalFixtures ?></h4>
          <p class="mb-0">Total Fixtures</p>
          <small>(<?= $fixtureCount ?> in current league)</small>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="card bg-gradient-info text-white shadow">
        <div class="card-body text-center">
          <div class="display-6">👤</div>
          <h4><?= $totalUsers ?></h4>
          <p class="mb-0">Total Users</p>
          <small>(<?= $userCount ?> in current league)</small>
        </div>
      </div>
    </div>
  </div>

  <!-- Security Dashboard Overview -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Security Dashboard Overview</h5>
          <a href="security_dashboard.php" class="btn btn-light btn-sm">
            <i class="fas fa-external-link-alt"></i> Full Dashboard
          </a>
        </div>
        <div class="card-body">
          <!-- Security Statistics Row -->
          <div class="row mb-3">
            <div class="col-lg-3 col-md-6 mb-3">
              <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                  <i class="fas fa-chart-line fa-2x mb-2"></i>
                  <h4><?= number_format($securityStats['total_events'] ?? 0) ?></h4>
                  <small>Total Events (7d)</small>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
              <div class="card bg-warning text-white h-100">
                <div class="card-body text-center">
                  <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                  <h4><?= number_format($securityStats['failed_logins'] ?? 0) ?></h4>
                  <small>Failed Logins (7d)</small>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
              <div class="card bg-danger text-white h-100">
                <div class="card-body text-center">
                  <i class="fas fa-shield-alt fa-2x mb-2"></i>
                  <h4><?= number_format($securityStats['risk_events'] ?? 0) ?></h4>
                  <small>Risk Events (7d)</small>
                </div>
              </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
              <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                  <i class="fas fa-users fa-2x mb-2"></i>
                  <h4><?= number_format($securityStats['active_sessions'] ?? 0) ?></h4>
                  <small>Active Sessions</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Unresolved Security Events -->
          <?php if (!empty($unresolvedEvents) && count($unresolvedEvents) > 0): ?>
          <div class="row mb-3">
            <div class="col-12">
              <div class="alert alert-danger">
                <h6 class="alert-heading">
                  <i class="fas fa-exclamation-circle"></i> 
                  Unresolved Security Events (<?= count($unresolvedEvents) ?>)
                </h6>
                <div class="row">
                  <?php foreach (array_slice($unresolvedEvents, 0, 3) as $event): ?>
                  <div class="col-md-4 mb-2">
                    <div class="card border-danger">
                      <div class="card-body p-2">
                        <small>
                          <strong><?= htmlspecialchars($event['event_type']) ?></strong><br>
                          <span class="badge bg-<?= 
                            $event['severity'] === 'CRITICAL' ? 'danger' : 
                            ($event['severity'] === 'HIGH' ? 'warning' : 'info') 
                          ?>"><?= $event['severity'] ?></span>
                          <br>
                          <?= htmlspecialchars(substr($event['description'], 0, 60)) ?>...
                        </small>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php if (count($unresolvedEvents) > 3): ?>
                <small class="text-muted">
                  And <?= count($unresolvedEvents) - 3 ?> more events requiring attention...
                </small>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Recent High Severity Events -->
          <?php if (!empty($recentSecurityEvents) && count($recentSecurityEvents) > 0): ?>
          <div class="row">
            <div class="col-12">
              <h6><i class="fas fa-history"></i> Recent High Severity Events</h6>
              <div class="table-responsive">
                <table class="table table-sm table-striped">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>User</th>
                      <th>Action</th>
                      <th>Severity</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_slice($recentSecurityEvents, 0, 5) as $event): ?>
                    <tr>
                      <td><small><?= date('M j, H:i', strtotime($event['created_at'])) ?></small></td>
                      <td><small><?= htmlspecialchars($event['username'] ?? 'N/A') ?></small></td>
                      <td><small><?= htmlspecialchars($event['action_type']) ?></small></td>
                      <td>
                        <span class="badge bg-<?= 
                          $event['severity_level'] === 'CRITICAL' ? 'danger' : 
                          ($event['severity_level'] === 'HIGH' ? 'warning' : 'info') 
                        ?> badge-sm">
                          <?= $event['severity_level'] ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($event['success']): ?>
                          <small class="text-success">✓ Success</small>
                        <?php else: ?>
                          <small class="text-danger">✗ Failed</small>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php else: ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <strong>All Clear!</strong> No high severity security events in the last 7 days.
          </div>
          <?php endif; ?>

          <!-- Quick Actions -->
          <div class="row mt-3">
            <div class="col-12">
              <div class="btn-group" role="group" aria-label="Security actions">
                <a href="security_dashboard.php" class="btn btn-primary">
                  <i class="fas fa-shield-alt"></i> Full Security Dashboard
                </a>
                <a href="manage_users.php" class="btn btn-outline-secondary">
                  <i class="fas fa-users-cog"></i> Manage Users
                </a>
                <a href="security_dashboard.php?export=csv&start_date=<?= date('Y-m-d', strtotime('-30 days')) ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-info">
                  <i class="fas fa-download"></i> Export Audit Log
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Center -->
  <div class="row mb-4">
    <div class="col-12">
      <h5 class="text-center mb-4">📥 Data Export Center</h5>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm text-center">
        <div class="card-header bg-primary text-white">
          <h6 class="mb-0">📈 Standings Export</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= $base ?>/leagues/standings.php" class="btn btn-info">👁️ View Standings</a>
            <a href="<?= $base ?>/admin/export/export_standings.php?league=<?= $leagueId ?>" class="btn btn-outline-primary">📄 CSV</a>
            <a href="<?= $base ?>/admin/export/export_standings_pdf.php?league=<?= $leagueId ?>" class="btn btn-primary">📋 Comprehensive PDF</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm text-center">
        <div class="card-header bg-success text-white">
          <h6 class="mb-0">📅 Fixtures Export</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= $base ?>/admin/export/export_fixtures.php?league=<?= $leagueId ?>" class="btn btn-outline-success">📄 CSV</a>
            <a href="<?= $base ?>/admin/export/export_fixtures_pdf.php?league=<?= $leagueId ?>" class="btn btn-outline-success">📋 PDF</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm text-center">
        <div class="card-header bg-warning text-dark">
          <h6 class="mb-0">🏁 Results Export</h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?= $base ?>/admin/export/export_results.php?league=<?= $leagueId ?>" class="btn btn-outline-warning">📄 CSV</a>
            <a href="<?= $base ?>/admin/export/export_results_pdf.php?league=<?= $leagueId ?>" class="btn btn-outline-warning">📋 PDF</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Standings Preview -->

<?php
require_once(__DIR__ . '/helpers/standings_helper.php');
$topStandings = getLeagueStandings($conn, $leagueId, 5);
?>
<div class="row mb-4">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">🏆 Current League Standings (Top 5)</h5>
        <div class="btn-group" role="group">
          <button class="btn btn-sm btn-light" onclick="copyDashboardStandings()">
            <i class="fab fa-whatsapp"></i> Share
          </button>
          <a href="<?= $base ?>/admin/standings_manager.php" class="btn btn-sm btn-light">
            <i class="fas fa-cog"></i> Manage
          </a>
        </div>
      </div>
      <div class="card-body">
        <?php if (count($topStandings) > 0): ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead class="table-dark">
              <tr>
                <th>Pos</th>
                <th>Team</th>
                <th>GP</th>
                <th>W</th>
                <th>L</th>
                <th>GD</th>
                <th>Pts</th>
                <th>Form</th>
              </tr>
            </thead>
            <tbody>
              <?php $position = 1; foreach ($topStandings as $team): ?>
              <tr class="<?= $position === 1 ? 'table-warning' : ($position <= 4 ? 'table-light' : '') ?>">
                <td><strong><?= $position ?></strong></td>
                <td><?= htmlspecialchars($team['name']) ?></td>
                <td><?= $team['played'] ?></td>
                <td><?= $team['wins'] ?></td>
                <td><?= $team['losses'] ?></td>
                <td class="<?= $team['goal_difference'] >= 0 ? 'text-success' : 'text-danger' ?>">
                  <?= $team['goal_difference'] >= 0 ? '+' : '' ?><?= $team['goal_difference'] ?>
                </td>
                <td><strong><?= $team['points'] ?></strong></td>
                <td>
                  <?php 
                  $form = $team['recent_form'] ?? '';
                  $formArray = str_split($form);
                  foreach (array_slice(array_reverse($formArray), 0, 3) as $result): ?>
                    <span class="badge badge-sm <?= $result === 'W' ? 'bg-success' : ($result === 'L' ? 'bg-danger' : ($result === 'F' ? 'bg-warning text-dark' : 'bg-secondary')) ?>">
                      <?= $result ?>
                    </span>
                  <?php endforeach; ?>
                </td>
              </tr>
              <?php $position++; endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="text-center mt-3">
          <a href="<?= $base ?>/leagues/standings.php" class="btn btn-success">View Full Standings</a>
          <a href="<?= $base ?>/admin/standings_manager.php" class="btn btn-primary">Standings Manager</a>
          <a href="<?= $base ?>/admin/export/export_standings_pdf.php?league=<?= $leagueId ?>" class="btn btn-outline-primary">Download PDF</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center">
          <h6>No standings data available</h6>
          <p class="mb-0">No matches have been played yet in this league.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

  <!-- Logout Section -->
  <div class="text-center mt-5 mb-4">
    <div class="card bg-dark text-white shadow-lg">
      <div class="card-body py-3">
        <p class="mb-2">Admin Session Active</p>
        <a href="<?= $base ?>/logout.php" class="btn btn-outline-light">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </div>
  </div>
</div>
</section>

<!-- Player Detail Modal -->
<div class="modal fade" id="playerModal" tabindex="-1" aria-labelledby="playerModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="playerModalLabel">Player Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="playerModalBody">
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Poster Maker Modal -->
<div class="modal fade" id="posterModal" tabindex="-1" aria-labelledby="posterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="posterModalLabel">🎨 Intelligent Poster Maker</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>📅 Select Match</h6>
            <div id="fixturesList" class="list-group mb-3">
              <div class="text-center py-3">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Loading fixtures...</span>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <h6>🖼️ Preview</h6>
            <div id="posterPreview" class="border rounded p-3 text-center bg-light" style="min-height: 300px;">
              <p class="text-muted">Select a match to generate poster preview</p>
            </div>
            <div id="posterActions" class="mt-3 d-none">
              <div class="d-grid gap-2">
                <button id="downloadPoster" class="btn btn-success">
                  <i class="fas fa-download"></i> Download Poster (JPEG)
                </button>
                <button id="sharePoster" class="btn btn-info">
                  <i class="fas fa-share"></i> Copy Share Link
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-3">
          <div class="alert alert-info">
            <small>
              <i class="fas fa-info-circle"></i> 
              <strong>Tip:</strong> Posters are automatically optimized for social media sharing (1080x1080). 
              Final result posters show match scores with winner highlights, while upcoming match posters include countdown timers.
            </small>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="loadFixturesForPoster()">
          <i class="fas fa-refresh"></i> Refresh Fixtures
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  // build mapping only from league-select options
  const logoMap = {};
  document.querySelectorAll('#league-select option').forEach(opt => {
    logoMap[opt.value] = opt.dataset.logo;
  });

  const select = document.querySelector('#league-select');
  const logo   = document.getElementById('leagueLogoPreview');
  if (logo && logoMap[select.value]) {
    logo.src = logoMap[select.value];
    select.addEventListener('change', e => {
      logo.src = logoMap[e.target.value] || '';
    });
  }

  // Add hover effects and animations
  document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Dashboard DOM loaded');
    
    // Check if Bootstrap and required elements are loaded
    if (typeof bootstrap === 'undefined') {
      console.warn('⚠️ Bootstrap not loaded on DOM ready');
    } else {
      console.log('✅ Bootstrap loaded successfully');
    }
    
    const posterModal = document.getElementById('posterModal');
    if (!posterModal) {
      console.warn('⚠️ Poster modal not found on DOM ready');
    } else {
      console.log('✅ Poster modal element found');
    }
    
    // Add hover animation to cards
    const cards = document.querySelectorAll('.card');
    cards.forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.transition = 'transform 0.3s ease';
      });
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });

    // Add click effect to action buttons
    const actionButtons = document.querySelectorAll('.btn');
    actionButtons.forEach(btn => {
      btn.addEventListener('click', function() {
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
          this.style.transform = 'scale(1)';
        }, 100);
      });
    });

    // Animate stats on load
    const statNumbers = document.querySelectorAll('.display-6, h4');
    statNumbers.forEach((stat, index) => {
      stat.style.opacity = '0';
      stat.style.transform = 'translateY(20px)';
      setTimeout(() => {
        stat.style.opacity = '1';
        stat.style.transform = 'translateY(0)';
        stat.style.transition = 'all 0.5s ease';
      }, index * 100);
    });
  });

  // Function to view player details
  function viewPlayer(playerId) {
    const modal = new bootstrap.Modal(document.getElementById('playerModal'));
    const modalBody = document.getElementById('playerModalBody');
    
    // Show loading spinner
    modalBody.innerHTML = `
      <div class="text-center">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    `;
    
    modal.show();
    
    // Fetch player details via AJAX
    fetch('<?= $base ?>/api/get_player_details.php?player_id=' + playerId)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          modalBody.innerHTML = `
            <div class="row">
              <div class="col-md-12">
                <h6><i class="fas fa-user"></i> ${data.player.name}</h6>
                <p><strong>Team:</strong> ${data.player.team_name}</p>
                <p><strong>Position:</strong> ${data.player.position || 'Not specified'}</p>
                <p><strong>League:</strong> ${data.player.league_name}</p>
                ${data.player.jersey_number ? `<p><strong>Jersey #:</strong> ${data.player.jersey_number}</p>` : ''}
              </div>
            </div>
            <div class="mt-3">
              <a href="<?= $base ?>/admin/add_players.php?team_id=${data.player.team_id}" class="btn btn-primary btn-sm">
                <i class="fas fa-edit"></i> Edit Player
              </a>
              <a href="<?= $base ?>/leagues/team_profile.php?team_id=${data.player.team_id}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-eye"></i> View Team
              </a>
            </div>
          `;
        } else {
          modalBody.innerHTML = `
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle"></i> Error loading player details: ${data.message}
            </div>
          `;
        }
      })
      .catch(error => {
        modalBody.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> Error loading player details. Please try again.
          </div>
        `;
      });
  }

  function copyDashboardStandings() {
  const standings = <?= json_encode($topStandings) ?>;
  const league = <?= json_encode($league) ?>;
  
  let text = `🏆 *${league.name} - Top 5 Standings*\n`;
  text += `📅 ${new Date().toLocaleDateString()}\n\n`;
  
  standings.forEach((team, index) => {
    const pos = index + 1;
    const emoji = pos === 1 ? '👑' : pos <= 4 ? '🥉' : '⚽';
    text += `${emoji} ${pos}. *${team.name}* - ${team.points} pts\n`;
    text += `   GP: ${team.played} | W: ${team.wins} | L: ${team.losses} | GD: ${team.goal_difference >= 0 ? '+' : ''}${team.goal_difference}\n\n`;
  });
  
  text += `⚡ Full standings on Nukta League Platform`;
  
  navigator.clipboard.writeText(text).then(() => {
    // Show success notification
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; width: 300px;';
    alert.innerHTML = `
      <strong>✅ Copied!</strong> Text ready for WhatsApp sharing
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
      alert.remove();
    }, 3000);
  }).catch(err => {
    alert('Failed to copy to clipboard');
  });
}

  // Poster Maker functionality
  function showPosterMaker() {
    console.log('🎨 showPosterMaker() called'); 
    
    try {
      // Check if Bootstrap is loaded
      if (typeof bootstrap === 'undefined') {
        console.error('❌ Bootstrap is not loaded!');
        alert('Error: Bootstrap library not loaded. Please refresh the page and try again.');
        return;
      }
      
      // Check if modal element exists
      const modalElement = document.getElementById('posterModal');
      if (!modalElement) {
        console.error('❌ Modal element #posterModal not found!');
        alert('Error: Poster modal not found. Opening debug page instead...');
        window.open('/ncl-league-platform/test_modal_debug.php', '_blank');
        return;
      }
      
      console.log('✅ Modal element found:', modalElement);
      
      // Create and show modal
      const modal = new bootstrap.Modal(modalElement);
      console.log('✅ Bootstrap modal object created:', modal);
      
      modal.show();
      console.log('✅ Modal.show() called');
      
      // Load fixtures with a slight delay to ensure modal is shown
      setTimeout(() => {
        console.log('⏳ Loading fixtures...');
        loadFixturesForPoster();
      }, 300);
      
    } catch (error) {
      console.error('❌ Error in showPosterMaker():', error);
      alert('Error opening poster maker: ' + error.message + '\n\nOpening debug page instead...');
      window.open('/ncl-league-platform/test_modal_debug.php', '_blank');
    }
  }

  function loadFixturesForPoster() {
    const fixturesList = document.getElementById('fixturesList');
    const leagueId = <?= $leagueId ?>;
    
    fetch(`<?= $base ?>/api/get_fixtures_for_poster.php?league_id=${leagueId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          renderFixturesList(data.fixtures);
        } else {
          fixturesList.innerHTML = `
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i> No fixtures found for poster generation
            </div>
          `;
        }
      })
      .catch(error => {
        fixturesList.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> Error loading fixtures
          </div>
        `;
      });
  }

  function renderFixturesList(fixtures) {
    const fixturesList = document.getElementById('fixturesList');
    
    let html = '';
    fixtures.forEach(fixture => {
      const matchDate = new Date(fixture.match_date).toLocaleDateString();
      const matchTime = fixture.match_time ? new Date(`2000-01-01 ${fixture.match_time}`).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'TBD';
      const isResult = fixture.status === 'played' && fixture.score_home !== null;
      const posterType = isResult ? 'Final Result' : 'Upcoming Match';
      const badgeClass = isResult ? 'bg-success' : 'bg-primary';
      
      html += `
        <div class="list-group-item list-group-item-action poster-fixture" 
             data-fixture-id="${fixture.fixture_id}" 
             data-type="${isResult ? 'result' : 'upcoming'}"
             onclick="selectFixtureForPoster(${fixture.fixture_id}, '${isResult ? 'result' : 'upcoming'}')">
          <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
              <h6 class="mb-1">
                ${fixture.home_team} vs ${fixture.away_team}
                <span class="badge ${badgeClass} ms-2">${posterType}</span>
              </h6>
              <p class="mb-1">
                <i class="fas fa-calendar"></i> ${matchDate} ${matchTime}
                ${fixture.venue ? `<br><i class="fas fa-map-marker-alt"></i> ${fixture.venue}` : ''}
              </p>
              ${isResult ? `<p class="mb-0 fw-bold text-success">Score: ${fixture.score_home} - ${fixture.score_away}</p>` : ''}
            </div>
            <small class="text-muted">Click to generate</small>
          </div>
        </div>
      `;
    });
    
    fixturesList.innerHTML = html;
  }

  function selectFixtureForPoster(fixtureId, type) {
    // Highlight selected fixture
    document.querySelectorAll('.poster-fixture').forEach(el => el.classList.remove('active'));
    document.querySelector(`[data-fixture-id="${fixtureId}"]`).classList.add('active');
    
    // Generate poster preview
    generatePosterPreview(fixtureId, type);
  }

  function generatePosterPreview(fixtureId, type) {
    const preview = document.getElementById('posterPreview');
    const actions = document.getElementById('posterActions');
    
    preview.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Generating poster...</span>
        </div>
        <p class="mt-2">Creating your poster...</p>
      </div>
    `;
    
    fetch(`<?= $base ?>/api/poster_generator.php?fixture_id=${fixtureId}&type=${type}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          preview.innerHTML = `
            <img src="<?= $base ?>/${data.poster_url}" 
                 class="img-fluid rounded shadow" 
                 alt="Generated Poster" 
                 style="max-height: 400px;">
            <p class="mt-2 text-success">
              <i class="fas fa-check-circle"></i> Poster generated successfully!
            </p>
          `;
          
          // Setup download functionality
          document.getElementById('downloadPoster').onclick = () => {
            window.open(`<?= $base ?>/api/poster_generator.php?fixture_id=${fixtureId}&type=${type}&action=download`, '_blank');
          };
          
          document.getElementById('sharePoster').onclick = () => {
            const shareUrl = `${window.location.origin}<?= $base ?>/${data.poster_url}`;
            navigator.clipboard.writeText(shareUrl).then(() => {
              showToast('✅ Share link copied to clipboard!', 'success');
            });
          };
          
          actions.classList.remove('d-none');
        } else {
          preview.innerHTML = `
            <div class="alert alert-danger">
              <i class="fas fa-exclamation-triangle"></i> Error: ${data.error}
            </div>
          `;
        }
      })
      .catch(error => {
        preview.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> Error generating poster
          </div>
        `;
      });
  }

  function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; width: 300px;';
    toast.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
      toast.remove();
    }, 4000);
  }
</script>

<style>
  .bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }
  
  .bg-gradient-success {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  }
  
  .bg-gradient-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  }
  
  .bg-gradient-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  }
  
  .hover-lift {
    transition: all 0.3s ease;
  }
  
  .hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
  }
  
  .card {
    border: none;
    transition: all 0.3s ease;
  }
  
  .btn {
    transition: all 0.2s ease;
  }
  
  .btn:hover {
    transform: translateY(-2px);
  }
  
  .table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.1);
  }
  
  .badge {
    font-size: 0.8em;
    font-weight: 600;
  }
  
  .display-6 {
    font-size: 2rem;
  }
  
  .table th {
    font-weight: 600;
    border-top: none;
  }
  
  .search-results-table {
    font-size: 0.95rem;
  }
  
  .action-btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
    margin: 0.1rem;
  }
  
  .stats-card {
    background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,249,250,0.9) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.3);
  }
  
  .admin-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
  }
  
  .admin-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" fill="rgba(255,255,255,0.1)"><polygon points="1000,100 1000,0 0,100"/></svg>');
    background-size: cover;
  }
  
  .admin-header .card-body {
    position: relative;
    z-index: 1;
  }
  
  /* Poster Maker Styles */
  .poster-fixture {
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .poster-fixture:hover {
    background-color: rgba(0,123,255,0.1);
    transform: translateX(5px);
  }
  
  .poster-fixture.active {
    background-color: rgba(0,123,255,0.2);
    border-left: 4px solid #007bff;
  }
  
  #posterPreview img {
    transition: transform 0.3s ease;
  }
  
  #posterPreview img:hover {
    transform: scale(1.05);
  }
  
  .modal-lg {
    max-width: 90%;
  }
  
  @media (max-width: 768px) {
    .modal-lg {
      max-width: 95%;
    }
  }
  
  @media (max-width: 768px) {
    .display-5 {
      font-size: 2rem;
    }
    
    .display-6 {
      font-size: 1.5rem;
    }
  }
</style>

<?php include('../includes/footer.php'); ?>