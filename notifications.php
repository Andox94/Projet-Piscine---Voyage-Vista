<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// ── LIST NOTIFICATIONS ─────────────────────────────────
if ($method === 'GET') {
    if ($user_id === 0) { echo json_encode([]); exit(); }
    $sql  = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result        = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    echo json_encode($notifications);

// ── UPDATE NOTIFICATION ────────────────────────────────
} elseif ($method === 'POST') {
    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'mark_read') {
        $id  = intval($data['id']);
        $uid = intval($data['user_id']);
        $sql = "UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);
        echo json_encode(["status" => "success"]);

    } elseif ($action === 'mark_all_read') {
        $uid = intval($data['user_id']);
        $sql = "UPDATE notifications SET is_read=1 WHERE user_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        echo json_encode(["status" => "success"]);

    } elseif ($action === 'delete') {
        $id  = intval($data['id']);
        $uid = intval($data['user_id']);
        $sql = "DELETE FROM notifications WHERE id=? AND user_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);
        echo json_encode(["status" => "success"]);

    } elseif ($action === 'delete_all') {
        $uid = intval($data['user_id']);
        $sql = "DELETE FROM notifications WHERE user_id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        echo json_encode(["status" => "success"]);
    }
}
?>
