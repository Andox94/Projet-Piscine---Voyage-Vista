<?php
require_once 'config.php';

$dest_id = isset($_GET['destination_id']) ? intval($_GET['destination_id']) : 0;

$sql = "SELECT * FROM transports WHERE destination_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $dest_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$transports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $transports[] = $row;
}

echo json_encode($transports);
?>