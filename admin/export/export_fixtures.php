<?php
require_once('../../db_connect.php');
$leagueId = (int) ($_GET['league'] ?? 0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="fixtures.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Date', 'Time', 'Match', 'Venue', 'Status']);

$sql = "
  SELECT f.match_date, f.match_time, f.venue, f.status,
         t1.name AS home, t2.name AS away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  WHERE f.league_id = $leagueId
  ORDER BY f.match_date ASC
";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
  $match = "{$row['home']} vs {$row['away']}";
  fputcsv($output, [$row['match_date'], $row['match_time'], $match, $row['venue'], $row['status']]);
}
fclose($output);
exit;
