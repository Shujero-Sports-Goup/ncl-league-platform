<?php
require_once('../db_connect.php');

header('Content-Type: application/json');

$leagueId = $_GET['league_id'] ?? 1;

// Get fixtures from the current league (both upcoming and recent results)
$sql = "
    SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
           t1.name AS home_team, t2.name AS away_team,
           r.score_home, r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.league_id = ?
    ORDER BY 
        CASE WHEN f.status = 'upcoming' THEN 1 ELSE 2 END,
        f.match_date DESC
    LIMIT 20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $leagueId);
$stmt->execute();
$result = $stmt->get_result();

$fixtures = [];
while ($row = $result->fetch_assoc()) {
    $fixtures[] = $row;
}

if (count($fixtures) > 0) {
    echo json_encode([
        'success' => true,
        'fixtures' => $fixtures,
        'count' => count($fixtures)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No fixtures found for this league'
    ]);
}
?>
