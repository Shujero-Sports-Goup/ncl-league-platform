<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Meta Essentials -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="League Management System – Professional sports league management powered by Nukta Sports Solutions." />
  <meta name="author" content="Nukta Sports Management • Shujero Sports Group" />
  <meta name="robots" content="index, follow" />

  <!-- Favicon (Optional) -->
  <link rel="icon" href="/ncl-league-platform/assets/images/nukta-logo.png" type="image/png" />

  <!-- Page Title -->
  <title>League Management System | Nukta</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- CSS Frameworks -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />


  <!-- Custom Styles -->
  <link rel="stylesheet" href="/ncl-league-platform/assets/css/style.css" />
  <link rel="stylesheet" href="/ncl-league-platform/assets/css/nukta-theme.css" />
  

  <!-- Responsive Tweaks -->
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      scroll-behavior: smooth;
    }
    @media (max-width: 576px) {
      .navbar-brand {
        font-size: 1rem;
      }
    }
  </style>
</head>

<body class="bg-dark text-light">

<?php // your navbar include here ?>
