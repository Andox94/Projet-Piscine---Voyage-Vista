<?php
require_once 'config.php';

$method  = $_SERVER['REQUEST_METHOD'];
$dest_id = isset($_GET['destination_id']) ? intval($_GET['destination_id']) : 0;

// ── LIST ACTIVITIES ────────────────────────────────────
if ($method === 'GET') {
    if ($dest_id === 0) {
        echo json_encode([]);
        exit();
    }

    $conditions = ["destination_id = ?", "is_active = 1"];
    $params     = [$dest_id];
    $types      = "i";

    // Filtre catégorie
    if (!empty($_GET['category'])) {
        $conditions[] = "category = ?";
        $params[]     = $_GET['category'];
        $types       .= "s";
    }

    // Filtre difficulté
    if (!empty($_GET['difficulty'])) {
        $conditions[] = "difficulty = ?";
        $params[]     = $_GET['difficulty'];
        $types       .= "s";
    }

    // Filtre prix max
    if (!empty($_GET['max_price'])) {
        $conditions[] = "price_per_person <= ?";
        $params[]     = intval($_GET['max_price']);
        $types       .= "i";
    }

    // Filtre nombre de voyageurs (vérifier capacité restante)
    $travelers = isset($_GET['travelers']) ? intval($_GET['travelers']) : 1;

    $sort_map = [
        'price_asc'  => 'price_per_person ASC',
        'price_desc' => 'price_per_person DESC',
        'popular'    => 'current_participants DESC',
    ];
    $sort  = isset($_GET['sort']) && isset($sort_map[$_GET['sort']]) ? $sort_map[$_GET['sort']] : 'id ASC';
    $where = implode(" AND ", $conditions);
    $sql   = "SELECT *, (max_participants - current_participants) AS spots_left FROM activities WHERE $where ORDER BY $sort";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result     = mysqli_stmt_get_result($stmt);
    $activities = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_full']    = ($row['spots_left'] <= 0);
        $row['has_enough'] = ($row['spots_left'] >= $travelers);
        $activities[]      = $row;
    }
    echo json_encode($activities);

// ── ADD ACTIVITY (admin/prestataire) ───────────────────
} elseif ($method === 'POST') {
    $data     = json_decode(file_get_contents("php://input"), true);
    $sql      = "INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, difficulty, includes, image_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt     = mysqli_prepare($conn, $sql);
    $dest     = intval($data['destination_id']);
    $name     = $data['name'];
    $category = $data['category'] ?? 'Autre';
    $desc     = $data['description'] ?? '';
    $duration = $data['duration'] ?? '2h';
    $price    = intval($data['price_per_person']);
    $max_p    = intval($data['max_participants'] ?? 20);
    $diff     = $data['difficulty'] ?? 'Facile';
    $includes = $data['includes'] ?? '';
    $img      = $data['image_url'] ?? '';

    mysqli_stmt_bind_param($stmt, "issssiisss",
        $dest, $name, $category, $desc, $duration, $price, $max_p, $diff, $includes, $img
    );
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(["status" => "success", "id" => mysqli_insert_id($conn)]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur insertion activité."]);
    }

// ── DELETE ACTIVITY ────────────────────────────────────
} elseif ($method === 'DELETE') {
    $id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $sql  = "UPDATE activities SET is_active=0 WHERE id=?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    echo json_encode(["status" => "success"]);
}
?>
