<?php
require_once('../db_connect.php');
require_once('../vendor/autoload.php');

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// Check if multi-league export
$isMultiLeague = isset($_GET['multi_league']) && $_GET['multi_league'] === 'true';

if ($isMultiLeague) {
    $nclLeagueId = $_GET['ncl_league_id'] ?? 1;
    $welLeagueId = $_GET['wel_league_id'] ?? 2;
    $leagueIds = [$nclLeagueId, $welLeagueId];
} else {
    $leagueId = $_GET['league_id'] ?? $_SESSION['league_id'] ?? 1;
    $leagueIds = [$leagueId];
}

$format = $_GET['format'] ?? 'weekly';
$includeVenues = isset($_GET['include_venues']);
$includeUnscheduled = isset($_GET['include_unscheduled']);

// Get league info
if ($isMultiLeague) {
    $leagueRes = $conn->query("SELECT * FROM leagues WHERE league_id IN (" . implode(',', $leagueIds) . ") ORDER BY league_id");
    $leagues = [];
    while ($row = $leagueRes->fetch_assoc()) {
        $leagues[] = $row;
    }
    $leagueName = "NCL & WEL Combined";
} else {
    $leagueRes = $conn->query("SELECT * FROM leagues WHERE league_id = {$leagueIds[0]}");
    $league = $leagueRes->fetch_assoc();
    $leagueName = $league['name'];
}

// Get all fixtures
$sql = "
    SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
           t1.name AS home_team, t2.name AS away_team,
           l.abbreviation AS league_abbr, l.name AS league_name, f.league_id,
           r.score_home, r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    JOIN leagues l ON f.league_id = l.league_id
    LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.league_id IN (" . implode(',', $leagueIds) . ")
    ORDER BY f.match_date, f.match_time
";

$stmt = $conn->prepare($sql);
if ($isMultiLeague) {
    $stmt->execute();
} else {
    $stmt->bind_param("i", $leagueIds[0]);
    $stmt->execute();
}
$result = $stmt->get_result();

$fixtures = [];
while ($row = $result->fetch_assoc()) {
    $fixtures[] = $row;
}

// Group fixtures by week for weekly format
function groupFixturesByWeek($fixtures) {
    $weeks = [];
    $weekNumber = 1;
    
    foreach ($fixtures as $fixture) {
        $fixtureDate = new DateTime($fixture['match_date']);
        $dayOfWeek = $fixtureDate->format('l');
        
        // Find the start of the week (Monday)
        $weekStart = clone $fixtureDate;
        $weekStart->modify('last monday');
        if ($weekStart > $fixtureDate) {
            $weekStart->modify('-7 days');
        }
        
        $weekKey = $weekStart->format('Y-m-d');
        
        if (!isset($weeks[$weekKey])) {
            $weekEnd = clone $weekStart;
            $weekEnd->modify('+6 days');
            
            $weeks[$weekKey] = [
                'week_number' => $weekNumber++,
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'fixtures' => [
                    'Friday' => [],
                    'Saturday' => [],
                    'Sunday' => []
                ]
            ];
        }
        
        if (in_array($dayOfWeek, ['Friday', 'Saturday', 'Sunday'])) {
            $weeks[$weekKey]['fixtures'][$dayOfWeek][] = $fixture;
        }
    }
    
    return array_values($weeks);
}

// Generate HTML based on format
$html = '';

if ($format === 'weekly') {
    $fixturesByWeek = groupFixturesByWeek($fixtures);
    $html = generateWeeklyHTML($league, $fixturesByWeek, $includeVenues, $includeUnscheduled);
} elseif ($format === 'chronological') {
    $html = generateChronologicalHTML($league, $fixtures, $includeVenues);
} elseif ($format === 'team_schedule') {
    $html = generateTeamScheduleHTML($league, $fixtures, $conn, $leagueId, $includeVenues);
}

// Generate PDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = sanitizeFilename($league['name']) . '_fixtures_' . $format . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);

function generateWeeklyHTML($league, $fixturesByWeek, $includeVenues, $includeUnscheduled) {
    $leagueName = htmlspecialchars($league['name']);
    $currentDate = date('F j, Y');
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$leagueName} - Season Fixtures</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
            .league-name { font-size: 28px; font-weight: bold; color: #007bff; margin: 0; }
            .subtitle { font-size: 16px; color: #666; margin: 5px 0; }
            .week-container { margin-bottom: 40px; page-break-inside: avoid; }
            .week-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 15px; border-radius: 8px 8px 0 0; font-size: 18px; font-weight: bold; }
            .week-dates { font-size: 14px; font-weight: normal; opacity: 0.9; }
            .week-content { border: 2px solid #007bff; border-top: none; border-radius: 0 0 8px 8px; }
            .day-section { padding: 15px; border-bottom: 1px solid #eee; }
            .day-section:last-child { border-bottom: none; }
            .day-header { font-size: 16px; font-weight: bold; margin-bottom: 10px; padding: 8px 0; border-bottom: 2px solid #f8f9fa; }
            .day-friday { color: #28a745; }
            .day-saturday { color: #ffc107; }
            .day-sunday { color: #17a2b8; }
            .fixture { margin-bottom: 8px; padding: 10px; background: #f8f9fa; border-left: 4px solid #007bff; border-radius: 4px; }
            .fixture-teams { font-weight: bold; font-size: 14px; }
            .fixture-details { font-size: 12px; color: #666; margin-top: 5px; }
            .no-games { padding: 20px; text-align: center; color: #999; font-style: italic; background: #fff9c4; border-radius: 4px; }
            .unscheduled { background: #fff3cd; border-left-color: #ffc107; }
            .page-break { page-break-before: always; }
            .summary { background: #e7f3ff; padding: 15px; border-radius: 8px; margin-top: 30px; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 15px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1 class='league-name'>{$leagueName}</h1>
            <p class='subtitle'>Season Fixtures - Weekly Schedule</p>
            <p class='subtitle'>Generated on {$currentDate}</p>
        </div>
    ";
    
    $totalGames = 0;
    $totalWeeks = count($fixturesByWeek);
    $pageBreakCounter = 0;
    
    foreach ($fixturesByWeek as $index => $weekData) {
        if ($pageBreakCounter > 0 && $pageBreakCounter % 3 === 0) {
            $html .= "<div class='page-break'></div>";
        }
        
        $weekStart = date('M j', strtotime($weekData['week_start']));
        $weekEnd = date('M j, Y', strtotime($weekData['week_end']));
        
        $html .= "
        <div class='week-container'>
            <div class='week-header'>
                Week {$weekData['week_number']}
                <span class='week-dates'>({$weekStart} - {$weekEnd})</span>
            </div>
            <div class='week-content'>
        ";
        
        foreach (['Friday', 'Saturday', 'Sunday'] as $day) {
            $dayClass = 'day-' . strtolower($day);
            $dayIcon = ['Friday' => '🌙', 'Saturday' => '☀️', 'Sunday' => '⭐'];
            
            $html .= "<div class='day-section'>";
            $html .= "<div class='day-header {$dayClass}'>{$dayIcon[$day]} {$day}</div>";
            
            if (isset($weekData['fixtures'][$day]) && count($weekData['fixtures'][$day]) > 0) {
                foreach ($weekData['fixtures'][$day] as $fixture) {
                    $time = date('g:i A', strtotime($fixture['match_time']));
                    $statusClass = $fixture['status'] === 'played' ? '' : (strtotime($fixture['match_date']) < time() ? 'unscheduled' : '');
                    
                    $html .= "<div class='fixture {$statusClass}'>";
                    $html .= "<div class='fixture-teams'>{$fixture['home_team']} vs {$fixture['away_team']}</div>";
                    $html .= "<div class='fixture-details'>";
                    $html .= "⏰ {$time}";
                    
                    if ($includeVenues) {
                        $html .= " | 📍 {$fixture['venue']}";
                    }
                    
                    if ($fixture['status'] === 'played' && isset($fixture['score_home'], $fixture['score_away'])) {
                        $html .= " | 🏆 Final: {$fixture['score_home']} - {$fixture['score_away']}";
                    }
                    
                    $html .= "</div></div>";
                    $totalGames++;
                }
            } else {
                if ($includeUnscheduled) {
                    $html .= "<div class='no-games'>⚠️ No games scheduled</div>";
                }
            }
            
            $html .= "</div>";
        }
        
        $html .= "</div></div>";
        $pageBreakCounter++;
    }
    
    $html .= "
        <div class='summary'>
            <h3>Season Summary</h3>
            <p><strong>Total Weeks:</strong> {$totalWeeks}</p>
            <p><strong>Total Games:</strong> {$totalGames}</p>
            <p><strong>Schedule:</strong> Games on Fridays, Saturdays, and Sundays</p>
        </div>
        
        <div class='footer'>
            <p>Generated by NCL League Platform | {$currentDate}</p>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

function generateChronologicalHTML($league, $fixtures, $includeVenues) {
    $leagueName = htmlspecialchars($league['name']);
    $currentDate = date('F j, Y');
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$leagueName} - Season Fixtures</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
            .league-name { font-size: 28px; font-weight: bold; color: #007bff; margin: 0; }
            .subtitle { font-size: 16px; color: #666; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #007bff; color: white; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            tr:hover { background-color: #e3f2fd; }
            .date-col { width: 15%; }
            .time-col { width: 10%; }
            .match-col { width: 40%; }
            .venue-col { width: 20%; }
            .status-col { width: 15%; }
            .played { color: #28a745; font-weight: bold; }
            .upcoming { color: #007bff; }
            .overdue { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1 class='league-name'>{$leagueName}</h1>
            <p class='subtitle'>Season Fixtures - Chronological List</p>
            <p class='subtitle'>Generated on {$currentDate}</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th class='date-col'>Date</th>
                    <th class='time-col'>Time</th>
                    <th class='match-col'>Match</th>";
    
    if ($includeVenues) {
        $html .= "<th class='venue-col'>Venue</th>";
    }
    
    $html .= "
                    <th class='status-col'>Status</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach ($fixtures as $fixture) {
        $date = date('M j, Y', strtotime($fixture['match_date']));
        $time = date('g:i A', strtotime($fixture['match_time']));
        $dayOfWeek = date('l', strtotime($fixture['match_date']));
        
        $statusClass = 'upcoming';
        $statusText = 'Upcoming';
        
        if ($fixture['status'] === 'played') {
            $statusClass = 'played';
            $statusText = 'Played';
            if (isset($fixture['score_home'], $fixture['score_away'])) {
                $statusText .= " ({$fixture['score_home']}-{$fixture['score_away']})";
            }
        } elseif (strtotime($fixture['match_date']) < time()) {
            $statusClass = 'overdue';
            $statusText = 'Overdue';
        }
        
        $html .= "<tr>";
        $html .= "<td>{$dayOfWeek}<br><small>{$date}</small></td>";
        $html .= "<td>{$time}</td>";
        $html .= "<td><strong>{$fixture['home_team']}</strong> vs <strong>{$fixture['away_team']}</strong></td>";
        
        if ($includeVenues) {
            $html .= "<td>{$fixture['venue']}</td>";
        }
        
        $html .= "<td class='{$statusClass}'>{$statusText}</td>";
        $html .= "</tr>";
    }
    
    $html .= "
            </tbody>
        </table>
        
        <div style='margin-top: 30px; text-align: center; font-size: 12px; color: #666;'>
            <p>Generated by NCL League Platform | {$currentDate}</p>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

function generateTeamScheduleHTML($league, $fixtures, $conn, $leagueId, $includeVenues) {
    // Get all teams
    $teamsRes = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId ORDER BY name");
    $teams = [];
    while ($row = $teamsRes->fetch_assoc()) {
        $teams[$row['team_id']] = $row['name'];
    }
    
    $leagueName = htmlspecialchars($league['name']);
    $currentDate = date('F j, Y');
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>{$leagueName} - Team Schedules</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
            .header { text-align: center; border-bottom: 3px solid #007bff; padding-bottom: 20px; margin-bottom: 30px; }
            .league-name { font-size: 28px; font-weight: bold; color: #007bff; margin: 0; }
            .subtitle { font-size: 16px; color: #666; margin: 5px 0; }
            .team-section { margin-bottom: 40px; page-break-inside: avoid; }
            .team-header { background: #007bff; color: white; padding: 15px; border-radius: 8px 8px 0 0; font-size: 18px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; border: 2px solid #007bff; border-top: none; border-radius: 0 0 8px 8px; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; font-weight: bold; }
            tr:nth-child(even) { background-color: #f8f9fa; }
            .home-game { background-color: #e8f5e8; }
            .away-game { background-color: #fff3cd; }
            .page-break { page-break-before: always; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1 class='league-name'>{$leagueName}</h1>
            <p class='subtitle'>Team-wise Schedule</p>
            <p class='subtitle'>Generated on {$currentDate}</p>
        </div>
    ";
    
    $teamCount = 0;
    foreach ($teams as $teamId => $teamName) {
        if ($teamCount > 0 && $teamCount % 2 === 0) {
            $html .= "<div class='page-break'></div>";
        }
        
        $teamFixtures = array_filter($fixtures, function($fixture) use ($teamId) {
            return $fixture['home_team'] == $teamId || $fixture['away_team'] == $teamId;
        });
        
        $html .= "
        <div class='team-section'>
            <div class='team-header'>{$teamName}</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Opponent</th>
                        <th>H/A</th>";
        
        if ($includeVenues) {
            $html .= "<th>Venue</th>";
        }
        
        $html .= "
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>";
        
        foreach ($teamFixtures as $fixture) {
            $isHome = $fixture['home_team'] == $teamId;
            $opponent = $isHome ? $fixture['away_team'] : $fixture['home_team'];
            $homeAway = $isHome ? 'Home' : 'Away';
            $rowClass = $isHome ? 'home-game' : 'away-game';
            
            $date = date('M j, Y', strtotime($fixture['match_date']));
            $time = date('g:i A', strtotime($fixture['match_time']));
            
            $result = '-';
            if ($fixture['status'] === 'played' && isset($fixture['score_home'], $fixture['score_away'])) {
                if ($isHome) {
                    $result = "{$fixture['score_home']} - {$fixture['score_away']}";
                } else {
                    $result = "{$fixture['score_away']} - {$fixture['score_home']}";
                }
            }
            
            $html .= "<tr class='{$rowClass}'>";
            $html .= "<td>{$date}</td>";
            $html .= "<td>{$time}</td>";
            $html .= "<td>{$opponent}</td>";
            $html .= "<td>{$homeAway}</td>";
            
            if ($includeVenues) {
                $html .= "<td>{$fixture['venue']}</td>";
            }
            
            $html .= "<td>{$result}</td>";
            $html .= "</tr>";
        }
        
        $html .= "
                </tbody>
            </table>
        </div>";
        
        $teamCount++;
    }
    
    $html .= "
        <div style='margin-top: 30px; text-align: center; font-size: 12px; color: #666;'>
            <p>Generated by NCL League Platform | {$currentDate}</p>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}
?>
