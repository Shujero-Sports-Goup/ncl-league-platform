<?php
require_once(__DIR__ . '/../db_connect.php');
session_start();

//–– System-wide analytics for all stakeholders ––
$json = [
  // total leagues
  'leagues'  => (int)$conn
    ->query("SELECT COUNT(*) AS c FROM leagues")
    ->fetch_assoc()['c'],

  // total teams across all leagues
  'teams'    => (int)$conn
    ->query("SELECT COUNT(*) AS c FROM teams")
    ->fetch_assoc()['c'],

  // total fixtures played across all leagues
  'played'   => (int)$conn
    ->query("
      SELECT COUNT(*) AS c
      FROM fixtures
      WHERE status = 'played'
    ")->fetch_assoc()['c'],

  // total upcoming fixtures
  'upcoming' => (int)$conn
    ->query("
      SELECT COUNT(*) AS c
      FROM fixtures
      WHERE status = 'upcoming'
    ")->fetch_assoc()['c'],

  // total registered users
  'users'    => (int)$conn
    ->query("SELECT COUNT(*) AS c FROM users")
    ->fetch_assoc()['c'],
];

header('Content-Type: application/json');
echo json_encode($json, JSON_NUMERIC_CHECK);