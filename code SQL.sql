CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('client', 'prestataire', 'admin') DEFAULT 'client'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    tag VARCHAR(50),
    price INT NOT NULL,
    image_url VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT,
    type VARCHAR(100) NOT NULL,
    time_window VARCHAR(50) NOT NULL,
    seats_left INT NOT NULL,
    price INT NOT NULL,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accommodations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT,
    name VARCHAR(100) NOT NULL,
    stars VARCHAR(10),
    type VARCHAR(50),
    price_per_night INT NOT NULL,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion de données de test pour voir si ça marche
INSERT INTO destinations (id, name, description, tag, price, image_url) VALUES
(1, 'Kyoto, Japon', 'Temples ancestraux et sérénité nippone.', 'Culture', 1200, 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=500'),
(2, 'Santorin, Grèce', 'Villages blancs perchés et couchers de soleil.', 'Romantique', 600, 'https://images.unsplash.com/photo-1613395877344-13d4a8e0d49e?w=500');

INSERT INTO transports (destination_id, type, time_window, seats_left, price) VALUES
(1, 'Vol Air France Premium', '08h15 - 21h30', 5, 850),
(1, 'Vol Éco Transavia', '14h20 - 04h05', 14, 420),
(2, 'Vol direct EasyJet', '10h00 - 13h30', 8, 180);

INSERT INTO accommodations (destination_id, name, stars, type, price_per_night) VALUES
(1, 'Ryokan Vista Tradition', '⭐⭐⭐⭐⭐', 'Hôtel', 180),
(1, 'Kyoto Capsule Hôtel', '⭐⭐', 'Insolite', 35),
(2, 'Santorin Luxury Villa', '⭐⭐⭐⭐⭐', 'Appartement', 250);