<?php
require_once('../includes/auth.php');
require_once('../db_connect.php');
requireRole('admin');

$leagueId = $_SESSION['league_id'] ?? 1;
$msg = '';
$success = false;

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $fixtureId = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM fixtures WHERE fixture_id = ? AND league_id = ?");
    $stmt->bind_param("ii", $fixtureId, $leagueId);
    $success = $stmt->execute();
    $msg = $success 
        ? "✅ Fixture #$fixtureId deleted successfully." 
        : "❌ Error deleting fixture: " . $stmt->error;
    $stmt->close();
}

// Handle status update
if (isset($_POST['update_status'])) {
    $fixtureId = intval($_POST['fixture_id']);
    $newStatus = $_POST['status'];
    $stmt = $conn->prepare("UPDATE fixtures SET status = ? WHERE fixture_id = ? AND league_id = ?");
    $stmt->bind_param("sii", $newStatus, $fixtureId, $leagueId);
    $success = $stmt->execute();
    $msg = $success 
        ? "✅ Fixture status updated to: $newStatus" 
        : "❌ Error updating status: " . $stmt->error;
    $stmt->close();
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$teamFilter = $_GET['team'] ?? '';
$dateFilter = $_GET['date'] ?? '';

// Build WHERE clause
$whereConditions = ["f.league_id = ?"];
$params = [$leagueId];
$types = "i";

if ($statusFilter !== 'all') {
    $whereConditions[] = "f.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if ($teamFilter) {
    $whereConditions[] = "(f.home_team = ? OR f.away_team = ?)";
    $params[] = $teamFilter;
    $params[] = $teamFilter;
    $types .= "ii";
}

if ($dateFilter) {
    $whereConditions[] = "f.match_date = ?";
    $params[] = $dateFilter;
    $types .= "s";
}

$whereClause = implode(" AND ", $whereConditions);

// Fetch fixtures with team names
$sql = "
    SELECT f.fixture_id, f.match_date, f.match_time, f.venue, f.status, f.created_at,
           t1.name AS home_team_name, t2.name AS away_team_name,
           f.home_team, f.away_team
    FROM fixtures f
    JOIN teams t1 ON f.home_team = t1.team_id
    JOIN teams t2 ON f.away_team = t2.team_id
    WHERE $whereClause
    ORDER BY f.match_date DESC, f.match_time DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$fixtures = $stmt->get_result();

// Get teams for filter dropdown
$teams = $conn->query("SELECT team_id, name FROM teams WHERE league_id = $leagueId ORDER BY name");

include('../includes/header.php');
include('../includes/navbar.php');
?>

<section class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">📅 Manage Fixtures</h3>
                    <p class="mb-0 small">View, edit, and manage all league fixtures</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show">
                    <?= $msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters and Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">🔍 Filters & Actions</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-select">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="upcoming" <?= $statusFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="played" <?= $statusFilter === 'played' ? 'selected' : '' ?>>Played</option>
                                <option value="postponed" <?= $statusFilter === 'postponed' ? 'selected' : '' ?>>Postponed</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Team Filter</label>
                            <select name="team" class="form-select">
                                <option value="">All Teams</option>
                                <?php while ($team = $teams->fetch_assoc()): ?>
                                    <option value="<?= $team['team_id'] ?>" <?= $teamFilter == $team['team_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($team['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date Filter</label>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($dateFilter) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="manage_fixtures.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="create_fixture.php" class="btn btn-success">
                            ➕ Create New Fixture
                        </a>
                        <button class="btn btn-outline-primary" onclick="exportFixtures()">
                            📄 Export to PDF
                        </button>
                        <button class="btn btn-outline-info" onclick="copyFixturesToClipboard()">
                            📋 Copy for WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fixtures Table -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">📋 Fixtures List (<?= $fixtures->num_rows ?> found)</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-light" onclick="selectAll()">Select All</button>
                        <button class="btn btn-outline-light" onclick="deselectAll()">Deselect All</button>
                        <button class="btn btn-outline-danger" onclick="bulkDelete()">Delete Selected</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($fixtures->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                        </th>
                                        <th>Actions</th>
                                        <th>Fixture ID</th>
                                        <th>Match</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Venue</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($fixture = $fixtures->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="fixture-checkbox" value="<?= $fixture['fixture_id'] ?>">
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_fixture.php?id=<?= $fixture['fixture_id'] ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        ✏️
                                                    </a>
                                                    <button class="btn btn-outline-info" 
                                                            onclick="copyFixture(<?= $fixture['fixture_id'] ?>)" 
                                                            title="Copy">
                                                        📋
                                                    </button>
                                                    <a href="?delete=1&id=<?= $fixture['fixture_id'] ?>" 
                                                       class="btn btn-outline-danger"
                                                       onclick="return confirm('Delete this fixture?')" 
                                                       title="Delete">
                                                        🗑️
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">#<?= $fixture['fixture_id'] ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars($fixture['home_team_name']) ?>
                                                    <span class="text-muted">vs</span>
                                                    <?= htmlspecialchars($fixture['away_team_name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= date("M d, Y", strtotime($fixture['match_date'])) ?>
                                            </td>
                                            <td>
                                                <?= date("H:i", strtotime($fixture['match_time'])) ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($fixture['venue']) ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="fixture_id" value="<?= $fixture['fixture_id'] ?>">
                                                    <select name="status" class="form-select form-select-sm status-select" 
                                                            onchange="this.form.submit()">
                                                        <option value="upcoming" <?= $fixture['status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                                        <option value="played" <?= $fixture['status'] === 'played' ? 'selected' : '' ?>>Played</option>
                                                        <option value="postponed" <?= $fixture['status'] === 'postponed' ? 'selected' : '' ?>>Postponed</option>
                                                        <option value="cancelled" <?= $fixture['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                    <input type="hidden" name="update_status" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date("M d, H:i", strtotime($fixture['created_at'])) ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-calendar-times fa-3x text-muted"></i>
                            </div>
                            <h5 class="text-muted">No fixtures found</h5>
                            <p class="text-muted">Try adjusting your filters or create a new fixture.</p>
                            <a href="create_fixture.php" class="btn btn-primary">
                                ➕ Create First Fixture
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Dashboard -->
    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-outline-primary">
            ⬅ Back to Dashboard
        </a>
    </div>
</section>

<script>
// Checkbox functionality
function toggleAll() {
    const masterCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.fixture-checkbox');
    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
}

function selectAll() {
    document.querySelectorAll('.fixture-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
}

function deselectAll() {
    document.querySelectorAll('.fixture-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
}

// Bulk operations
function bulkDelete() {
    const selected = Array.from(document.querySelectorAll('.fixture-checkbox:checked'))
                          .map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Please select fixtures to delete');
        return;
    }
    
    if (confirm(`Delete ${selected.length} selected fixture(s)?`)) {
        window.location.href = `bulk_delete_fixtures.php?ids=${selected.join(',')}`;
    }
}

// Copy single fixture
function copyFixture(fixtureId) {
    // Find the fixture row
    const row = document.querySelector(`input[value="${fixtureId}"]`).closest('tr');
    const cells = row.querySelectorAll('td');
    
    const match = cells[3].textContent.trim();
    const date = cells[4].textContent.trim();
    const time = cells[5].textContent.trim();
    const venue = cells[6].textContent.trim();
    
    const text = `🏀 ${match}\n📅 ${date} at ${time}\n📍 ${venue}`;
    
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Fixture details copied to clipboard!', 'success');
    }).catch(() => {
        alert('Failed to copy to clipboard');
    });
}

// Export functions
function exportFixtures() {
    window.open('export/export_fixtures_pdf.php', '_blank');
}

function copyFixturesToClipboard() {
    const rows = document.querySelectorAll('tbody tr');
    let text = '🏀 *FIXTURES LIST*\n\n';
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        const match = cells[3].textContent.trim();
        const date = cells[4].textContent.trim();
        const time = cells[5].textContent.trim();
        const venue = cells[6].textContent.trim();
        
        text += `${match}\n📅 ${date} ${time}\n📍 ${venue}\n\n`;
    });
    
    text += '⚡ NCL League Platform';
    
    navigator.clipboard.writeText(text).then(() => {
        showNotification('All fixtures copied for WhatsApp!', 'success');
    }).catch(() => {
        alert('Failed to copy to clipboard');
    });
}

// Notification helper
function showNotification(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; width: 300px;';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 3000);
}

// Auto-submit status changes with confirmation
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function(e) {
        const fixtureId = this.closest('form').querySelector('input[name="fixture_id"]').value;
        const newStatus = this.value;
        
        if (!confirm(`Change fixture #${fixtureId} status to "${newStatus}"?`)) {
            e.preventDefault();
            // Reset to original value
            this.selectedIndex = Array.from(this.options).findIndex(opt => opt.defaultSelected);
        }
    });
});

// Add hover effects
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});
</script>

<style>
.btn-group-sm .btn {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
}

.status-select {
    width: auto;
    min-width: 100px;
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.fixture-checkbox {
    transform: scale(1.2);
}

.badge {
    font-size: 0.8em;
}

.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05) !important;
}

@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin-bottom: 0.25rem;
    }
    
    .table-responsive {
        font-size: 0.9rem;
    }
}
</style>

<?php include('../includes/footer.php'); ?>
