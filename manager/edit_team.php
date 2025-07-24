<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('manager');

// Get team ID — via session or query
$teamId = $_GET['team_id'] ?? $_SESSION['team_id'] ?? 0;
if (!$teamId) die("<div class='alert alert-danger m-5'>❌ No team found.</div>");

// Fetch team
$stmt = $conn->prepare("SELECT * FROM teams WHERE team_id = ?");
$stmt->bind_param("i", $teamId);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
if (!$team) die("<div class='alert alert-danger m-5'>❌ Team not found.</div>");

// Handle POST submission
$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name       = trim($_POST['name'] ?? '');
  $coach      = trim($_POST['coach_name'] ?? '');
  $bio        = trim($_POST['description'] ?? '');
  $logoPath   = $team['logo_url'];

  if (!empty($_FILES['logo_file']['tmp_name'])) {
    $allowed = ['image/png', 'image/jpeg', 'image/svg+xml'];
    $mime = mime_content_type($_FILES['logo_file']['tmp_name']);
    if (in_array($mime, $allowed)) {
      $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
      $filename = uniqid('logo_') . "." . $ext;
      $uploadFolder = "../uploads/logos/";
      if (!is_dir($uploadFolder)) mkdir($uploadFolder, 0755, true);
      move_uploaded_file($_FILES['logo_file']['tmp_name'], $uploadFolder . $filename);
      $logoPath = "uploads/logos/" . $filename;
    } else {
      $feedback = "<div class='alert alert-danger'>❌ Invalid image format. Use PNG, JPG or SVG.</div>";
    }
  }

  // Update DB
  $stmt = $conn->prepare("
    UPDATE teams SET name = ?, coach_name = ?, description = ?, logo_url = ? 
    WHERE team_id = ?
  ");
  $stmt->bind_param("ssssi", $name, $coach, $bio, $logoPath, $teamId);
  $stmt->execute();

  $team['name'] = $name;
  $team['coach_name'] = $coach;
  $team['description'] = $bio;
  $team['logo_url'] = $logoPath;
  $feedback = "<div class='alert alert-success'>✅ Team updated successfully.</div>";
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container py-5" style="max-width: 700px;">
  <h2 class="text-center mb-4">✏️ Edit Team: <?= htmlspecialchars($team['name']) ?></h2>
  <?= $feedback ?>

  <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm border-0 bg-light">

    <div class="mb-3">
      <label class="form-label fw-bold">Team Name</label>
      <input type="text" name="name" class="form-control" required
             value="<?= htmlspecialchars($team['name']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Coach Name</label>
      <input type="text" name="coach_name" class="form-control"
             value="<?= htmlspecialchars($team['coach_name']) ?>">
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Team Description / Bio</label>
      <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($team['description']) ?></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label fw-bold">Upload Logo (PNG, JPG, SVG)</label>
      <input type="file" name="logo_file" class="form-control" accept="image/png,image/jpeg,image/svg+xml">
    </div>

    <?php if (!empty($team['logo_url'])): ?>
      <div class="text-center mb-3">
        <img id="logoPreview" src="/ncl-league-platform/<?= htmlspecialchars($team['logo_url']) ?>"
             alt="Team Logo" class="img-fluid rounded shadow-sm" style="height: 100px;">
      </div>
    <?php endif; ?>

    <div class="d-grid gap-2">
      <button class="btn btn-primary">💾 Save Changes</button>
      <a href="panel.php" class="btn btn-outline-secondary">⬅ Back to Panel</a>
    </div>
  </form>
</div>

<script>
document.querySelector('input[name="logo_file"]').addEventListener('change', function () {
  const img = document.getElementById('logoPreview');
  const file = this.files[0];
  if (file && img) {
    img.src = URL.createObjectURL(file);
  }
});
</script>

<?php include('../includes/footer.php'); ?>
