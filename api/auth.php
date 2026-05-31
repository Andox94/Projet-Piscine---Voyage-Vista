<?php
require_once 'config.php';

$data = json_decode(file_get_contents("php://input"), true);
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ── LOGIN ──────────────────────────────────────────────
if ($action === 'login') {
    if (!empty($data['email']) && !empty($data['password'])) {
        $email = $data['email'];
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            echo json_encode([
                "status" => "success",
                "user" => [
                    "id"        => $user['id'],
                    "name"      => $user['name'],
                    "email"     => $user['email'],
                    "role"      => $user['role'],
                    "phone"     => $user['phone'],
                    "bio"       => $user['bio'],
                    "avatar_url"=> $user['avatar_url'],
                    "created_at"=> $user['created_at']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Identifiants invalides."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Email et mot de passe requis."]);
    }

// ── REGISTER ───────────────────────────────────────────
} elseif ($action === 'register') {
    if (!empty($data['name']) && !empty($data['email']) && !empty($data['password'])) {
        $name          = $data['name'];
        $email         = $data['email'];
        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role          = isset($data['role']) ? $data['role'] : 'client';
        $phone         = isset($data['phone']) ? $data['phone'] : null;

        // Vérifier si l'email existe déjà
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Cet email est déjà utilisé."]);
            exit();
        }

        $sql  = "INSERT INTO users (name, email, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $password_hash, $role, $phone);

        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            // Notification de bienvenue
            createNotification($conn, $new_id, 'systeme',
                'Bienvenue sur VoyageVista !',
                'Votre compte a été créé avec succès. Explorez nos destinations et composez votre voyage idéal.');
            echo json_encode(["status" => "success", "message" => "Compte créé avec succès !", "user_id" => $new_id]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Impossible de créer le compte."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Données obligatoires manquantes."]);
    }

// ── UPDATE PROFILE ─────────────────────────────────────
} elseif ($action === 'update') {
    if (!empty($data['id'])) {
        $id    = intval($data['id']);
        $name  = isset($data['name'])  ? $data['name']  : null;
        $phone = isset($data['phone']) ? $data['phone'] : null;
        $bio   = isset($data['bio'])   ? $data['bio']   : null;

        // Mise à jour du mot de passe si fourni
        if (!empty($data['password'])) {
            $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
            $sql  = "UPDATE users SET name=?, phone=?, bio=?, password_hash=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $phone, $bio, $password_hash, $id);
        } else {
            $sql  = "UPDATE users SET name=?, phone=?, bio=? WHERE id=?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $name, $phone, $bio, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            // Récupérer le profil mis à jour
            $sel  = mysqli_prepare($conn, "SELECT id,name,email,role,phone,bio,avatar_url,created_at FROM users WHERE id=?");
            mysqli_stmt_bind_param($sel, "i", $id);
            mysqli_stmt_execute($sel);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($sel));
            echo json_encode(["status" => "success", "user" => $user]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Mise à jour échouée."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID utilisateur manquant."]);
    }

// ── GET PROFILE ────────────────────────────────────────
} elseif ($action === 'profile') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        $sql  = "SELECT id,name,email,role,phone,bio,avatar_url,created_at FROM users WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($user) {
            echo json_encode(["status" => "success", "user" => $user]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Utilisateur introuvable."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID manquant."]);
    }

// ── LIST ALL USERS (admin) ─────────────────────────────
} elseif ($action === 'list') {
    $sql    = "SELECT id,name,email,role,phone,created_at FROM users ORDER BY id ASC";
    $result = mysqli_query($conn, $sql);
    $users  = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    echo json_encode($users);

// ── DELETE USER (admin) ────────────────────────────────
} elseif ($action === 'delete') {
    $id = isset($data['id']) ? intval($data['id']) : 0;
    if ($id > 0) {
        $sql  = "DELETE FROM users WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Utilisateur supprimé."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Suppression échouée."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID manquant."]);
    }

// ── UPDATE ROLE (admin) ────────────────────────────────
} elseif ($action === 'update_role') {
    $id   = isset($data['id'])   ? intval($data['id'])  : 0;
    $role = isset($data['role']) ? $data['role']         : '';
    if ($id > 0 && in_array($role, ['client', 'prestataire', 'admin'])) {
        $sql  = "UPDATE users SET role=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $role, $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["status" => "success", "message" => "Rôle mis à jour."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Mise à jour du rôle échouée."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Données invalides."]);
    }

} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Action inconnue."]);
}
?>
