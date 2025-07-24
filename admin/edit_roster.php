<?php
require_once('../db_connect.php');
require_once('../includes/auth.php');
requireRole('manager');

$team = $conn->query("SELECT team_id FROM teams WHERE coach_name = '{$_SESSION['name']}'")->fetch_assoc();
$teamId = $team['team_id'];

// Delete player
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM players WHERE player_id = $id AND team_id = $teamId");
    header("Location: edit_team.php"); // ⬅️ This should match your actual file name
    exit;
}


// Fetch updated roster
$players = $conn->query("SELECT * FROM players WHERE team_id = $teamId");
?>

<!DOCTYPE html>
<html>
<head><title>Edit Roster</title></head>
<body>
    <h2>Team Roster</h2>
    <table border="1" cellpadding="6">
        <tr><th>Name</th><th>Position</th><th>Action</th></tr>
        <?php while ($p = $players->fetch_assoc()): ?>
            <tr>
                <td><?= $p['name'] ?></td>
                <td><?= $p['position'] ?></td>
                <td><a href="?delete=<?= $p['player_id'] ?>" onclick="return confirm('Remove this player?')">🗑️ Remove</a></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <br><a href="panel.php">⬅ Back to Panel</a>
</body>
</html>
