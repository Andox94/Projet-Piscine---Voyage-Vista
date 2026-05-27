<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

$host = "localhost";
$user = "root";
$password = "root"; // "root" si utilisation avecc MAMP; à remplacer par "" avec WAMP
$dbname = "voyagevista";

// Connexion via l'extension mysqli
$conn = mysqli_connect($host, $user, $password, $dbname);

if (!$conn) {
    echo json_encode(["error" => "Échec de la connexion MySQLI : " . mysqli_connect_error()]);
    exit();
}

// Forcer l'encodage pour éviter les problèmes d'accents
mysqli_set_charset($conn, "utf8mb4");
?>