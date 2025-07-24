<?php
session_start();
require_once('db_connect.php');
include('includes/header.php');
include('includes/navbar.php');

// Handle league selection
if (isset($_POST['league_id'])) {
  $_SESSION['league_id'] = intval($_POST['league_id']);
  header("Location: leagues/home.php");
  exit;
}

$leagues = $conn->query("
  SELECT league_id, name, abbreviation, logo_url
  FROM leagues
  ORDER BY abbreviation
");
?>
<!-- 🎉 Hero Section -->
<section
  class="hero-section text-white text-center d-flex align-items-center justify-content-center"
  style="min-height:95vh;"
  data-aos="fade-down"
  data-aos-duration="900"
>
  <div class="container py-5">
    <h1
      class="display-2 fw-bold mb-3"
      data-aos="fade-down"
      data-aos-delay="200"
    >
      League Management System
    </h1>
    <p
      class="lead mb-4 fs-4"
      data-aos="fade-up"
      data-aos-delay="400"
    >
      Professional sports league management powered by Nukta. Streamlining competitions with modern technology and innovative solutions.
    </p>
    <div
      class="d-flex flex-wrap justify-content-center gap-3"
      data-aos="fade-up"
      data-aos-delay="600"
    >
      <a href="#leagues" class="btn btn-accent btn-lg px-4">Explore Our Leagues</a>
      <a href="leagues/standings.php" class="btn btn-outline-primary btn-lg px-4" style="border-color: #0000ff; color: #0000ff;">View Standings</a>
    </div>
  </div>
</section>

<!-- 🏀 League Grid -->
<section
  id="leagues"
  class="container my-5"
  data-aos="fade-up"
  data-aos-duration="800"
  data-aos-delay="200"
>
  <h2
    class="text-center text-accent fw-bold display-5 mb-5"
    data-aos="fade-down"
    data-aos-delay="300"
  >
    Choose Your League
  </h2>
  <!-- Professional League Cards -->
  <div class="row g-4 justify-content-center">
    <?php
    $animations = ['fade-up', 'fade-up', 'fade-up', 'fade-up'];
    $colors = [
      'NCL' => '#0000ff',
      'WEL' => '#DF2A57', 
      'FSL' => '#FFA908',
      'YBL' => '#462022',
      'SIEL' => '#F9D96B',
    ];
    $i = 0;
    while ($league = $leagues->fetch_assoc()):
      $abbr = $league['abbreviation'];
      $primaryColor = $colors[$abbr] ?? '#2d3748';
      $hasLogo = !empty($league['logo_url']);
      $taglines = [
        'NCL' => 'Nairobi\'s premier basketball competition',
        'WEL' => 'Empowering women through basketball',
        'FSL' => 'Developing young basketball talent',
        'YBL' => 'Youth basketball development league',
        'SIEL' => 'Empowering African basketball talent and creating a world-class professional competition that showcases the best of African sports.

',
      ];
      $tagline = $taglines[$abbr] ?? 'Professional basketball league';
      $delay = 100 * ($i + 1);
      $i++;
    ?>
      <div class="col-sm-6 col-lg-4 col-xl-3" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
        <form method="POST" class="league-card-professional">
          <input type="hidden" name="league_id" value="<?= $league['league_id'] ?>">
          
          <div class="card border-0 h-100" 
               style="border-radius: 12px; 
                      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                      transition: all 0.3s ease;
                      cursor: pointer;
                      background: #ffffff;"
               onmouseover="this.style.transform='translateY(-4px)'; 
                          this.style.boxShadow='0 8px 30px rgba(0,0,0,0.12)';"
               onmouseout="this.style.transform='translateY(0)'; 
                         this.style.boxShadow='0 4px 20px rgba(0,0,0,0.08)';">
            
            <!-- Card Header -->
            <div class="card-header border-0 p-0" 
                 style="background: <?= $primaryColor ?>; 
                        border-radius: 12px 12px 0 0; 
                        height: 60px;">
              
              <!-- League Abbreviation -->
              <div class="d-flex align-items-center justify-content-between p-3">
                <span class="text-white fw-bold" style="font-size: 0.9rem; letter-spacing: 0.5px;">
                  <?= htmlspecialchars($abbr) ?>
                </span>
                <div style="width: 8px; height: 8px; background: rgba(255,255,255,0.6); border-radius: 50%;"></div>
              </div>
            </div>
            
            <!-- Card Body -->
            <div class="card-body p-4 d-flex flex-column">
              
              <!-- Logo/Avatar Section -->
              <div class="text-center mb-3" style="margin-top: -30px;">
                <?php if ($hasLogo): ?>
                  <!-- League Logo -->
                  <div class="logo-container mx-auto" 
                       style="width: 150px; height: 150px; background: white; 
                              border-radius: 10px; padding: 8px;
                              box-shadow: 0 4px 16px rgba(0,0,0,0.1);
                              border: 1px solid #f7fafc;">
                    <img src="<?= htmlspecialchars($league['logo_url']) ?>"
                         alt="<?= htmlspecialchars($league['name']) ?> Logo"
                         style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px;">
                  </div>
                <?php else: ?>
                  <!-- Initials Avatar -->
                  <div class="avatar-container mx-auto d-flex align-items-center justify-content-center" 
                       style="width: 60px; height: 60px; background: <?= $primaryColor ?>; 
                              border-radius: 10px; 
                              box-shadow: 0 4px 16px rgba(0,0,0,0.1);">
                    <span style="color: white; font-weight: 600; font-size: 1.2rem; letter-spacing: 0.5px;">
                      <?= strtoupper(substr($abbr, 0, 4)) ?>
                    </span>
                  </div>
                <?php endif; ?>
              </div>
              
              <!-- League Name -->
              <h5 class="card-title text-center fw-bold mb-2" 
                  style="color: #2d3748; font-size: 1rem; line-height: 1.3;">
                <?= htmlspecialchars($league['name']) ?>
              </h5>
              
              <!-- League Type -->
              <div class="text-center mb-3">
                <span class="badge" 
                      style="background: <?= $primaryColor ?>15; 
                             color: <?= $primaryColor ?>; 
                             border-radius: 6px; 
                             font-size: 0.75rem; 
                             font-weight: 500;
                             padding: 4px 8px;">
                  <?= $hasLogo ? 'Official' : 'Community' ?>
                </span>
              </div>
              
              <!-- Description -->
              <div class="flex-grow-1 d-flex align-items-center">
                <p class="text-muted text-center mb-0" 
                   style="font-size: 0.85rem; line-height: 1.4;">
                  <?= $tagline ?>
                </p>
              </div>
              
              <!-- Enter Button -->
              <div class="mt-4">
                <button type="submit" 
                        class="btn w-100 text-white fw-medium"
                        style="background: <?= $primaryColor ?>; 
                               border: none; 
                               border-radius: 8px; 
                               padding: 12px; 
                               font-size: 0.9rem;
                               transition: all 0.2s ease;"
                        onmouseover="this.style.background='<?= $primaryColor ?>dd';"
                        onmouseout="this.style.background='<?= $primaryColor ?>';">
                  Enter League
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    <?php endwhile; ?>
  </div>
</section>

<!-- 📊 League Snapshot -->
<section
  class="container py-5"
  data-aos="fade-up"
  data-aos-duration="800"
  data-aos-delay="200"
>
  <h2
    class="text-center text-accent fw-bold display-5 mb-5"
    data-aos="fade-down"
    data-aos-delay="300"
  >
    League Snapshot
  </h2>
  <div class="row g-4 justify-content-center text-center">
    <?php 
    // Fetch real-time statistics from database
    $leagues_count = $conn->query("SELECT COUNT(*) as count FROM leagues")->fetch_assoc()['count'];
    $teams_count = $conn->query("SELECT COUNT(*) as count FROM teams")->fetch_assoc()['count'];
    $games_played = $conn->query("SELECT COUNT(*) as count FROM fixtures WHERE status = 'played'")->fetch_assoc()['count'];
    $active_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    
    // Get additional stats for more dynamic display
    $total_matches = $conn->query("SELECT COUNT(*) as count FROM match_results WHERE cancelled_by_referee = 0")->fetch_assoc()['count'];
    $upcoming_games = $conn->query("SELECT COUNT(*) as count FROM fixtures WHERE status = 'upcoming'")->fetch_assoc()['count'];
    
    $stats = [
      ['count'=>$leagues_count,'label'=>'Active Leagues','color'=>'primary','icon'=>''],
      ['count'=>$teams_count,'label'=>'Teams Registered','color'=>'accent','icon'=>''],
      ['count'=>$games_played,'label'=>'Games Played','color'=>'success','icon'=>''],
      ['count'=>$active_users,'label'=>'Active Users','color'=>'info','icon'=>''],
    ];
    
    foreach ($stats as $idx => $s): 
    ?>
      <div class="col-6 col-md-3" data-aos="zoom-in-up" data-aos-delay="<?= 100*($idx+1) ?>">
        <div class="p-4 bg-white rounded shadow-sm border-top border-4 border-<?= $s['color'] ?> h-100">
          <div class="fs-1 mb-2"><?= $s['icon'] ?></div>
          <div class="fs-2 fw-bold text-<?= $s['color'] ?>"><?= $s['count'] ?></div>
          <p class="text-muted mb-0"><?= $s['label'] ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  
  <!-- Additional Stats Row -->
  <div class="row g-4 justify-content-center text-center mt-4">
    <div class="col-6 col-md-3" data-aos="zoom-in-up" data-aos-delay="500">
      <div class="p-3 bg-gradient-primary text-white rounded shadow-sm">
        <div class="fs-3 fw-bold"><?= $total_matches ?></div>
        <p class="mb-0 small">Total Results</p>
      </div>
    </div>
    <div class="col-6 col-md-3" data-aos="zoom-in-up" data-aos-delay="600">
      <div class="p-3 bg-gradient-success text-white rounded shadow-sm">
        <div class="fs-3 fw-bold"><?= $upcoming_games ?></div>
        <p class="mb-0 small">Upcoming Games</p>
      </div>
    </div>
    <?php 
    // Get most active league
    $active_league = $conn->query("
      SELECT l.name, COUNT(f.fixture_id) as games 
      FROM leagues l 
      LEFT JOIN fixtures f ON l.league_id = f.league_id 
      WHERE f.status = 'played' 
      GROUP BY l.league_id 
      ORDER BY games DESC 
      LIMIT 1
    ")->fetch_assoc();
    ?>
    <div class="col-6 col-md-3" data-aos="zoom-in-up" data-aos-delay="700">
      <div class="p-3 bg-gradient-info text-white rounded shadow-sm">
        <div class="fs-6 fw-bold"><?= $active_league['name'] ?? 'N/A' ?></div>
        <p class="mb-0 small">Most Active League</p>
      </div>
    </div>
    <?php 
    // Get latest match result
    $latest_match = $conn->query("
      SELECT f.match_date 
      FROM fixtures f 
      JOIN match_results mr ON f.fixture_id = mr.fixture_id 
      WHERE mr.cancelled_by_referee = 0 
      ORDER BY f.match_date DESC 
      LIMIT 1
    ")->fetch_assoc();
    ?>
    <div class="col-6 col-md-3" data-aos="zoom-in-up" data-aos-delay="800">
      <div class="p-3 bg-gradient-warning text-white rounded shadow-sm">
        <div class="fs-6 fw-bold"><?= $latest_match ? date('M j', strtotime($latest_match['match_date'])) : 'N/A' ?></div>
        <p class="mb-0 small">Latest Match</p>
      </div>
    </div>
  </div>
  
  <p
    class="text-center text-white-50 mt-4"
    data-aos="fade-in"
    data-aos-delay="500"
  >
    Data reflects real-time activity across all leagues. Last updated: <?= date('M j, Y \a\t g:i A') ?>
  </p>
</section>

<!-- 🔗 Quick Links -->
<section
  class="py-5 bg-dark border-top"
  data-aos="fade-up"
  data-aos-duration="800"
  data-aos-delay="200"
>
  <div class="container text-center">
    <h4
      class="text-accent fw-bold mb-4"
      data-aos="fade-down"
      data-aos-delay="300"
    >
      Already part of the action?
    </h4>
    <div
      class="d-flex flex-wrap justify-content-center gap-3"
      data-aos="fade-up"
      data-aos-delay="400"
    >
      <a data-aos="zoom-in" data-aos-delay="450" href="leagues/fixtures.php" class="btn btn-outline-accent px-4">Fixtures</a>
      <a data-aos="zoom-in" data-aos-delay="550" href="leagues/standings.php" class="btn btn-accent px-4">Standings</a>
      <a data-aos="zoom-in" data-aos-delay="650" href="leagues/teams.php" class="btn btn-outline-accent px-4">Meet the Teams</a>
    </div>
  </div>
</section>

<?php include('includes/footer.php'); ?>