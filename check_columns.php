<?php
require_once 'db_connect.php';

// Check if the database uses home_team or home_team_id
$tables = ['fixtures', 'match_results'];

foreach ($tables as $table) {
    echo "<h3>$table Table Columns:</h3>";
    try {
        $result = $conn->query("SHOW COLUMNS FROM $table");
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        echo "<p>Columns: " . implode(', ', $columns) . "</p>";
        
        // Check for specific column patterns
        $homeTeamColumn = in_array('home_team_id', $columns) ? 'home_team_id' : 
                         (in_array('home_team', $columns) ? 'home_team' : 'NOT_FOUND');
        $awayTeamColumn = in_array('away_team_id', $columns) ? 'away_team_id' : 
                         (in_array('away_team', $columns) ? 'away_team' : 'NOT_FOUND');
        
        echo "<p><strong>Home team column:</strong> $homeTeamColumn</p>";
        echo "<p><strong>Away team column:</strong> $awayTeamColumn</p>";
        
        if ($table == 'match_results') {
            $homeScoreColumn = in_array('home_score', $columns) ? 'home_score' : 
                              (in_array('score_home', $columns) ? 'score_home' : 'NOT_FOUND');
            $awayScoreColumn = in_array('away_score', $columns) ? 'away_score' : 
                              (in_array('score_away', $columns) ? 'score_away' : 'NOT_FOUND');
            
            echo "<p><strong>Home score column:</strong> $homeScoreColumn</p>";
            echo "<p><strong>Away score column:</strong> $awayScoreColumn</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error with $table: " . $e->getMessage() . "</p>";
    }
    echo "<hr>";
}
?>
