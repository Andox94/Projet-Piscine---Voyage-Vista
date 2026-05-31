<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$dest_id = isset($_GET['destination_id']) ? intval($_GET['destination_id']) : 0;

// ── LIST TRANSPORTS (with optional filters) ────────────
if ($method === 'GET' && !isset($_GET['action'])) {
    if ($dest_id === 0) {
        echo json_encode([]);
        exit();
    }

    $conditions = ["destination_id = ?", "is_active = 1"];
    $params     = [$dest_id];
    $types      = "i";

    // Filtre classe
    if (!empty($_GET['class'])) {
        $conditions[] = "class = ?";
        $params[]     = $_GET['class'];
        $types       .= "s";
    }

    // Filtre prix max
    if (!empty($_GET['max_price'])) {
        $conditions[] = "price <= ?";
        $params[]     = intval($_GET['max_price']);
        $types       .= "i";
    }

    // Filtre places disponibles min (au moins N places)
    $travelers = isset($_GET['travelers']) ? intval($_GET['travelers']) : 1;
    if ($travelers > 0) {
        $conditions[] = "seats_left >= ?";
        $params[]     = $travelers;
        $types       .= "i";
    }

    $sort_map = [
        'price_asc'  => 'price ASC',
        'price_desc' => 'price DESC',
        'duration'   => 'duration ASC',
    ];
    $sort  = isset($_GET['sort']) && isset($sort_map[$_GET['sort']]) ? $sort_map[$_GET['sort']] : 'price ASC';
    $where = implode(" AND ", $conditions);
    $sql   = "SELECT *, (seats_left * 100 / seats_total) AS availability_pct FROM transports WHERE $where ORDER BY $sort";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $transports = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $transports[] = $row;
    }
    echo json_encode($transports);

// ── ADD TRANSPORT (admin/prestataire) ──────────────────
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $sql  = "INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, seats_total, price, class)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $destination_id  = intval($data['destination_id']);
    $type            = $data['type'];
    $company         = isset($data['company']) ? $data['company'] : '';
    $departure_time  = isset($data['departure_time']) ? $data['departure_time'] : '';
    $arrival_time    = isset($data['arrival_time'])   ? $data['arrival_time']   : '';
    $duration        = isset($data['duration'])       ? $data['duration']       : '';
    $stops           = isset($data['stops'])          ? intval($data['stops'])  : 0;
    $seats           = isset($data['seats_left'])     ? intval($data['seats_left']) : 50;
    $seats_total     = $seats;
    $price           = intval($data['price']);
    $class           = isset($data['class'])          ? $data['class']          : 'Économique';

    mysqli_stmt_bind_param($stmt, "isssssiiiis",
        $destination_id, $type, $company, $departure_time, $arrival_time,
        $duration, $stops, $seats, $seats_total, $price, $class
    );
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "id" => mysqli_insert_id($conn)]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur insertion transport."]);
    }

// ── DELETE TRANSPORT (admin) ───────────────────────────
} elseif ($method === 'DELETE') {
    $id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $sql  = "UPDATE transports SET is_active=0 WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo json_encode(["status" => "success"]);
}
?>
