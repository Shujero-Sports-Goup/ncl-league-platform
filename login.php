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

<!-- Modern Nukta Login Section -->
<section class="min-vh-100 d-flex align-items-center" 
         style="background: linear-gradient(135deg, #0000ff 0%, rgba(0, 0, 255, 0.8) 50%, #0000ff 100%); 
                position: relative; overflow: hidden;">
    
    <!-- Nukta Logo Background -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
                background: url('assets/images/nukta-logo.png') center/contain no-repeat; 
                opacity: 0.05; z-index: 0;"></div>
    
    <div class="container" style="position: relative; z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-6">
                
                <!-- Login Card -->
                <div class="card border-0 shadow-lg" 
                     style="border-radius: 25px; background: white; overflow: hidden;"
                     data-aos="fade-up" data-aos-duration="800">
                    
                    <!-- Card Header -->
                    <div class="card-header border-0 text-center py-5" 
                         style="background: linear-gradient(135deg, rgba(0, 0, 255, 0.05), rgba(0, 0, 255, 0.02));">
                        
                        <!-- Nukta Logo -->
                        <div class="mb-4">
                            <img src="assets/images/nukta-logo.png" alt="Nukta Sports Management" 
                                 style="height: 100px; width: auto; filter: drop-shadow(0 4px 8px rgba(0, 0, 255, 0.1));">
                        </div>
                        
                        <!-- Welcome Text -->
                        <h2 class="fw-bold mb-2" style="color: #0000ff;">Welcome Back</h2>
                        <p class="text-muted mb-0">Sign in to Nukta League Management System</p>
                        <div class="mt-3 mx-auto" style="width: 60px; height: 3px; background: linear-gradient(135deg, #0000ff, #4169E1); border-radius: 2px;"></div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="card-body p-5">
                        
                        <!-- Error Alert -->
                        <?php if ($error): ?>
                            <div class="alert border-0 mb-4" 
                                 style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05)); 
                                        color: #dc3545; border-radius: 15px;">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST">
                            
                            <!-- Username Field -->
                            <div class="mb-4">
                                <label for="username" class="form-label fw-bold" style="color: #333;">
                                    <i class="fas fa-user me-2" style="color: #0000ff;"></i>Username
                                </label>
                                <input type="text" name="username" id="username" 
                                       class="form-control py-3" 
                                       style="border: 2px solid #e9ecef; border-radius: 15px; 
                                              transition: all 0.3s ease; font-size: 1rem;"
                                       placeholder="Enter your username"
                                       required autofocus
                                       onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                                       onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'">
                            </div>

                            <!-- Password Field -->
                            <div class="mb-4">
                                <label for="password" class="form-label fw-bold" style="color: #333;">
                                    <i class="fas fa-lock me-2" style="color: #0000ff;"></i>Password
                                </label>
                                <div class="position-relative">
                                    <input type="password" name="password" id="password" 
                                           class="form-control py-3" 
                                           style="border: 2px solid #e9ecef; border-radius: 15px; 
                                                  transition: all 0.3s ease; font-size: 1rem; padding-right: 50px;"
                                           placeholder="Enter your password"
                                           required
                                           onfocus="this.style.borderColor='#0000ff'; this.style.boxShadow='0 0 0 0.2rem rgba(0, 0, 255, 0.1)'"
                                           onblur="this.style.borderColor='#e9ecef'; this.style.boxShadow='none'">
                                    <button type="button" class="btn position-absolute top-50 end-0 translate-middle-y me-3" 
                                            style="border: none; background: none; color: #6c757d; z-index: 5;"
                                            onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Login Button -->
                            <div class="d-grid mb-4">
                                <button type="submit" class="btn py-3 fw-bold" 
                                        style="background: linear-gradient(135deg, #0000ff, #4169E1); 
                                               color: white; border: none; border-radius: 15px; 
                                               font-size: 1.1rem; transition: all 0.3s ease;
                                               box-shadow: 0 4px 15px rgba(0, 0, 255, 0.3);"
                                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0, 0, 255, 0.4)'"
                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0, 0, 255, 0.3)'">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </button>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="text-center">
                                <small class="text-muted">
                                    Powered by <span class="fw-bold" style="color: #0000ff;">Nukta Sports Management</span>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Back to Home Link -->
                <div class="text-center mt-4">
                    <a href="index.php" class="text-white text-decoration-none fw-bold" 
                       style="transition: all 0.3s ease;"
                       onmouseover="this.style.textShadow='0 2px 10px rgba(255, 255, 255, 0.5)'"
                       onmouseout="this.style.textShadow='none'">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Password Toggle Script -->
<script>
function togglePassword() {
    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        toggleIcon.className = 'fas fa-eye';
    }
}
</script>

<?php include('includes/footer.php'); ?>
