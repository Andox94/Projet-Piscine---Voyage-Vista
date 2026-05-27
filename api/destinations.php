<?php
require_once 'config.php';

$sql = "SELECT * FROM destinations ORDER BY id ASC";
$result = mysqli_query($conn, $sql);

$destinations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $destinations[] = $row;
}

echo json_encode($destinations);
?>