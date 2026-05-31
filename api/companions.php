<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$data    = ($method !== 'GET') ? json_decode(file_get_contents("php://input"), true) : [];
$action  = $_GET['action'] ?? ($data['action'] ?? '');

// ── SEARCH USERS (for adding companions) ──────────────
if ($method === 'GET' && $action === 'search') {
    $q       = isset($_GET['q']) ? trim($_GET['q']) : '';
    $exc_id  = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0; // exclude the owner
    $trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : 0;

    if (strlen($q) < 2) { echo json_encode([]); exit(); }

    $like = "%{$q}%";
    // Exclude owner and already-added companions
    $sql = "SELECT u.id, u.name, u.email, u.avatar_url
            FROM users u
            WHERE (u.name LIKE ? OR u.email LIKE ?)
              AND u.id != ?
              AND u.id NOT IN (
                  SELECT companion_user_id FROM trip_companions WHERE trip_id = ?
              )
            LIMIT 10";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssii", $like, $like, $exc_id, $trip_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $users  = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    echo json_encode($users);

// ── LIST COMPANIONS FOR A TRIP ─────────────────────────
} elseif ($method === 'GET' && $action === 'list') {
    $trip_id = isset($_GET['trip_id']) ? intval($_GET['trip_id']) : 0;
    if (!$trip_id) { echo json_encode([]); exit(); }
    $sql  = "SELECT u.id, u.name, u.email, u.avatar_url, tc.added_at
             FROM trip_companions tc
             JOIN users u ON tc.companion_user_id = u.id
             WHERE tc.trip_id = ?
             ORDER BY tc.added_at ASC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $trip_id);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $companions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $companions[] = $row;
    }
    echo json_encode($companions);

// ── ADD COMPANION ──────────────────────────────────────
} elseif ($method === 'POST' && $action === 'add') {
    $trip_id      = intval($data['trip_id']);
    $owner_id     = intval($data['owner_id']);
    $companion_id = intval($data['companion_id']);

    // Verify requester owns the trip
    $chk = mysqli_prepare($conn, "SELECT id FROM trips WHERE id=? AND user_id=? AND status='confirmed'");
    mysqli_stmt_bind_param($chk, "ii", $trip_id, $owner_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) === 0) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Voyage introuvable ou accès refusé."]);
        exit();
    }

    // Don't add owner as companion
    if ($companion_id === $owner_id) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Vous ne pouvez pas vous ajouter vous-même."]);
        exit();
    }

    // Verify companion exists
    $uchk = mysqli_prepare($conn, "SELECT name FROM users WHERE id=?");
    mysqli_stmt_bind_param($uchk, "i", $companion_id);
    mysqli_stmt_execute($uchk);
    $urow = mysqli_fetch_assoc(mysqli_stmt_get_result($uchk));
    if (!$urow) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Utilisateur introuvable."]);
        exit();
    }

    // Insert (ignore duplicate)
    $ins = mysqli_prepare($conn, "INSERT IGNORE INTO trip_companions (trip_id, companion_user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($ins, "ii", $trip_id, $companion_id);
    if (mysqli_stmt_execute($ins)) {
        // Notify the companion
        $trip_q  = mysqli_prepare($conn, "SELECT d.name AS dest, t.reference_code FROM trips t JOIN destinations d ON t.destination_id=d.id WHERE t.id=?");
        mysqli_stmt_bind_param($trip_q, "i", $trip_id);
        mysqli_stmt_execute($trip_q);
        $trip_row = mysqli_fetch_assoc(mysqli_stmt_get_result($trip_q));

        $owner_q = mysqli_prepare($conn, "SELECT name FROM users WHERE id=?");
        mysqli_stmt_bind_param($owner_q, "i", $owner_id);
        mysqli_stmt_execute($owner_q);
        $owner_row = mysqli_fetch_assoc(mysqli_stmt_get_result($owner_q));

        if ($trip_row && $owner_row) {
            createNotification($conn, $companion_id, 'reservation',
                "Vous avez été ajouté à un voyage !",
                "{$owner_row['name']} vous a ajouté au voyage vers {$trip_row['dest']} (réf. {$trip_row['reference_code']})."
            );
        }
        echo json_encode(["status" => "success", "message" => "{$urow['name']} ajouté au voyage."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur lors de l'ajout."]);
    }

// ── REMOVE COMPANION ───────────────────────────────────
} elseif ($method === 'POST' && $action === 'remove') {
    $trip_id      = intval($data['trip_id']);
    $owner_id     = intval($data['owner_id']);
    $companion_id = intval($data['companion_id']);

    // Verify ownership OR self-removal
    $chk = mysqli_prepare($conn, "SELECT id FROM trips WHERE id=? AND (user_id=?)");
    mysqli_stmt_bind_param($chk, "ii", $trip_id, $owner_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if (mysqli_stmt_num_rows($chk) === 0) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Accès refusé."]);
        exit();
    }

    $del = mysqli_prepare($conn, "DELETE FROM trip_companions WHERE trip_id=? AND companion_user_id=?");
    mysqli_stmt_bind_param($del, "ii", $trip_id, $companion_id);
    if (mysqli_stmt_execute($del)) {
        echo json_encode(["status" => "success", "message" => "Voyageur retiré du voyage."]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur lors de la suppression."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Action inconnue."]);
}
?>
