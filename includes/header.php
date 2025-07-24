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
  <meta name="description" content="NCL League Platform – Fixtures, Teams, Standings & Updates from Kenya's grassroots basketball system." />
  <meta name="author" content="NCL League • James Ngunjiri" />
  <meta name="robots" content="index, follow" />

  <!-- Favicon (Optional) -->
  <link rel="icon" href="/ncl-league-platform/assets/images/favicon.ico" type="image/x-icon" />

  <!-- Page Title -->
  <title>NCL League Platform</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />

  <!-- CSS Frameworks -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />


  <!-- Custom Styles -->
  <link rel="stylesheet" href="/ncl-league-platform/assets/css/style.css" />
  <link rel="stylesheet" href="/ncl-league-platform/assets/css/ncl-theme.css" />
  

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
