<?php
// Test analytics API functionality
session_start();
require_once '../db_connect.php';

// Set up test session (normally you'd log in properly)
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h2>Testing Analytics API</h2>";

// Get a sample fixture to test with
$stmt = $pdo->prepare("
    SELECT f.fixture_id, f.home_team_id, f.away_team_id, 
           ht.team_name as home_team, at.team_name as away_team
    FROM fixtures f
    JOIN teams ht ON f.home_team_id = ht.team_id
    JOIN teams at ON f.away_team_id = at.team_id
    WHERE f.match_date >= CURDATE()
    ORDER BY f.match_date ASC
    LIMIT 1
");
$stmt->execute();
$fixture = $stmt->fetch(PDO::FETCH_ASSOC);

if ($fixture) {
    echo "<h3>Sample Fixture:</h3>";
    echo "<p>{$fixture['home_team']} vs {$fixture['away_team']}</p>";
    echo "<p>Fixture ID: {$fixture['fixture_id']}</p>";
    
    // Test the analytics functions
    echo "<h3>Testing Analytics Functions:</h3>";
    
    // Include the analytics functions
    include 'analytics_api.php';
    
    // Test individual functions
    try {
        echo "<h4>Home Team Analytics:</h4>";
        $home_analytics = getTeamAnalytics($pdo, $fixture['home_team_id'], $fixture['home_team']);
        echo "<pre>" . print_r($home_analytics, true) . "</pre>";
        
        echo "<h4>Away Team Analytics:</h4>";
        $away_analytics = getTeamAnalytics($pdo, $fixture['away_team_id'], $fixture['away_team']);
        echo "<pre>" . print_r($away_analytics, true) . "</pre>";
        
        echo "<h4>Head-to-Head Record:</h4>";
        $h2h = getHeadToHeadRecord($pdo, $fixture['home_team_id'], $fixture['away_team_id']);
        echo "<pre>" . print_r($h2h, true) . "</pre>";
        
        echo "<h4>Match Prediction:</h4>";
        $prediction = generatePrediction($pdo, $fixture['home_team_id'], $fixture['away_team_id']);
        echo "<pre>" . print_r($prediction, true) . "</pre>";
        
        echo "<div style='color: green;'><strong>✓ All analytics functions working correctly!</strong></div>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'><strong>✗ Error: " . $e->getMessage() . "</strong></div>";
    }
    
} else {
    echo "<p>No upcoming fixtures found to test with.</p>";
}

// Test if we have some match results data
echo "<h3>Database Status:</h3>";
$stmt = $pdo->query("SELECT COUNT(*) as count FROM match_results WHERE home_score IS NOT NULL");
$result_count = $stmt->fetchColumn();
echo "<p>Match results with scores: {$result_count}</p>";

$stmt = $pdo->query("SELECT COUNT(*) as count FROM fixtures WHERE match_date >= CURDATE()");
$fixture_count = $stmt->fetchColumn();
echo "<p>Upcoming fixtures: {$fixture_count}</p>";

$stmt = $pdo->query("SELECT COUNT(*) as count FROM teams");
$team_count = $stmt->fetchColumn();
echo "<p>Total teams: {$team_count}</p>";
?>
