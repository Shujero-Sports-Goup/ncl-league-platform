<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

$leagueId = $_SESSION['league_id'] ?? 1;
$msg = '';
$success = false;

// Fetch teams and venues
$teams  = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");
$venues = $conn->query("SELECT venue_id, name FROM venues");

$teamList = [];
while ($t = $teams->fetch_assoc()) {
  $teamList[] = $t;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $home  = intval($_POST['home_team'] ?? 0);
  $away  = intval($_POST['away_team'] ?? 0);
  $date  = $_POST['match_date'] ?? '';
  $time  = $_POST['match_time'] ?? '';
  $venue = trim($_POST['venue'] ?? '');

  // Additional validation for NCL and WEL time rules
  $currentLeague = $_SESSION['league_name'] ?? '';
  $isNCLorWEL = strpos($currentLeague, 'NCL') !== false || 
                strpos($currentLeague, 'WEL') !== false ||
                stripos($currentLeague, 'nairobi') !== false || 
                stripos($currentLeague, 'women') !== false;
  
  $timeValidation = true;
  if ($isNCLorWEL && $date) {
    $dayOfWeek = date('w', strtotime($date)); // 0=Sunday, 1=Monday, ..., 6=Saturday
    
    if ($dayOfWeek == 5) { // Friday
      $allowedTimes = ['18:00', '19:30'];
      if (!in_array($time, $allowedTimes)) {
        $timeValidation = false;
        $msg = "❌ For NCL/WEL Friday matches, only 6:00 PM (18:00) or 7:30 PM (19:30) are allowed.";
      }
    } elseif ($dayOfWeek == 0 || $dayOfWeek == 6) { // Weekend
      $allowedTimes = ['10:00', '11:30', '13:00', '14:30', '16:00'];
      if (!in_array($time, $allowedTimes)) {
        $timeValidation = false;
        $msg = "❌ For NCL/WEL weekend matches, only 10:00 AM, 11:30 AM, 1:00 PM, 2:30 PM, or 4:00 PM are allowed.";
      }
    }
  }

  if ($home === $away) {
    $msg = "❌ Home and away teams must be different.";
  } elseif (!$date || !$time || strtotime("$date $time") < time()) {
    $msg = "❌ Match must be scheduled for a valid future date and time.";
  } elseif (empty($venue)) {
    $msg = "❌ Venue selection is required.";
  } elseif (!$timeValidation) {
    // Time validation error message already set above
  } else {
    $stmt = $conn->prepare("
      INSERT INTO fixtures (league_id, home_team, away_team, match_date, match_time, venue)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiisss", $leagueId, $home, $away, $date, $time, $venue);
    $success = $stmt->execute();

    $homeName = $teamList[array_search($home, array_column($teamList, 'team_id'))]['name'] ?? 'Home';
    $awayName = $teamList[array_search($away, array_column($teamList, 'team_id'))]['name'] ?? 'Away';

    $msg = $success
      ? "✅ Fixture scheduled: <strong>$homeName</strong> vs <strong>$awayName</strong> on <strong>$date $time</strong> at <strong>$venue</strong>."
      : "❌ Error: " . $stmt->error;
  }
}

include('../includes/header.php');
include('../includes/navbar.php');
?>
  
<link 
  rel="stylesheet" 
  href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0/dist/css/bootstrap-select.min.css"
/>
<style>
  /* Custom overrides */
  .card-custom { max-width: 700px; margin: auto; }
  .btn-accent { background: #0000ff; color: #fff; }
  .btn-accent:disabled { opacity: .7; }
</style>

<section class="container py-5">
  <h2 class="text-center mb-4">📅 Schedule New Fixture</h2>
  
  <?php if ($msg): ?>
    <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <form id="fixtureForm" method="POST" class="card card-custom p-4 shadow-sm">
    <div class="row g-3">
      <div class="col-md-6">
        <label for="home_team" class="form-label">Home Team</label>
        <select 
          id="home_team" 
          name="home_team" 
          class="form-select selectpicker" 
          data-live-search="true" 
          required
        >
          <option value="">Select…</option>
          <?php foreach ($teamList as $team): ?>
            <option value="<?= $team['team_id'] ?>">
              <?= htmlspecialchars($team['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label for="away_team" class="form-label">Away Team</label>
        <select 
          id="away_team" 
          name="away_team" 
          class="form-select selectpicker" 
          data-live-search="true" 
          required
        >
          <option value="">Select…</option>
          <?php foreach ($teamList as $team): ?>
            <option value="<?= $team['team_id'] ?>">
              <?= htmlspecialchars($team['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label for="match_date" class="form-label">Match Date</label>
        <input 
          type="date" 
          id="match_date" 
          name="match_date" 
          class="form-control" 
          min="<?= date('Y-m-d') ?>" 
          required
        >
      </div>

      <div class="col-md-6">
        <label for="match_time" class="form-label">Match Time</label>
        <?php 
        // Check if current league is NCL or WEL
        $currentLeague = $_SESSION['league_name'] ?? '';
        $isNCLorWEL = strpos($currentLeague, 'NCL') !== false || 
                      strpos($currentLeague, 'WEL') !== false ||
                      stripos($currentLeague, 'nairobi') !== false || 
                      stripos($currentLeague, 'women') !== false;
        ?>
        <input 
          type="time" 
          id="match_time" 
          name="match_time" 
          class="form-control" 
          required
        >
        <?php if ($isNCLorWEL): ?>
        <small class="form-text text-muted">
          <strong>Time Rules:</strong> Friday: 6:00 PM or 7:30 PM | Weekend: 10:00 AM, 11:30 AM, 1:00 PM, 2:30 PM, or 4:00 PM
        </small>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <label for="venue" class="form-label">Venue</label>
        <select 
          id="venue" 
          name="venue" 
          class="form-select selectpicker" 
          data-live-search="true" 
          required
        >
          <option value="">Select…</option>
          <?php while ($v = $venues->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($v['name']) ?>">
              <?= htmlspecialchars($v['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>

    <div class="mt-4 text-center">
      <button 
        id="submitBtn" 
        type="submit" 
        class="btn btn-accent px-5"
      >
        <span id="btnText">Schedule Fixture</span>
        <span 
          id="btnSpinner" 
          class="spinner-border spinner-border-sm ms-2 d-none" 
          role="status"
        ></span>
      </button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-primary">
      ⬅ Back to Dashboard
    </a>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script 
  src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0/dist/js/bootstrap-select.min.js">
</script>
<script>
  $(function(){
    $('.selectpicker').selectpicker();

    // prevent same‐team selection
    const $home = $('#home_team'), $away = $('#away_team');
    function toggleTeams(){
      const h = $home.val(), a = $away.val();
      $home.find('option').prop('disabled', false);
      $away.find('option').prop('disabled', false);
      if (h) $away.find(`option[value="${h}"]`).prop('disabled', true);
      if (a) $home.find(`option[value="${a}"]`).prop('disabled', true);
      $home.selectpicker('refresh');
      $away.selectpicker('refresh');
    }
    $home.on('changed.bs.select', toggleTeams);
    $away.on('changed.bs.select', toggleTeams);

    // Smart time defaults based on league and day
    $('#match_date').on('change', function(){
      const selectedDate = new Date(this.value);
      const dayOfWeek = selectedDate.getDay(); // 0=Sunday, 1=Monday, ..., 6=Saturday
      const $timeSelect = $('#match_time');
      
      // Get current league from session or determine league type
      const currentLeague = '<?= $_SESSION['league_name'] ?? '' ?>';
      const isNCLorWEL = currentLeague.includes('NCL') || currentLeague.includes('WEL') || 
                        currentLeague.toLowerCase().includes('nairobi') || 
                        currentLeague.toLowerCase().includes('women');
      
      if (isNCLorWEL) {
        // NCL and WEL specific time rules
        if (dayOfWeek === 5) { // Friday
          // Replace time input with select dropdown for Friday
          $timeSelect.replaceWith(`
            <select id="match_time" name="match_time" class="form-select" required>
              <option value="">Select Time...</option>
              <option value="18:00">6:00 PM</option>
              <option value="19:30">7:30 PM</option>
            </select>
          `);
        } else if (dayOfWeek === 0 || dayOfWeek === 6) { // Saturday or Sunday
          // Replace time input with select dropdown for weekends
          $timeSelect.replaceWith(`
            <select id="match_time" name="match_time" class="form-select" required>
              <option value="">Select Time...</option>
              <option value="10:00">10:00 AM</option>
              <option value="11:30">11:30 AM</option>
              <option value="13:00">1:00 PM</option>
              <option value="14:30">2:30 PM</option>
              <option value="16:00">4:00 PM</option>
            </select>
          `);
        } else {
          // Other days - revert to time input
          $timeSelect.replaceWith(`
            <input type="time" id="match_time" name="match_time" class="form-control" required>
          `);
          $('#match_time').val('19:00'); // Default evening time for other days
        }
      } else {
        // Other leagues - use standard logic
        if ($timeSelect.prop('tagName') !== 'INPUT') {
          $timeSelect.replaceWith(`
            <input type="time" id="match_time" name="match_time" class="form-control" required>
          `);
        }
        $('#match_time').val(
          (dayOfWeek === 0 || dayOfWeek === 6) ? '15:00' : '19:00'
        );
      }
    });

    // form submit spinner
    $('#fixtureForm').on('submit', function(){
      $('#submitBtn').prop('disabled', true);
      $('#btnText').text('Scheduling…');
      $('#btnSpinner').removeClass('d-none');
    });
  });
</script>

<?php include('../includes/footer.php'); ?>
