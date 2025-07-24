<?php
header('Content-Type: application/json');
session_start();
require_once('../db_connect.php');
require_once('../includes/auth.php');

// Check if user is authenticated and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Validate player_id parameter
if (!isset($_GET['player_id']) || !is_numeric($_GET['player_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid player ID']);
    exit;
}

$playerId = (int)$_GET['player_id'];

try {
    // Fetch player details with team and league information
    $stmt = $conn->prepare("
        SELECT 
            p.player_id,
            p.name,
            p.position,
            t.team_id,
            t.name as team_name,
            l.name as league_name,
            l.abbreviation as league_abbreviation
        FROM players p
        JOIN teams t ON p.team_id = t.team_id
        JOIN leagues l ON t.league_id = l.league_id
        WHERE p.player_id = ?
    ");
    
    $stmt->bind_param("i", $playerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($player = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'player' => $player
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Player not found'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error fetching player details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}

$conn->close();
?>
