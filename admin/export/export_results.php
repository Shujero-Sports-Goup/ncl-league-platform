<?php
require_once('../../db_connect.php');
$leagueId = (int) ($_GET['league'] ?? 0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="results.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Match', 'Score', 'Venue']);

$sql = "
  SELECT f.match_date, f.venue,
         t1.name AS home, t2.name AS away,
         r.score_home, r.score_away
  FROM fixtures f
  JOIN match_results r ON f.fixture_id = r.fixture_id
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  WHERE f.league_id = $leagueId AND f.status = 'played'
  ORDER BY f.match_date ASC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
  $match = "{$row['home']} vs {$row['away']}";
  $score = "{$row['score_home']} : {$row['score_away']}";
  fputcsv($output, [$row['match_date'], $match, $score, $row['venue']]);
}
fclose($output);
exit;
