<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// ── LIST TRIPS ─────────────────────────────────────────
if ($method === 'GET' && !isset($_GET['action'])) {
    if ($user_id === 0) { echo json_encode([]); exit(); }
    $sql  = "SELECT t.*, d.name AS destination_name, d.country, d.image_url AS destination_image,
                    tr.type AS transport_type, tr.company AS transport_company,
                    tr.departure_time, tr.arrival_time, tr.duration AS transport_duration,
                    ac.name AS accommodation_name, ac.type AS accommodation_type,
                    ac.price_per_night AS accommodation_price_per_night
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
        $sql_act  = "SELECT a.id, a.name, a.category, a.price_per_person, ta.quantity
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
    // Fetch companion info for each trip
    $comp_stmt = mysqli_prepare($conn,
        "SELECT u.id, u.name, u.email FROM trip_companions tc JOIN users u ON tc.companion_user_id=u.id WHERE tc.trip_id=?"
    );
    foreach ($trips as &$trip) {
        mysqli_stmt_bind_param($comp_stmt, "i", $trip['id']);
        mysqli_stmt_execute($comp_stmt);
        $comp_res = mysqli_stmt_get_result($comp_stmt);
        $trip['companions'] = [];
        while ($cr = mysqli_fetch_assoc($comp_res)) {
            $trip['companions'][] = $cr;
        }
    }
    unset($trip);

    // Also fetch trips where user is a companion (shared trips)
    $shared_sql = "SELECT t.*, d.name AS destination_name, d.country, d.image_url AS destination_image,
                          tr.type AS transport_type, tr.company AS transport_company,
                          tr.departure_time, tr.arrival_time, tr.duration AS transport_duration,
                          ac.name AS accommodation_name, ac.type AS accommodation_type,
                          ac.price_per_night AS accommodation_price_per_night,
                          u.name AS owner_name
                   FROM trip_companions tc
                   JOIN trips t ON tc.trip_id = t.id
                   JOIN destinations d ON t.destination_id = d.id
                   LEFT JOIN transports tr ON t.transport_id = tr.id
                   LEFT JOIN accommodations ac ON t.accommodation_id = ac.id
                   JOIN users u ON t.user_id = u.id
                   WHERE tc.companion_user_id = ?
                   ORDER BY t.created_at DESC";
    $shared_stmt = mysqli_prepare($conn, $shared_sql);
    mysqli_stmt_bind_param($shared_stmt, "i", $user_id);
    mysqli_stmt_execute($shared_stmt);
    $shared_res = mysqli_stmt_get_result($shared_stmt);
    $shared_trips = [];
    while ($srow = mysqli_fetch_assoc($shared_res)) {
        $srow['is_shared']   = true;
        $srow['activities']  = [];
        $srow['companions']  = [];
        $shared_trips[] = $srow;
    }

    echo json_encode(array_merge($trips, $shared_trips));

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

// ── POST ACTIONS ───────────────────────────────────────
} elseif ($method === 'POST') {
    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    // ── CANCEL ────────────────────────────────────────
    if ($action === 'cancel') {
        $trip_id = intval($data['trip_id']);
        $uid     = intval($data['user_id']);

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
            $upd  = mysqli_prepare($conn, "UPDATE trips SET status='cancelled', updated_at=NOW() WHERE id=?");
            mysqli_stmt_bind_param($upd, "i", $trip_id);
            mysqli_stmt_execute($upd);

            if ($trip['transport_id']) {
                $upd = mysqli_prepare($conn, "UPDATE transports SET seats_left = seats_left + ? WHERE id=?");
                mysqli_stmt_bind_param($upd, "ii", $trip['travelers'], $trip['transport_id']);
                mysqli_stmt_execute($upd);
            }

            if ($trip['accommodation_id']) {
                $upd = mysqli_prepare($conn, "UPDATE accommodations SET rooms_left = rooms_left + 1 WHERE id=?");
                mysqli_stmt_bind_param($upd, "i", $trip['accommodation_id']);
                mysqli_stmt_execute($upd);
            }

            $acts_q  = mysqli_prepare($conn, "SELECT activity_id, quantity FROM trip_activities WHERE trip_id=?");
            mysqli_stmt_bind_param($acts_q, "i", $trip_id);
            mysqli_stmt_execute($acts_q);
            $acts_res = mysqli_stmt_get_result($acts_q);
            while ($act_row = mysqli_fetch_assoc($acts_res)) {
                $upd = mysqli_prepare($conn, "UPDATE activities SET current_participants = current_participants - ? WHERE id=?");
                mysqli_stmt_bind_param($upd, "ii", $act_row['quantity'], $act_row['activity_id']);
                mysqli_stmt_execute($upd);
            }

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
            echo json_encode(["status" => "success", "message" => "Réservation annulée avec succès."]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }

    // ── UPDATE ────────────────────────────────────────
    } elseif ($action === 'update') {
        $trip_id   = intval($data['trip_id']);
        $uid       = intval($data['user_id']);
        $travelers = intval($data['travelers'] ?? 1);
        $notes     = $data['notes'] ?? '';
        $dep_date  = (!empty($data['departure_date']) && strlen($data['departure_date']) >= 8) ? $data['departure_date'] : null;
        $ret_date  = (!empty($data['return_date'])    && strlen($data['return_date']) >= 8)    ? $data['return_date']    : null;
        $nights    = isset($data['nights']) ? intval($data['nights']) : null;

        // Validate dates
        if ($dep_date && $ret_date) {
            $dep = new DateTime($dep_date);
            $ret = new DateTime($ret_date);
            if ($ret <= $dep) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "La date de retour doit être postérieure à la date de départ."]);
                exit();
            }
            // Auto-compute nights from date delta
            $nights = (int)$dep->diff($ret)->days;
        }
        if (!$nights || $nights < 1) $nights = 1;

        // ── Recalculate total price ───────────────────
        $price_q = mysqli_prepare($conn,
            "SELECT d.price AS base_price,
                    tr.price AS transport_price,
                    ac.price_per_night
             FROM trips t
             JOIN destinations d ON t.destination_id = d.id
             LEFT JOIN transports tr ON t.transport_id = tr.id
             LEFT JOIN accommodations ac ON t.accommodation_id = ac.id
             WHERE t.id = ? AND t.user_id = ? AND t.status = 'confirmed'"
        );
        mysqli_stmt_bind_param($price_q, "ii", $trip_id, $uid);
        mysqli_stmt_execute($price_q);
        $price_row = mysqli_fetch_assoc(mysqli_stmt_get_result($price_q));

        if (!$price_row) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Voyage introuvable ou déjà annulé."]);
            exit();
        }

        $base_price      = intval($price_row['base_price']);
        $transport_price = intval($price_row['transport_price'] ?? 0);
        $price_per_night = intval($price_row['price_per_night'] ?? 0);

        // Activities total
        $acts_q = mysqli_prepare($conn,
            "SELECT SUM(a.price_per_person) AS acts_total
             FROM trip_activities ta JOIN activities a ON ta.activity_id = a.id
             WHERE ta.trip_id = ?"
        );
        mysqli_stmt_bind_param($acts_q, "i", $trip_id);
        mysqli_stmt_execute($acts_q);
        $acts_row  = mysqli_fetch_assoc(mysqli_stmt_get_result($acts_q));
        $acts_unit = intval($acts_row['acts_total'] ?? 0);

        $new_total = $base_price
                   + ($transport_price * $travelers)
                   + ($price_per_night * $nights)
                   + ($acts_unit * $travelers);

        $upd = mysqli_prepare($conn,
            "UPDATE trips SET travelers=?, notes=?, departure_date=?, return_date=?, nights=?, total_price=?, updated_at=NOW()
             WHERE id=? AND user_id=? AND status='confirmed'"
        );
        mysqli_stmt_bind_param($upd, "isssiiiii", $travelers, $notes, $dep_date, $ret_date, $nights, $new_total, $trip_id, $uid);

        if (mysqli_stmt_execute($upd)) {
            $sel  = mysqli_prepare($conn, "SELECT t.reference_code, d.name AS dest FROM trips t JOIN destinations d ON t.destination_id=d.id WHERE t.id=?");
            mysqli_stmt_bind_param($sel, "i", $trip_id);
            mysqli_stmt_execute($sel);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));

            if ($row) {
                createNotification($conn, $uid, 'modification',
                    "Réservation modifiée",
                    "Réservation (réf. {$row['reference_code']}) pour {$row['dest']} mise à jour — {$travelers} voyageur(s), {$nights} nuit(s), total : {$new_total} €."
                );
            }
            echo json_encode(["status" => "success", "message" => "Réservation mise à jour avec succès.", "new_total" => $new_total, "new_nights" => $nights]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Modification échouée: " . mysqli_error($conn)]);
        }

    // ── DELETE ────────────────────────────────────────
    } elseif ($action === 'delete') {
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
