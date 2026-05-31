<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "localhost";
$user = "root";
$password = "root"; // "root" si utilisation avec MAMP; "" avec WAMP
$dbname = "voyagevista";

$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    echo json_encode(["error" => "Échec de la connexion MySQL : " . mysqli_connect_error()]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

/**
 * Crée une notification pour un utilisateur
 */
function createNotification($conn, $user_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "issss", $user_id, $type, $title, $message, $link);
    mysqli_stmt_execute($stmt);
}
?>
