<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// ── LIST TRIPS ─────────────────────────────────────────
if ($method === 'GET' && !isset($_GET['action'])) {
    if ($user_id === 0) {
        echo json_encode([]);
        exit();
    }
    $sql  = "SELECT t.*, d.name AS destination_name, d.country, d.image_url AS destination_image,
                    tr.type AS transport_type, tr.company AS transport_company,
                    ac.name AS accommodation_name, ac.type AS accommodation_type
             FROM trips t
             JOIN destinations d ON t.destination_id = d.id
             LEFT JOIN transports tr ON t.transport_id = tr.id
             LEFT JOIN accommodations ac ON t.accommodation_id = ac.id
             WHERE t.user_id = ?
             ORDER BY t.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $trips  = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Récupérer les activités associées
        $sql_act  = "SELECT a.name, a.category, a.price_per_person, ta.quantity
                     FROM trip_activities ta JOIN activities a ON ta.activity_id = a.id
                     WHERE ta.trip_id = ?";
        $stmt_act = mysqli_prepare($conn, $sql_act);
        mysqli_stmt_bind_param($stmt_act, "i", $row['id']);
        mysqli_stmt_execute($stmt_act);
        $res_act     = mysqli_stmt_get_result($stmt_act);
        $row['activities'] = [];
        while ($act = mysqli_fetch_assoc($res_act)) {
            $row['activities'][] = $act;
        }
        $trips[] = $row;
    }
    echo json_encode($trips);

// ── LIST ALL TRIPS (admin) ─────────────────────────────
} elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'all') {
    $sql    = "SELECT t.*, d.name AS destination_name, u.name AS user_name, u.email AS user_email
               FROM trips t
               JOIN destinations d ON t.destination_id = d.id
               JOIN users u ON t.user_id = u.id
               ORDER BY t.created_at DESC LIMIT 100";
    $result = mysqli_query($conn, $sql);
    $trips  = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $trips[] = $row;
    }
    echo json_encode($trips);

// ── CANCEL TRIP ────────────────────────────────────────
} elseif ($method === 'POST') {
    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'cancel') {
        $trip_id = intval($data['trip_id']);
        $uid     = intval($data['user_id']);

        // Récupérer le voyage pour remettre les disponibilités
        $sel  = mysqli_prepare($conn, "SELECT * FROM trips WHERE id=? AND user_id=? AND status != 'cancelled'");
        mysqli_stmt_bind_param($sel, "ii", $trip_id, $uid);
        mysqli_stmt_execute($sel);
        $trip = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));

        if (!$trip) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Voyage introuvable ou déjà annulé."]);
            exit();
        }

        mysqli_begin_transaction($conn);
        try {
            // Mettre à jour le statut
            $upd  = mysqli_prepare($conn, "UPDATE trips SET status='cancelled', updated_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($upd, "i", $trip_id);
            mysqli_stmt_execute($upd);

            // Remettre les places transport
            if ($trip['transport_id']) {
                $upd = mysqli_prepare($conn, "UPDATE transports SET seats_left = seats_left + ? WHERE id=?");
                mysqli_stmt_bind_param($upd, "ii", $trip['travelers'], $trip['transport_id']);
                mysqli_stmt_execute($upd);
            }

            // Remettre les chambres hébergement
            if ($trip['accommodation_id']) {
                $upd = mysqli_prepare($conn, "UPDATE accommodations SET rooms_left = rooms_left + 1 WHERE id=?");
                mysqli_stmt_bind_param($upd, "i", $trip['accommodation_id']);
                mysqli_stmt_execute($upd);
            }

            // Remettre les places activités
            $acts_q  = mysqli_prepare($conn, "SELECT activity_id, quantity FROM trip_activities WHERE trip_id=?");
            mysqli_stmt_bind_param($acts_q, "i", $trip_id);
            mysqli_stmt_execute($acts_q);
            $acts_res = mysqli_stmt_get_result($acts_q);
            while ($act_row = mysqli_fetch_assoc($acts_res)) {
                $upd = mysqli_prepare($conn, "UPDATE activities SET current_participants = current_participants - ? WHERE id=?");
                mysqli_stmt_bind_param($upd, "ii", $act_row['quantity'], $act_row['activity_id']);
                mysqli_stmt_execute($upd);
            }

            // Récupérer le nom destination pour la notif
            $dest_q = mysqli_prepare($conn, "SELECT name FROM destinations WHERE id=?");
            mysqli_stmt_bind_param($dest_q, "i", $trip['destination_id']);
            mysqli_stmt_execute($dest_q);
            $dest_row  = mysqli_fetch_assoc(mysqli_stmt_get_result($dest_q));
            $dest_name = $dest_row ? $dest_row['name'] : 'votre destination';

            createNotification($conn, $uid, 'annulation',
                "Réservation annulée — {$dest_name}",
                "Votre voyage vers {$dest_name} (réf. {$trip['reference_code']}) a été annulé avec succès."
            );

            mysqli_commit($conn);
            echo json_encode(["status" => "success", "message" => "Réservation annulée."]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

    } elseif ($action === 'update') {
        // Modifier le nombre de voyageurs et/ou les notes
        $trip_id   = intval($data['trip_id']);
        $uid       = intval($data['user_id']);
        $travelers = intval($data['travelers'] ?? 1);
        $notes     = $data['notes'] ?? '';

        $upd  = mysqli_prepare($conn, "UPDATE trips SET travelers=?, notes=?, updated_at=NOW() WHERE id=? AND user_id=? AND status='confirmed'");
        mysqli_stmt_bind_param($upd, "isii", $travelers, $notes, $trip_id, $uid);
        if (mysqli_stmt_execute($upd)) {
            // Récupérer le voyage
            $sel  = mysqli_prepare($conn, "SELECT t.reference_code, d.name AS dest FROM trips t JOIN destinations d ON t.destination_id=d.id WHERE t.id=?");
            mysqli_stmt_bind_param($sel, "i", $trip_id);
            mysqli_stmt_execute($sel);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));

            createNotification($conn, $uid, 'modification',
                "Réservation modifiée",
                "Votre réservation (réf. {$row['reference_code']}) pour {$row['dest']} a été mise à jour."
            );
            echo json_encode(["status" => "success", "message" => "Réservation mise à jour."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Modification échouée."]);
        }

    } elseif ($action === 'delete') {
        // Suppression définitive (admin only)
        $trip_id = intval($data['trip_id']);
        $sql     = "DELETE FROM trips WHERE id=?";
        $stmt    = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $trip_id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Itinéraire supprimé."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Suppression échouée."]);
        }
    }
}
?>
