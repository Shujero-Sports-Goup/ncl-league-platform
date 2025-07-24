<?php
session_start();
require_once('../db_connect.php');
require_once('../includes/auth.php');
requireRole('admin');

// Multi-league setup for NCL and WEL
$nclLeagueId = 1; // NCL Volt Cup
$welLeagueId = 2; // Women Empowerment League

// Get both leagues
$leaguesRes = $conn->query("SELECT * FROM leagues WHERE league_id IN ($nclLeagueId, $welLeagueId) ORDER BY league_id");
$leagues = [];
while ($row = $leaguesRes->fetch_assoc()) {
    $leagues[$row['league_id']] = $row;
}

// Get teams from both leagues
$nclTeamsRes = $conn->query("SELECT team_id, name, league_id FROM teams WHERE league_id = $nclLeagueId ORDER BY name");
$welTeamsRes = $conn->query("SELECT team_id, name, league_id FROM teams WHERE league_id = $welLeagueId ORDER BY name");

$nclTeams = [];
$welTeams = [];
while ($row = $nclTeamsRes->fetch_assoc()) {
    $nclTeams[] = $row;
}
while ($row = $welTeamsRes->fetch_assoc()) {
    $welTeams[] = $row;
}

// Get existing fixtures from both leagues to avoid duplicates and track team schedules
$existingFixtures = [];
$teamLastGameDate = []; // Track when each team last played
$existingDailySchedule = []; // Track existing daily team schedules

$existingRes = $conn->query("
    SELECT CONCAT(home_team, '-', away_team, '-', match_date) as fixture_key,
           home_team, away_team, match_date, league_id
    FROM fixtures 
    WHERE league_id IN ($nclLeagueId, $welLeagueId)
    ORDER BY match_date DESC
");

while ($row = $existingRes->fetch_assoc()) {
    $existingFixtures[] = $row['fixture_key'];
    
    // Track last game date for each team to prevent back-to-back games
    $gameDate = $row['match_date'];
    $homeTeam = $row['home_team'];
    $awayTeam = $row['away_team'];
    
    if (!isset($teamLastGameDate[$homeTeam]) || $gameDate > $teamLastGameDate[$homeTeam]) {
        $teamLastGameDate[$homeTeam] = $gameDate;
    }
    if (!isset($teamLastGameDate[$awayTeam]) || $gameDate > $teamLastGameDate[$awayTeam]) {
        $teamLastGameDate[$awayTeam] = $gameDate;
    }
    
    // Track existing daily schedules to prevent same-day conflicts
    if (!isset($existingDailySchedule[$gameDate])) {
        $existingDailySchedule[$gameDate] = [];
    }
    $existingDailySchedule[$gameDate][] = $homeTeam;
    $existingDailySchedule[$gameDate][] = $awayTeam;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST['action'] ?? '' === 'generate') {
    $startDate = $_POST['start_date'] ?? '2025-07-18';
    $endDate = $_POST['end_date'] ?? '2025-11-30';
    $roundRobin = isset($_POST['round_robin']);
    
    $result = generateMultiLeagueFixtures($conn, $nclLeagueId, $welLeagueId, $nclTeams, $welTeams, $startDate, $endDate, $roundRobin, $existingFixtures, $teamLastGameDate, $existingDailySchedule);
    $message = $result['message'];
    $messageType = $result['type'];
}

if ($_POST['action'] ?? '' === 'clear_all') {
    $deleteResult = $conn->query("DELETE FROM fixtures WHERE league_id IN ($nclLeagueId, $welLeagueId)");
    $message = $deleteResult ? "All fixtures cleared for both NCL and WEL leagues!" : "Error clearing fixtures: " . $conn->error;
    $messageType = $deleteResult ? 'success' : 'danger';
    
    // Refresh existing fixtures
    $existingFixtures = [];
    $teamLastGameDate = [];
    $existingDailySchedule = [];
}

// Get all fixtures for display from both leagues
$allFixtures = getMultiLeagueFixturesForDisplay($conn, $nclLeagueId, $welLeagueId);
$fixturesByWeek = groupMultiLeagueFixturesByWeek($allFixtures);

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-gradient-primary text-white">
                    <h2 class="mb-0">
                        <i class="fas fa-calendar-plus"></i> 
                        Multi-League Fixture Generator - NCL & WEL
                    </h2>
                    <p class="mb-0 mt-2">Generate synchronized season fixtures for both leagues with shared court scheduling</p>
                    <small class="text-light">NCL plays Fri/Sat/Sun • WEL plays weekends only (Sat/Sun) • No team plays twice same day</small>
                </div>
                
                <div class="card-body">
                    <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Generation Controls -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="generate">
                                
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Season Start Date</label>
                                    <input type="date" name="start_date" class="form-control" 
                                           value="2025-07-18" min="2025-07-18" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Season End Date</label>
                                    <input type="date" name="end_date" class="form-control" 
                                           value="2025-11-30" max="2025-11-30" required>
                                </div>
                                
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-check">
                                        <input type="checkbox" name="round_robin" id="round_robin" 
                                               class="form-check-input" checked>
                                        <label class="form-check-label" for="round_robin">
                                            Round Robin (Home & Away)
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success btn-lg me-2">
                                        <i class="fas fa-magic"></i> Generate Fixtures
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="downloadPDF()">
                                        <i class="fas fa-file-pdf"></i> Download PDF
                                    </button>
                                    <button type="button" class="btn btn-warning" onclick="showPreview()">
                                        <i class="fas fa-eye"></i> Preview Season
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card bg-light h-100">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-info-circle text-primary"></i> Multi-League Season Info
                                    </h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><strong>NCL Teams:</strong> <?= count($nclTeams) ?></li>
                                        <li><strong>WEL Teams:</strong> <?= count($welTeams) ?></li>
                                        <li><strong>Total Fixtures:</strong> <?= count($existingFixtures) ?></li>
                                        <li><strong>Shared Court:</strong> KFC Court</li>
                                        <li><strong>NCL Games:</strong> Fri, Sat, Sun</li>
                                        <li><strong>WEL Games:</strong> Sat, Sun only</li>
                                        <li><strong>Duration:</strong> ~19 weeks</li>
                                    </ul>
                                    
                                    <?php if (count($existingFixtures) > 0): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="action" value="clear_all">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Clear all existing fixtures?')">
                                            <i class="fas fa-trash"></i> Clear All
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Season Schedule Display -->
                    <?php if (!empty($fixturesByWeek)): ?>
                    <div class="season-schedule">
                        <h4 class="mb-4">
                            <i class="fas fa-calendar-alt text-primary"></i> 
                            Season Schedule Layout
                            <span class="badge bg-primary ms-2"><?= count($fixturesByWeek) ?> Weeks</span>
                        </h4>
                        
                        <div class="row">
                            <?php foreach ($fixturesByWeek as $weekData): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card week-card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-gradient-info text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-calendar-week"></i>
                                            Week <?= $weekData['week_number'] ?>
                                            <small class="float-end">
                                                <?= date('M j', strtotime($weekData['week_start'])) ?> - 
                                                <?= date('M j', strtotime($weekData['week_end'])) ?>
                                            </small>
                                        </h6>
                                    </div>
                                    <div class="card-body p-3">
                                        <?php foreach (['Friday', 'Saturday', 'Sunday'] as $day): ?>
                                        <div class="day-section mb-3">
                                            <h6 class="day-header text-<?= getdayColor($day) ?> mb-2">
                                                <i class="fas fa-<?= getDayIcon($day) ?>"></i> <?= $day ?>
                                                <?php if ($day === 'Friday'): ?>
                                                    <small class="badge bg-primary ms-1">NCL Only</small>
                                                <?php endif; ?>
                                            </h6>
                                            
                                            <?php if (isset($weekData['fixtures'][$day]) && count($weekData['fixtures'][$day]) > 0): ?>
                                                <?php foreach ($weekData['fixtures'][$day] as $fixture): ?>
                                                <div class="fixture-item mb-2 p-2 rounded bg-light border-start border-3 border-<?= getGameStatusColor($fixture) ?>">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div class="fixture-teams">
                                                            <span class="badge bg-<?= $fixture['league_id'] == 1 ? 'primary' : 'success' ?> me-1 small">
                                                                <?= htmlspecialchars($fixture['league_abbr']) ?>
                                                            </span>
                                                            <strong><?= htmlspecialchars($fixture['home_team']) ?></strong>
                                                            <small class="text-muted">vs</small>
                                                            <strong><?= htmlspecialchars($fixture['away_team']) ?></strong>
                                                        </div>
                                                        <div class="fixture-time">
                                                            <small class="text-muted">
                                                                <?= date('H:i', strtotime($fixture['match_time'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="fixture-venue">
                                                        <small class="text-muted">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                            <?= htmlspecialchars($fixture['venue']) ?>
                                                            <?php if ($fixture['venue'] === 'KFC Court'): ?>
                                                                <i class="fas fa-star text-warning ms-1" title="Shared Court"></i>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="unscheduled-day p-2 rounded <?= $day === 'Friday' ? 'bg-info bg-opacity-25 border border-info' : 'bg-warning bg-opacity-25 border border-warning' ?>">
                                                    <small class="text-muted">
                                                        <?php if ($day === 'Friday'): ?>
                                                            <i class="fas fa-info-circle text-info"></i>
                                                            WEL plays weekends only
                                                        <?php else: ?>
                                                            <i class="fas fa-exclamation-triangle text-warning"></i>
                                                            No games scheduled
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times text-muted" style="font-size: 4rem;"></i>
                        <h4 class="text-muted mt-3">No Fixtures Generated Yet</h4>
                        <p class="text-muted">Use the generator above to create your season schedule</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PDF Export Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfModalLabel">
                    <i class="fas fa-file-pdf text-danger"></i> Export Season Fixtures
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="pdfExportForm">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Export Format</label>
                            <select name="format" class="form-select">
                                <option value="weekly">Weekly Schedule (Recommended)</option>
                                <option value="chronological">Chronological List</option>
                                <option value="team_schedule">Team-wise Schedule</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Include</label>
                            <div class="form-check">
                                <input type="checkbox" name="include_venues" id="include_venues" class="form-check-input" checked>
                                <label class="form-check-label" for="include_venues">Venue Information</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="include_unscheduled" id="include_unscheduled" class="form-check-input" checked>
                                <label class="form-check-label" for="include_unscheduled">Highlight Unscheduled Days</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="exportPDF()">
                    <i class="fas fa-download"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function downloadPDF() {
    const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
    modal.show();
}

function exportPDF() {
    const form = document.getElementById('pdfExportForm');
    const formData = new FormData(form);
    formData.append('ncl_league_id', <?= $nclLeagueId ?>);
    formData.append('wel_league_id', <?= $welLeagueId ?>);
    formData.append('multi_league', 'true');
    
    // Create download link
    const params = new URLSearchParams(formData);
    window.open(`export_fixtures_pdf.php?${params.toString()}`, '_blank');
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('pdfModal')).hide();
}

function showPreview() {
    // Scroll to schedule section
    document.querySelector('.season-schedule')?.scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}

// Auto-refresh page after fixture generation
<?php if ($messageType === 'success'): ?>
setTimeout(() => {
    window.location.href = window.location.pathname;
}, 3000);
<?php endif; ?>
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.bg-gradient-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.week-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.week-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
}

.fixture-item {
    transition: background-color 0.3s ease;
}

.fixture-item:hover {
    background-color: #e3f2fd !important;
}

.day-header {
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unscheduled-day {
    border-style: dashed !important;
}

.border-success { border-color: #28a745 !important; }
.border-warning { border-color: #ffc107 !important; }
.border-info { border-color: #17a2b8 !important; }

.text-friday { color: #28a745; }
.text-saturday { color: #ffc107; }
.text-sunday { color: #17a2b8; }
</style>

<?php
// Multi-league helper functions
function generateMultiLeagueFixtures($conn, $nclLeagueId, $welLeagueId, $nclTeams, $welTeams, $startDate, $endDate, $roundRobin, $existingFixtures, $teamLastGameDate, $existingDailySchedule = []) {
    $nclTeamCount = count($nclTeams);
    $welTeamCount = count($welTeams);
    
    if ($nclTeamCount < 2 && $welTeamCount < 2) {
        return ['message' => 'Need at least 2 teams in each league to generate fixtures', 'type' => 'danger'];
    }
    
    // Shared court venues with KFC Court as primary
    $venues = ['KFC Court', 'Main Court', 'Court A', 'Court B', 'Sports Complex'];
    
    // Optimized time slots - WEL only on weekends, NCL on all three days
    $timeSlots = [
        'Friday' => ['15:00:00', '17:00:00', '19:00:00', '21:00:00'], // NCL only
        'Saturday' => ['09:00:00', '11:00:00', '13:00:00', '15:00:00', '17:00:00', '19:00:00'], // Both leagues
        'Sunday' => ['09:00:00', '11:00:00', '13:00:00', '15:00:00', '17:00:00'] // Both leagues
    ];
    
    // Define allowed days for each league
    $allowedDays = [
        $nclLeagueId => ['Friday', 'Saturday', 'Sunday'], // NCL can play all three days
        $welLeagueId => ['Saturday', 'Sunday'] // WEL only on weekends
    ];
    
    $allFixtures = [];
    $fixturesAdded = 0;
    
    // Generate fixtures for NCL
    if ($nclTeamCount >= 2) {
        $nclFixtures = generateLeagueFixtures($nclTeams, $nclLeagueId, $roundRobin);
        $allFixtures = array_merge($allFixtures, $nclFixtures);
    }
    
    // Generate fixtures for WEL
    if ($welTeamCount >= 2) {
        $welFixtures = generateLeagueFixtures($welTeams, $welLeagueId, $roundRobin);
        $allFixtures = array_merge($allFixtures, $welFixtures);
    }
    
    // Shuffle fixtures to distribute evenly
    shuffle($allFixtures);
    
    // Schedule fixtures across the season with intelligent spacing
    $currentDate = new DateTime($startDate);
    $endDateTime = new DateTime($endDate);
    $fixtureIndex = 0;
    $scheduledSlots = []; // Track occupied time slots
    $teamSchedule = []; // Track team game distribution
    $teamDailySchedule = $existingDailySchedule; // Initialize with existing schedules to prevent same-day conflicts
    
    while ($currentDate <= $endDateTime && $fixtureIndex < count($allFixtures)) {
        $dayOfWeek = $currentDate->format('l');
        
        if (in_array($dayOfWeek, ['Friday', 'Saturday', 'Sunday'])) {
            $availableSlots = $timeSlots[$dayOfWeek];
            $dateStr = $currentDate->format('Y-m-d');
            
            foreach ($availableSlots as $timeSlot) {
                if ($fixtureIndex >= count($allFixtures)) break;
                
                $fixture = $allFixtures[$fixtureIndex];
                $homeTeam = $fixture['home_team'];
                $awayTeam = $fixture['away_team'];
                $leagueId = $fixture['league_id'];
                
                // Check if this league is allowed to play on this day
                if (!in_array($dayOfWeek, $allowedDays[$leagueId])) {
                    // Skip to next fixture if this league can't play on this day
                    $fixtureIndex++;
                    continue;
                }
                
                // Check if teams can play (no back-to-back games)
                if (!canTeamsPlay($homeTeam, $awayTeam, $dateStr, $teamLastGameDate)) {
                    $fixtureIndex++;
                    continue;
                }
                
                // Check if either team is already scheduled to play on this day
                if (isTeamScheduledOnDay($homeTeam, $awayTeam, $dateStr, $teamDailySchedule)) {
                    $fixtureIndex++;
                    continue;
                }
                
                $fixtureKey = $homeTeam . '-' . $awayTeam . '-' . $dateStr;
                
                // Skip if fixture already exists
                if (in_array($fixtureKey, $existingFixtures)) {
                    $fixtureIndex++;
                    continue;
                }
                
                // Prioritize KFC Court for shared scheduling
                $venue = ($fixtureIndex % 3 === 0) ? 'KFC Court' : $venues[array_rand($venues)];
                
                $sql = "INSERT INTO fixtures (league_id, home_team, away_team, match_date, match_time, venue, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'upcoming')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiisss", 
                    $leagueId, 
                    $homeTeam, 
                    $awayTeam, 
                    $dateStr, 
                    $timeSlot, 
                    $venue
                );
                
                if ($stmt->execute()) {
                    $fixturesAdded++;
                    
                    // Update team last game dates
                    $teamLastGameDate[$homeTeam] = $dateStr;
                    $teamLastGameDate[$awayTeam] = $dateStr;
                    
                    // Track teams scheduled for this day
                    if (!isset($teamDailySchedule[$dateStr])) {
                        $teamDailySchedule[$dateStr] = [];
                    }
                    $teamDailySchedule[$dateStr][] = $homeTeam;
                    $teamDailySchedule[$dateStr][] = $awayTeam;
                    
                    // Track scheduled slots
                    $scheduledSlots[$dateStr][$timeSlot] = true;
                } else {
                    error_log("Error inserting fixture: " . $conn->error);
                }
                
                $fixtureIndex++;
            }
        }
        
        $currentDate->add(new DateInterval('P1D'));
    }
    
    $message = "Generated $fixturesAdded new fixtures for both NCL and WEL leagues!";
    if ($fixtureIndex < count($allFixtures)) {
        $remaining = count($allFixtures) - $fixtureIndex;
        $message .= " ($remaining fixtures couldn't be scheduled within the season dates)";
    }
    
    return ['message' => $message, 'type' => 'success'];
}

function generateLeagueFixtures($teams, $leagueId, $roundRobin) {
    $fixtures = [];
    $teamCount = count($teams);
    
    for ($round = 1; $round <= ($roundRobin ? 2 : 1); $round++) {
        for ($i = 0; $i < $teamCount; $i++) {
            for ($j = $i + 1; $j < $teamCount; $j++) {
                if ($round == 1) {
                    $homeTeam = $teams[$i]['team_id'];
                    $awayTeam = $teams[$j]['team_id'];
                } else {
                    $homeTeam = $teams[$j]['team_id'];
                    $awayTeam = $teams[$i]['team_id'];
                }
                
                $fixtures[] = [
                    'home_team' => $homeTeam,
                    'away_team' => $awayTeam,
                    'league_id' => $leagueId,
                    'home_name' => $round == 1 ? $teams[$i]['name'] : $teams[$j]['name'],
                    'away_name' => $round == 1 ? $teams[$j]['name'] : $teams[$i]['name']
                ];
            }
        }
    }
    
    return $fixtures;
}

function canTeamsPlay($homeTeam, $awayTeam, $gameDate, $teamLastGameDate) {
    $minDaysBetweenGames = 3; // Minimum days between games for same team
    
    foreach ([$homeTeam, $awayTeam] as $teamId) {
        if (isset($teamLastGameDate[$teamId])) {
            $lastGameDate = new DateTime($teamLastGameDate[$teamId]);
            $currentGameDate = new DateTime($gameDate);
            $daysDiff = $currentGameDate->diff($lastGameDate)->days;
            
            if ($daysDiff < $minDaysBetweenGames) {
                return false;
            }
        }
    }
    
    return true;
}

function isTeamScheduledOnDay($homeTeam, $awayTeam, $gameDate, $teamDailySchedule) {
    // Check if either team is already scheduled to play on this day
    if (isset($teamDailySchedule[$gameDate])) {
        $scheduledTeams = $teamDailySchedule[$gameDate];
        if (in_array($homeTeam, $scheduledTeams) || in_array($awayTeam, $scheduledTeams)) {
            return true; // Team already has a game on this day
        }
    }
    return false;
}

function getMultiLeagueFixturesForDisplay($conn, $nclLeagueId, $welLeagueId) {
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
        WHERE f.league_id IN (?, ?)
        ORDER BY f.match_date, f.match_time
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $nclLeagueId, $welLeagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $fixtures = [];
    while ($row = $result->fetch_assoc()) {
        $fixtures[] = $row;
    }
    
    return $fixtures;
}

function groupMultiLeagueFixturesByWeek($fixtures) {
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

function getGameStatusColor($fixture) {
    if ($fixture['status'] === 'played') {
        return 'success';
    } elseif (strtotime($fixture['match_date']) < time()) {
        return 'warning'; // Past due
    } else {
        return 'info'; // Upcoming
    }
}

function getDayColor($day) {
    return strtolower($day);
}

function getDayIcon($day) {
    $icons = [
        'Friday' => 'moon',
        'Saturday' => 'sun',
        'Sunday' => 'star'
    ];
    return $icons[$day] ?? 'calendar';
}

include('../includes/footer.php');
?>
