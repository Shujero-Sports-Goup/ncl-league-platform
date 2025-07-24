<?php
require_once('../db_connect.php');
header('Content-Type: application/json');

// Accept league ID from GET request
$leagueId = isset($_GET['league_id']) ? intval($_GET['league_id']) : 1;

// Fetch played fixtures and scores
$sql = "
    SELECT f.match_date, t1.name AS home_team, t2.name AS away_team,
           r.score_home, r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.league_id = $leagueId AND f.status = 'played'
    ORDER BY f.match_date DESC
";

$result = $conn->query($sql);
$output = [];

while ($row = $result->fetch_assoc()) {
    $output[] = [
        'date' => $row['match_date'],
        'home' => $row['home_team'],
        'away' => $row['away_team'],
        'score' => "{$row['score_home']} - {$row['score_away']}"
    ];
}

echo json_encode($output);
