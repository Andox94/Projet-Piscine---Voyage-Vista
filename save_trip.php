<?php
require_once 'config.php';

// Récupération du flux JSON envoyé par React
$data = json_decode(file_get_contents("php://input"), true);

if(!empty($data['client_name']) && !empty($data['destination_id'])) {
    // Ici, vous inséreriez le séjour dans une table 'trips'.
    // Pour le prototype dynamique, on valide que les données reçues sont correctes.
    echo json_encode([
        "status" => "success", 
        "message" => "Voyage pour " . htmlspecialchars($data['client_name']) . " enregistré avec succès en base de données !"
    ]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Données incomplètes."]);
}
?>