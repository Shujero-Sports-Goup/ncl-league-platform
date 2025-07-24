<?php
/**
 * Comprehensive Standings Helper
 * Powers both dashboard preview and PDF exports with consistent logic from standings.php
 */

function getLeagueStandings($conn, $leagueId, $limit = null) {
    // Fetch teams - using the EXACT same logic as standings.php
    $standings = [];
    $teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");

    if ($teams) {
        while ($row = $teams->fetch_assoc()) {
            $standings[$row['team_id']] = [
                'team_id' => $row['team_id'],
                'name' => $row['name'],
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'forfeits' => 0, // NEW: Forfeit tracking
                'points' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'goal_difference' => 0,
                'recent_form' => '', // Changed to string for consistency
                'last_match_date' => null
            ];
        }
    }

    // Fetch fixtures with scores - using the EXACT same logic as standings.php
    $sql = "
      SELECT f.fixture_id, f.home_team, f.away_team, f.match_date, r.score_home, r.score_away
      FROM fixtures f
      LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
      WHERE f.league_id = $leagueId
        AND r.score_home IS NOT NULL
        AND r.score_away IS NOT NULL
      ORDER BY f.match_date DESC
    ";
    $results = $conn->query($sql);

    // Store all matches for form calculation
    $allMatches = [];
    
    // Aggregate standings - using the EXACT same forfeit logic as standings.php
    if ($results) {
        while ($match = $results->fetch_assoc()) {
            $allMatches[] = $match;
            $home = $match['home_team'];
            $away = $match['away_team'];
            $sh = (int) $match['score_home'];
            $sa = (int) $match['score_away'];

            if (!isset($standings[$home]) || !isset($standings[$away])) continue;

            $standings[$home]['played']++;
            $standings[$away]['played']++;
            $standings[$home]['goals_for'] += $sh;
            $standings[$home]['goals_against'] += $sa;
            $standings[$away]['goals_for'] += $sa;
            $standings[$away]['goals_against'] += $sh;

            // FORFEIT LOGIC - EXACT same as standings.php
            $homeForfeit = ($sh === 0);
            $awayForfeit = ($sa === 0);

            if ($homeForfeit && $awayForfeit) {
                // Both teams forfeit - both get -1 point
                $standings[$home]['forfeits']++;
                $standings[$away]['forfeits']++;
                $standings[$home]['points'] -= 1;
                $standings[$away]['points'] -= 1;
                $standings[$home]['losses']++;
                $standings[$away]['losses']++;
                $standings[$home]['recent_form'] = 'F' . $standings[$home]['recent_form'];
                $standings[$away]['recent_form'] = 'F' . $standings[$away]['recent_form'];
            } elseif ($homeForfeit) {
                // Home team forfeits - away team wins, home team gets -1 point
                $standings[$away]['wins']++;
                $standings[$away]['points'] += 2;
                $standings[$home]['losses']++;
                $standings[$home]['forfeits']++;
                $standings[$home]['points'] -= 1;
                $standings[$home]['recent_form'] = 'F' . $standings[$home]['recent_form'];
                $standings[$away]['recent_form'] = 'W' . $standings[$away]['recent_form'];
            } elseif ($awayForfeit) {
                // Away team forfeits - home team wins, away team gets -1 point
                $standings[$home]['wins']++;
                $standings[$home]['points'] += 2;
                $standings[$away]['losses']++;
                $standings[$away]['forfeits']++;
                $standings[$away]['points'] -= 1;
                $standings[$away]['recent_form'] = 'F' . $standings[$away]['recent_form'];
                $standings[$home]['recent_form'] = 'W' . $standings[$home]['recent_form'];
            } else {
                // Normal game - no forfeits
                if ($sh > $sa) {
                    $standings[$home]['wins']++;
                    $standings[$home]['points'] += 2;
                    $standings[$away]['losses']++;
                    $standings[$away]['points'] += 1;
                    $standings[$home]['recent_form'] = 'W' . $standings[$home]['recent_form'];
                    $standings[$away]['recent_form'] = 'L' . $standings[$away]['recent_form'];
                } elseif ($sa > $sh) {
                    $standings[$away]['wins']++;
                    $standings[$away]['points'] += 2;
                    $standings[$home]['losses']++;
                    $standings[$home]['points'] += 1;
                    $standings[$away]['recent_form'] = 'W' . $standings[$away]['recent_form'];
                    $standings[$home]['recent_form'] = 'L' . $standings[$home]['recent_form'];
                }
            }

            // Update last match date
            $matchDate = $match['match_date'];
            if (!$standings[$home]['last_match_date'] || $matchDate > $standings[$home]['last_match_date']) {
                $standings[$home]['last_match_date'] = $matchDate;
            }
            if (!$standings[$away]['last_match_date'] || $matchDate > $standings[$away]['last_match_date']) {
                $standings[$away]['last_match_date'] = $matchDate;
            }
        }
    }

    // Calculate goal difference and recent form
    foreach ($standings as $teamId => &$team) {
        $team['goal_difference'] = $team['goals_for'] - $team['goals_against'];
        
        // Trim recent form to last 5 games - it's already built as a string
        if (strlen($team['recent_form']) > 5) {
            $team['recent_form'] = substr($team['recent_form'], 0, 5);
        }
    }

    // Convert to indexed array and sort by points descending - same as standings.php
    $standings = array_values($standings);
    usort($standings, function($a, $b) {
        if ($b['points'] !== $a['points']) {
            return $b['points'] <=> $a['points'];
        }
        if ($b['goal_difference'] !== $a['goal_difference']) {
            return $b['goal_difference'] <=> $a['goal_difference'];
        }
        return $b['goals_for'] <=> $a['goals_for'];
    });

    if ($limit) {
        return array_slice($standings, 0, $limit);
    }
    return $standings;
}

function getStandingsStats($conn, $leagueId) {
    $stats = [
        'total_teams' => 0,
        'total_matches' => 0,
        'total_goals' => 0,
        'avg_goals_per_game' => 0,
        'highest_scoring_team' => null,
        'best_defense' => null,
        'last_updated' => null,
        'matches_this_week' => 0,
        'pending_matches' => 0
    ];

    // Get basic stats
    $stats['total_teams'] = $conn->query("SELECT COUNT(*) as total FROM teams WHERE league_id = $leagueId")->fetch_assoc()['total'];
    
    $matchStats = $conn->query("
        SELECT 
            COUNT(*) as total_matches,
            SUM(r.score_home + r.score_away) as total_goals,
            MAX(f.match_date) as last_updated
        FROM fixtures f 
        JOIN match_results r ON f.fixture_id = r.fixture_id 
        WHERE f.league_id = $leagueId
    ")->fetch_assoc();
    
    $stats['total_matches'] = $matchStats['total_matches'] ?? 0;
    $stats['total_goals'] = $matchStats['total_goals'] ?? 0;
    $stats['avg_goals_per_game'] = $stats['total_matches'] > 0 ? round($stats['total_goals'] / $stats['total_matches'], 2) : 0;
    $stats['last_updated'] = $matchStats['last_updated'];

    // Get this week's matches
    $stats['matches_this_week'] = $conn->query("
        SELECT COUNT(*) as total 
        FROM fixtures f 
        JOIN match_results r ON f.fixture_id = r.fixture_id 
        WHERE f.league_id = $leagueId 
        AND f.match_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
        AND f.match_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
    ")->fetch_assoc()['total'];

    // Get pending matches
    $stats['pending_matches'] = $conn->query("
        SELECT COUNT(*) as total 
        FROM fixtures 
        WHERE league_id = $leagueId AND status = 'upcoming'
    ")->fetch_assoc()['total'];

    // Get best attacking and defensive teams
    $standings = getLeagueStandings($conn, $leagueId);
    if (!empty($standings)) {
        // Highest scoring team
        $highestScoring = array_reduce($standings, function($max, $team) {
            return ($team['goals_for'] > ($max['goals_for'] ?? 0)) ? $team : $max;
        }, []);
        $stats['highest_scoring_team'] = $highestScoring['name'] ?? null;

        // Best defense (lowest goals against with at least 1 game)
        $bestDefense = array_reduce($standings, function($min, $team) {
            if ($team['played'] == 0) return $min;
            return ($team['goals_against'] < ($min['goals_against'] ?? PHP_INT_MAX)) ? $team : $min;
        }, []);
        $stats['best_defense'] = $bestDefense['name'] ?? null;
    }

    return $stats;
}

function getRecentResults($conn, $leagueId, $limit = 10) {
    $sql = "
        SELECT 
            f.fixture_id,
            f.match_date,
            t1.name AS home_team,
            t2.name AS away_team,
            r.score_home,
            r.score_away,
            DATEDIFF(CURDATE(), f.match_date) as days_ago
        FROM fixtures f
        JOIN teams t1 ON f.home_team = t1.team_id
        JOIN teams t2 ON f.away_team = t2.team_id
        JOIN match_results r ON f.fixture_id = r.fixture_id
        WHERE f.league_id = ? AND f.status = 'played'
        ORDER BY f.match_date DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $leagueId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUpcomingFixtures($conn, $leagueId, $limit = 5) {
    $sql = "
        SELECT 
            f.fixture_id,
            f.match_date,
            f.match_time,
            f.venue,
            t1.name AS home_team,
            t2.name AS away_team
        FROM fixtures f
        JOIN teams t1 ON f.home_team = t1.team_id
        JOIN teams t2 ON f.away_team = t2.team_id
        WHERE f.league_id = ? AND f.status = 'upcoming'
        ORDER BY f.match_date ASC, f.match_time ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $leagueId, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Basketball-specific analytics functions
 */

function getBasketballStats($conn, $leagueId) {
    $stats = [];
    
    // Get all teams in the league
    $teamsSql = "SELECT team_id, name FROM teams WHERE league_id = ?";
    $teamsStmt = $conn->prepare($teamsSql);
    $teamsStmt->bind_param("i", $leagueId);
    $teamsStmt->execute();
    $teamsResult = $teamsStmt->get_result();
    
    while ($team = $teamsResult->fetch_assoc()) {
        $teamId = $team['team_id'];
        $teamName = $team['name'];
        
        // Get all scores for this team
        $scoresSql = "
            SELECT 
                CASE 
                    WHEN f.home_team = ? THEN r.score_home 
                    ELSE r.score_away 
                END AS team_score,
                CASE 
                    WHEN f.home_team = ? THEN r.score_away 
                    ELSE r.score_home 
                END AS opponent_score,
                f.match_date
            FROM fixtures f
            JOIN match_results r ON f.fixture_id = r.fixture_id
            WHERE f.league_id = ? AND (f.home_team = ? OR f.away_team = ?)
            ORDER BY f.match_date DESC
        ";
        
        $scoresStmt = $conn->prepare($scoresSql);
        $scoresStmt->bind_param("iiiii", $teamId, $teamId, $leagueId, $teamId, $teamId);
        $scoresStmt->execute();
        $scoresResult = $scoresStmt->get_result();
        
        $scores = [];
        $results = [];
        
        while ($row = $scoresResult->fetch_assoc()) {
            $teamScore = (int)$row['team_score'];
            $oppScore = (int)$row['opponent_score'];
            
            $scores[] = $teamScore;
            $results[] = $teamScore > $oppScore ? 'W' : 'L';
        }
        
        // Calculate statistics
        $gamesPlayed = count($scores);
        $avgPoints = $gamesPlayed > 0 ? array_sum($scores) / $gamesPlayed : 0;
        
        // Calculate scoring variance
        $variance = 0;
        if ($gamesPlayed > 1) {
            $squaredDiffs = array_map(function($score) use ($avgPoints) {
                return pow($score - $avgPoints, 2);
            }, $scores);
            $variance = array_sum($squaredDiffs) / ($gamesPlayed - 1);
        }
        
        // Get recent form (last 5 games)
        $recentForm = implode('', array_slice($results, 0, 5));
        
        $stats[$teamId] = [
            'team_name' => $teamName,
            'avg_points' => $avgPoints,
            'scoring_variance' => sqrt($variance), // Standard deviation
            'recent_form' => $recentForm,
            'games_played' => $gamesPlayed
        ];
    }
    
    return $stats;
}

function getTeamStreaks($conn, $leagueId) {
    $streaks = [];
    
    // Get all teams
    $teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");
    
    while ($team = $teams->fetch_assoc()) {
        $teamId = $team['team_id'];
        
        // Get recent results for this team
        $sql = "
            SELECT 
                f.home_team,
                f.away_team,
                r.score_home,
                r.score_away,
                f.match_date
            FROM fixtures f
            JOIN match_results r ON f.fixture_id = r.fixture_id
            WHERE f.league_id = ? AND (f.home_team = ? OR f.away_team = ?)
            ORDER BY f.match_date DESC
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $leagueId, $teamId, $teamId);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $currentStreak = null;
        $streakCount = 0;
        
        foreach ($results as $result) {
            $isHome = $result['home_team'] == $teamId;
            $teamScore = (int)($isHome ? $result['score_home'] : $result['score_away']);
            $oppScore = (int)($isHome ? $result['score_away'] : $result['score_home']);
            
            // Check for forfeit (score of 0) - same logic as standings.php
            $teamForfeit = ($teamScore === 0);
            $oppForfeit = ($oppScore === 0);
            
            if ($teamForfeit) {
                $gameResult = 'F'; // Forfeit
            } elseif ($teamScore > $oppScore) {
                $gameResult = 'W'; // Win
            } else {
                $gameResult = 'L'; // Loss
            }
            
            if ($currentStreak === null) {
                $currentStreak = $gameResult;
                $streakCount = 1;
            } elseif ($currentStreak === $gameResult) {
                $streakCount++;
            } else {
                break;
            }
        }
        
        $streaks[$teamId] = [
            'team_name' => $team['name'],
            'type' => $currentStreak ?? 'N/A',
            'count' => $streakCount
        ];
    }
    
    return $streaks;
}

function getHeadToHeadStats($conn, $leagueId) {
    $h2h = [];
    
    $sql = "
        SELECT 
            f.home_team,
            f.away_team,
            r.score_home,
            r.score_away,
            t1.name AS home_name,
            t2.name AS away_name
        FROM fixtures f
        JOIN teams t1 ON f.home_team = t1.team_id
        JOIN teams t2 ON f.away_team = t2.team_id
        JOIN match_results r ON f.fixture_id = r.fixture_id
        WHERE f.league_id = ?
        ORDER BY f.match_date DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $result) {
        $key = min($result['home_team'], $result['away_team']) . '_' . max($result['home_team'], $result['away_team']);
        
        if (!isset($h2h[$key])) {
            $h2h[$key] = [
                'team1' => $result['home_team'] < $result['away_team'] ? $result['home_name'] : $result['away_name'],
                'team2' => $result['home_team'] < $result['away_team'] ? $result['away_name'] : $result['home_name'],
                'team1_wins' => 0,
                'team2_wins' => 0,
                'total_games' => 0
            ];
        }
        
        $h2h[$key]['total_games']++;
        
        if ($result['score_home'] > $result['score_away']) {
            if ($result['home_team'] < $result['away_team']) {
                $h2h[$key]['team1_wins']++;
            } else {
                $h2h[$key]['team2_wins']++;
            }
        } else {
            if ($result['away_team'] < $result['home_team']) {
                $h2h[$key]['team1_wins']++;
            } else {
                $h2h[$key]['team2_wins']++;
            }
        }
    }
    
    return $h2h;
}

function getHomeAwayStats($conn, $leagueId) {
    $homeAway = [];
    
    // Get all teams first
    $teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId");
    
    while ($team = $teams->fetch_assoc()) {
        $teamId = $team['team_id'];
        $homeAway[$teamId] = [
            'home_wins' => 0,
            'home_losses' => 0,
            'home_games' => 0,
            'away_wins' => 0,
            'away_losses' => 0,
            'away_games' => 0
        ];
    }
    
    // Get home stats
    $sql = "
        SELECT 
            f.home_team,
            r.score_home,
            r.score_away
        FROM fixtures f
        JOIN match_results r ON f.fixture_id = r.fixture_id
        WHERE f.league_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $result) {
        $homeTeam = $result['home_team'];
        
        if (isset($homeAway[$homeTeam])) {
            $homeAway[$homeTeam]['home_games']++;
            if ($result['score_home'] > $result['score_away']) {
                $homeAway[$homeTeam]['home_wins']++;
            } else {
                $homeAway[$homeTeam]['home_losses']++;
            }
        }
    }
    
    // Get away stats
    $sql = "
        SELECT 
            f.away_team,
            r.score_home,
            r.score_away
        FROM fixtures f
        JOIN match_results r ON f.fixture_id = r.fixture_id
        WHERE f.league_id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $leagueId);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $result) {
        $awayTeam = $result['away_team'];
        
        if (isset($homeAway[$awayTeam])) {
            $homeAway[$awayTeam]['away_games']++;
            if ($result['score_away'] > $result['score_home']) {
                $homeAway[$awayTeam]['away_wins']++;
            } else {
                $homeAway[$awayTeam]['away_losses']++;
            }
        }
    }
    
    return $homeAway;
}
?>