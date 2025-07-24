<?php
require_once('../../db_connect.php');
require_once('../../vendor/autoload.php');


$leagueId = (int) ($_GET['league'] ?? 0);
$pdf = new TCPDF();
$pdf->SetTitle('Match Results');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

$html = '<h2 style="text-align:center">Match Results</h2>';
$html .= '<table border="1" cellpadding="5">';
$html .= '<tr bgcolor="#d9ead3">
  <th><b>Date</b></th>
  <th><b>Match</b></th>
  <th><b>Score</b></th>
  <th><b>Venue</b></th>
</tr>';

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
  $html .= "<tr>
    <td>{$row['match_date']}</td>
    <td>$match</td>
    <td><b>$score</b></td>
    <td>{$row['venue']}</td>
  </tr>";
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('results.pdf', 'D');
exit;
