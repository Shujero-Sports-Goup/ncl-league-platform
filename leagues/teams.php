<?php
session_start();
require_once('../db_connect.php');

$leagueId = $_SESSION['league_id'] ?? 1;
$league = $conn->query("SELECT name FROM leagues WHERE league_id = $leagueId")->fetch_assoc();
$leagueName = $league['name'] ?? 'League';

// Get teams with additional stats for better display
$teams = $conn->query("
    SELECT t.team_id, t.name, t.coach_name, t.logo_url,
           COUNT(p.player_id) as player_count
    FROM teams t 
    LEFT JOIN players p ON t.team_id = p.team_id 
    WHERE t.league_id = $leagueId 
    GROUP BY t.team_id
    ORDER BY t.name
");

$totalTeams = $teams->num_rows;

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.9) 50%, #0000ff 100%); min-height: 400px; position: relative;">
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('/ncl-league-platform/assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.1; z-index: 0;"></div>
    
    <div class="container py-5" data-aos="fade-down" data-aos-duration="800" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1 class="hero-heading display-3 fw-bold mb-4 text-white"><?= htmlspecialchars($leagueName) ?> Teams</h1>
                <p class="hero-subtext lead mb-4 text-white">Discover the powerhouse teams driving excellence in basketball — where talent meets determination.</p>
                <div class="hero-divider mx-auto" style="width: 100px; height: 3px; background: white; border-radius: 2px;"></div>
            </div>
        </div>
    </div>
</section>

<!-- Teams Stats Overview -->
<section class="container py-5">
    <div class="teams-stats p-4 mb-5" data-aos="fade-up" data-aos-duration="600" 
         style="background: linear-gradient(135deg, rgba(0, 0, 255, 0.05), rgba(0, 0, 255, 0.02)); 
                border-radius: 20px; border: 1px solid rgba(0, 0, 255, 0.1);">
        <div class="row">
            <div class="col-md-4">
                <div class="text-center p-3">
                    <span class="d-block fw-bold text-primary" style="font-size: 2.5rem; color: #0000ff !important;"><?= $totalTeams ?></span>
                    <span class="text-muted fw-medium" style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.9rem;">Active Teams</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3">
                    <span class="d-block fw-bold text-primary" style="font-size: 1.5rem; color: #0000ff !important;"><?= htmlspecialchars($leagueName) ?></span>
                    <span class="text-muted fw-medium" style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.9rem;">League</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3">
                    <span class="d-block fw-bold text-primary" style="font-size: 2.5rem; color: #0000ff !important;">2025</span>
                    <span class="text-muted fw-medium" style="text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.9rem;">Season</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Teams Grid Section -->
<section style="background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 100%); padding: 80px 0;">
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold mb-3" style="color: #0000ff; font-size: 2.5rem;">Meet the Teams</h2>
                <p class="text-muted lead">Each team brings unique strengths and strategies to the court</p>
            </div>
        </div>

        <?php if ($totalTeams > 0): ?>
            <div class="row g-4">
                <?php 
                $i = 0;
                $teams->data_seek(0); // Reset result pointer
                while ($team = $teams->fetch_assoc()):
                    $name = htmlspecialchars($team['name']);
                    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $name)));
                    $hasLogo = !empty($team['logo_url']);
                    $playerCount = $team['player_count'] ?? 0;
                ?>
                    <div class="col-xl-4 col-lg-6 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
                        <div class="card h-100 border-0 shadow-sm" 
                             style="border-radius: 20px; 
                                    background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%); 
                                    border: 1px solid rgba(0, 0, 255, 0.1) !important;
                                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                    position: relative;
                                    overflow: hidden;"
                             onmouseover="this.style.transform='translateY(-10px)'; this.style.boxShadow='0 20px 40px rgba(0, 0, 255, 0.15)'; this.style.borderColor='#0000ff';"
                             onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(0, 0, 0, 0.1)'; this.style.borderColor='rgba(0, 0, 255, 0.1)';">
                            
                            <!-- Top border accent -->
                            <div style="position: absolute; top: 0; left: 0; right: 0; height: 4px; 
                                        background: linear-gradient(90deg, #0000ff, #4169E1); opacity: 0; transition: opacity 0.3s ease;"
                                 class="team-border"></div>

                            <div class="card-body d-flex flex-column align-items-center text-center p-4">
                                
                                <!-- Team Logo/Avatar -->
                                <div class="mb-4">
                                    <?php if ($hasLogo): ?>
                                        <img
                                            src="/ncl-league-platform/<?= htmlspecialchars($team['logo_url']) ?>"
                                            alt="<?= $name ?> Logo"
                                            class="rounded-circle"
                                            style="width: 100px; height: 100px; object-fit: cover; 
                                                   border: 3px solid rgba(0, 0, 255, 0.1); 
                                                   transition: all 0.3s ease;"
                                            onmouseover="this.style.transform='scale(1.1)'; this.style.borderColor='#0000ff'; this.style.boxShadow='0 10px 25px rgba(0, 0, 255, 0.2)';"
                                            onmouseout="this.style.transform='scale(1)'; this.style.borderColor='rgba(0, 0, 255, 0.1)'; this.style.boxShadow='none';" />
                                    <?php else: ?>
                                        <div style="width: 100px; height: 100px; 
                                                    background: linear-gradient(135deg, #0000ff, #4169E1); 
                                                    color: white; border-radius: 50%; 
                                                    display: flex; align-items: center; justify-content: center; 
                                                    font-size: 2rem; font-weight: bold; 
                                                    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
                                                    transition: all 0.3s ease;"
                                             onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 10px 25px rgba(0, 0, 255, 0.3)';"
                                             onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                            <?= $initials ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Team Information -->
                                <div class="mb-3 w-100">
                                    <h5 class="fw-bold mb-3" style="color: #0000ff; font-size: 1.4rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?= $name ?>
                                    </h5>
                                    
                                    <div class="p-2 mb-3" style="background: rgba(0, 0, 255, 0.05); border-radius: 20px; border-left: 3px solid #0000ff;">
                                        <small class="text-muted d-block">Head Coach</small>
                                        <span class="fw-semibold" style="color: #0000ff;"><?= htmlspecialchars($team['coach_name'] ?: 'TBA') ?></span>
                                    </div>

                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1" style="color: #0000ff;"></i>
                                            <?= $playerCount ?> Players
                                        </small>
                                    </div>
                                </div>

                                <!-- Action Button -->
                                <div class="mt-auto">
                                    <a href="team_profile.php?team_id=<?= $team['team_id'] ?>" 
                                       class="btn text-white fw-semibold px-4 py-2"
                                       style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                              border: none; border-radius: 25px; 
                                              text-transform: uppercase; letter-spacing: 0.5px;
                                              transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(0, 0, 255, 0.2);
                                              text-decoration: none;"
                                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 255, 0.3)'; this.style.background='linear-gradient(135deg, #0000CC, #0000ff)';"
                                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 0, 255, 0.2)'; this.style.background='linear-gradient(135deg, #0000ff, #4169E1)';">
                                        <i class="fas fa-eye me-2"></i>View Team Profile
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php 
                $i++;
                endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">
                    <div class="py-5">
                        <i class="fas fa-basketball-ball fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Teams Found</h4>
                        <p class="text-muted">Teams will appear here once they're registered for this league.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back to League Button -->
        <div class="text-center mt-5">
            <a href="home.php" 
               class="btn fw-semibold px-5 py-3"
               style="background: transparent; border: 2px solid #0000ff; color: #0000ff; 
                      border-radius: 30px; text-transform: uppercase; letter-spacing: 1px;
                      transition: all 0.3s ease; text-decoration: none;"
               onmouseover="this.style.background='#0000ff'; this.style.color='white'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 255, 0.2)';"
               onmouseout="this.style.background='transparent'; this.style.color='#0000ff'; this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                <i class="fas fa-arrow-left me-2"></i>
                Back to League Home
            </a>
        </div>
    </div>
</section>
<!-- AOS Animation Script -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        offset: 100,
        once: true,
        easing: 'ease-out-cubic'
    });

    // Add hover effects for team cards
    document.addEventListener('DOMContentLoaded', function() {
        const teamCards = document.querySelectorAll('.card');
        teamCards.forEach((card, index) => {
            // Add staggered animation delay
            card.style.animationDelay = `${index * 0.1}s`;
            
            // Enhanced hover effect for top border
            card.addEventListener('mouseenter', function() {
                const border = this.querySelector('.team-border');
                if (border) border.style.opacity = '1';
            });
            
            card.addEventListener('mouseleave', function() {
                const border = this.querySelector('.team-border');
                if (border) border.style.opacity = '0';
            });
        });
    });
</script>

<?php include('../includes/footer.php'); ?>