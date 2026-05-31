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
    $user_id      = isset($data['user_id'])         ? intval($data['user_id'])         : 2;
    $dest_id      = intval($data['destination_id']);
    $trans_id     = isset($data['transport_id'])     ? intval($data['transport_id'])     : null;
    $accom_id     = isset($data['accommodation_id']) ? intval($data['accommodation_id']) : null;
    $name         = $data['client_name'];
    $email        = $data['client_email'] ?? '';
    $total        = intval($data['total_price']);
    $nights       = intval($data['nights'] ?? 3);
    $travelers    = intval($data['travelers'] ?? 1);
    $dep_date     = !empty($data['departure_date'])  ? $data['departure_date']  : null;
    $ret_date     = !empty($data['return_date'])     ? $data['return_date']     : null;
    $payment_meth = $data['payment_method']          ?? 'carte';
    $notes        = $data['notes']                   ?? null;
    $ref_code     = 'VV-' . strtoupper(substr(uniqid(), -6));

    // ── Vérifier les dates ────────────────────────────
    if ($dep_date && $ret_date) {
        $dep = new DateTime($dep_date);
        $ret = new DateTime($ret_date);
        if ($ret <= $dep) {
            throw new Exception("La date de retour doit être postérieure à la date de départ.");
        }
    }

    // ── Vérifier la disponibilité du transport ────────
    if ($trans_id) {
        $check = mysqli_prepare($conn, "SELECT seats_left FROM transports WHERE id=? AND is_active=1");
        mysqli_stmt_bind_param($check, "i", $trans_id);
        mysqli_stmt_execute($check);
        $t_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        if (!$t_row) throw new Exception("Transport introuvable.");
        if ($t_row['seats_left'] < $travelers) {
            throw new Exception("Plus assez de places disponibles pour ce transport ({$t_row['seats_left']} places restantes).");
        }
    }

    // ── Vérifier la disponibilité de l'hébergement ────
    if ($accom_id) {
        $check = mysqli_prepare($conn, "SELECT rooms_left FROM accommodations WHERE id=? AND is_active=1");
        mysqli_stmt_bind_param($check, "i", $accom_id);
        mysqli_stmt_execute($check);
        $a_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
        if (!$a_row) throw new Exception("Hébergement introuvable.");
        if ($a_row['rooms_left'] < 1) {
            throw new Exception("Cet hébergement n'a plus de chambres disponibles.");
        }
    }

    // ── Vérifier la capacité des activités ────────────
    if (!empty($data['activities'])) {
        foreach ($data['activities'] as $act_id) {
            $check = mysqli_prepare($conn, "SELECT name, max_participants, current_participants FROM activities WHERE id=? AND is_active=1");
            mysqli_stmt_bind_param($check, "i", $act_id);
            mysqli_stmt_execute($check);
            $act_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            if (!$act_row) continue;
            $spots_left = $act_row['max_participants'] - $act_row['current_participants'];
            if ($spots_left < $travelers) {
                throw new Exception("L'activité \"{$act_row['name']}\" n'a plus assez de places ({$spots_left} restante(s)).");
            }
        }
    }

    // ── Insérer le voyage ─────────────────────────────
    $sql  = "INSERT INTO trips (user_id, destination_id, transport_id, accommodation_id, client_name, client_email,
             travelers, departure_date, return_date, nights, total_price, reference_code, payment_method, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iiiisssissisis",
        $user_id, $dest_id, $trans_id, $accom_id, $name, $email,
        $travelers, $dep_date, $ret_date, $nights, $total, $ref_code, $payment_meth, $notes
    );
    mysqli_stmt_execute($stmt);
    $trip_id = mysqli_insert_id($conn);

    // ── Lier les activités ────────────────────────────
    if (!empty($data['activities'])) {
        $sql_act = "INSERT INTO trip_activities (trip_id, activity_id, quantity) VALUES (?, ?, ?)";
        $stmt_act = mysqli_prepare($conn, $sql_act);
        foreach ($data['activities'] as $act_id) {
            $act_id_int = intval($act_id);
            mysqli_stmt_bind_param($stmt_act, "iii", $trip_id, $act_id_int, $travelers);
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
            $upd = mysqli_prepare($conn, "UPDATE activities SET current_participants = current_participants + ? WHERE id=?");
            mysqli_stmt_bind_param($upd, "ii", $travelers, intval($act_id));
            mysqli_stmt_execute($upd);
        }
    }

    // ── Récupérer le nom de la destination ────────────
    $dest_q = mysqli_prepare($conn, "SELECT name FROM destinations WHERE id=?");
    mysqli_stmt_bind_param($dest_q, "i", $dest_id);
    mysqli_stmt_execute($dest_q);
    $dest_row  = mysqli_fetch_assoc(mysqli_stmt_get_result($dest_q));
    $dest_name = $dest_row ? $dest_row['name'] : 'destination';

    // ── Créer la notification ─────────────────────────
    createNotification($conn, $user_id, 'reservation',
        "Réservation confirmée — {$dest_name}",
        "Votre voyage vers {$dest_name} est confirmé ! Code de référence : {$ref_code}. Total : {$total} €."
    );

    mysqli_commit($conn);
    echo json_encode([
        "status"     => "success",
        "message"    => "Réservation enregistrée avec succès !",
        "ref_code"   => $ref_code,
        "trip_id"    => $trip_id
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
