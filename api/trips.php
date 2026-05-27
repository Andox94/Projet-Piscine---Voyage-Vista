<?php
require_once 'config.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 2;

$sql = "SELECT t.*, d.name AS destination_name, d.country FROM trips t 
        JOIN destinations d ON t.destination_id = d.id 
        WHERE t.user_id = ? ORDER BY t.created_at DESC";
        
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$trips = [];
while ($row = mysqli_fetch_assoc($result)) {
    $trips[] = $row;
}

echo json_encode($trips);
?>