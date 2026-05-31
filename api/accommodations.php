<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$dest_id = isset($_GET['destination_id']) ? intval($_GET['destination_id']) : 0;
 
// ── LIST ACCOMMODATIONS ────────────────────────────────
if ($method === 'GET') {
    if ($dest_id === 0) {
        echo json_encode([]);
        exit();
    } 
        
    $conditions = ["destination_id = ?", "is_active = 1"];
    $params     = [$dest_id];
    $types      = "i";

    // Filtre type
    if (!empty($_GET['type'])) {
        $conditions[] = "type = ?";
        $params[]     = $_GET['type'];
        $types       .= "s";
    }

    // Filtre étoiles min
    if (!empty($_GET['min_stars'])) {
        $conditions[] = "stars >= ?";
        $params[]     = intval($_GET['min_stars']);
        $types       .= "i";
    }

    // Filtre prix max par nuit
    if (!empty($_GET['max_price'])) {
        $conditions[] = "price_per_night <= ?";
        $params[]     = intval($_GET['max_price']);
        $types       .= "i";
    }

    // Filtre annulation gratuite
    if (isset($_GET['free_cancellation']) && $_GET['free_cancellation'] === '1') {
        $conditions[] = "free_cancellation = 1";
    }

    // Filtre disponibilité
    if (isset($_GET['available_only']) && $_GET['available_only'] === '1') {
        $conditions[] = "rooms_left > 0";
    }

    $sort_map = [
        'price_asc'   => 'price_per_night ASC',
        'price_desc'  => 'price_per_night DESC',
        'rating_desc' => 'rating DESC',
        'stars_desc'  => 'stars DESC',
    ];
    $sort  = isset($_GET['sort']) && isset($sort_map[$_GET['sort']]) ? $sort_map[$_GET['sort']] : 'stars DESC';
    $where = implode(" AND ", $conditions);
    $sql   = "SELECT *, (rooms_left * 100 / rooms_total) AS availability_pct FROM accommodations WHERE $where ORDER BY $sort";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result         = mysqli_stmt_get_result($stmt);
    $accommodations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $accommodations[] = $row;
    }
    echo json_encode($accommodations);

// ── ADD ACCOMMODATION (admin/prestataire) ──────────────
} elseif ($method === 'POST') {
    $data        = json_decode(file_get_contents("php://input"), true);
    $sql         = "INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, rooms_total, free_cancellation)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt        = mysqli_prepare($conn, $sql);
    $dest        = intval($data['destination_id']);
    $name        = $data['name'];
    $stars       = intval($data['stars'] ?? 3);
    $type        = $data['type'] ?? 'Hôtel';
    $ppn         = intval($data['price_per_night']);
    $rating      = floatval($data['rating'] ?? 4.0);
    $amenities   = $data['amenities'] ?? '';
    $image_url   = $data['image_url'] ?? 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400';
    $rooms_left  = intval($data['rooms_left'] ?? 10);
    $rooms_total = $rooms_left;
    $free_cancel = intval($data['free_cancellation'] ?? 1);

    mysqli_stmt_bind_param($stmt, "isisisssiii",
        $dest, $name, $stars, $type, $ppn, $rating, $amenities, $image_url, $rooms_left, $rooms_total, $free_cancel
    );
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "id" => mysqli_insert_id($conn)]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur insertion hébergement."]);
    }
 
// ── DELETE ACCOMMODATION (admin) ───────────────────────
} elseif ($method === 'DELETE') {
    $id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $sql  = "UPDATE accommodations SET is_active=0 WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo json_encode(["status" => "success"]);
}
?>
