<?php
session_start();
require_once('../db_connect.php');

$leagueId = $_SESSION['league_id'] ?? 1;
$league = $conn->query("SELECT name FROM leagues WHERE league_id = $leagueId")->fetch_assoc();
$leagueName = $league['name'] ?? 'League';

$teams = $conn->query("SELECT team_id, name, coach_name, logo_url FROM teams WHERE league_id = $leagueId");

include('../includes/header.php');
include('../includes/navbar.php');
?>

<!-- Hero Section -->
<section class="hero-banner-teams d-flex align-items-center text-white text-center">
  <div class="container py-5" data-aos="fade-down" data-aos-duration="800">
    <h1 class="hero-heading display-4 fw-bold mb-3"><?= htmlspecialchars($leagueName) ?> Teams</h1>
    <p class="hero-subtext lead mb-0">Explore the rosters shaping the future of Kenyan basketball — talent, grit, and heart on full display.</p>
  </div>
</section>


<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({
    duration: 800,
    offset: 80,
    once: true
  });
</script>
<!-- Team Grid -->
<?php $i = 0; // counter for animation delay 
?>
<section class="container py-5">
  <div class="row g-4">

    <?php while ($team = $teams->fetch_assoc()):
      $name = htmlspecialchars($team['name']);
      $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $name)));
      $hasLogo = !empty($team['logo_url']);
    ?>

      <!-- 👇 Added AOS animation with staggered delay -->
      <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $i * 100 ?>">
        <div class="card team-card h-100 border-0 shadow-lg bg-dark text-white position-relative">
          <div class="card-body d-flex flex-column align-items-center text-center p-4">

            <!-- Logo or Initials Avatar with hover scale -->
            <?php if ($hasLogo): ?>
              <img
                src="/ncl-league-platform/<?= htmlspecialchars($team['logo_url']) ?>"
                alt="<?= $name ?> Logo"
                class="mb-3 team-logo rounded-circle border border-light shadow team-image"
                style="width: 100px; height: 100px; object-fit: cover;" />


            <?php else: ?>
              <div class="mb-3 team-avatar fw-bold border border-light shadow team-image">
                <?= $initials ?>
              </div>
            <?php endif; ?>

            <!-- Team Name -->
            <h5 class="fw-bold text-warning mb-1"><?= $name ?></h5>

            <!-- Coach Info -->
            <p class="small text-muted mb-2">
              Coach: <span class="coach-name"><?= htmlspecialchars($team['coach_name'] ?: '—') ?></span>
            </p>

            <!-- CTA Button -->
            <a href="team_profile.php?team_id=<?= $team['team_id'] ?>" class="btn btn-outline-light btn-sm mt-auto">
              View Profile
            </a>

          </div>
        </div>
      </div>

    <?php $i++;
    endwhile; ?>

  </div>
</section>




<div class="text-center mt-5">
  <a href="home.php" class="btn btn-outline-accent px-4">⬅ Back to League Home</a>
</div>
</section>

<?php include('../includes/footer.php'); ?>