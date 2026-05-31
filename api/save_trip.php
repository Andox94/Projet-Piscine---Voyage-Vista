<?php
require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['destination_id']) || !isset($data['client_name'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Données obligatoires manquantes."]);
    exit();
}

mysqli_begin_transaction($conn);

try {
    $user_id      = isset($data['user_id']) && $data['user_id'] ? intval($data['user_id']) : 0;
    if ($user_id <= 0) {
        throw new Exception("Utilisateur non identifié. Veuillez vous connecter.");
    }
    // Vérifier que l'utilisateur existe réellement
    $u_check = mysqli_prepare($conn, "SELECT id FROM users WHERE id=?");
    mysqli_stmt_bind_param($u_check, "i", $user_id);
    mysqli_stmt_execute($u_check);
    mysqli_stmt_store_result($u_check);
    if (mysqli_stmt_num_rows($u_check) === 0) {
        throw new Exception("Compte utilisateur introuvable (id={$user_id}). Reconnectez-vous.");
    }
    $dest_id      = intval($data['destination_id']);
    // Use NULL or valid int for nullable FK columns
    $trans_id     = (!empty($data['transport_id']))     ? intval($data['transport_id'])     : null;
    $accom_id     = (!empty($data['accommodation_id'])) ? intval($data['accommodation_id']) : null;
    $name         = $data['client_name'];
    $email        = $data['client_email'] ?? '';
    $total        = intval($data['total_price']);
    $nights       = intval($data['nights'] ?? 3);
    $travelers    = intval($data['travelers'] ?? 1);
    // DATE columns: must be 'YYYY-MM-DD' or NULL
    $dep_date     = (!empty($data['departure_date']) && strlen($data['departure_date']) >= 8) ? $data['departure_date'] : null;
    $ret_date     = (!empty($data['return_date'])    && strlen($data['return_date']) >= 8)    ? $data['return_date']    : null;
    $payment_meth = $data['payment_method'] ?? 'carte';
    $notes        = $data['notes']          ?? null;
    $origin_city  = $data['origin_city']    ?? 'Paris';
    $ref_code     = 'VV-' . strtoupper(substr(uniqid(), -6));

    // ── Vérifier les dates ────────────────────────────
    if ($dep_date && $ret_date) {
        $dep = new DateTime($dep_date);
        $ret = new DateTime($ret_date);
        if ($ret <= $dep) {
            throw new Exception("La date de retour doit être postérieure à la date de départ.");
        }
    }

    // ── Vérifier transport ────────────────────────────
    if ($trans_id) {
        $check = mysqli_prepare($conn, "SELECT seats_left FROM transports WHERE id=? AND is_active=1");
        mysqli_stmt_bind_param($check, "i", $trans_id);
        mysqli_stmt_execute($check);
        $t_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        if (!$t_row) throw new Exception("Transport introuvable.");
        if ($t_row['seats_left'] < $travelers) {
            throw new Exception("Plus assez de places ({$t_row['seats_left']} restantes).");
        }
    }

    // ── Vérifier hébergement ──────────────────────────
    if ($accom_id) {
        $check = mysqli_prepare($conn, "SELECT rooms_left FROM accommodations WHERE id=? AND is_active=1");
        mysqli_stmt_bind_param($check, "i", $accom_id);
        mysqli_stmt_execute($check);
        $a_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        if (!$a_row) throw new Exception("Hébergement introuvable.");
        if ($a_row['rooms_left'] < 1) throw new Exception("Hébergement complet.");
    }

    // ── Vérifier activités ────────────────────────────
    if (!empty($data['activities'])) {
        foreach ($data['activities'] as $act_id) {
            $aid = intval($act_id);
            $check = mysqli_prepare($conn, "SELECT name, max_participants, current_participants FROM activities WHERE id=? AND is_active=1");
            mysqli_stmt_bind_param($check, "i", $aid);
            mysqli_stmt_execute($check);
            $act_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            if (!$act_row) continue;
            $spots = $act_row['max_participants'] - $act_row['current_participants'];
            if ($spots < $travelers) {
                throw new Exception("Activité \"{$act_row['name']}\" : seulement {$spots} place(s) disponible(s).");
            }
        }
    }

    // ── Insérer le voyage ─────────────────────────────
    // Use separate handling for nullable INT columns to avoid PHP mysqli NULL binding issues
    $sql = "INSERT INTO trips (user_id, destination_id, transport_id, accommodation_id, client_name, client_email, origin_city,
            travelers, departure_date, return_date, nights, total_price, reference_code, payment_method, notes,
            status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'paid')";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("Prepare failed: " . mysqli_error($conn));

    // Bind nullable ints as strings ('s') — MySQL correctly converts NULL strings to NULL for INT columns
    mysqli_stmt_bind_param($stmt, "iisssssissiisss",
        $user_id,   // i
        $dest_id,   // i
        $trans_id,  // s (nullable INT → bind as 's' so NULL passes correctly)
        $accom_id,  // s (nullable INT → bind as 's')
        $name,      // s
        $email,     // s
        $origin_city, // s
        $travelers, // i
        $dep_date,  // s (DATE or NULL)
        $ret_date,  // s (DATE or NULL)
        $nights,    // i
        $total,     // i
        $ref_code,  // s
        $payment_meth, // s
        $notes      // s
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur insertion voyage: " . mysqli_stmt_error($stmt));
    }
    $trip_id = mysqli_insert_id($conn);

    // ── Lier les activités ────────────────────────────
    if (!empty($data['activities'])) {
        $sql_act  = "INSERT INTO trip_activities (trip_id, activity_id, quantity) VALUES (?, ?, ?)";
        $stmt_act = mysqli_prepare($conn, $sql_act);
        foreach ($data['activities'] as $act_id) {
            $aid = intval($act_id);
            mysqli_stmt_bind_param($stmt_act, "iii", $trip_id, $aid, $travelers);
            mysqli_stmt_execute($stmt_act);
        }
    }

    // ── Mettre à jour les disponibilités ──────────────
    if ($trans_id) {
        $upd = mysqli_prepare($conn, "UPDATE transports SET seats_left = seats_left - ? WHERE id=?");
        mysqli_stmt_bind_param($upd, "ii", $travelers, $trans_id);
        mysqli_stmt_execute($upd);
    }
    if ($accom_id) {
        $upd = mysqli_prepare($conn, "UPDATE accommodations SET rooms_left = rooms_left - 1 WHERE id=?");
        mysqli_stmt_bind_param($upd, "i", $accom_id);
        mysqli_stmt_execute($upd);
    }
    if (!empty($data['activities'])) {
        foreach ($data['activities'] as $act_id) {
            $aid = intval($act_id);
            $upd = mysqli_prepare($conn, "UPDATE activities SET current_participants = current_participants + ? WHERE id=?");
            mysqli_stmt_bind_param($upd, "ii", $travelers, $aid);
            mysqli_stmt_execute($upd);
        }
    }

    // ── Notification ──────────────────────────────────
    $dest_q = mysqli_prepare($conn, "SELECT name FROM destinations WHERE id=?");
    mysqli_stmt_bind_param($dest_q, "i", $dest_id);
    mysqli_stmt_execute($dest_q);
    $dest_row  = mysqli_fetch_assoc(mysqli_stmt_get_result($dest_q));
    $dest_name = $dest_row ? $dest_row['name'] : 'destination';

    createNotification($conn, $user_id, 'reservation',
        "Réservation confirmée — {$dest_name}",
        "Voyage vers {$dest_name} confirmé ! Réf : {$ref_code}. Total : {$total} €."
    );

    mysqli_commit($conn);
    echo json_encode([
        "status"   => "success",
        "message"  => "Réservation enregistrée avec succès !",
        "ref_code" => $ref_code,
        "trip_id"  => $trip_id
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
