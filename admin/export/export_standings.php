<?php
require_once('../../db_connect.php');
$leagueId = (int) ($_GET['league'] ?? 0);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="standings.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Team', 'Wins', 'Draws', 'Losses', 'Points']);

$sql = "
  SELECT t.name AS team,
    SUM(CASE WHEN ((f.home_team = t.team_id AND r.score_home > r.score_away) 
                OR (f.away_team = t.team_id AND r.score_away > r.score_home)) THEN 1 ELSE 0 END) AS wins,
    SUM(CASE WHEN r.score_home = r.score_away THEN 1 ELSE 0 END) AS draws,
    SUM(CASE WHEN ((f.home_team = t.team_id AND r.score_home < r.score_away) 
                OR (f.away_team = t.team_id AND r.score_away < r.score_home)) THEN 1 ELSE 0 END) AS losses,
    SUM(CASE 
          WHEN ((f.home_team = t.team_id AND r.score_home > r.score_away) 
             OR (f.away_team = t.team_id AND r.score_away > r.score_home)) THEN 3
          WHEN r.score_home = r.score_away THEN 1 ELSE 0 
        END) AS points
  FROM fixtures f
  JOIN match_results r ON f.fixture_id = r.fixture_id
  JOIN teams t ON t.team_id IN (f.home_team, f.away_team)
  WHERE f.league_id = $leagueId AND f.status = 'played'
  GROUP BY t.team_id
  ORDER BY points DESC
";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) fputcsv($output, $row);
fclose($output);
exit;
