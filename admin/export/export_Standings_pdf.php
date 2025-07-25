<?php
session_start();
require_once('../../db_connect.php');
require_once('../../vendor/autoload.php');
require_once('../../includes/auth.php');
require_once('../helpers/standings_helper.php');

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

$leagueId = (int) ($_GET['league'] ?? 0);
if (!$leagueId) {
    die('Invalid league ID');
}

// Get league information
$leagueQuery = $conn->prepare("SELECT name, abbreviation, logo_url FROM leagues WHERE league_id = ?");
$leagueQuery->bind_param("i", $leagueId);
$leagueQuery->execute();
$leagueResult = $leagueQuery->get_result();
$league = $leagueResult->fetch_assoc();

if (!$league) {
    die('League not found');
}

// Create PDF with enhanced settings
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Nukta League Platform');
$pdf->SetAuthor('Nukta League Administration');
$pdf->SetTitle($league['name'] . ' - Complete Standings Report');
$pdf->SetSubject('League Standings with Statistics');

// Set margins
$pdf->SetMargins(15, 20, 15);
$pdf->SetAutoPageBreak(TRUE, 20);

// Add page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Header with league info
$headerHtml = '
<table width="100%" style="border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;">
    <tr>
        <td style="text-align: left; width: 70%;">
            <h1 style="color: #2c5aa0; margin: 0; font-size: 24px;">' . htmlspecialchars($league['name']) . '</h1>
            <h2 style="color: #666; margin: 5px 0 0 0; font-size: 18px;">Complete Standings Report</h2>
            <p style="color: #888; margin: 5px 0 0 0; font-size: 12px;">Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        </td>
        <td style="text-align: right; width: 30%;">
            <p style="color: #666; font-size: 14px; margin: 0;"><strong>' . htmlspecialchars($league['abbreviation']) . '</strong></p>
        </td>
    </tr>
</table>';

$pdf->writeHTML($headerHtml, true, false, true, false, '');

// Get comprehensive standings data
$standings = getLeagueStandings($conn, $leagueId);

// Build standings table
$standingsHtml = '
<h3 style="color: #2c5aa0; margin: 20px 0 10px 0;">League Standings</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #2c5aa0; color: white;">
        <th style="text-align: center; width: 7%;"><strong>Pos</strong></th>
        <th style="text-align: left; width: 28%;"><strong>Team</strong></th>
        <th style="text-align: center; width: 7%;"><strong>GP</strong></th>
        <th style="text-align: center; width: 7%;"><strong>W</strong></th>
        <th style="text-align: center; width: 7%;"><strong>L</strong></th>
        <th style="text-align: center; width: 7%;"><strong>F</strong></th>
        <th style="text-align: center; width: 8%;"><strong>GF</strong></th>
        <th style="text-align: center; width: 8%;"><strong>GA</strong></th>
        <th style="text-align: center; width: 8%;"><strong>GD</strong></th>
        <th style="text-align: center; width: 8%;"><strong>Pts</strong></th>
    </tr>';

$position = 1;
$teamsData = [];
foreach ($standings as $row) {
    $teamsData[] = $row;
    $goalDiff = ($row['goals_for'] ?? 0) - ($row['goals_against'] ?? 0);
    
    $standingsHtml .= '<tr>';
    $standingsHtml .= '<td style="text-align: center; font-weight: bold;">' . $position . '</td>';
    $standingsHtml .= '<td style="text-align: left;">' . htmlspecialchars($row['name']) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($row['played'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($row['wins'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($row['losses'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center; color: #d97706;">' . ($row['forfeits'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($row['goals_for'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($row['goals_against'] ?? 0) . '</td>';
    $standingsHtml .= '<td style="text-align: center;">' . ($goalDiff >= 0 ? '+' : '') . $goalDiff . '</td>';
    $standingsHtml .= '<td style="text-align: center; font-weight: bold;' . (($row['points'] ?? 0) < 0 ? ' color: #dc2626;' : '') . '">' . ($row['points'] ?? 0) . '</td>';
    $standingsHtml .= '</tr>';
    $position++;
}

$standingsHtml .= '</table>';

// Legend
$legendHtml = '
<div style="margin-top: 15px;">
    <h4 style="color: #2c5aa0; margin: 15px 0 5px 0;">Legend & Scoring Rules:</h4>
    <p style="font-size: 10px; margin: 2px 0;">
        <strong>GP:</strong> Games Played &nbsp;&nbsp;
        <strong>W:</strong> Wins &nbsp;&nbsp;
        <strong>L:</strong> Losses &nbsp;&nbsp;
        <strong>F:</strong> Forfeits &nbsp;&nbsp;
        <strong>GF:</strong> Goals For &nbsp;&nbsp;
        <strong>GA:</strong> Goals Against &nbsp;&nbsp;
        <strong>GD:</strong> Goal Difference &nbsp;&nbsp;
        <strong>Pts:</strong> Points
    </p>
    <p style="font-size: 10px; margin: 5px 0 0 0; color: #666;">
        <strong>Scoring:</strong> Win = 2 points, Loss = 1 point, Forfeit = -1 point (marked by referee)
    </p>
</div>';

$pdf->writeHTML($standingsHtml, true, false, true, false, '');
$pdf->writeHTML($legendHtml, true, false, true, false, '');

// Recent Results Section
$recentResultsQuery = "
    SELECT 
        f.fixture_id,
        f.match_date,
        t1.name AS home_team,
        t2.name AS away_team,
        r.score_home,
        r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.league_id = ? AND f.status = 'played'
    ORDER BY f.match_date DESC
    LIMIT 10
";

$recentStmt = $conn->prepare($recentResultsQuery);
$recentStmt->bind_param("i", $leagueId);
$recentStmt->execute();
$recentResults = $recentStmt->get_result();

if ($recentResults->num_rows > 0) {
    $resultsHtml = '
    <h3 style="color: #2c5aa0; margin: 25px 0 10px 0;">Recent Results</h3>
    <table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse;">
        <tr style="background-color: #f0f0f0;">
            <th style="text-align: center; width: 20%;"><strong>Date</strong></th>
            <th style="text-align: left; width: 35%;"><strong>Home Team</strong></th>
            <th style="text-align: center; width: 10%;"><strong>Score</strong></th>
            <th style="text-align: left; width: 35%;"><strong>Away Team</strong></th>
        </tr>';

    while ($match = $recentResults->fetch_assoc()) {
        $resultsHtml .= '<tr>';
        $resultsHtml .= '<td style="text-align: center;">' . date('M j, Y', strtotime($match['match_date'])) . '</td>';
        $resultsHtml .= '<td style="text-align: left;">' . htmlspecialchars($match['home_team']) . '</td>';
        $resultsHtml .= '<td style="text-align: center; font-weight: bold;">' . $match['score_home'] . ' - ' . $match['score_away'] . '</td>';
        $resultsHtml .= '<td style="text-align: left;">' . htmlspecialchars($match['away_team']) . '</td>';
        $resultsHtml .= '</tr>';
    }

    $resultsHtml .= '</table>';
    $pdf->writeHTML($resultsHtml, true, false, true, false, '');
}

// Statistics Summary
$totalGames = $conn->query("SELECT COUNT(*) as total FROM fixtures WHERE league_id = $leagueId AND status = 'played'")->fetch_assoc()['total'];
$totalGoals = $conn->query("SELECT SUM(score_home + score_away) as total FROM match_results r JOIN fixtures f ON r.fixture_id = f.fixture_id WHERE f.league_id = $leagueId")->fetch_assoc()['total'];
$avgGoals = $totalGames > 0 ? round($totalGoals / $totalGames, 2) : 0;

$statsHtml = '
<h3 style="color: #2c5aa0; margin: 25px 0 10px 0;">League Statistics</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
    <tr style="background-color: #f8f9fa;">
        <td style="width: 50%; font-weight: bold;">Total Teams:</td>
        <td style="width: 50%;">' . count($teamsData) . '</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Total Games Played:</td>
        <td>' . $totalGames . '</td>
    </tr>
    <tr style="background-color: #f8f9fa;">
        <td style="font-weight: bold;">Total Goals Scored:</td>
        <td>' . ($totalGoals ?? 0) . '</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Average Goals per Game:</td>
        <td>' . $avgGoals . '</td>
    </tr>
</table>';

$pdf->writeHTML($statsHtml, true, false, true, false, '');

// Footer
$footerHtml = '
<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
    <p>Generated by Nukta League Platform | ' . date('Y') . '</p>
    <p>Powered by Nukta</p>
    <p>This report contains official league standings and statistics</p>
</div>';

$pdf->writeHTML($footerHtml, true, false, true, false, '');

// Output PDF
$filename = $league['abbreviation'] . '_Standings_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
exit;
