<?php
require_once 'config.php';

$dest_id = isset($_GET['dest_id']) ? intval($_GET['dest_id']) : 0;

$stmt = $db->prepare("SELECT * FROM accommodations WHERE destination_id = ?");
$stmt->execute([$dest_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>