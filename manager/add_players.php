<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('manager');

// 1. Get manager’s team IDs
$teamIds = $_SESSION['managed_teams'] ?? [];
if (empty($teamIds)) {
  die("<div class='alert alert-warning m-5'>⚠️ You have no teams assigned.</div>");
}

// Flash helper
function flash($msg, $type = 'success') {
  return "<div class='alert alert-{$type}'>{$msg}</div>";
}
$alerts = [];

// 2. AJAX delete handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_player') {
  header('Content-Type: application/json');
  $pid = intval($_POST['player_id']);
  $check = $conn->query("
    SELECT 1 FROM players 
    WHERE player_id = $pid 
      AND team_id IN (" . implode(',', $teamIds) . ")
  ");
  if ($check->num_rows) {
    $conn->query("DELETE FROM players WHERE player_id = $pid");
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => 'Not authorized or not found.']);
  }
  exit;
}

// 3. Bulk CSV import
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_csv'])) {
  $bulkTeam = intval($_POST['bulk_team_id']);
  if (!in_array($bulkTeam, $teamIds)) {
    $alerts[] = flash("❌ Invalid team selected.", 'danger');
  } elseif (empty($_FILES['csv_file']['tmp_name'])) {
    $alerts[] = flash("❌ No CSV uploaded.", 'danger');
  } else {
    $f = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $row=0; $added=0;
    while (($data=fgetcsv($f,1000,","))!==false) {
      $row++;
      if ($row===1) continue; // skip header
      list($name,$pos)=array_map('trim',$data+['','']);
      if ($name!=='') {
        $stmt=$conn->prepare(
          "INSERT INTO players(team_id,name,position) VALUES(?,?,?)"
        );
        $stmt->bind_param("iss",$bulkTeam,$name,$pos);
        $stmt->execute();
        $added++;
      }
    }
    fclose($f);
    $alerts[] = flash("✅ Imported {$added} player(s).");
  }
}

// 4. Add single player
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_single'])) {
  $tid = intval($_POST['team_id']);
  $nm  = trim($_POST['name'] ?? '');
  $pos = trim($_POST['position'] ?? '');
  if (!in_array($tid, $teamIds) || $nm==='') {
    $alerts[] = flash("❌ Select a team & enter a name.", 'danger');
  } else {
    $stmt=$conn->prepare(
      "INSERT INTO players(team_id,name,position) VALUES(?,?,?)"
    );
    $stmt->bind_param("iss",$tid,$nm,$pos);
    $stmt->execute();
    $alerts[] = flash("✅ Player added.");
  }
}

// 5. Edit player
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_player'])) {
  $pid = intval($_POST['player_id']);
  $tid = intval($_POST['team_id']);
  $nm  = trim($_POST['name']);
  $pos = trim($_POST['position']);
  $check = $conn->query("
    SELECT 1 FROM players 
    WHERE player_id=$pid AND team_id IN (" . implode(',', $teamIds) . ")
  ");
  if (!$check->num_rows) {
    $alerts[] = flash("❌ Not authorized or not found.", 'danger');
  } else {
    $stmt=$conn->prepare(
      "UPDATE players SET team_id=?,name=?,position=? WHERE player_id=?"
    );
    $stmt->bind_param("issi",$tid,$nm,$pos,$pid);
    $stmt->execute();
    $alerts[] = flash("✅ Player updated.");
  }
}

// 6. Fetch teams & players
$teams   = $conn->query("
  SELECT team_id,name 
  FROM teams 
  WHERE team_id IN (" . implode(',', $teamIds) . ")
");
$players = $conn->query("
  SELECT p.player_id,p.name,p.position,t.name AS team_name 
  FROM players p 
  JOIN teams t ON p.team_id=t.team_id 
  WHERE p.team_id IN (" . implode(',', $teamIds) . ")
  ORDER BY t.name,p.name
");

// 7. Prepare edit form if requested
$editPlayer = null;
if (($_GET['action'] ?? '')==='edit' && isset($_GET['player_id'])) {
  $pid = intval($_GET['player_id']);
  $res = $conn->query("
    SELECT player_id,team_id,name,position 
    FROM players 
    WHERE player_id=$pid 
      AND team_id IN (" . implode(',', $teamIds) . ")
  ");
  $editPlayer = $res->fetch_assoc();
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container py-5">
  <h2 class="text-center mb-4">👥 Manage Roster</h2>
  <?php foreach ($alerts as $a) echo $a; ?>

  <div class="row g-4 mb-4">
    <!-- Bulk CSV Upload -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header">📥 Import Players (CSV)</div>
        <div class="card-body">
          <form method="POST" enctype="multipart/form-data">
            <select name="bulk_team_id" class="form-select mb-2" required>
              <option value="">– Select Team –</option>
              <?php while($t=$teams->fetch_assoc()): ?>
                <option value="<?= $t['team_id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
              <?php endwhile; ?>
            </select>
            <input type="file" name="csv_file" class="form-control mb-2" accept=".csv" required>
            <small class="text-muted">CSV header: name,position</small>
            <button name="upload_csv" class="btn btn-primary w-100 mt-2">Upload CSV</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Add / Edit Single Player -->
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-header">
          <?= $editPlayer ? '✏️ Edit Player' : '➕ Add Player' ?>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="<?= $editPlayer ? 'edit_player' : 'add_single' ?>" value="1">
            <?php if ($editPlayer): ?>
              <input type="hidden" name="player_id" value="<?= $editPlayer['player_id'] ?>">
            <?php endif; ?>

            <select name="team_id" class="form-select mb-2" required>
              <option value="">– Select Team –</option>
              <?php 
              // re-fetch teams
              $teams2 = $conn->query("
                SELECT team_id,name 
                FROM teams 
                WHERE team_id IN (" . implode(',', $teamIds) . ")
              ");
              while($t=$teams2->fetch_assoc()): ?>
                <option value="<?= $t['team_id'] ?>"
                  <?= $editPlayer && $t['team_id']==$editPlayer['team_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t['name']) ?>
                </option>
              <?php endwhile; ?>
            </select>

            <input type="text" 
                   name="name" 
                   class="form-control mb-2" 
                   placeholder="Player Name" required
                   value="<?= htmlspecialchars($editPlayer['name'] ?? '') ?>">

            <input list="positions" 
                   name="position" 
                   class="form-control mb-2" 
                   placeholder="Position (G/F/C)"
                   value="<?= htmlspecialchars($editPlayer['position'] ?? '') ?>">
            <datalist id="positions">
              <option value="G"><option value="F"><option value="C">
            </datalist>

            <button class="btn <?= $editPlayer ? 'btn-warning' : 'btn-success' ?> w-100">
              <?= $editPlayer ? 'Update Player' : 'Add Player' ?>
            </button>
            <?php if ($editPlayer): ?>
              <a href="add_players.php" class="btn btn-secondary mt-2 w-100">Cancel</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Roster Table -->
  <div class="card shadow-sm">
    <div class="card-header">📋 Current Roster</div>
    <div class="card-body table-responsive">
      <table class="table table-bordered table-hover">
        <thead class="table-light">
          <tr><th>Team</th><th>Name</th><th>Position</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php while($p=$players->fetch_assoc()): ?>
            <tr id="player-<?= $p['player_id'] ?>">
              <td><?= htmlspecialchars($p['team_name']) ?></td>
              <td><?= htmlspecialchars($p['name']) ?></td>
              <td><?= htmlspecialchars($p['position']) ?></td>
              <td class="text-center">
                <a href="add_players.php?action=edit&player_id=<?= $p['player_id'] ?>"
                   class="btn btn-sm btn-outline-primary me-1">Edit</a>
                <button type="button"
                        class="btn btn-sm btn-outline-danger delete-player"
                        data-id="<?= $p['player_id'] ?>">
                  Delete
                </button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
document.querySelectorAll('.delete-player').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm('Delete this player?')) return;
    const pid = btn.dataset.id;
    fetch('add_players.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/x-www-form-urlencoded' },
      body: `action=delete_player&player_id=${encodeURIComponent(pid)}`
    })
    .then(r => r.json())
    .then(json => {
      if (json.success) {
        const row = document.getElementById(`player-${pid}`);
        row && row.remove();
      } else {
        alert(json.message || '❌ Could not delete player.');
      }
    });
  });
});
</script>