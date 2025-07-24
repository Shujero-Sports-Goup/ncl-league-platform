<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

header('Content-Type: application/json');
$leagueId = $_SESSION['league_id'] ?? 1;
$action = $_GET['action'] ?? '';

function paginate($query, $term = '', $column = 'name', $page = 1, $limit = 20) {
  global $conn;
  $offset = ($page - 1) * $limit;
  $termEsc = $conn->real_escape_string($term);
  $query .= $term ? " AND $column LIKE '%$termEsc%'" : '';
  $query .= " ORDER BY $column LIMIT $limit OFFSET $offset";
  $res = $conn->query($query);
  $results = [];
  while ($row = $res->fetch_assoc()) {
    $results[] = ['id'=>$row[$column.'_id'] ?? $row['id'], 'text'=>$row[$column] ?? $row['name']];
  }
  return $results;
}

if ($action === 'search_teams') {
  $term = $_GET['q'] ?? '';
  $page = intval($_GET['page'] ?? 1);
  $results = paginate("SELECT team_id AS id, name FROM teams WHERE league_id=$leagueId", $term, 'name', $page);
  echo json_encode(['results'=>$results]);
  exit;
}

if ($action === 'search_venues') {
  $term = $_GET['q'] ?? '';
  $page = intval($_GET['page'] ?? 1);
  $results = paginate("SELECT venue_id AS id, name FROM venues", $term, 'name', $page);
  echo json_encode(['results'=>$results]);
  exit;
}

if ($action === 'team_stats') {
  $teamId = intval($_GET['team_id']);
  $now = date('Y-m-d H:i:s');
  $stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM fixtures WHERE league_id=$leagueId AND (home_team=$teamId OR away_team=$teamId)")->fetch_row()[0],
    'upcoming' => $conn->query("SELECT COUNT(*) FROM fixtures WHERE league_id=$leagueId AND (home_team=$teamId OR away_team=$teamId) AND CONCAT(match_date,' ',match_time) > '$now'")->fetch_row()[0],
    'last_played' => $conn->query("SELECT match_date, match_time FROM fixtures WHERE league_id=$leagueId AND (home_team=$teamId OR away_team=$teamId) AND CONCAT(match_date,' ',match_time) < '$now' ORDER BY match_date DESC, match_time DESC LIMIT 1")->fetch_assoc()
  ];
  echo json_encode(['success'=>true,'stats'=>$stats]);
  exit;
}

if ($action === 'venue_info') {
  $venueId = intval($_GET['venue_id']);
  $now = date('Y-m-d H:i:s');
  $v = $conn->query("SELECT * FROM venues WHERE venue_id=$venueId")->fetch_assoc();
  $bookings = $conn->query("SELECT COUNT(*) FROM fixtures WHERE venue=$venueId AND CONCAT(match_date,' ',match_time) > '$now'")->fetch_row()[0];
  echo json_encode([
    'success'=>true,
    'info'=>[
      'capacity'=>$v['capacity'],
      'location'=>$v['location'],
      'surface_type'=>$v['surface_type'],
      'bookings'=>intval($bookings)
    ]
  ]);
  exit;
}

if ($action === 'check_conflict') {
  $home = intval($_GET['home']);
  $away = intval($_GET['away']);
  $venue = intval($_GET['venue']);
  $date = $_GET['date'];
  $time = $_GET['time'];
  $dt = "$date $time";
  $conflicts = [];
  $suggestions = [];

  $buffer = 4;

  // double-booking check
  foreach ([$home, $away] as $team) {
    $result = $conn->query("
      SELECT COUNT(*) FROM fixtures 
      WHERE league_id=$leagueId AND (home_team=$team OR away_team=$team) 
      AND CONCAT(match_date,' ',match_time) = '$dt'
    ")->fetch_row()[0];
    if ($result > 0) $conflicts[] = "Team $team is already scheduled at that time.";
  }

  // tight buffer check
  foreach ([$home, $away] as $team) {
    $bufferCheck = $conn->query("
      SELECT COUNT(*) FROM fixtures 
      WHERE league_id=$leagueId AND (home_team=$team OR away_team=$team) 
      AND ABS(TIMESTAMPDIFF(HOUR, CONCAT(match_date,' ',match_time), '$dt')) < $buffer
    ")->fetch_row()[0];
    if ($bufferCheck > 0) $conflicts[] = "Team $team has a game within $buffer hours.";
  }

  // venue conflict
  $venueBooked = $conn->query("
    SELECT COUNT(*) FROM fixtures 
    WHERE venue=$venue AND CONCAT(match_date,' ',match_time) = '$dt'
  ")->fetch_row()[0];
  if ($venueBooked > 0) $conflicts[] = "Venue is already booked at that time.";

  // suggestion
  $suggestions[] = "Try adjusting the time or swapping venue.";

  echo json_encode(['success'=>true, 'conflicts'=>$conflicts, 'suggestions'=>$suggestions]);
  exit;
}

echo json_encode(['success'=>false,'error'=>'Invalid action']);
