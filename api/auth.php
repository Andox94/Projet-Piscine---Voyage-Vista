<?php
require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true);
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'login') {
    if (!empty($data['email']) && !empty($data['password'])) {
        $email = $data['email'];
        
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // Vérification du mot de passe haché
        if ($user && password_verify($data['password'], $user['password_hash'])) {
            echo json_encode([
                "status" => "success",
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Identifiants invalides."]);
        }
    }
} elseif ($action === 'register') {
    if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
        $name = $data['name'];
        $email = $data['email'];
        // Hachage sécurisé exigé par les normes de développement
        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = isset($data['role']) ? $data['role'] : 'client';

        $sql = "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $password_hash, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Compte créé avec succès !"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Impossible de créer le compte (email doublon)."]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Action manquante."]);
}
?>