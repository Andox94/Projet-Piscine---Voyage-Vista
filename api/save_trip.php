<?php
require_once 'config.php';

// Récupérer le corps de la requête JSON (venant de React fontend)
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['destination_id']) && isset($data['client_name'])) {
    
    // On démarre une transaction manuelle pour s'assurer que tout s'insère bien ensemble
    mysqli_begin_transaction($conn);

    try {
        $sql = "INSERT INTO trips (user_id, destination_id, transport_id, accommodation_id, client_name, client_email, total_price, reference_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        $user_id = 2; // On associe par défaut à Marie Dupont (ID 2) pour la démonstration
        $dest_id = intval($data['destination_id']);
        $trans_id = isset($data['transport_id']) ? intval($data['transport_id']) : null;
        $accom_id = isset($data['accommodation_id']) ? intval($data['accommodation_id']) : null;
        $name = $data['client_name'];
        $email = $data['client_email'];
        $total = intval($data['total_price']);
        $ref_code = 'VV-' . strtoupper(substr(uniqid(), -6));

        mysqli_stmt_bind_param($stmt, "iiiissis", $user_id, $dest_id, $trans_id, $accom_id, $name, $email, $total, $ref_code);
        mysqli_stmt_execute($stmt);
        
        // Récupérer l'ID généré pour la table trips
        $trip_id = mysqli_insert_id($conn);

        // Si des activités ont été ajoutées au panier, on les lie au voyage
        if (!empty($data['activities'])) {
            $sql_act = "INSERT INTO trip_activities (trip_id, activity_id) VALUES (?, ?)";
            $stmt_act = mysqli_prepare($conn, $sql_act);
            
            foreach ($data['activities'] as $act_id) {
                $act_id_int = intval($act_id);
                mysqli_stmt_bind_param($stmt_act, "ii", $trip_id, $act_id_int);
                mysqli_stmt_execute($stmt_act);
            }
        }

        // Valider la transaction en BDD
        mysqli_commit($conn);
        echo json_encode(["status" => "success", "message" => "Réservation stockée ! Code de référence : " . $ref_code]);

    } catch (Exception $e) {
        // En cas de problème, on annule tout pour ne pas corrompre la BDD
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Échec de l'insertion : " . $e->getMessage()]);
    }

} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Données obligatoires manquantes."]);
}
?>