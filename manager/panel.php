<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('manager');

$userId   = $_SESSION['user_id'];
$userName = $_SESSION['name'];

// Fetch teams this manager handles, including description and logo
$stmt = $conn->prepare("
  SELECT 
    t.team_id, 
    t.name         AS team_name,
    t.description,
    t.logo_url,
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

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); 
                min-height: 350px; position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('<?= $base ?>/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-4" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center justify-content-center mb-3">
                    <div class="icon-badge me-3" 
                         style="width: 60px; height: 60px; background: rgba(255, 255, 255, 0.2); 
                                border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user-tie fa-2x text-white"></i>
                    </div>
                    <h1 class="hero-heading display-4 fw-bold mb-0 text-white">Team Manager Hub</h1>
                </div>
                <p class="lead mb-3 text-white">Welcome back, <span class="fw-bold"><?= htmlspecialchars($userName) ?></span></p>
                <p class="text-white-50 mb-4">Manage your teams, players, and track performance</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<div class="container py-5" style="background: white; margin-top: 2rem;">

  <?php if (empty($teamList)): ?>
    <div class="card border-0 shadow-lg" style="border-radius: 20px;">
      <div class="card-body text-center py-5">
        <i class="fas fa-users-slash fa-4x text-muted mb-4"></i>
        <h4 class="text-muted mb-3">No Teams Assigned</h4>
        <p class="text-muted mb-4">You are not currently assigned to manage any teams. Please contact your administrator for team assignments.</p>
        <div class="alert border-0" 
             style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05)); 
                    color: #856404; border-radius: 15px;">
          <i class="fas fa-info-circle me-2"></i>Team assignments are managed by system administrators
        </div>
      </div>
    </div>
  <?php else: ?>
    <!-- Teams Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" data-aos="fade-up">
      <?php foreach ($teamList as $team): ?>
        <div class="col">
          <div class="card border-0 shadow-lg h-100" 
               style="border-radius: 20px; overflow: hidden; transition: all 0.3s ease;"
               onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0, 0, 255, 0.15)'"
               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(0, 0, 0, 0.1)'">
            
            <!-- Card Header -->
            <div class="card-header border-0 py-4" 
                 style="background: linear-gradient(135deg, #0000ff, #4169E1);">
              <div class="d-flex align-items-center">
                
                <!-- Team Logo/Avatar -->
                <?php 
                $hasLogo = !empty($team['logo_url']);
                $logoUrl = $hasLogo ? '../' . ltrim($team['logo_url'], '/') : '';
                $teamInitials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $team['team_name'])));
                ?>
                
                <div class="team-logo-container me-3" style="width: 60px; height: 60px; position: relative;">
                  <?php if ($hasLogo): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>" 
                         alt="<?= htmlspecialchars($team['team_name']) ?> Logo"
                         class="team-logo"
                         style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; 
                                border: 3px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="team-avatar d-none align-items-center justify-content-center fw-bold"
                         style="width: 60px; height: 60px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1)); 
                                border-radius: 50%; color: white; font-size: 1.5rem; 
                                border: 3px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                      <?= htmlspecialchars($teamInitials) ?>
                    </div>
                  <?php else: ?>
                    <div class="team-avatar d-flex align-items-center justify-content-center fw-bold"
                         style="width: 60px; height: 60px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1)); 
                                border-radius: 50%; color: white; font-size: 1.5rem; 
                                border: 3px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                      <?= htmlspecialchars($teamInitials) ?>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="flex-grow-1">
                  <h5 class="mb-1 fw-bold text-white"><?= htmlspecialchars($team['team_name']) ?></h5>
                  <span class="badge px-3 py-2 fw-bold" 
                        style="background: rgba(255, 255, 255, 0.2); color: white; border-radius: 15px;">
                    <?= htmlspecialchars($team['league_abbr']) ?>
                  </span>
                </div>
              </div>
            </div>

            <!-- Card Body -->
            <div class="card-body d-flex flex-column p-4" style="background: white;">
              
              <?php if (!empty($team['description'])): ?>
                <div class="mb-3">
                  <p class="text-muted small mb-0" style="line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($team['description'])) ?>
                  </p>
                </div>
              <?php endif; ?>

              <p class="card-text text-muted mb-4">
                <i class="fas fa-info-circle me-2" style="color: #0000ff;"></i>
                Manage your team roster, view fixtures, and track performance
              </p>

              <!-- Action Buttons -->
              <div class="mt-auto">
                <div class="d-grid gap-3">
                  
                  <!-- Primary Action - Manage Roster -->
                  <a href="add_players.php?team_id=<?= $team['team_id'] ?>" 
                     class="btn py-3 fw-bold" 
                     style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                            color: white; border: none; border-radius: 15px; 
                            transition: all 0.3s ease; text-decoration: none;"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(0, 0, 255, 0.3)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <i class="fas fa-users me-2"></i>Manage Roster
                  </a>
                  
                  <!-- Secondary Actions Row -->
                  <div class="row g-2">
                    <div class="col-6">
                      <a href="../leagues/fixtures.php?team_id=<?= $team['team_id'] ?>" 
                         class="btn btn-sm w-100 py-2 fw-bold" 
                         style="background: transparent; color: #0000ff; border: 2px solid #0000ff; 
                                border-radius: 12px; transition: all 0.3s ease; text-decoration: none;"
                         onmouseover="this.style.background='rgba(0, 0, 255, 0.1)'"
                         onmouseout="this.style.background='transparent'">
                        <i class="fas fa-calendar me-1"></i>Fixtures
                      </a>
                    </div>
                    <div class="col-6">
                      <a href="../leagues/team_profile.php?team_id=<?= $team['team_id'] ?>" 
                         class="btn btn-sm w-100 py-2 fw-bold" 
                         style="background: transparent; color: #17a2b8; border: 2px solid #17a2b8; 
                                border-radius: 12px; transition: all 0.3s ease; text-decoration: none;"
                         onmouseover="this.style.background='rgba(23, 162, 184, 0.1)'"
                         onmouseout="this.style.background='transparent'">
                        <i class="fas fa-eye me-1"></i>Profile
                      </a>
                    </div>
                  </div>
                  
                  <!-- Edit Team Button -->
                  <a href="edit_team.php?team_id=<?= $team['team_id'] ?>" 
                     class="btn py-2 fw-bold" 
                     style="background: linear-gradient(135deg, #ffc107, #ffed4e); 
                            color: #333; border: none; border-radius: 12px; 
                            transition: all 0.3s ease; text-decoration: none;"
                     onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(255, 193, 7, 0.3)'"
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <i class="fas fa-edit me-2"></i>Edit Team Info
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Navigation -->
  <div class="text-center mt-5" data-aos="fade-in">
    <a href="../logout.php" class="btn px-5 py-3 fw-bold" 
       style="background: linear-gradient(135deg, #dc3545, #c82333); color: white; 
              border-radius: 50px; border: none; box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3); 
              transition: all 0.3s ease; text-decoration: none;"
       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 35px rgba(220, 53, 69, 0.4)'"
       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(220, 53, 69, 0.3)'">
      <i class="fas fa-sign-out-alt me-2"></i>Logout
    </a>
  </div>
</div>

<?php include('../includes/footer.php'); ?>
