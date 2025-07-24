<?php
require_once('../db_connect.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check if GD extension is loaded
if (!extension_loaded('gd')) {
    echo json_encode([
        'success' => false, 
        'error' => 'GD extension not loaded. Please enable GD extension in php.ini',
        'solution' => [
            'For XAMPP: Open XAMPP Control Panel → Apache Config → PHP (php.ini) → Find ";extension=gd" and remove semicolon → Restart Apache',
            'For other systems: Install php-gd package and restart web server',
            'Verify installation by checking phpinfo() for GD section'
        ]
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$fixtureId = $_GET['fixture_id'] ?? 0;
$type = $_GET['type'] ?? 'upcoming'; // 'upcoming' or 'result'

if (!$fixtureId) {
    echo json_encode(['success' => false, 'error' => 'Fixture ID required']);
    exit;
}

// Get fixture details
$sql = "
    SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status,
           f.league_id, l.name as league_name, l.logo_url,
           t1.name AS home_team, t1.logo_url as home_logo,
           t2.name AS away_team, t2.logo_url as away_logo,
           r.score_home, r.score_away
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    JOIN leagues l ON f.league_id = l.league_id
    LEFT JOIN match_results r ON f.fixture_id = r.fixture_id
    WHERE f.fixture_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $fixtureId);
$stmt->execute();
$result = $stmt->get_result();
$fixture = $result->fetch_assoc();

if (!$fixture) {
    echo json_encode(['success' => false, 'error' => 'Fixture not found']);
    exit;
}

// Determine poster type
$isResult = ($fixture['status'] === 'played' && $fixture['score_home'] !== null);
$posterType = $isResult ? 'result' : 'upcoming';

// Create poster
try {
    $posterPath = generatePoster($fixture, $posterType);
    
    if ($action === 'download') {
        // Check if file exists before trying to serve it
        if (!file_exists('../' . $posterPath)) {
            echo json_encode(['success' => false, 'error' => 'Generated poster file not found']);
            exit;
        }
        
        // Return file for download
        header('Content-Type: image/jpeg');
        header('Content-Disposition: attachment; filename="' . basename($posterPath) . '"');
        header('Content-Length: ' . filesize('../' . $posterPath));
        readfile('../' . $posterPath);
        exit;
    } else {
        // Return JSON with file path
        echo json_encode([
            'success' => true,
            'poster_url' => '/ncl-league-platform/' . $posterPath,
            'filename' => basename($posterPath),
            'type' => $posterType
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generatePoster($fixture, $type) {
    // Poster dimensions
    $width = 1080;
    $height = 1080;
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    if (!$image) {
        throw new Exception('Failed to create image canvas');
    }
    
    // Colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 30, 30, 30);
    $gray = imagecolorallocate($image, 128, 128, 128);
    $accent = imagecolorallocate($image, 65, 84, 241); // Primary blue
    $gold = imagecolorallocate($image, 255, 193, 7);
    $green = imagecolorallocate($image, 40, 167, 69);
    $red = imagecolorallocate($image, 220, 53, 69);
    
    // Gradient background
    createGradientBackground($image, $width, $height, $accent, $white);
    
    // Load and add league logo
    $leagueLogoY = 50;
    addLogo($image, $fixture['logo_url'], $width/2, $leagueLogoY, 120, true, $fixture['league_name']);
    
    // League name
    $leagueName = strtoupper($fixture['league_name']);
    addText($image, $leagueName, $width/2, $leagueLogoY + 100, 28, $white, true, true);
    
    if ($type === 'result') {
        generateResultPoster($image, $fixture, $width, $height, $white, $black, $gray, $accent, $gold, $green, $red);
    } else {
        generateUpcomingPoster($image, $fixture, $width, $height, $white, $black, $gray, $accent, $gold, $green);
    }
    
    // Save poster
    $filename = $type . '_' . $fixture['fixture_id'] . '_' . date('Y-m-d_H-i-s') . '.jpg';
    $posterDir = '../assets/posters/';
    
    // Create directory if it doesn't exist
    if (!file_exists($posterDir)) {
        if (!mkdir($posterDir, 0755, true)) {
            imagedestroy($image);
            throw new Exception('Failed to create poster directory');
        }
    }
    
    $posterPath = $posterDir . $filename;
    $success = imagejpeg($image, $posterPath, 95);
    imagedestroy($image);
    
    if (!$success) {
        throw new Exception('Failed to save poster image');
    }
    
    return 'assets/posters/' . $filename;
}

function generateResultPoster($image, $fixture, $width, $height, $white, $black, $gray, $accent, $gold, $green, $red) {
    // Add modern decorative frame with gradient
    $frameGradient1 = imagecolorallocate($image, 255, 215, 0); // Gold
    $frameGradient2 = imagecolorallocate($image, 255, 165, 0); // Orange gold
    
    // Multiple frame layers for depth
    for ($i = 0; $i < 8; $i++) {
        $frameColor = $i % 2 == 0 ? $frameGradient1 : $frameGradient2;
        imagerectangle($image, 20 + $i, 20 + $i, $width - 20 - $i, $height - 20 - $i, $frameColor);
    }
    
    // "FINAL RESULT" header with modern styling
    $headerBg = imagecolorallocatealpha($image, 0, 0, 0, 40);
    $headerAccent = imagecolorallocate($image, 255, 193, 7);
    
    // Rounded rectangle effect for header
    imagefilledrectangle($image, 120, 220, $width-120, 290, $headerBg);
    imagefilledrectangle($image, 125, 225, $width-125, 285, $headerAccent);
    imagefilledrectangle($image, 130, 230, $width-130, 280, $headerBg);
    
    addText($image, "🏆 FINAL RESULT 🏆", $width/2, 240, 32, $gold, true, true);
    
    // Team section with modern glass effect background
    $teamBg = imagecolorallocatealpha($image, 255, 255, 255, 15);
    $teamBorder = imagecolorallocatealpha($image, 255, 255, 255, 40);
    
    imagefilledrectangle($image, 50, 320, $width-50, 600, $teamBg);
    imagerectangle($image, 50, 320, $width-50, 600, $teamBorder);
    imagerectangle($image, 52, 322, $width-52, 598, $teamBorder);
    
    // Team logos and names
    $teamY = 380;
    $logoSize = 180;
    
    // Home team (left) with enhanced styling
    addLogo($image, $fixture['home_logo'], $width/4, $teamY, $logoSize, false, $fixture['home_team']);
    addText($image, $fixture['home_team'], $width/4, $teamY + $logoSize/2 + 50, 24, $white, true, true);
    
    // Away team (right) with enhanced styling
    addLogo($image, $fixture['away_logo'], 3*$width/4, $teamY, $logoSize, false, $fixture['away_team']);
    addText($image, $fixture['away_team'], 3*$width/4, $teamY + $logoSize/2 + 50, 24, $white, true, true);
    
    // Enhanced VS separator with multiple effects
    $vsCircleBg = imagecolorallocatealpha($image, 255, 255, 255, 20);
    $vsCircleBorder = imagecolorallocate($image, 255, 193, 7);
    
    // Multiple circle layers for depth
    imagefilledellipse($image, $width/2, $teamY, 130, 130, $vsCircleBg);
    imageellipse($image, $width/2, $teamY, 135, 135, $vsCircleBorder);
    imageellipse($image, $width/2, $teamY, 125, 125, $vsCircleBorder);
    
    addText($image, "VS", $width/2, $teamY - 10, 48, $white, true, true);
    
    // Score section with enhanced styling
    $scoreY = $teamY + 200;
    $scoreBg = imagecolorallocatealpha($image, 0, 0, 0, 50);
    $scoreBorder = imagecolorallocate($image, 255, 193, 7);
    
    imagefilledrectangle($image, 150, $scoreY - 60, $width-150, $scoreY + 110, $scoreBg);
    imagerectangle($image, 150, $scoreY - 60, $width-150, $scoreY + 110, $scoreBorder);
    imagerectangle($image, 152, $scoreY - 58, $width-152, $scoreY + 108, $scoreBorder);
    
    $homeScore = $fixture['score_home'];
    $awayScore = $fixture['score_away'];
    
    // Determine winner colors and add winner indicators
    $homeColor = $white;
    $awayColor = $white;
    $homeWinner = false;
    $awayWinner = false;
    
    if ($homeScore > $awayScore) {
        $homeColor = $green;
        $homeWinner = true;
    } elseif ($awayScore > $homeScore) {
        $awayColor = $green;
        $awayWinner = true;
    }
    
    // Check for forfeits
    if ($homeScore == 0 || $awayScore == 0) {
        if ($homeScore == 0) $homeColor = $red;
        if ($awayScore == 0) $awayColor = $red;
    }
    
    // Add winner crowns with glow effect
    if ($homeWinner) {
        $crownGlow = imagecolorallocatealpha($image, 255, 215, 0, 50);
        addText($image, "👑", $width/4 - 42, $scoreY - 22, 32, $crownGlow, true);
        addText($image, "👑", $width/4 - 40, $scoreY - 20, 32, $gold, true);
    }
    if ($awayWinner) {
        $crownGlow = imagecolorallocatealpha($image, 255, 215, 0, 50);
        addText($image, "👑", 3*$width/4 - 42, $scoreY - 22, 32, $crownGlow, true);
        addText($image, "👑", 3*$width/4 - 40, $scoreY - 20, 32, $gold, true);
    }
    
    addText($image, $homeScore, $width/4, $scoreY, 84, $homeColor, true, true);
    addText($image, $awayScore, 3*$width/4, $scoreY, 84, $awayColor, true, true);
    addText($image, "-", $width/2, $scoreY, 84, $white, true, true);
    
    // Match details with enhanced styling
    $detailsY = $scoreY + 150;
    $matchDate = date("d M Y", strtotime($fixture['match_date']));
    $matchTime = date("H:i", strtotime($fixture['match_time']));
    
    addText($image, "📅 " . $matchDate . "  🕐 " . $matchTime, $width/2, $detailsY, 20, $gray, true);
    addText($image, "📍 " . $fixture['venue'], $width/2, $detailsY + 35, 18, $gray, true);
    
    // Winner announcement with enhanced styling
    if ($homeScore != $awayScore && $homeScore != 0 && $awayScore != 0) {
        $winner = $homeScore > $awayScore ? $fixture['home_team'] : $fixture['away_team'];
        $winnerBg = imagecolorallocatealpha($image, 255, 215, 0, 25);
        $winnerBorder = imagecolorallocate($image, 255, 215, 0);
        
        imagefilledrectangle($image, 150, $detailsY + 70, $width-150, $detailsY + 140, $winnerBg);
        imagerectangle($image, 150, $detailsY + 70, $width-150, $detailsY + 140, $winnerBorder);
        imagerectangle($image, 152, $detailsY + 72, $width-152, $detailsY + 138, $winnerBorder);
        
        addText($image, "🏆 " . strtoupper($winner) . " WINS! 🏆", $width/2, $detailsY + 100, 28, $gold, true, true);
    } elseif ($homeScore == 0 || $awayScore == 0) {
        $forfeitBg = imagecolorallocatealpha($image, 220, 53, 69, 25);
        $forfeitBorder = imagecolorallocate($image, 220, 53, 69);
        
        imagefilledrectangle($image, 150, $detailsY + 70, $width-150, $detailsY + 140, $forfeitBg);
        imagerectangle($image, 150, $detailsY + 70, $width-150, $detailsY + 140, $forfeitBorder);
        
        addText($image, "⚠️ FORFEIT RESULT ⚠️", $width/2, $detailsY + 100, 28, $red, true, true);
    } else {
        $drawBg = imagecolorallocatealpha($image, 128, 128, 128, 25);
        addText($image, "🤝 DRAW", $width/2, $detailsY + 100, 28, $white, true, true);
    }
    
    // Add stylish timestamp
    $timestampBg = imagecolorallocatealpha($image, 0, 0, 0, 30);
    imagefilledrectangle($image, $width/2 - 120, $height - 60, $width/2 + 120, $height - 20, $timestampBg);
    addText($image, "Generated: " . date("Y-m-d H:i"), $width/2, $height - 40, 12, $gray, true);
}

function generateUpcomingPoster($image, $fixture, $width, $height, $white, $black, $gray, $accent, $gold, $green) {
    // Add red color for countdown
    $red = imagecolorallocate($image, 220, 53, 69);
    
    // Add modern decorative frame with gradient
    $frameGradient1 = imagecolorallocate($image, 65, 84, 241); // Primary blue
    $frameGradient2 = imagecolorallocate($image, 76, 175, 80); // Green accent
    
    // Multiple frame layers for depth
    for ($i = 0; $i < 8; $i++) {
        $frameColor = $i % 2 == 0 ? $frameGradient1 : $frameGradient2;
        imagerectangle($image, 20 + $i, 20 + $i, $width - 20 - $i, $height - 20 - $i, $frameColor);
    }
    
    // "UPCOMING MATCH" header with modern styling
    $headerBg = imagecolorallocatealpha($image, 65, 84, 241, 40);
    $headerAccent = imagecolorallocate($image, 255, 193, 7);
    
    // Animated-style header
    imagefilledrectangle($image, 120, 220, $width-120, 290, $headerBg);
    imagefilledrectangle($image, 125, 225, $width-125, 285, $headerAccent);
    imagefilledrectangle($image, 130, 230, $width-130, 280, $headerBg);
    
    addText($image, "⚡ UPCOMING MATCH ⚡", $width/2, 240, 32, $gold, true, true);
    
    // Team section with modern glass effect background
    $teamBg = imagecolorallocatealpha($image, 255, 255, 255, 15);
    $teamBorder = imagecolorallocatealpha($image, 255, 255, 255, 40);
    
    imagefilledrectangle($image, 50, 320, $width-50, 600, $teamBg);
    imagerectangle($image, 50, 320, $width-50, 600, $teamBorder);
    imagerectangle($image, 52, 322, $width-52, 598, $teamBorder);
    
    // Team logos and names
    $teamY = 380;
    $logoSize = 180;
    
    // Home team (left) with enhanced styling
    addLogo($image, $fixture['home_logo'], $width/4, $teamY, $logoSize, false, $fixture['home_team']);
    addText($image, $fixture['home_team'], $width/4, $teamY + $logoSize/2 + 50, 24, $white, true, true);
    addText($image, "HOME", $width/4, $teamY + $logoSize/2 + 80, 16, $gray, true);
    
    // Away team (right) with enhanced styling
    addLogo($image, $fixture['away_logo'], 3*$width/4, $teamY, $logoSize, false, $fixture['away_team']);
    addText($image, $fixture['away_team'], 3*$width/4, $teamY + $logoSize/2 + 50, 24, $white, true, true);
    addText($image, "AWAY", 3*$width/4, $teamY + $logoSize/2 + 80, 16, $gray, true);
    
    // Enhanced VS separator with animated-style elements
    $vsCircleBg = imagecolorallocatealpha($image, 65, 84, 241, 30);
    $vsRing1 = imagecolorallocate($image, 255, 193, 7);
    $vsRing2 = imagecolorallocate($image, 76, 175, 80);
    
    // Multiple circle layers for depth and animation feel
    imagefilledellipse($image, $width/2, $teamY, 140, 140, $vsCircleBg);
    imageellipse($image, $width/2, $teamY, 145, 145, $vsRing1);
    imageellipse($image, $width/2, $teamY, 135, 135, $vsRing2);
    imageellipse($image, $width/2, $teamY, 125, 125, $vsRing1);
    
    addText($image, "VS", $width/2, $teamY - 10, 48, $white, true, true);
    
    // Match details section with prominent styling
    $detailsY = $teamY + 220;
    $detailsBg = imagecolorallocatealpha($image, 0, 0, 0, 50);
    $detailsBorder = imagecolorallocate($image, 255, 193, 7);
    
    imagefilledrectangle($image, 120, $detailsY - 90, $width-120, $detailsY + 90, $detailsBg);
    imagerectangle($image, 120, $detailsY - 90, $width-120, $detailsY + 90, $detailsBorder);
    imagerectangle($image, 122, $detailsY - 88, $width-122, $detailsY + 88, $detailsBorder);
    
    $matchDate = date("d M Y", strtotime($fixture['match_date']));
    $matchTime = date("H:i", strtotime($fixture['match_time']));
    
    // Date and time (highlighted with special styling)
    addText($image, "📅 " . strtoupper($matchDate), $width/2, $detailsY - 50, 32, $gold, true, true);
    addText($image, "🕐 " . $matchTime, $width/2, $detailsY - 10, 28, $white, true, true);
    
    // Venue with location pin
    addText($image, "📍 " . strtoupper($fixture['venue']), $width/2, $detailsY + 40, 22, $gray, true);
    
    // Call to action with exciting styling
    $ctaBg = imagecolorallocatealpha($image, 255, 193, 7, 25);
    $ctaBorder = imagecolorallocate($image, 255, 193, 7);
    
    imagefilledrectangle($image, 150, $detailsY + 120, $width-150, $detailsY + 180, $ctaBg);
    imagerectangle($image, 150, $detailsY + 120, $width-150, $detailsY + 180, $ctaBorder);
    imagerectangle($image, 152, $detailsY + 122, $width-152, $detailsY + 178, $ctaBorder);
    
    addText($image, "🎯 DON'T MISS THE ACTION! 🎯", $width/2, $detailsY + 145, 24, $accent, true, true);
    
    // Countdown element with enhanced styling
    $matchTimestamp = strtotime($fixture['match_date'] . ' ' . $fixture['match_time']);
    $daysUntil = max(0, floor(($matchTimestamp - time()) / 86400));
    
    if ($daysUntil >= 0) {
        $countdownBg = imagecolorallocatealpha($image, 220, 53, 69, 25);
        $countdownBorder = imagecolorallocate($image, 220, 53, 69);
        
        imagefilledrectangle($image, $width/2 - 120, $detailsY + 200, $width/2 + 120, $detailsY + 260, $countdownBg);
        imagerectangle($image, $width/2 - 120, $detailsY + 200, $width/2 + 120, $detailsY + 260, $countdownBorder);
        imagerectangle($image, $width/2 - 118, $detailsY + 202, $width/2 + 118, $detailsY + 258, $countdownBorder);
        
        if ($daysUntil == 0) {
            addText($image, "⚡ TODAY! ⚡", $width/2, $detailsY + 225, 24, $red, true, true);
        } elseif ($daysUntil == 1) {
            addText($image, "⏰ TOMORROW", $width/2, $detailsY + 225, 20, $red, true, true);
        } else {
            addText($image, "⏳ IN $daysUntil DAYS", $width/2, $detailsY + 225, 20, $red, true, true);
        }
    }
    
    // Add stylish timestamp
    $timestampBg = imagecolorallocatealpha($image, 0, 0, 0, 30);
    imagefilledrectangle($image, $width/2 - 120, $height - 60, $width/2 + 120, $height - 20, $timestampBg);
    addText($image, "Generated: " . date("Y-m-d H:i"), $width/2, $height - 40, 12, $gray, true);
}

function createGradientBackground($image, $width, $height, $color1, $color2) {
    // Extract RGB components properly
    $r1 = ($color1 >> 16) & 0xFF;
    $g1 = ($color1 >> 8) & 0xFF;
    $b1 = $color1 & 0xFF;
    
    $r2 = ($color2 >> 16) & 0xFF;
    $g2 = ($color2 >> 8) & 0xFF;
    $b2 = $color2 & 0xFF;
    
    // Create a more complex gradient with multiple layers
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        
        // Main gradient
        $r = (int)($r1 + ($r2 - $r1) * $ratio);
        $g = (int)($g1 + ($g2 - $g1) * $ratio);
        $b = (int)($b1 + ($b2 - $b1) * $ratio);
        
        $color = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $width, $y, $color);
    }
    
    // Add subtle texture overlay
    $overlay = imagecolorallocatealpha($image, 255, 255, 255, 100);
    for ($i = 0; $i < 50; $i++) {
        $x = rand(0, $width);
        $y = rand(0, $height);
        $size = rand(1, 3);
        imagefilledellipse($image, $x, $y, $size, $size, $overlay);
    }
    
    // Add geometric patterns
    $pattern = imagecolorallocatealpha($image, 255, 255, 255, 110);
    
    // Diagonal lines pattern
    for ($i = 0; $i < $width + $height; $i += 40) {
        imageline($image, $i, 0, $i - $height, $height, $pattern);
    }
}

function addLogo($image, $logoUrl, $x, $y, $size, $center = false, $teamName = '') {
    if (!$logoUrl || $logoUrl === '') {
        // Create beautiful team initial logo
        createTeamInitialLogo($image, $x, $y, $size, $teamName);
        return;
    }
    
    // Try to load the logo
    $logoPath = '';
    
    // Check multiple possible paths
    $possiblePaths = [
        '../' . ltrim($logoUrl, '/'),
        '../assets/images/logos/' . basename($logoUrl),
        '../uploads/logos/' . basename($logoUrl),
        $logoUrl // Direct path
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $logoPath = $path;
            break;
        }
    }
    
    if (!$logoPath) {
        // Create beautiful team initial logo if no logo found
        createTeamInitialLogo($image, $x, $y, $size, $teamName);
        return;
    }
    
    if (file_exists($logoPath)) {
        $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $logo = imagecreatefromjpeg($logoPath);
                break;
            case 'png':
                $logo = imagecreatefrompng($logoPath);
                break;
            case 'gif':
                $logo = imagecreatefromgif($logoPath);
                break;
            default:
                // Create beautiful team initial logo for unsupported format
                createTeamInitialLogo($image, $x, $y, $size, $teamName);
                return;
        }
        
        if ($logo) {
            $logoWidth = imagesx($logo);
            $logoHeight = imagesy($logo);
            
            // Calculate position
            $logoX = (int)($x - $size/2);
            $logoY = (int)($y - $size/2);
            
            // Create circular mask for logo
            $mask = imagecreatetruecolor($size, $size);
            $transparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
            imagefill($mask, 0, 0, $transparent);
            
            $white = imagecolorallocate($mask, 255, 255, 255);
            imagefilledellipse($mask, $size/2, $size/2, $size-4, $size-4, $white);
            
            // Create circular logo
            $circularLogo = imagecreatetruecolor($size, $size);
            $transparent = imagecolorallocatealpha($circularLogo, 0, 0, 0, 127);
            imagefill($circularLogo, 0, 0, $transparent);
            imagesavealpha($circularLogo, true);
            
            // Resize and copy logo to circular canvas
            imagecopyresampled($circularLogo, $logo, 0, 0, 0, 0, $size, $size, $logoWidth, $logoHeight);
            
            // Apply circular mask
            for ($px = 0; $px < $size; $px++) {
                for ($py = 0; $py < $size; $py++) {
                    $maskColor = imagecolorat($mask, $px, $py);
                    if (($maskColor & 0xFF) === 0) { // If mask is black/transparent
                        imagesetpixel($circularLogo, $px, $py, $transparent);
                    }
                }
            }
            
            // Add shadow behind logo
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 50);
            imagefilledellipse($image, $x + 4, $y + 4, $size, $size, $shadow);
            
            // Add border
            $border = imagecolorallocate($image, 255, 255, 255);
            imageellipse($image, $x, $y, $size + 6, $size + 6, $border);
            
            // Copy circular logo to main image
            imagecopy($image, $circularLogo, $logoX, $logoY, 0, 0, $size, $size);
            
            imagedestroy($logo);
            imagedestroy($mask);
            imagedestroy($circularLogo);
        } else {
            createTeamInitialLogo($image, $x, $y, $size, $teamName);
        }
    } else {
        createTeamInitialLogo($image, $x, $y, $size, $teamName);
    }
}

function createTeamInitialLogo($image, $x, $y, $size, $teamName) {
    // Generate team initials (first letters of each word)
    $words = explode(' ', trim($teamName));
    $initials = '';
    foreach ($words as $word) {
        if (strlen($word) > 0) {
            $initials .= strtoupper($word[0]);
            if (strlen($initials) >= 3) break; // Max 3 initials
        }
    }
    if (empty($initials)) $initials = 'TM'; // Fallback
    
    // Generate beautiful gradient colors based on team name
    $hash = crc32($teamName);
    $hue = abs($hash) % 360;
    
    // Convert HSV to RGB for beautiful colors
    $colors = hsvToRgb($hue, 0.8, 0.9);
    $primaryColor = imagecolorallocate($image, $colors[0], $colors[1], $colors[2]);
    
    $colors2 = hsvToRgb(($hue + 60) % 360, 0.7, 0.7);
    $secondaryColor = imagecolorallocate($image, $colors2[0], $colors2[1], $colors2[2]);
    
    $white = imagecolorallocate($image, 255, 255, 255);
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 50);
    
    // Position calculations
    $logoX = (int)($x - $size/2);
    $logoY = (int)($y - $size/2);
    
    // Add shadow
    imagefilledellipse($image, $x + 4, $y + 4, $size, $size, $shadow);
    
    // Create gradient circle
    for ($i = 0; $i < $size/2; $i++) {
        $ratio = $i / ($size/2);
        $r = (int)($colors[0] + ($colors2[0] - $colors[0]) * $ratio);
        $g = (int)($colors[1] + ($colors2[1] - $colors[1]) * $ratio);
        $b = (int)($colors[2] + ($colors2[2] - $colors[2]) * $ratio);
        $gradientColor = imagecolorallocate($image, $r, $g, $b);
        
        imagefilledellipse($image, $x, $y, $size - $i*2, $size - $i*2, $gradientColor);
    }
    
    // Add white border
    imageellipse($image, $x, $y, $size + 4, $size + 4, $white);
    imageellipse($image, $x, $y, $size + 2, $size + 2, $white);
    
    // Add team initials with proper font sizing
    $fontSize = strlen($initials) <= 2 ? 5 : 4; // Bigger font for 2 letters
    $textColor = $white;
    
    // Add text shadow for better contrast
    addText($image, $initials, $x + 2, $y + 2, 24, $shadow, true, true);
    addText($image, $initials, $x, $y, 24, $textColor, true, true);
}

function hsvToRgb($h, $s, $v) {
    $h = $h / 360;
    $i = floor($h * 6);
    $f = $h * 6 - $i;
    $p = $v * (1 - $s);
    $q = $v * (1 - $f * $s);
    $t = $v * (1 - (1 - $f) * $s);
    
    switch($i % 6) {
        case 0: $r = $v; $g = $t; $b = $p; break;
        case 1: $r = $q; $g = $v; $b = $p; break;
        case 2: $r = $p; $g = $v; $b = $t; break;
        case 3: $r = $p; $g = $q; $b = $v; break;
        case 4: $r = $t; $g = $p; $b = $v; break;
        case 5: $r = $v; $g = $p; $b = $q; break;
    }
    
    return [
        (int)($r * 255),
        (int)($g * 255),
        (int)($b * 255)
    ];
}

function addText($image, $text, $x, $y, $size, $color, $center = false, $bold = false) {
    // Enhanced text rendering with better fonts and effects
    $font = $bold ? 5 : 4;
    
    if ($center) {
        $textWidth = imagefontwidth($font) * strlen($text);
        $x = (int)($x - $textWidth / 2);
    }
    
    // Enhanced text shadow with multiple layers for depth and glow effect
    $shadow1 = imagecolorallocatealpha($image, 0, 0, 0, 70);
    $shadow2 = imagecolorallocatealpha($image, 0, 0, 0, 40);
    $shadow3 = imagecolorallocatealpha($image, 0, 0, 0, 20);
    
    // Multiple shadow layers for depth
    imagestring($image, $font, $x + 4, $y + 4, $text, $shadow1);
    imagestring($image, $font, $x + 3, $y + 3, $text, $shadow2);
    imagestring($image, $font, $x + 2, $y + 2, $text, $shadow2);
    imagestring($image, $font, $x + 1, $y + 1, $text, $shadow3);
    
    // Main text
    imagestring($image, $font, $x, $y, $text, $color);
    
    // Add outline and glow for better contrast if bold
    if ($bold) {
        $outline = imagecolorallocatealpha($image, 0, 0, 0, 60);
        $glow = imagecolorallocatealpha($image, 255, 255, 255, 30);
        
        // Outline
        imagestring($image, $font, $x-1, $y, $text, $outline);
        imagestring($image, $font, $x+1, $y, $text, $outline);
        imagestring($image, $font, $x, $y-1, $text, $outline);
        imagestring($image, $font, $x, $y+1, $text, $outline);
        
        // Subtle glow effect
        imagestring($image, $font, $x-1, $y-1, $text, $glow);
        imagestring($image, $font, $x+1, $y+1, $text, $glow);
        
        // Re-apply main text for crisp finish
        imagestring($image, $font, $x, $y, $text, $color);
    }
}
?>
