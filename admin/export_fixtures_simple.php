<?php
require_once('../db_connect.php');
session_start();

$leagueId = $_GET['league_id'] ?? $_SESSION['league_id'] ?? 1;
$format = $_GET['format'] ?? 'weekly';

// Get league info
$leagueRes = $conn->query("SELECT * FROM leagues WHERE league_id = $leagueId");
$league = $leagueRes->fetch_assoc();

// Get all fixtures
$sql = "
    SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
           t1.name AS home_team, t2.name AS away_team,
           r.score_home, r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.league_id = ?
    ORDER BY f.match_date, f.match_time
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $leagueId);
$stmt->execute();
$result = $stmt->get_result();

$fixtures = [];
while ($row = $result->fetch_assoc()) {
    $fixtures[] = $row;
}

// Set headers for download
$filename = sanitizeFilename($league['name']) . '_fixtures_' . $format . '_' . date('Y-m-d') . '.html';
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
}

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

$leagueName = htmlspecialchars($league['name']);
$currentDate = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $leagueName ?> - Season Fixtures</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            text-align: center;
            border-bottom: 4px solid #007bff;
            padding-bottom: 30px;
            margin-bottom: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
        }
        
        .league-name {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .subtitle {
            font-size: 18px;
            margin: 10px 0;
            opacity: 0.9;
        }
        
        .controls {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 5px solid #007bff;
        }
        
        .week-container {
            margin-bottom: 40px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
            overflow: hidden;
            page-break-inside: avoid;
        }
        
        .week-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .week-dates {
            font-size: 14px;
            font-weight: normal;
            opacity: 0.9;
        }
        
        .week-content {
            background: white;
        }
        
        .day-section {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .day-section:last-child {
            border-bottom: none;
        }
        
        .day-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding: 10px 0;
            border-bottom: 3px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .day-friday { color: #28a745; }
        .day-saturday { color: #ffc107; }
        .day-sunday { color: #17a2b8; }
        
        .fixture {
            margin-bottom: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 5px solid #007bff;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        
        .fixture:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .fixture-teams {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .fixture-details {
            font-size: 14px;
            color: #666;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .no-games {
            padding: 25px;
            text-align: center;
            color: #999;
            font-style: italic;
            background: #fff9c4;
            border-radius: 8px;
            border: 2px dashed #ffc107;
        }
        
        .unscheduled {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .summary {
            background: linear-gradient(135deg, #e7f3ff, #cce7ff);
            padding: 25px;
            border-radius: 12px;
            margin-top: 40px;
            border-left: 5px solid #007bff;
        }
        
        .summary h3 {
            color: #007bff;
            margin-top: 0;
        }
        
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .fixture-details {
                flex-direction: column;
                gap: 5px;
            }
            
            .week-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="league-name"><?= $leagueName ?></h1>
        <p class="subtitle">🏀 Season Fixtures - Weekly Schedule</p>
        <p class="subtitle">📅 Generated on <?= $currentDate ?></p>
    </div>

    <div class="controls no-print">
        <h4>📋 Export Options</h4>
        <p><strong>Instructions:</strong> Use your browser's print function (Ctrl+P) to save as PDF or print this schedule.</p>
        <button onclick="window.print()" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
            🖨️ Print / Save as PDF
        </button>
    </div>

    <?php
    if ($format === 'weekly') {
        $fixturesByWeek = groupFixturesByWeek($fixtures);
        $totalGames = 0;
        $totalWeeks = count($fixturesByWeek);
        
        foreach ($fixturesByWeek as $index => $weekData) {
            if ($index > 0 && $index % 4 === 0) {
                echo "<div class='page-break'></div>";
            }
            
            $weekStart = date('M j', strtotime($weekData['week_start']));
            $weekEnd = date('M j, Y', strtotime($weekData['week_end']));
            
            echo "<div class='week-container'>";
            echo "<div class='week-header'>";
            echo "<div>📅 Week {$weekData['week_number']}</div>";
            echo "<div class='week-dates'>{$weekStart} - {$weekEnd}</div>";
            echo "</div>";
            echo "<div class='week-content'>";
            
            foreach (['Friday', 'Saturday', 'Sunday'] as $day) {
                $dayClass = 'day-' . strtolower($day);
                $dayIcons = ['Friday' => '🌙', 'Saturday' => '☀️', 'Sunday' => '⭐'];
                
                echo "<div class='day-section'>";
                echo "<div class='day-header {$dayClass}'>";
                echo "<span>{$dayIcons[$day]}</span>";
                echo "<span>{$day}</span>";
                echo "</div>";
                
                if (isset($weekData['fixtures'][$day]) && count($weekData['fixtures'][$day]) > 0) {
                    foreach ($weekData['fixtures'][$day] as $fixture) {
                        $time = date('g:i A', strtotime($fixture['match_time']));
                        $statusClass = $fixture['status'] === 'played' ? '' : (strtotime($fixture['match_date']) < time() ? 'unscheduled' : '');
                        
                        echo "<div class='fixture {$statusClass}'>";
                        echo "<div class='fixture-teams'>{$fixture['home_team']} vs {$fixture['away_team']}</div>";
                        echo "<div class='fixture-details'>";
                        echo "<span>⏰ {$time}</span>";
                        echo "<span>📍 {$fixture['venue']}</span>";
                        
                        if ($fixture['status'] === 'played' && isset($fixture['score_home'], $fixture['score_away'])) {
                            echo "<span>🏆 Final: {$fixture['score_home']} - {$fixture['score_away']}</span>";
                        } else {
                            echo "<span>🎯 " . ucfirst($fixture['status']) . "</span>";
                        }
                        
                        echo "</div></div>";
                        $totalGames++;
                    }
                } else {
                    echo "<div class='no-games'>⚠️ No games scheduled for this day</div>";
                }
                
                echo "</div>";
            }
            
            echo "</div></div>";
        }
        
        // Summary section
        echo "<div class='summary'>";
        echo "<h3>📊 Season Summary</h3>";
        echo "<div class='stats-grid'>";
        echo "<div class='stat-item'><div class='stat-number'>{$totalWeeks}</div><div class='stat-label'>Total Weeks</div></div>";
        echo "<div class='stat-item'><div class='stat-number'>{$totalGames}</div><div class='stat-label'>Total Games</div></div>";
        echo "<div class='stat-item'><div class='stat-number'>3</div><div class='stat-label'>Game Days/Week</div></div>";
        
        $playedGames = count(array_filter($fixtures, function($f) { return $f['status'] === 'played'; }));
        echo "<div class='stat-item'><div class='stat-number'>{$playedGames}</div><div class='stat-label'>Games Played</div></div>";
        echo "</div>";
        
        echo "<p><strong>📅 Schedule:</strong> Games are scheduled on Fridays, Saturdays, and Sundays</p>";
        echo "<p><strong>🏟️ Multiple Venues:</strong> Games distributed across available courts</p>";
        echo "<p><strong>⚠️ Highlighted sections:</strong> Indicate unscheduled days or past due games</p>";
        echo "</div>";
        
    } else {
        // Chronological format
        echo "<div class='week-container'>";
        echo "<div class='week-header'><div>📋 All Fixtures - Chronological Order</div></div>";
        echo "<div class='week-content' style='padding: 20px;'>";
        
        $currentMonth = '';
        foreach ($fixtures as $fixture) {
            $fixtureMonth = date('F Y', strtotime($fixture['match_date']));
            
            if ($currentMonth !== $fixtureMonth) {
                if ($currentMonth !== '') echo "</div>";
                echo "<h4 style='color: #007bff; margin-top: 30px; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;'>{$fixtureMonth}</h4>";
                echo "<div>";
                $currentMonth = $fixtureMonth;
            }
            
            $date = date('D, M j', strtotime($fixture['match_date']));
            $time = date('g:i A', strtotime($fixture['match_time']));
            
            echo "<div class='fixture'>";
            echo "<div class='fixture-teams'>{$fixture['home_team']} vs {$fixture['away_team']}</div>";
            echo "<div class='fixture-details'>";
            echo "<span>📅 {$date}</span>";
            echo "<span>⏰ {$time}</span>";
            echo "<span>📍 {$fixture['venue']}</span>";
            
            if ($fixture['status'] === 'played' && isset($fixture['score_home'], $fixture['score_away'])) {
                echo "<span>🏆 Final: {$fixture['score_home']} - {$fixture['score_away']}</span>";
            } else {
                echo "<span>🎯 " . ucfirst($fixture['status']) . "</span>";
            }
            
            echo "</div></div>";
        }
        echo "</div></div>";
    }
    ?>

    <div class="footer">
        <p>🏀 Generated by Nukta League Platform | <?= $currentDate ?></p>
        <p>📧 For questions about fixtures, contact league administration</p>
    </div>
</body>
</html>
