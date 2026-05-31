<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// ── LIST / SEARCH / FILTER ─────────────────────────────
if ($method === 'GET') {
    $conditions = ["is_active = 1"];
    $params     = [];
    $types      = "";

    // Recherche textuelle
    if (!empty($_GET['search'])) {
        $search       = "%" . $_GET['search'] . "%";
        $conditions[] = "(name LIKE ? OR country LIKE ? OR description LIKE ? OR tag LIKE ?)";
        $params[]     = $search; $params[] = $search;
        $params[]     = $search; $params[] = $search;
        $types       .= "ssss";
    }

    // Filtre par tag/catégorie
    if (!empty($_GET['tag'])) {
        $conditions[] = "tag = ?";
        $params[]     = $_GET['tag'];
        $types       .= "s";
    }

    // Filtre prix max
    if (!empty($_GET['max_price'])) {
        $conditions[] = "price <= ?";
        $params[]     = intval($_GET['max_price']);
        $types       .= "i";
    }

    // Filtre prix min
    if (!empty($_GET['min_price'])) {
        $conditions[] = "price >= ?";
        $params[]     = intval($_GET['min_price']);
        $types       .= "i";
    }

    // Filtre continent
    if (!empty($_GET['continent'])) {
        $conditions[] = "continent = ?";
        $params[]     = $_GET['continent'];
        $types       .= "s";
    }

    // Tri
    $sort_map = [
        'price_asc'   => 'price ASC',
        'price_desc'  => 'price DESC',
        'rating_desc' => 'rating DESC',
        'name_asc'    => 'name ASC',
        'duration'    => 'duration_days ASC',
    ];
    $sort = isset($_GET['sort']) && isset($sort_map[$_GET['sort']]) ? $sort_map[$_GET['sort']] : 'id ASC';

    $where = implode(" AND ", $conditions);
    $sql   = "SELECT * FROM destinations WHERE $where ORDER BY $sort";

    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $sql);
    }

    $destinations = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $destinations[] = $row;
    }
    echo json_encode($destinations);

// ── ADD DESTINATION (admin/prestataire) ───────────────
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $required = ['name', 'country', 'price'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Champ requis manquant: $field"]);
            exit();
        }
    }

    $sql  = "INSERT INTO destinations (name, country, description, tag, price, image_url, rating, duration_days, continent, language, currency, best_season, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $name        = $data['name'];
    $country     = $data['country'];
    $description = isset($data['description'])  ? $data['description']  : '';
    $tag         = isset($data['tag'])           ? $data['tag']          : 'Autre';
    $price       = intval($data['price']);
    $image_url   = isset($data['image_url'])     ? $data['image_url']    : 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=600';
    $rating      = isset($data['rating'])        ? floatval($data['rating']) : 4.0;
    $duration    = isset($data['duration_days']) ? intval($data['duration_days']) : 7;
    $continent   = isset($data['continent'])     ? $data['continent']    : '';
    $language    = isset($data['language'])      ? $data['language']     : '';
    $currency    = isset($data['currency'])      ? $data['currency']     : 'EUR';
    $best_season = isset($data['best_season'])   ? $data['best_season']  : '';
    $created_by  = isset($data['created_by'])    ? intval($data['created_by']) : 1;

    mysqli_stmt_bind_param($stmt, "ssssissssssi",
        $name, $country, $description, $tag, $price, $image_url,
        $rating, $duration, $continent, $language, $currency, $best_season, $created_by
    );
    // fix: use correct types string
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssisssisssi",
        $name, $country, $description, $tag, $price, $image_url,
        $rating, $duration, $continent, $language, $currency, $best_season, $created_by
    );

    if (mysqli_stmt_execute($stmt)) {
        $new_id = mysqli_insert_id($conn);
        echo json_encode(["status" => "success", "message" => "Destination ajoutée.", "id" => $new_id]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Erreur lors de l'ajout."]);
    }

// ── DELETE DESTINATION (admin) ─────────────────────────
} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        // Soft delete
        $sql  = "UPDATE destinations SET is_active=0 WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Destination supprimée."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Suppression échouée."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID manquant."]);
    }
}
?>
