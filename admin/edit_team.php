<?php
session_start();
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole(['admin', 'manager']);

$teamId = intval($_GET['team_id'] ?? 0); // <-- Make sure this is present!
$isAdmin = $_SESSION['role'] === 'admin';
$allowedTeams = $_SESSION['managed_teams'] ?? [];

if (!$isAdmin && !in_array($teamId, $allowedTeams)) {
  die("<div class='alert alert-danger m-5'>❌ You are not authorized to edit this team.</div>");
}
// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_team']) && $isAdmin) {
  $conn->query("DELETE FROM players WHERE team_id = $teamId");
  $conn->query("DELETE FROM team_managers WHERE team_id = $teamId");
  $stmt = $conn->prepare("DELETE FROM teams WHERE team_id = ?");
  $stmt->bind_param("i", $teamId);
  $stmt->execute();
  header("Location: dashboard.php?deleted=1");
  exit;
}

// Handle Update
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_team'])) {
  $coach = trim($_POST['coach_name']);
  $logo_url = trim($_POST['logo_url']);
  $desc = trim($_POST['description']);

  // Handle file upload if provided
  if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
    $fileType = mime_content_type($_FILES['logo_file']['tmp_name']);
    if (in_array($fileType, $allowed)) {
      $ext = pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION);
      $newName = 'team_' . $teamId . '_' . time() . '.' . $ext;
      $uploadDir = '../assets/team_logos/';
      if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
      $dest = $uploadDir . $newName;
      if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
        $logo_url = $dest;
      } else {
        $error = "❌ Failed to upload logo file.";
      }
    } else {
      $error = "❌ Invalid file type. Please upload PNG, JPG, JPEG, or SVG.";
    }
  }

  if (!$error) {
    $stmt = $conn->prepare("UPDATE teams SET coach_name = ?, logo_url = ?, description = ? WHERE team_id = ?");
    $stmt->bind_param("sssi", $coach, $logo_url, $desc, $teamId);
    $success = $stmt->execute();
    if (!$success) $error = "❌ Update failed. Please try again.";
  }
}

// Fetch team info
$stmt = $conn->prepare("SELECT name, coach_name, logo_url, description FROM teams WHERE team_id = ?");
$stmt->bind_param("i", $teamId);
$stmt->execute();
$team = $stmt->get_result()->fetch_assoc();
$stmt->close();

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container py-5" style="max-width: 700px;">
  <h2 class="mb-4 text-center">✏️ Edit Team Info: <?= htmlspecialchars($team['name']) ?></h2>

  <?php if ($success): ?>
    <div class="alert alert-success">✅ Team info updated successfully.</div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm mb-3">
    <div class="mb-3 text-center">
      <?php if (!empty($team['logo_url'])): ?>
        <img src="<?= htmlspecialchars($team['logo_url']) ?>" alt="Team Logo" style="height: 90px; border-radius: 10px; max-width: 100%;">
      <?php else: ?>
        <span class="text-muted small">No logo uploaded yet.</span>
      <?php endif; ?>
    </div>
    <div class="mb-3">
      <label class="form-label">Coach Name</label>
      <input type="text" name="coach_name" class="form-control" required
             value="<?= htmlspecialchars($team['coach_name']) ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Team Logo (PNG, JPG, SVG, or URL)</label>
      <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.svg" class="form-control mb-2">
      <input type="url" name="logo_url" class="form-control" placeholder="Or paste image URL" value="<?= htmlspecialchars($team['logo_url']) ?>">
      <small class="form-text text-muted">Upload a file or paste an image URL. Uploading a file will override the URL.</small>
    </div>
    <div class="mb-3">
      <label class="form-label">Team Description</label>
      <textarea name="description" class="form-control" rows="5"><?= htmlspecialchars($team['description']) ?></textarea>
    </div>
    <div class="d-grid d-md-flex gap-2">
      <button type="submit" class="btn btn-accent">💾 Save Changes</button>
      <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
  </form>

  <?php if ($isAdmin): ?>
    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this team? This action cannot be undone.');">
      <input type="hidden" name="delete_team" value="1">
      <button type="submit" class="btn btn-danger w-100">🗑️ Delete Team</button>
    </form>
  <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>