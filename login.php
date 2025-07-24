<?php
session_start();
require_once('db_connect.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if (!$username || !$password) {
    $error = "❌ Please enter both username and password.";
  } else {
    $stmt = $conn->prepare("
      SELECT user_id, password, role, name FROM users WHERE username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
      $user = $result->fetch_assoc();

      $valid = password_verify($password, $user['password']) ||
               $user['password'] === hash('sha256', $password);

      if ($valid) {
        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $username;
        $_SESSION['role']     = $user['role'];
        $_SESSION['name']     = $user['name'];

        // 🛡️ Update legacy password hash if needed
        if ($user['password'] === hash('sha256', $password)) {
          $newHash = password_hash($password, PASSWORD_DEFAULT);
          $u = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
          $u->bind_param("si", $newHash, $user['user_id']);
          $u->execute();
        }

        // 👔 For managers: load assigned team IDs
        if ($user['role'] === 'manager') {
          $stmt = $conn->prepare("
            SELECT team_id FROM team_managers WHERE user_id = ?
          ");
          $stmt->bind_param("i", $user['user_id']);
          $stmt->execute();
          $result = $stmt->get_result();
          $_SESSION['managed_teams'] = array_column($result->fetch_all(MYSQLI_ASSOC), 'team_id');
          $stmt->close();
        }

        // ⚖️ For referees: load accessible leagues
        if ($user['role'] === 'referee') {
          $r = $conn->prepare("
            SELECT league_id FROM referee_assignments WHERE user_id = ?
          ");
          $r->bind_param("i", $user['user_id']);
          $r->execute();
          $rows = $r->get_result()->fetch_all(MYSQLI_NUM);
          $_SESSION['accessible_leagues'] = array_column($rows, 0);
        }

        // 🎯 Redirect by role
        switch ($user['role']) {
          case 'admin':   header("Location: admin/dashboard.php"); break;
          case 'manager': header("Location: manager/panel.php"); break;
          case 'referee': header("Location: referee/submit_score.php"); break;
          default: exit("Unknown role.");
        }
        exit;

      } else {
        $error = "❌ Invalid password.";
      }
    } else {
      $error = "❌ User not found.";
    }
  }
}

// Include header and navbar
include('includes/header.php');
?>

<section class="container py-5" style="max-width: 500px;">
  <h2 class="text-center mb-4"> Login to NCL League Platform</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" name="username" id="username" class="form-control" required autofocus>
    </div>

    <div class="mb-4">
      <label for="password" class="form-label">Password</label>
      <input type="password" name="password" id="password" class="form-control" required>
    </div>

    <div class="d-grid">
      <button type="submit" class="btn btn-accent">Login</button>
    </div>
  </form>
</section>

<?php include('includes/footer.php'); ?>
