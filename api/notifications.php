<?php
require_once 'config.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 2; // Marie Dupont par défaut

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

echo json_encode($notifications);
?>