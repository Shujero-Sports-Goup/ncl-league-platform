<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

$msg     = '';
$success = false;
$uploadDir = __DIR__ . '/../assets/uploads/logos/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

//–– Handle UPDATE –– 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
  $id    = intval($_POST['edit_id']);
  $name  = trim($_POST['name'] ?? '');
  $abbr  = strtoupper(trim($_POST['abbreviation'] ?? ''));
  $logo  = ''; 

  // fetch old logo
  $old = $conn->prepare("SELECT logo_url FROM leagues WHERE league_id=?");
  $old->bind_param("i", $id);
  $old->execute();
  $oldLogo = $old->get_result()->fetch_assoc()['logo_url'] ?? '';
  $old->close();

  // file upload if exists
  if (!empty($_FILES['logo_file']['name'])) {
    $file    = $_FILES['logo_file'];
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png'];
    if ($file['error']===UPLOAD_ERR_OK && isset($allowed[$file['type']]) && $file['size']<=2e6) {
      $ext     = $allowed[$file['type']];
      $newName = 'lg_'.uniqid().".$ext";
      if (move_uploaded_file($file['tmp_name'],$uploadDir.$newName)) {
        $logo = 'assets/uploads/logos/'.$newName;
        // remove old file
        if ($oldLogo && file_exists(__DIR__.'/../'.$oldLogo)) {
          @unlink(__DIR__.'/../'.$oldLogo);
        }
      } else {
        $msg = "❌ Failed to upload new logo.";
      }
    } else {
      $msg = "❌ Invalid logo (JPG/PNG ≤2MB).";
    }
  }

  // validation
  if (!$msg && (empty($name)||empty($abbr)||strlen($abbr)>10)) {
    $msg = "❌ Name/Abbreviation invalid.";
  }

  // update
  if (!$msg) {
    $sql = "UPDATE leagues SET name=?, abbreviation=?" 
         .($logo? ", logo_url=?": "") 
         ." WHERE league_id=?";
    $stmt = $conn->prepare($sql);
    if ($logo) {
      $stmt->bind_param("sssi",$name,$abbr,$logo,$id);
    } else {
      $stmt->bind_param("ssi",$name,$abbr,$id);
    }
    $success = $stmt->execute();
    $msg = $success 
      ? "✅ League updated successfully." 
      : "❌ Update error: ".$stmt->error;
    $stmt->close();
  }
}

//–– Handle CREATE –– 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_id'])) {
  $name = trim($_POST['name'] ?? '');
  $abbr = strtoupper(trim($_POST['abbreviation'] ?? ''));
  $logo = ''; // will hold final path

  // 1) handle file upload
  if (!empty($_FILES['logo_file']['name'])) {
    $file    = $_FILES['logo_file'];
    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png'];

    if ($file['error'] === UPLOAD_ERR_OK && isset($allowed[$file['type']])) {
      if ($file['size'] <= 2 * 1024 * 1024) { // max 2MB
        $ext      = $allowed[$file['type']];
        $newName  = 'lg_' . uniqid() . '.' . $ext;
        $destPath = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
          // store relative URL
          $logo = 'assets/uploads/logos/' . $newName;
        } else {
          $msg = "❌ Failed to move uploaded file.";
        }
      } else {
        $msg = "❌ File too large (max 2MB).";
      }
    } else {
      $msg = "❌ Invalid file type. Only JPG/PNG allowed.";
    }
  }

  // 2) validate name/abbr
  if (!$msg) {
    if (strlen($abbr) > 10) {
      $msg = "❌ Abbreviation too long.";
    } elseif (empty($name) || empty($abbr)) {
      $msg = "❌ Name and abbreviation are required.";
    }
  }

  // 3) check duplicates & insert
  if (!$msg) {
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM leagues WHERE abbreviation = ?");
    $check->bind_param("s", $abbr);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc()['cnt'];
    $check->close();

    if ($exists) {
      $msg = "❌ A league with abbreviation <strong>$abbr</strong> already exists.";
    } else {
      $stmt = $conn->prepare(
        "INSERT INTO leagues (name, abbreviation, logo_url) VALUES (?, ?, ?)"
      );
      $stmt->bind_param("sss", $name, $abbr, $logo);
      $success = $stmt->execute();
      $msg     = $success
        ? "✅ League <strong>$abbr – $name</strong> added successfully!"
        : "❌ Error: " . $stmt->error;
      $stmt->close();
    }
  }
}

// fetch all
$leagues = $conn->query("SELECT * FROM leagues ORDER BY abbreviation ASC");
include('../includes/header.php');
include('../includes/navbar.php');
?>
<section class="container py-5">
  <h2 class="text-center mb-4">🏆 Manage Leagues</h2>
  <?php if ($msg): ?>
    <div class="alert <?= $success?'alert-success':'alert-danger' ?>">
      <?= $msg ?>
    </div>
  <?php endif; ?>

  <div class="table-responsive mb-5">
    <table class="table table-bordered text-center align-middle">
      <thead class="bg-primary text-light">
        <tr>
          <th>ID</th><th>Abbr</th><th>Name</th><th>Logo</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($l = $leagues->fetch_assoc()): ?>
          <tr>
            <td><?= $l['league_id'] ?></td>
            <td><?= htmlspecialchars($l['abbreviation']) ?></td>
            <td><?= htmlspecialchars($l['name']) ?></td>
            <td>
              <?php if ($l['logo_url'] && file_exists(__DIR__.'/../'.$l['logo_url'])): ?>
                <img src="../<?= $l['logo_url'] ?>" style="max-height:40px">
              <?php else: ?>
                <span class="text-muted">N/A</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="?edit_id=<?= $l['league_id'] ?>" 
                 class="btn btn-sm btn-outline-primary">
                Edit
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <?php if (isset($_GET['edit_id'])):
    $eid = intval($_GET['edit_id']);
    $E = $conn->query("SELECT * FROM leagues WHERE league_id=$eid")
             ->fetch_assoc() ?? null;
    if ($E):
  ?>
  <h4 class="mb-3 text-center">✏️ Edit League <?= htmlspecialchars($E['abbreviation']) ?></h4>
  <form method="POST" enctype="multipart/form-data"
        class="card p-4 shadow-sm mb-5" style="max-width:500px;margin:auto;">
    <input type="hidden" name="edit_id" value="<?= $E['league_id'] ?>">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control"
             value="<?= htmlspecialchars($E['name']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Abbreviation</label>
      <input type="text" name="abbreviation" class="form-control"
             maxlength="10" value="<?= htmlspecialchars($E['abbreviation']) ?>" required>
    </div>
    <div class="mb-4">
      <label class="form-label">Replace Logo (JPG/PNG, ≤2MB)</label>
      <input type="file" name="logo_file" accept="image/png,image/jpeg"
             class="form-control">
    </div>
    <div class="d-grid">
      <button type="submit" class="btn btn-primary">
        Update League
      </button>
    </div>
  </form>
  <?php endif; endif; ?>

  <h4 class="mb-3 text-center">➕ Add New League</h4>
  <form method="POST" enctype="multipart/form-data"
        class="card p-4 shadow-sm" style="max-width:500px;margin:auto;">
    <div class="mb-3">
      <label class="form-label">Name</label>
      <input type="text" name="name" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Abbreviation (max 10)</label>
      <input type="text" name="abbreviation" class="form-control" maxlength="10" required>
    </div>

    <div class="mb-4">
      <label class="form-label">Logo Upload (JPG/PNG, ≤2MB)</label>
      <input 
        type="file" 
        name="logo_file" 
        accept="image/png, image/jpeg" 
        class="form-control"
      >
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-accent">Add League</button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-primary">
      ⬅ Back to Dashboard
    </a>
  </div>
</section>

<?php include('../includes/footer.php'); ?>
