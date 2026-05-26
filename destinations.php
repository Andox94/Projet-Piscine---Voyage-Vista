<?php
require_once 'config.php';

$query = $db->query("SELECT * FROM destinations");
$destinations = $query->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($destinations);
?>