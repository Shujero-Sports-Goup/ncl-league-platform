<?php
require_once '../db_connect.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class FixtureManager {
    private $conn;
    private $ncl_teams = [];
    private $wel_teams = [];
    private $existing_fixtures = [];
    private $restricted_teams = ['Vikapu Academy', 'Rebels', 'Waylight Academy', 'Kayole South', 'Nibs Panthers', 'RRBA', 'Blazers'];
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadTeams();
        $this->loadExistingFixtures();
    }
    
    private function loadTeams() {
        // Load NCL teams
        $sql = "SELECT team_id, name FROM teams WHERE league_id = 1";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $this->ncl_teams[] = $row;
        }
        
        // Load WEL teams
        $sql = "SELECT team_id, name FROM teams WHERE league_id = 2";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $this->wel_teams[] = $row;
        }
    }
    
    private function loadExistingFixtures() {
        // Load ALL existing fixtures (including played games) with team details
        // This ensures we don't reschedule already played games
        $sql = "SELECT f.fixture_id, f.home_team, f.away_team, f.match_date, f.match_time, f.league_id, f.status, f.venue,
                       ht.name as home_team_name, at.name as away_team_name, l.name as league_name
                FROM fixtures f
                JOIN teams ht ON f.home_team = ht.team_id
                JOIN teams at ON f.away_team = at.team_id
                JOIN leagues l ON f.league_id = l.league_id
                ORDER BY f.match_date, f.match_time";
        
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $this->existing_fixtures[] = $row;
        }
    }
    
    private function isFixtureExists($home_id, $away_id, $league_id) {
        // Check if fixture exists (either as home vs away or away vs home)
        // This includes ALL fixtures: upcoming, played, postponed, etc.
        foreach ($this->existing_fixtures as $fixture) {
            if ($fixture['league_id'] == $league_id) {
                if (($fixture['home_team'] == $home_id && $fixture['away_team'] == $away_id) ||
                    ($fixture['home_team'] == $away_id && $fixture['away_team'] == $home_id)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    private function isGamePlayed($home_id, $away_id, $league_id) {
        foreach ($this->existing_fixtures as $fixture) {
            if (($fixture['home_team'] == $home_id && $fixture['away_team'] == $away_id && $fixture['league_id'] == $league_id) ||
                ($fixture['home_team'] == $away_id && $fixture['away_team'] == $home_id && $fixture['league_id'] == $league_id)) {
                return $fixture['status'] === 'played';
            }
        }
        return false;
    }
    
    private function canPlayOnWeekday($team_name) {
        return !in_array($team_name, $this->restricted_teams);
    }
    
    private function isTeamAvailableOnDate($team_id, $date, $fixtures) {
        // Check if team is already scheduled to play on this date in new fixtures
        foreach ($fixtures as $fixture) {
            if ($fixture['match_date'] == $date && 
                ($fixture['home_team']['team_id'] == $team_id || $fixture['away_team']['team_id'] == $team_id)) {
                return false;
            }
        }
        
        // Check existing fixtures in database (comprehensive check including ALL statuses)
        foreach ($this->existing_fixtures as $fixture) {
            if ($fixture['match_date'] == $date && 
                ($fixture['home_team'] == $team_id || $fixture['away_team'] == $team_id)) {
                // Team is already scheduled on this date (regardless of status)
                return false;
            }
        }
        
        return true;
    }
    
    private function getTeamScheduleForDate($team_id, $date) {
        $schedule = [];
        
        // Check existing fixtures in database
        foreach ($this->existing_fixtures as $fixture) {
            if ($fixture['match_date'] == $date && 
                ($fixture['home_team'] == $team_id || $fixture['away_team'] == $team_id)) {
                $schedule[] = [
                    'time' => $fixture['match_time'],
                    'opponent' => $fixture['home_team'] == $team_id ? $fixture['away_team_name'] : $fixture['home_team_name'],
                    'status' => $fixture['status']
                ];
            }
        }
        
        return $schedule;
    }
    
    private function canScheduleFixture($fixture, $date, $scheduled_fixtures) {
        return $this->isTeamAvailableOnDate($fixture['home_team']['team_id'], $date, $scheduled_fixtures) &&
               $this->isTeamAvailableOnDate($fixture['away_team']['team_id'], $date, $scheduled_fixtures);
    }
    
    private function generateRoundRobinFixtures($teams, $league_id) {
        $fixtures = [];
        $team_count = count($teams);
        
        // Generate all possible pairings
        for ($i = 0; $i < $team_count; $i++) {
            for ($j = $i + 1; $j < $team_count; $j++) {
                // Only include if fixture doesn't exist OR exists but hasn't been played
                // This prevents scheduling already played games
                if (!$this->isFixtureExists($teams[$i]['team_id'], $teams[$j]['team_id'], $league_id)) {
                    $fixtures[] = [
                        'home_team' => $teams[$i],
                        'away_team' => $teams[$j],
                        'league_id' => $league_id
                    ];
                }
            }
        }
        
        return $fixtures;
    }
    
    private function generateWeekendDates($start_date, $end_date) {
        $dates = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $day_of_week = $current->format('N');
            
            // Saturday (6) and Sunday (7)
            if ($day_of_week == 6 || $day_of_week == 7) {
                // Only include dates that don't already have scheduled games or have capacity for more
                if (!$this->isDateFullyScheduled($current->format('Y-m-d'))) {
                    $dates[] = $current->format('Y-m-d');
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $dates;
    }
    
    private function generateFridayDates($start_date, $end_date) {
        $dates = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $day_of_week = $current->format('N');
            
            // Friday (5)
            if ($day_of_week == 5) {
                // Only include dates that don't already have 2 Friday games
                if (!$this->isDateFullyScheduled($current->format('Y-m-d'), true)) {
                    $dates[] = $current->format('Y-m-d');
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $dates;
    }
    
    private function isDateFullyScheduled($date, $is_friday = false) {
        $max_games = $is_friday ? 2 : 5; // Friday has 2 slots, weekend has 5
        $scheduled_count = 0;
        
        // Count ALL fixtures on this date (upcoming, played, postponed, etc.)
        foreach ($this->existing_fixtures as $fixture) {
            if ($fixture['match_date'] == $date) {
                $scheduled_count++;
            }
        }
        
        return $scheduled_count >= $max_games;
    }
    
    private function getTimeSlots($is_weekend = true, $is_holiday = false) {
        if ($is_holiday) {
            return ['10:00:00', '11:30:00', '13:00:00', '14:30:00', '16:00:00'];
        } elseif ($is_weekend) {
            return ['10:00:00', '11:30:00', '13:00:00', '14:30:00', '16:00:00'];
        } else {
            return ['18:00:00', '19:30:00']; // Friday evening slots
        }
    }
    
    public function generateFixtures($start_date = '2025-07-18', $end_date = '2025-11-30') {
        $fixtures = [];
        $incomplete_weeks = [];

        // First, get all existing scheduled fixtures to include in the comprehensive view
        $existing_scheduled = $this->getExistingScheduledFixtures($start_date, $end_date);

        // Generate all fixtures needed (excluding already played games)
        $ncl_fixtures = $this->generateRoundRobinFixtures($this->ncl_teams, 1);
        $wel_fixtures = $this->generateRoundRobinFixtures($this->wel_teams, 2);
        
        // Shuffle fixtures for variety
        shuffle($ncl_fixtures);
        shuffle($wel_fixtures);
        
        // Track games played for balancing
        $team_game_counts = $this->getTeamGameCounts($existing_scheduled);
        $ncl_fixtures = $this->prioritizeIdleTeams($ncl_fixtures, $team_game_counts);
        $wel_fixtures = $this->prioritizeIdleTeams($wel_fixtures, $team_game_counts);

        // Get available dates (excluding fully scheduled dates)
        $weekend_dates = $this->generateWeekendDates($start_date, $end_date);
        $weeks = [];
        foreach ($weekend_dates as $date) {
            $week = date('o-W', strtotime($date));
            $weeks[$week][] = $date;
        }

        // For each week, schedule exactly 5 games per day with required split
        foreach ($weeks as $week => $dates) {
            $week_fixtures = [];
            $week_incomplete = false;
            foreach ($dates as $date) {
                $day_of_week = date('N', strtotime($date));
                $is_holiday = ($date == '2025-10-10');
                $time_slots = $this->getTimeSlots(true, $is_holiday);
                $already_scheduled = $this->getScheduledGamesForDate($date);
                $available_slots = array_values(array_diff($time_slots, array_column($already_scheduled, 'match_time')));
                $daily_scheduled = [];
                $slot_index = 0;
                $wel_needed = ($day_of_week == 6) ? 3 : 2;
                $ncl_needed = ($day_of_week == 6) ? 2 : 3;
                $wel_scheduled = 0;
                $ncl_scheduled = 0;
                
                // Create alternating pattern: Start with WEL if more WEL needed, otherwise NCL
                $current_league = ($wel_needed > $ncl_needed) ? 'WEL' : 'NCL';
                
                // Schedule games in alternating pattern
                while ($slot_index < count($available_slots) && ($wel_scheduled < $wel_needed || $ncl_scheduled < $ncl_needed)) {
                    $scheduled_this_slot = false;
                    
                    if ($current_league == 'WEL' && $wel_scheduled < $wel_needed) {
                        // Try to schedule WEL game
                        for ($i = 0; $i < count($wel_fixtures); $i++) {
                            $fixture = $wel_fixtures[$i];
                            if ($this->canScheduleFixture($fixture, $date, $daily_scheduled)) {
                                $fixture['match_date'] = $date;
                                $fixture['match_time'] = $available_slots[$slot_index];
                                $fixture['venue'] = 'KFC Court';
                                $fixture['league_name'] = 'WEL';
                                $fixture['is_new'] = true;
                                $week_fixtures[] = $fixture;
                                $daily_scheduled[] = $fixture;
                                array_splice($wel_fixtures, $i, 1);
                                $wel_scheduled++;
                                $slot_index++;
                                $scheduled_this_slot = true;
                                // Update game counts for balancing
                                $team_game_counts[$fixture['home_team']['team_id']]++;
                                $team_game_counts[$fixture['away_team']['team_id']]++;
                                $wel_fixtures = $this->prioritizeIdleTeams($wel_fixtures, $team_game_counts);
                                break;
                            }
                        }
                        $current_league = 'NCL'; // Switch to NCL for next slot
                    } else if ($current_league == 'NCL' && $ncl_scheduled < $ncl_needed) {
                        // Try to schedule NCL game
                        for ($i = 0; $i < count($ncl_fixtures); $i++) {
                            $fixture = $ncl_fixtures[$i];
                            if ($this->canScheduleFixture($fixture, $date, $daily_scheduled)) {
                                // On Saturday, prioritize restricted teams
                                $can_schedule = false;
                                if ($day_of_week == 6 && (!$this->canPlayOnWeekday($fixture['home_team']['name']) || !$this->canPlayOnWeekday($fixture['away_team']['name']))) {
                                    $can_schedule = true;
                                } elseif ($day_of_week != 6) { // Sunday: any NCL team
                                    $can_schedule = true;
                                }
                                
                                if ($can_schedule) {
                                    $fixture['match_date'] = $date;
                                    $fixture['match_time'] = $available_slots[$slot_index];
                                    $fixture['venue'] = 'KFC Court';
                                    $fixture['league_name'] = 'NCL';
                                    $fixture['is_new'] = true;
                                    $week_fixtures[] = $fixture;
                                    $daily_scheduled[] = $fixture;
                                    array_splice($ncl_fixtures, $i, 1);
                                    $ncl_scheduled++;
                                    $slot_index++;
                                    $scheduled_this_slot = true;
                                    // Update game counts for balancing
                                    $team_game_counts[$fixture['home_team']['team_id']]++;
                                    $team_game_counts[$fixture['away_team']['team_id']]++;
                                    $ncl_fixtures = $this->prioritizeIdleTeams($ncl_fixtures, $team_game_counts);
                                    break;
                                }
                            }
                        }
                        $current_league = 'WEL'; // Switch to WEL for next slot
                    } else {
                        // Current league quota filled, try the other league
                        if ($current_league == 'WEL') {
                            $current_league = 'NCL';
                        } else {
                            $current_league = 'WEL';
                        }
                    }
                    
                    // If we couldn't schedule anything in this slot, break to avoid infinite loop
                    if (!$scheduled_this_slot) {
                        break;
                    }
                }
                // If not enough games scheduled for the day, mark week as incomplete
                if (($wel_scheduled + $ncl_scheduled) < 5) {
                    $week_incomplete = true;
                }
            }
            if ($week_incomplete) {
                $incomplete_weeks[] = $week;
            }
            $fixtures = array_merge($fixtures, $week_fixtures);
        }
        // Schedule Friday fixtures (NCL only, eligible teams only)
        $friday_dates = $this->generateFridayDates($start_date, $end_date);
        foreach ($friday_dates as $date) {
            if (empty($ncl_fixtures)) break;
            $time_slots = $this->getTimeSlots(false);
            $already_scheduled = $this->getScheduledGamesForDate($date);
            $available_slots = array_values(array_diff($time_slots, array_column($already_scheduled, 'match_time')));
            $slot_index = 0;
            $daily_scheduled = [];
            $attempts = 0;
            $max_attempts = count($ncl_fixtures) * 2;
            while ($slot_index < count($available_slots) && $attempts < $max_attempts) {
                $found_fixture = false;
                for ($i = 0; $i < count($ncl_fixtures); $i++) {
                    $fixture = $ncl_fixtures[$i];
                    if ($this->canPlayOnWeekday($fixture['home_team']['name']) && 
                        $this->canPlayOnWeekday($fixture['away_team']['name']) &&
                        $this->canScheduleFixture($fixture, $date, $daily_scheduled)) {
                        $fixture['match_date'] = $date;
                        $fixture['match_time'] = $available_slots[$slot_index];
                        $fixture['venue'] = 'KFC Court';
                        $fixture['league_name'] = 'NCL';
                        $fixture['is_new'] = true;
                        $fixtures[] = $fixture;
                        $daily_scheduled[] = $fixture;
                        array_splice($ncl_fixtures, $i, 1);
                        $slot_index++;
                        $found_fixture = true;
                        break;
                    }
                }
                if (!$found_fixture) {
                    break;
                }
                $attempts++;
            }
        }
        // Combine existing scheduled fixtures with new fixtures
        $fixtures = array_merge($existing_scheduled, $fixtures);
        // Attach incomplete week info for UI feedback
        $GLOBALS['incomplete_weeks'] = $incomplete_weeks;
        return $fixtures;
    }
    
    private function getExistingScheduledFixtures($start_date, $end_date) {
        $fixtures = [];
        $sql = "SELECT f.fixture_id, f.league_id, f.match_date, f.match_time, f.venue, f.status,
                       ht.team_id as home_team_id, ht.name as home_team_name,
                       at.team_id as away_team_id, at.name as away_team_name,
                       l.name as league_name
                FROM fixtures f
                JOIN teams ht ON f.home_team = ht.team_id
                JOIN teams at ON f.away_team = at.team_id
                JOIN leagues l ON f.league_id = l.league_id
                WHERE f.match_date >= ? AND f.match_date <= ? 
                ORDER BY f.match_date, f.match_time";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $fixtures[] = [
                'fixture_id' => $row['fixture_id'],
                'league_id' => $row['league_id'],
                'home_team' => ['team_id' => $row['home_team_id'], 'name' => $row['home_team_name']],
                'away_team' => ['team_id' => $row['away_team_id'], 'name' => $row['away_team_name']],
                'match_date' => $row['match_date'],
                'match_time' => $row['match_time'],
                'venue' => $row['venue'],
                'league_name' => $row['league_name'],
                'status' => $row['status'],
                'is_new' => false
            ];
        }
        
        return $fixtures;
    }
    
    private function getScheduledGamesForDate($date) {
        $games = [];
        // Get ALL games scheduled for this date (regardless of status)
        foreach ($this->existing_fixtures as $fixture) {
            if ($fixture['match_date'] == $date) {
                $games[] = $fixture;
            }
        }
        return $games;
    }
    
    public function validateSchedule($fixtures) {
        $conflicts = [];
        $team_schedule = [];
        
        // Check for same-day conflicts
        foreach ($fixtures as $index => $fixture) {
            $date = $fixture['match_date'];
            $home_team = $fixture['home_team']['team_id'];
            $away_team = $fixture['away_team']['team_id'];
            
            // Track team schedules
            if (!isset($team_schedule[$home_team])) {
                $team_schedule[$home_team] = [];
            }
            if (!isset($team_schedule[$away_team])) {
                $team_schedule[$away_team] = [];
            }
            
            // Check if teams already have games on this date
            if (isset($team_schedule[$home_team][$date])) {
                $conflicts[] = [
                    'type' => 'same_day_conflict',
                    'team' => $fixture['home_team']['name'],
                    'date' => $date,
                    'existing_game' => $team_schedule[$home_team][$date],
                    'new_game' => $fixture
                ];
            }
            
            if (isset($team_schedule[$away_team][$date])) {
                $conflicts[] = [
                    'type' => 'same_day_conflict',
                    'team' => $fixture['away_team']['name'],
                    'date' => $date,
                    'existing_game' => $team_schedule[$away_team][$date],
                    'new_game' => $fixture
                ];
            }
            
            // Add to team schedule
            $team_schedule[$home_team][$date] = $fixture;
            $team_schedule[$away_team][$date] = $fixture;
        }
        
        return $conflicts;
    }
    
    public function getDatabaseStats() {
        $stats = [];
        
        // Get total fixtures in database
        $result = $this->conn->query("SELECT COUNT(*) as total FROM fixtures");
        $stats['total_fixtures'] = $result->fetch_assoc()['total'];
        
        // Get fixtures by league
        $result = $this->conn->query("SELECT l.name, COUNT(*) as count FROM fixtures f JOIN leagues l ON f.league_id = l.league_id GROUP BY f.league_id");
        while ($row = $result->fetch_assoc()) {
            $stats['by_league'][$row['name']] = $row['count'];
        }
        
        // Get fixtures by status
        $result = $this->conn->query("SELECT status, COUNT(*) as count FROM fixtures GROUP BY status");
        while ($row = $result->fetch_assoc()) {
            $stats['by_status'][$row['status']] = $row['count'];
        }
        
        // Get upcoming fixtures
        $result = $this->conn->query("SELECT COUNT(*) as count FROM fixtures WHERE match_date >= CURDATE() AND status = 'upcoming'");
        $stats['upcoming'] = $result->fetch_assoc()['count'];
        
        return $stats;
    }
    
    // Get comprehensive fixture statistics including played games
    public function getFixtureStatistics() {
        $stats = [
            'total_fixtures' => 0,
            'played_games' => 0,
            'upcoming_games' => 0,
            'postponed_games' => 0,
            'ncl_total' => 0,
            'wel_total' => 0,
            'ncl_played' => 0,
            'wel_played' => 0,
            'ncl_upcoming' => 0,
            'wel_upcoming' => 0
        ];
        
        foreach ($this->existing_fixtures as $fixture) {
            $stats['total_fixtures']++;
            
            // Count by status
            switch ($fixture['status']) {
                case 'played':
                    $stats['played_games']++;
                    break;
                case 'upcoming':
                    $stats['upcoming_games']++;
                    break;
                case 'postponed':
                    $stats['postponed_games']++;
                    break;
            }
            
            // Count by league
            if ($fixture['league_id'] == 1) { // NCL
                $stats['ncl_total']++;
                if ($fixture['status'] == 'played') {
                    $stats['ncl_played']++;
                } elseif ($fixture['status'] == 'upcoming') {
                    $stats['ncl_upcoming']++;
                }
            } elseif ($fixture['league_id'] == 2) { // WEL
                $stats['wel_total']++;
                if ($fixture['status'] == 'played') {
                    $stats['wel_played']++;
                } elseif ($fixture['status'] == 'upcoming') {
                    $stats['wel_upcoming']++;
                }
            }
        }
        
        return $stats;
    }

    public function saveFixtures($fixtures) {
        $saved_count = 0;
        
        foreach ($fixtures as $fixture) {
            // Only save new fixtures (not existing ones)
            if (isset($fixture['is_new']) && $fixture['is_new']) {
                $sql = "INSERT INTO fixtures (league_id, home_team, away_team, match_date, match_time, venue, status, created_at, priority) 
                        VALUES (?, ?, ?, ?, ?, ?, 'upcoming', NOW(), 'Normal')";
                
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("iiisss", 
                    $fixture['league_id'],
                    $fixture['home_team']['team_id'],
                    $fixture['away_team']['team_id'],
                    $fixture['match_date'],
                    $fixture['match_time'],
                    $fixture['venue']
                );
                
                if ($stmt->execute()) {
                    $saved_count++;
                }
            }
        }
        
        return $saved_count;
    }
    
    public function generatePDF($fixtures) {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        $html = $this->generatePDFHTML($fixtures);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $filename = 'Combined_Fixtures_' . date('Y-m-d_H-i-s') . '.pdf';
        $dompdf->stream($filename, array('Attachment' => true));
    }
    
    private function generatePDFHTML($fixtures) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                @page {
                    margin: 15mm;
                    size: A4;
                }
                
                body { 
                    font-family: "Arial", sans-serif; 
                    margin: 0; 
                    padding: 0;
                    line-height: 1.4;
                    color: #333;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                    border-bottom: 3px solid #2c3e50;
                    padding-bottom: 15px;
                }
                
                .header h1 {
                    color: #2c3e50;
                    font-size: 24px;
                    margin: 0 0 5px 0;
                    font-weight: bold;
                }
                
                .header .subtitle {
                    color: #7f8c8d;
                    font-size: 14px;
                    margin: 0;
                }
                
                .header .date-range {
                    color: #34495e;
                    font-size: 12px;
                    margin: 5px 0 0 0;
                    font-style: italic;
                }
                
                .stats-summary {
                    background: #f8f9fa;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 5px;
                    border: 1px solid #dee2e6;
                }
                
                .stats-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                
                .stats-row:last-child {
                    margin-bottom: 0;
                }
                
                .stat-item {
                    text-align: center;
                    flex: 1;
                }
                
                .stat-number {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2c3e50;
                }
                
                .stat-label {
                    font-size: 11px;
                    color: #7f8c8d;
                    text-transform: uppercase;
                }
                
                .week-section {
                    page-break-inside: avoid;
                    margin-bottom: 25px;
                }
                
                .week-header {
                    background: linear-gradient(135deg, #3498db, #2980b9);
                    color: white;
                    padding: 10px 15px;
                    margin-bottom: 15px;
                    border-radius: 5px;
                    font-weight: bold;
                    font-size: 16px;
                }
                
                .day-section {
                    margin-bottom: 20px;
                }
                
                .day-header {
                    background: #ecf0f1;
                    padding: 8px 12px;
                    margin-bottom: 10px;
                    border-left: 4px solid #3498db;
                    font-weight: bold;
                    font-size: 14px;
                    color: #2c3e50;
                }
                
                .fixtures-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 15px;
                }
                
                .fixtures-table th {
                    background: #34495e;
                    color: white;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .fixtures-table td {
                    padding: 8px;
                    border-bottom: 1px solid #dee2e6;
                    font-size: 12px;
                }
                
                .fixtures-table tr:hover {
                    background: #f8f9fa;
                }
                
                .time-cell {
                    width: 60px;
                    text-align: center;
                    font-weight: bold;
                    color: #2c3e50;
                }
                
                .league-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 12px;
                    font-size: 10px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    width: 35px;
                    text-align: center;
                }
                
                .league-ncl {
                    background: #e74c3c;
                    color: white;
                }
                
                .league-wel {
                    background: #27ae60;
                    color: white;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 10px;
                    font-size: 9px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                }
                
                .status-new {
                    background: #2ecc71;
                    color: white;
                }
                
                .status-scheduled {
                    background: #95a5a6;
                    color: white;
                }
                
                .status-played {
                    background: #f39c12;
                    color: white;
                }
                
                .teams-cell {
                    font-weight: 500;
                    color: #2c3e50;
                }
                
                .vs-separator {
                    color: #7f8c8d;
                    font-weight: normal;
                    margin: 0 5px;
                }
                
                .venue-cell {
                    color: #7f8c8d;
                    font-style: italic;
                    font-size: 11px;
                }
                
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #7f8c8d;
                    border-top: 1px solid #dee2e6;
                    padding-top: 15px;
                }
                
                .legend {
                    margin-top: 20px;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    border: 1px solid #dee2e6;
                }
                
                .legend-title {
                    font-weight: bold;
                    margin-bottom: 8px;
                    color: #2c3e50;
                    font-size: 12px;
                }
                
                .legend-item {
                    display: inline-block;
                    margin-right: 15px;
                    font-size: 11px;
                    color: #7f8c8d;
                }
                
                .no-fixtures {
                    text-align: center;
                    color: #7f8c8d;
                    font-style: italic;
                    padding: 20px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>NCL & WEL Combined Season Fixtures</h1>
                <p class="subtitle">Official Match Schedule</p>
                <p class="date-range">July 2025 - November 2025 • Regular Season</p>
            </div>
            
            <div class="stats-summary">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number">' . count($fixtures) . '</div>
                        <div class="stat-label">Total Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return $f['league_id'] == 1; })) . '</div>
                        <div class="stat-label">NCL Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return $f['league_id'] == 2; })) . '</div>
                        <div class="stat-label">WEL Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return isset($f['is_new']) && $f['is_new']; })) . '</div>
                        <div class="stat-label">New Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return isset($f['status']) && $f['status'] === 'played'; })) . '</div>
                        <div class="stat-label">Played Games</div>
                    </div>
                </div>
            </div>';
        
        // Group fixtures by week
        $weeks = [];
        foreach ($fixtures as $fixture) {
            $week_start = date('Y-m-d', strtotime('monday this week', strtotime($fixture['match_date'])));
            $weeks[$week_start][] = $fixture;
        }
        
        foreach ($weeks as $week_start => $week_fixtures) {
            $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));
            $html .= '<div class="week-section">';
            $html .= '<div class="week-header">Week of ' . date('F j', strtotime($week_start)) . ' - ' . date('F j, Y', strtotime($week_end)) . '</div>';
            
            // Group by day
            $days = [];
            foreach ($week_fixtures as $fixture) {
                $days[$fixture['match_date']][] = $fixture;
            }
            
            foreach ($days as $date => $day_fixtures) {
                $day_name = date('l, F j', strtotime($date));
                $html .= '<div class="day-section">';
                $html .= '<div class="day-header">' . $day_name . '</div>';
                
                if (empty($day_fixtures)) {
                    $html .= '<div class="no-fixtures">No fixtures scheduled</div>';
                } else {
                    $html .= '<table class="fixtures-table">';
                    $html .= '<thead>';
                    $html .= '<tr>';
                    $html .= '<th>Time</th>';
                    $html .= '<th>League</th>';
                    $html .= '<th>Match</th>';
                    $html .= '<th>Status</th>';
                    $html .= '<th>Venue</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    
                    // Sort fixtures by time
                    usort($day_fixtures, function($a, $b) {
                        return strtotime($a['match_time']) - strtotime($b['match_time']);
                    });
                    
                    foreach ($day_fixtures as $fixture) {
                        $league_class = $fixture['league_id'] == 1 ? 'league-ncl' : 'league-wel';
                        $status_class = 'status-scheduled';
                        $status_text = 'Scheduled';
                        
                        if (isset($fixture['is_new']) && $fixture['is_new']) {
                            $status_class = 'status-new';
                            $status_text = 'New';
                        } elseif (isset($fixture['status']) && $fixture['status'] === 'played') {
                            $status_class = 'status-played';
                            $status_text = 'Played';
                        }
                        
                        $html .= '<tr>';
                        $html .= '<td class="time-cell">' . date('g:i A', strtotime($fixture['match_time'])) . '</td>';
                        $html .= '<td><span class="league-badge ' . $league_class . '">' . $fixture['league_name'] . '</span></td>';
                        $html .= '<td class="teams-cell">' . htmlspecialchars($fixture['home_team']['name']) . ' <span class="vs-separator">vs</span> ' . htmlspecialchars($fixture['away_team']['name']) . '</td>';
                        $html .= '<td><span class="status-badge ' . $status_class . '">' . $status_text . '</span></td>';
                        $html .= '<td class="venue-cell">' . htmlspecialchars($fixture['venue']) . '</td>';
                        $html .= '</tr>';
                    }
                    
                    $html .= '</tbody>';
                    $html .= '</table>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '
            <div class="legend">
                <div class="legend-title">Legend:</div>
                <div class="legend-item"><span class="league-badge league-ncl">NCL</span> Nairobi County League</div>
                <div class="legend-item"><span class="league-badge league-wel">WEL</span> Women\'s Elite League</div>
                <div class="legend-item"><span class="status-badge status-new">New</span> Newly Generated</div>
                <div class="legend-item"><span class="status-badge status-scheduled">Scheduled</span> Previously Scheduled</div>
                <div class="legend-item"><span class="status-badge status-played">Played</span> Completed</div>
            </div>
            
            <div class="footer">
                <p>Generated on ' . date('F j, Y \a\t g:i A') . ' • Nukta League Platform</p>
                <p>Powered by Nukta </p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    // Helper to count games for each team in a fixture list
    private function getTeamGameCounts($fixtures) {
        $counts = [];
        foreach ($this->ncl_teams as $team) {
            $counts[$team['team_id']] = 0;
        }
        foreach ($this->wel_teams as $team) {
            $counts[$team['team_id']] = 0;
        }
        foreach ($fixtures as $fixture) {
            $counts[$fixture['home_team']['team_id']]++;
            $counts[$fixture['away_team']['team_id']]++;
        }
        return $counts;
    }

    // Helper to sort fixtures by teams with fewest games played
    private function prioritizeIdleTeams($fixtures, $team_game_counts) {
        usort($fixtures, function($a, $b) use ($team_game_counts) {
            $a_min = min($team_game_counts[$a['home_team']['team_id']], $team_game_counts[$a['away_team']['team_id']]);
            $b_min = min($team_game_counts[$b['home_team']['team_id']], $team_game_counts[$b['away_team']['team_id']]);
            return $a_min <=> $b_min;
        });
        return $fixtures;
    }
    
    public function getAvailableWeeks($start_date = '2025-07-18', $end_date = '2025-11-30') {
        $weeks = [];
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        // Find the Monday of the week containing start_date
        $current->modify('monday this week');
        
        while ($current <= $end) {
            $week_start = $current->format('Y-m-d');
            $week_end = clone $current;
            $week_end->modify('sunday this week');
            
            // Check if this week has any fixtures
            $has_fixtures = $this->weekHasFixtures($week_start, $week_end->format('Y-m-d'));
            
            $weeks[] = [
                'start_date' => $week_start,
                'end_date' => $week_end->format('Y-m-d'),
                'display_name' => $current->format('M j') . ' - ' . $week_end->format('M j, Y'),
                'has_fixtures' => $has_fixtures
            ];
            
            $current->modify('+1 week');
        }
        
        return $weeks;
    }
    
    private function weekHasFixtures($week_start, $week_end) {
        $sql = "SELECT COUNT(*) as count FROM fixtures 
                WHERE match_date >= ? AND match_date <= ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $week_start, $week_end);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        return $count > 0;
    }
    
    public function generateWeeklyPDF($week_start_date) {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Get fixtures for the specific week
        $week_fixtures = $this->getWeeklyFixtures($week_start_date);
        
        $html = $this->generateWeeklyPDFHTML($week_fixtures, $week_start_date);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start_date)));
        $filename = 'Weekly_Fixtures_' . date('M_j', strtotime($week_start_date)) . '_to_' . date('M_j_Y', strtotime($week_end)) . '.pdf';
        $dompdf->stream($filename, array('Attachment' => true));
    }
    
    private function getWeeklyFixtures($week_start_date) {
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start_date)));
        
        $sql = "SELECT f.fixture_id, f.league_id, f.match_date, f.match_time, f.venue, f.status,
                       ht.team_id as home_team_id, ht.name as home_team_name,
                       at.team_id as away_team_id, at.name as away_team_name,
                       l.name as league_name
                FROM fixtures f
                JOIN teams ht ON f.home_team = ht.team_id
                JOIN teams at ON f.away_team = at.team_id
                JOIN leagues l ON f.league_id = l.league_id
                WHERE f.match_date >= ? AND f.match_date <= ? 
                ORDER BY f.match_date, f.match_time";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $week_start_date, $week_end);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $fixtures = [];
        while ($row = $result->fetch_assoc()) {
            $fixtures[] = [
                'fixture_id' => $row['fixture_id'],
                'league_id' => $row['league_id'],
                'home_team' => ['team_id' => $row['home_team_id'], 'name' => $row['home_team_name']],
                'away_team' => ['team_id' => $row['away_team_id'], 'name' => $row['away_team_name']],
                'match_date' => $row['match_date'],
                'match_time' => $row['match_time'],
                'venue' => $row['venue'],
                'league_name' => $row['league_name'],
                'status' => $row['status']
            ];
        }
        
        return $fixtures;
    }
    
    private function generateWeeklyPDFHTML($fixtures, $week_start_date) {
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start_date)));
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                @page {
                    margin: 15mm;
                    size: A4;
                }
                
                body { 
                    font-family: "Arial", sans-serif; 
                    margin: 0; 
                    padding: 0;
                    line-height: 1.4;
                    color: #333;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 25px;
                    border-bottom: 3px solid #2c3e50;
                    padding-bottom: 15px;
                }
                
                .header h1 {
                    color: #2c3e50;
                    font-size: 28px;
                    margin: 0 0 5px 0;
                    font-weight: bold;
                }
                
                .header .week-range {
                    color: #3498db;
                    font-size: 18px;
                    margin: 10px 0;
                    font-weight: bold;
                }
                
                .header .subtitle {
                    color: #7f8c8d;
                    font-size: 14px;
                    margin: 0;
                }
                
                .stats-summary {
                    background: #f8f9fa;
                    padding: 20px;
                    margin-bottom: 30px;
                    border-radius: 8px;
                    border: 1px solid #dee2e6;
                    text-align: center;
                }
                
                .stats-row {
                    display: flex;
                    justify-content: space-around;
                    margin-bottom: 10px;
                }
                
                .stat-item {
                    text-align: center;
                    flex: 1;
                }
                
                .stat-number {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2c3e50;
                }
                
                .stat-label {
                    font-size: 12px;
                    color: #7f8c8d;
                    text-transform: uppercase;
                    margin-top: 5px;
                }
                
                .day-section {
                    margin-bottom: 30px;
                    page-break-inside: avoid;
                }
                
                .day-header {
                    background: linear-gradient(135deg, #3498db, #2980b9);
                    color: white;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 8px;
                    font-weight: bold;
                    font-size: 18px;
                    text-align: center;
                }
                
                .fixtures-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 15px;
                    margin-bottom: 20px;
                }
                
                .fixture-card {
                    background: white;
                    border: 2px solid #dee2e6;
                    border-radius: 8px;
                    padding: 15px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                
                .fixture-card.ncl {
                    border-left: 6px solid #e74c3c;
                }
                
                .fixture-card.wel {
                    border-left: 6px solid #27ae60;
                }
                
                .fixture-time {
                    text-align: center;
                    font-size: 18px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                }
                
                .fixture-league {
                    text-align: center;
                    margin-bottom: 15px;
                }
                
                .league-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                
                .league-ncl {
                    background: #e74c3c;
                    color: white;
                }
                
                .league-wel {
                    background: #27ae60;
                    color: white;
                }
                
                .fixture-teams {
                    text-align: center;
                    margin-bottom: 15px;
                }
                
                .team-name {
                    font-weight: bold;
                    color: #2c3e50;
                    font-size: 14px;
                    margin: 5px 0;
                }
                
                .vs-separator {
                    color: #7f8c8d;
                    font-weight: bold;
                    margin: 10px 0;
                    font-size: 16px;
                }
                
                .fixture-venue {
                    text-align: center;
                    color: #7f8c8d;
                    font-size: 12px;
                    font-style: italic;
                }
                
                .no-fixtures {
                    text-align: center;
                    color: #7f8c8d;
                    font-style: italic;
                    padding: 40px;
                    background: #f8f9fa;
                    border-radius: 8px;
                }
                
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 12px;
                    color: #7f8c8d;
                    border-top: 2px solid #dee2e6;
                    padding-top: 20px;
                }
                
                .share-info {
                    background: #e3f2fd;
                    border: 1px solid #bbdefb;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                    text-align: center;
                }
                
                .share-info h3 {
                    color: #1976d2;
                    margin-bottom: 10px;
                    font-size: 16px;
                }
                
                .share-info p {
                    color: #424242;
                    margin: 5px 0;
                    font-size: 12px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Weekly Fixtures</h1>
                <div class="week-range">' . date('F j', strtotime($week_start_date)) . ' - ' . date('F j, Y', strtotime($week_end)) . '</div>
                <p class="subtitle">NCL & WEL League Matches</p>
            </div>
            
            <div class="share-info">
                <h3>📱 Share This Week\'s Fixtures</h3>
                <p>This weekly fixture list is perfect for sharing with teams, players, and fans</p>
                <p>Print or share digitally via WhatsApp, email, or social media</p>
            </div>
            
            <div class="stats-summary">
                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-number">' . count($fixtures) . '</div>
                        <div class="stat-label">Total Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return $f['league_id'] == 1; })) . '</div>
                        <div class="stat-label">NCL Games</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">' . count(array_filter($fixtures, function($f) { return $f['league_id'] == 2; })) . '</div>
                        <div class="stat-label">WEL Games</div>
                    </div>
                </div>
            </div>';
        
        // Group fixtures by day
        $days = [];
        foreach ($fixtures as $fixture) {
            $days[$fixture['match_date']][] = $fixture;
        }
        
        // Generate days from Monday to Sunday
        $current_date = new DateTime($week_start_date);
        for ($i = 0; $i < 7; $i++) {
            $date = $current_date->format('Y-m-d');
            $day_name = $current_date->format('l, F j');
            $day_fixtures = $days[$date] ?? [];
            
            $html .= '<div class="day-section">';
            $html .= '<div class="day-header">' . $day_name . '</div>';
            
            if (empty($day_fixtures)) {
                $html .= '<div class="no-fixtures">No fixtures scheduled for this day</div>';
            } else {
                // Sort fixtures by time
                usort($day_fixtures, function($a, $b) {
                    return strtotime($a['match_time']) - strtotime($b['match_time']);
                });
                
                $html .= '<div class="fixtures-grid">';
                foreach ($day_fixtures as $fixture) {
                    $league_class = $fixture['league_id'] == 1 ? 'ncl' : 'wel';
                    $league_badge_class = $fixture['league_id'] == 1 ? 'league-ncl' : 'league-wel';
                    
                    $html .= '<div class="fixture-card ' . $league_class . '">';
                    $html .= '<div class="fixture-time">' . date('g:i A', strtotime($fixture['match_time'])) . '</div>';
                    $html .= '<div class="fixture-league"><span class="league-badge ' . $league_badge_class . '">' . $fixture['league_name'] . '</span></div>';
                    $html .= '<div class="fixture-teams">';
                    $html .= '<div class="team-name">' . htmlspecialchars($fixture['home_team']['name']) . '</div>';
                    $html .= '<div class="vs-separator">VS</div>';
                    $html .= '<div class="team-name">' . htmlspecialchars($fixture['away_team']['name']) . '</div>';
                    $html .= '</div>';
                    $html .= '<div class="fixture-venue">' . htmlspecialchars($fixture['venue']) . '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $current_date->modify('+1 day');
        }
        
        $html .= '
            <div class="footer">
                <p><strong>Generated on ' . date('F j, Y \a\t g:i A') . '</strong></p>
                <p>Nukta League Platform • For live updates visit the official platform</p>
                <p>📧 Contact: info@nclleague.com • 📱 WhatsApp: +254 XXX XXX XXX</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}

// Handle form submission
$message = '';
$fixtures = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $start_date = $_POST['start_date'] ?? '2025-07-18';
    $end_date = $_POST['end_date'] ?? '2025-11-30';
    
    $manager = new FixtureManager($conn);
    
    if ($action === 'generate') {
        $fixtures = $manager->generateFixtures($start_date, $end_date);
        $conflicts = $manager->validateSchedule($fixtures);
        $new_count = count(array_filter($fixtures, function($f) { return isset($f['is_new']) && $f['is_new']; }));
        $existing_count = count(array_filter($fixtures, function($f) { return !isset($f['is_new']) || !$f['is_new']; }));
        
        if (empty($conflicts)) {
            $message = "✅ Comprehensive schedule generated successfully: {$existing_count} existing games + {$new_count} new games = " . count($fixtures) . " total fixtures through November 30th. No scheduling conflicts detected.";
        } else {
            $message = "⚠️ Schedule generated with " . count($conflicts) . " conflicts detected. Please review before saving.";
        }
        
        // Get database stats
        $db_stats = $manager->getDatabaseStats();
        
    } elseif ($action === 'save') {
        $fixtures = $manager->generateFixtures($start_date, $end_date);
        $conflicts = $manager->validateSchedule($fixtures);
        
        if (empty($conflicts)) {
            $saved = $manager->saveFixtures($fixtures);
            $message = "✅ Successfully saved {$saved} NEW fixtures to database! (Existing games were preserved)";
        } else {
            $message = "❌ Cannot save fixtures due to " . count($conflicts) . " scheduling conflicts. Please regenerate.";
        }
        
    } elseif ($action === 'download') {
        $fixtures = $manager->generateFixtures($start_date, $end_date);
        $manager->generatePDF($fixtures);
        exit;
        
    } elseif ($action === 'download_weekly') {
        $week_start = $_POST['week_start'] ?? '';
        if ($week_start) {
            $manager->generateWeeklyPDF($week_start);
            exit;
        } else {
            $message = "❌ Please select a week to download.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCL & WEL Fixture Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .fixture-card { margin-bottom: 20px; }
        .league-ncl { border-left: 4px solid #e74c3c; }
        .league-wel { border-left: 4px solid #27ae60; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .week-section { margin-bottom: 40px; }
        .day-header { background: #e9ecef; padding: 10px; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">NCL & WEL Fixture Manager</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo strpos($message, '✅') !== false ? 'success' : (strpos($message, '⚠️') !== false ? 'warning' : 'info'); ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($conflicts) && !empty($conflicts)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle"></i> Scheduling Conflicts Detected:</h6>
                        <ul class="mb-0">
                            <?php foreach ($conflicts as $conflict): ?>
                                <li><?php echo htmlspecialchars($conflict['team']); ?> has multiple games on <?php echo date('M j, Y', strtotime($conflict['date'])); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($db_stats)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6><i class="fas fa-database"></i> Database Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="h4 text-primary"><?php echo $db_stats['total_fixtures']; ?></div>
                                        <small class="text-muted">Total in Database</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="h4 text-info"><?php echo $db_stats['upcoming'] ?? 0; ?></div>
                                        <small class="text-muted">Upcoming Games</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="h4 text-danger"><?php echo $db_stats['by_league']['NCL Volt Cup'] ?? 0; ?></div>
                                        <small class="text-muted">NCL Games</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <div class="h4 text-success"><?php echo $db_stats['by_league']['WEL'] ?? 0; ?></div>
                                        <small class="text-muted">WEL Games</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>📅 Weekly Fixture Downloads</h5>
                        <small class="text-muted">Download individual weeks for easy sharing with teams and fans</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-8">
                                <label for="week_start" class="form-label">Select Week</label>
                                <select class="form-select" name="week_start" id="week_start" required>
                                    <option value="">Choose a week...</option>
                                    <?php 
                                    if (!isset($manager)) {
                                        $manager = new FixtureManager($conn);
                                    }
                                    $available_weeks = $manager->getAvailableWeeks();
                                    foreach ($available_weeks as $week): 
                                    ?>
                                        <option value="<?php echo $week['start_date']; ?>" <?php echo $week['has_fixtures'] ? '' : 'disabled'; ?>>
                                            <?php echo $week['display_name']; ?>
                                            <?php echo $week['has_fixtures'] ? '' : ' (No fixtures)'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" name="action" value="download_weekly" class="btn btn-info">
                                        📄 Download Weekly PDF
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> <strong>Perfect for sharing!</strong> Weekly PDFs are designed for easy distribution via WhatsApp, email, or printing for team notice boards.
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Comprehensive Season Fixture Manager</h5>
                        <small class="text-muted">Shows existing games + generates new fixtures to complete the season through November 30th</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="2025-07-18">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="2025-11-30">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="action" value="generate" class="btn btn-primary">Generate Preview</button>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="btn-group" role="group">
                                    <button type="submit" name="action" value="save" class="btn btn-success">💾 Save to Database</button>
                                    <button type="submit" name="action" value="download" class="btn btn-secondary">📄 Download PDF</button>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-info-circle"></i> Generated fixtures are only previewed. Use "Save to Database" to store them permanently.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($fixtures)): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number text-primary"><?php echo count($fixtures); ?></div>
                            <div>Total Games</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo count(array_filter($fixtures, function($f) { return isset($f['is_new']) && $f['is_new']; })); ?></div>
                            <div>New Games</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-info"><?php echo count(array_filter($fixtures, function($f) { return !isset($f['is_new']) || !$f['is_new']; })); ?></div>
                            <div>Existing Games</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-danger"><?php echo count(array_filter($fixtures, function($f) { return $f['league_id'] == 1; })); ?></div>
                            <div>NCL Games</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-success"><?php echo count(array_filter($fixtures, function($f) { return $f['league_id'] == 2; })); ?></div>
                            <div>WEL Games</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number text-warning"><?php echo count(array_filter($fixtures, function($f) { return isset($f['status']) && $f['status'] === 'played'; })); ?></div>
                            <div>Played Games</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <h3>Generated Fixtures</h3>
                            
                            <?php
                            // Group fixtures by week
                            $weeks = [];
                            foreach ($fixtures as $fixture) {
                                $week_start = date('Y-m-d', strtotime('monday this week', strtotime($fixture['match_date'])));
                                $weeks[$week_start][] = $fixture;
                            }
                            
                            foreach ($weeks as $week_start => $week_fixtures):
                                $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));
                            ?>
                                <div class="week-section">
                                    <h4 class="text-primary">Week of <?php echo date('M j', strtotime($week_start)); ?> - <?php echo date('M j, Y', strtotime($week_end)); ?></h4>
                                    
                                    <?php
                                    // Group by day
                                    $days = [];
                                    foreach ($week_fixtures as $fixture) {
                                        $days[$fixture['match_date']][] = $fixture;
                                    }
                                    
                                    foreach ($days as $date => $day_fixtures):
                                        $day_name = date('l, M j', strtotime($date));
                                    ?>
                                        <div class="day-header"><?php echo $day_name; ?></div>
                                        <div class="row">
                                            <?php foreach ($day_fixtures as $fixture): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="card fixture-card league-<?php echo $fixture['league_id'] == 1 ? 'ncl' : 'wel'; ?> <?php echo (isset($fixture['is_new']) && $fixture['is_new']) ? 'border-success' : 'border-secondary'; ?>">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <span class="badge bg-<?php echo $fixture['league_id'] == 1 ? 'danger' : 'success'; ?>"><?php echo $fixture['league_name']; ?></span>
                                                                <span class="text-muted"><?php echo date('g:i A', strtotime($fixture['match_time'])); ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <?php if (isset($fixture['is_new']) && $fixture['is_new']): ?>
                                                                    <span class="badge bg-success">NEW</span>
                                                                <?php elseif (isset($fixture['status']) && $fixture['status'] === 'played'): ?>
                                                                    <span class="badge bg-warning">PLAYED</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-secondary">SCHEDULED</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <h6 class="card-title"><?php echo htmlspecialchars($fixture['home_team']['name']); ?></h6>
                                                            <p class="card-text text-center"><strong>VS</strong></p>
                                                            <h6 class="card-title"><?php echo htmlspecialchars($fixture['away_team']['name']); ?></h6>
                                                            <small class="text-muted"><?php echo htmlspecialchars($fixture['venue']); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($GLOBALS['incomplete_weeks']) && !empty($GLOBALS['incomplete_weeks'])): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Some weeks could not be fully scheduled!</strong><br>
                        The following weeks have fewer than 10 games (5 per day with required league split):<br>
                        <ul class="mb-0">
                            <?php foreach ($GLOBALS['incomplete_weeks'] as $week): ?>
                                <li>Week <?php echo $week; ?> (starting <?php echo date('M j, Y', strtotime($week . ' Monday')); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                        <small>Not enough available teams or fixtures to fill all slots. Please review or adjust team/fixture data if needed.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
