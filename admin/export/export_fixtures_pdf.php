<?php
require_once('../../db_connect.php');
require_once('../../vendor/autoload.php');



$leagueId = (int) ($_GET['league'] ?? 0);
$pdf = new TCPDF();
$pdf->SetTitle('Fixture Schedule');
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

$html = '<h2 style="text-align:center">Upcoming Fixtures</h2>';
$html .= '<table border="1" cellpadding="5">';
$html .= '<tr bgcolor="#ffd966">
  <th><b>Date</b></th>
  <th><b>Time</b></th>
  <th><b>Match</b></th>
  <th><b>Venue</b></th>
  <th><b>Status</b></th>
</tr>';

$sql = "
  SELECT f.match_date, f.match_time, f.venue, f.status,
         t1.name AS home, t2.name AS away
  FROM fixtures f
  JOIN teams t1 ON f.home_team = t1.team_id
  JOIN teams t2 ON f.away_team = t2.team_id
  WHERE f.league_id = $leagueId
  ORDER BY f.match_date, f.match_time
";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
  $match = "{$row['home']} vs {$row['away']}";
  $html .= "<tr>
    <td>{$row['match_date']}</td>
    <td>{$row['match_time']}</td>
    <td>$match</td>
    <td>{$row['venue']}</td>
    <td>{$row['status']}</td>
  </tr>";
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('fixtures.pdf', 'D');
exit;
