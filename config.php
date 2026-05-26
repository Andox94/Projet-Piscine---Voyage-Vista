<?php
header("Content-Type: application/json; charset=UTF-8");

$host = "localhost";
$db_name = "voyagevista";
$username = "root";
$password = "root"; // Mettez "" (vide) si vous utilisez XAMPP au lieu de MAMP

try {
    $db = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["error" => "Erreur de connexion : " . $exception->getMessage()]);
    exit();
}
?>