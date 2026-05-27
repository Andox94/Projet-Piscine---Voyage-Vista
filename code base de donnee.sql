-- ============================================================================
-- VoyageVista — Base de données relationnelle complète (Version MySQL / MAMP)
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS trip_activities;
DROP TABLE IF EXISTS trips;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS accommodations;
DROP TABLE IF EXISTS transports;
DROP TABLE IF EXISTS destinations;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- 1. TABLE : UTILISATEURS (users)
-- ============================================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('client', 'prestataire', 'admin') DEFAULT 'client',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. TABLE : DESTINATIONS (destinations)
-- ============================================================================
CREATE TABLE destinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    country VARCHAR(100) NOT NULL,
    description TEXT,
    tag VARCHAR(50),
    price INT NOT NULL,
    image_url VARCHAR(500),
    rating DECIMAL(2,1) DEFAULT 4.0,
    duration_days INT DEFAULT 7,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 3. TABLE : TRANSPORTS (transports)
-- ============================================================================
CREATE TABLE transports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    type VARCHAR(100) NOT NULL,
    company VARCHAR(100),
    departure_time VARCHAR(10),
    arrival_time VARCHAR(10),
    duration VARCHAR(30),
    stops INT DEFAULT 0,
    seats_left INT NOT NULL,
    price INT NOT NULL,
    class VARCHAR(50) DEFAULT 'Économique',
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 4. TABLE : HÉBERGEMENTS (accommodations)
-- ============================================================================
CREATE TABLE accommodations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    stars INT DEFAULT 3,
    type VARCHAR(50),
    price_per_night INT NOT NULL,
    rating DECIMAL(2,1) DEFAULT 4.0,
    amenities TEXT,
    image_url VARCHAR(500),
    rooms_left INT DEFAULT 10,
    free_cancellation TINYINT(1) DEFAULT 1,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 5. TABLE : ACTIVITÉS (activities)
-- ============================================================================
CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(50),
    description TEXT,
    duration VARCHAR(50),
    price_per_person INT NOT NULL,
    max_participants INT DEFAULT 20,
    current_participants INT DEFAULT 0,
    image_url VARCHAR(500),
    difficulty VARCHAR(30) DEFAULT 'Facile',
    includes TEXT,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 6. TABLE : VOYAGES / RÉSERVATIONS (trips)
-- ============================================================================
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    destination_id INT NOT NULL,
    transport_id INT,
    accommodation_id INT,
    client_name VARCHAR(100) NOT NULL,
    client_email VARCHAR(100),
    travelers INT DEFAULT 1,
    departure_date DATE,
    return_date DATE,
    nights INT DEFAULT 3,
    total_price INT NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'confirmed',
    reference_code VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (destination_id) REFERENCES destinations(id) ON DELETE CASCADE,
    FOREIGN KEY (transport_id) REFERENCES transports(id) ON DELETE SET NULL,
    FOREIGN KEY (accommodation_id) REFERENCES accommodations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 7. TABLE : ACTIVITÉS LIÉES AUX VOYAGES (trip_activities)
-- ============================================================================
CREATE TABLE trip_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    activity_id INT NOT NULL,
    quantity INT DEFAULT 1,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 8. TABLE : NOTIFICATIONS (notifications)
-- ============================================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('reservation', 'transport', 'hebergement', 'activite', 'promotion', 'systeme') DEFAULT 'systeme',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
--                      INSERTION DES DONNÉES DE DÉMONSTRATION
-- ============================================================================

-- 1. Insertion des Utilisateurs (Le mot de passe hashé correspond à 'password')
INSERT INTO users (id, name, email, password_hash, role) VALUES
(1, 'Admin VoyageVista', 'admin@voyagevista.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Marie Dupont', 'marie@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client'),
(3, 'Hôtels Paradis', 'contact@hotelsparadis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'prestataire');

-- 2. Insertion des Destinations
INSERT INTO destinations (id, name, country, description, tag, price, image_url, rating, duration_days) VALUES
(1, 'Kyoto', 'Japon', 'Temples ancestraux, jardins zen et traditions millénaires au cœur du Japon impérial.', 'Culture', 1200, 'https://images.unsplash.com/photo-1493976040374-85c8e12f0c0e?w=600', 4.8, 10),
(2, 'Santorin', 'Grèce', 'Villages blancs perchés sur des falaises volcaniques face à la mer Égée.', 'Romantique', 600, 'https://images.unsplash.com/photo-1613395877344-13d4a8e0d49e?w=600', 4.7, 7),
(3, 'Bali', 'Indonésie', 'Rizières émeraude, temples hindous et plages de sable noir volcanique.', 'Nature', 890, 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=600', 4.6, 12),
(4, 'Marrakech', 'Maroc', 'Souks envoûtants, palais et riads au pied de l\'Atlas.', 'Aventure', 450, 'https://images.unsplash.com/photo-1597212618440-806262de4f6b?w=600', 4.4, 5),
(5, 'Islande', 'Islande', 'Aurores boréales, geysers et paysages lunaires à couper le souffle.', 'Nature', 1500, 'https://images.unsplash.com/photo-1504829857797-ddff29c27927?w=600', 4.9, 8),
(6, 'New York', 'États-Unis', 'La ville qui ne dort jamais : gratte-ciels, Broadway et Central Park.', 'City Break', 980, 'https://images.unsplash.com/photo-1496442226666-8d4d0e62e6e9?w=600', 4.5, 6);

-- 3. Insertion des Transports
-- Kyoto
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(1, 'Vol direct', 'Air France', '11:30', '05:15+1', '13h 45min', 0, 5, 789, 'Économique'),
(1, 'Vol 1 escale', 'Qatar Airways', '15:20', '17:50+1', '20h 30min', 1, 12, 672, 'Économique'),
(1, 'Vol 1 escale', 'Emirates', '21:40', '23:50+1', '20h 10min', 1, 8, 699, 'Affaires');
-- Santorin
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(2, 'Vol direct', 'Transavia', '06:45', '10:30', '3h 45min', 0, 22, 159, 'Économique'),
(2, 'Vol direct', 'Aegean Airlines', '14:10', '18:05', '3h 55min', 0, 9, 210, 'Économique'),
(2, 'Vol 1 escale', 'Lufthansa', '08:00', '14:30', '6h 30min', 1, 15, 185, 'Économique');
-- Bali
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(3, 'Vol 1 escale', 'Singapore Airlines', '19:10', '08:00+1', '18h 50min', 1, 6, 728, 'Économique'),
(3, 'Vol 1 escale', 'Cathay Pacific', '22:00', '14:20+1', '22h 20min', 1, 18, 580, 'Économique');
-- Marrakech
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(4, 'Vol direct', 'Ryanair', '07:00', '09:30', '2h 30min', 0, 30, 89, 'Économique'),
(4, 'Vol direct', 'Royal Air Maroc', '13:00', '15:25', '2h 25min', 0, 14, 145, 'Économique'),
(4, 'Train + Ferry', 'SNCF / Baleària', '06:00', '22:00', '16h', 2, 40, 120, 'Standard');
-- Islande
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(5, 'Vol direct', 'Icelandair', '10:30', '12:15', '3h 45min', 0, 7, 380, 'Économique'),
(5, 'Vol 1 escale', 'SAS', '08:15', '15:40', '7h 25min', 1, 20, 295, 'Économique');
-- New York
INSERT INTO transports (destination_id, type, company, departure_time, arrival_time, duration, stops, seats_left, price, class) VALUES
(6, 'Vol direct', 'Air France', '10:00', '12:30', '8h 30min', 0, 10, 520, 'Économique'),
(6, 'Vol direct', 'Delta', '14:30', '17:00', '8h 30min', 0, 4, 480, 'Économique'),
(6, 'Vol 1 escale', 'British Airways', '07:00', '16:00', '11h', 1, 25, 390, 'Économique');

-- 4. Insertion des Hébergements
-- Kyoto
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(1, 'Ryokan Vista Tradition', 5, 'Ryokan', 180, 4.9, 'Onsen privé, Tatami, Petit-déjeuner kaiseki', 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=400', 3, 1),
(1, 'Kyoto Garden Hotel', 4, 'Hôtel', 95, 4.5, 'Wi-Fi, Climatisation, Restaurant', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400', 8, 1),
(1, 'Capsule Inn Kyoto', 2, 'Insolite', 35, 4.0, 'Wi-Fi, Casier, Douche commune', 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=400', 20, 0);
-- Santorin
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(2, 'Aegean Luxury Suites', 5, 'Hôtel', 320, 4.9, 'Piscine à débordement, Vue caldera, Spa', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=400', 2, 1),
(2, 'Oia White Cave', 4, 'Appartement', 180, 4.6, 'Terrasse privée, Cuisine, Vue mer', 'https://images.unsplash.com/photo-1602343168338-47e1c4047fd0?w=400', 5, 1),
(2, 'Hostel Fira Central', 2, 'Auberge', 45, 3.8, 'Wi-Fi, Petit-déjeuner, Dortoir', 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=400', 15, 0);
-- Bali
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(3, 'Ubud Tropical Resort', 5, 'Resort', 250, 4.8, 'Piscine, Spa, Navette aéroport', 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=400', 4, 1),
(3, 'Villa Rizières Vertes', 4, 'Villa', 120, 4.5, 'Piscine privée, Jardin, Cuisine', 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?w=400', 6, 1),
(3, 'Bali Backpacker Haven', 2, 'Auberge', 20, 4.1, 'Wi-Fi, Bar, Surf lessons', 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=400', 25, 0);
-- Marrakech
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(4, 'Riad des Orangers', 5, 'Riad', 190, 4.7, 'Piscine, Hammam, Terrasse rooftop', 'https://images.unsplash.com/photo-1590073242678-70ee3fc28e8e?w=400', 3, 1),
(4, 'Hôtel Atlas Medina', 3, 'Hôtel', 65, 4.2, 'Wi-Fi, Restaurant, Parking', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400', 12, 1),
(4, 'Dar Jenna Budget', 2, 'Maison d\'hôtes', 30, 3.9, 'Petit-déjeuner, Terrasse', 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=400', 8, 0);
-- Islande
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(5, 'Northern Lights Lodge', 5, 'Lodge', 350, 4.9, 'Toit vitré, Hot tub, Guide aurores', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=400', 2, 1),
(5, 'Reykjavik City Hotel', 3, 'Hôtel', 130, 4.3, 'Wi-Fi, Bar, Petit-déjeuner', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400', 10, 1);
-- New York
INSERT INTO accommodations (destination_id, name, stars, type, price_per_night, rating, amenities, image_url, rooms_left, free_cancellation) VALUES
(6, 'The Manhattan Grand', 5, 'Hôtel', 420, 4.7, 'Vue skyline, Rooftop bar, Spa', 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=400', 3, 1),
(6, 'Brooklyn Boutique', 4, 'Hôtel', 180, 4.4, 'Design, Wi-Fi, Coffee bar', 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=400', 7, 1),
(6, 'HI NYC Hostel', 2, 'Auberge', 55, 4.0, 'Cuisine commune, Activités, Wi-Fi', 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?w=400', 30, 0);

-- 5. Insertion des Activités
-- Kyoto
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(1, 'Cérémonie du thé traditionnelle', 'Culture', 'Participez à une authentique cérémonie du thé dans un pavillon historique.', '2h', 45, 8, 3, 'Facile', 'Guide bilingue, Matcha, Pâtisseries'),
(1, 'Randonnée Fushimi Inari', 'Nature', 'Traversez les milliers de torii vermillon du sanctuaire Fushimi Inari.', '4h', 30, 15, 7, 'Modéré', 'Guide, Eau, Collation'),
(1, 'Atelier calligraphie japonaise', 'Culture', 'Apprenez l\'art ancestral du shodo avec un maître calligraphe.', '1h30', 55, 6, 2, 'Facile', 'Matériel inclus, Œuvre à emporter');
-- Santorin
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(2, 'Croisière coucher de soleil', 'Romantique', 'Navigation vers la caldera avec dîner et vin local au coucher du soleil.', '4h', 90, 20, 12, 'Facile', 'Dîner, Vin, Baignade'),
(2, 'Dégustation de vins', 'Gastronomie', 'Visitez trois domaines viticoles et dégustez les cépages volcaniques.', '3h', 65, 12, 5, 'Facile', 'Transport, 12 vins, Mezzés'),
(2, 'Kayak dans la caldera', 'Aventure', 'Pagayez autour des îles volcaniques et baignez-vous dans les sources chaudes.', '3h', 55, 10, 4, 'Modéré', 'Équipement, Guide, Collation');
-- Bali
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(3, 'Plongée sous-marine Tulamben', 'Aventure', 'Explorez l\'épave du USS Liberty et les jardins de corail.', '5h', 85, 8, 3, 'Modéré', 'Équipement, Transport, Guide PADI'),
(3, 'Cours de cuisine balinaise', 'Gastronomie', 'Marché local puis atelier avec un chef dans les rizières d\'Ubud.', '4h', 60, 10, 6, 'Facile', 'Ingrédients, Recettes, Repas'),
(3, 'Excursion rizières Tegallalang', 'Nature', 'Randonnée guidée à travers les spectaculaires rizières en terrasses.', '3h', 35, 15, 8, 'Facile', 'Guide local, Eau, Chapeau');
-- Marrakech
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(4, 'Excursion vallée de l\'Ourika', 'Nature', 'Randonnée dans la vallée berbère avec cascade et déjeuner chez l\'habitant.', '6h', 40, 12, 5, 'Modéré', 'Transport, Guide, Déjeuner'),
(4, 'Cours de cuisine marocaine', 'Gastronomie', 'Apprenez à préparer tajine, couscous et pastilla dans un riad.', '3h', 50, 8, 3, 'Facile', 'Ingrédients, Tablier, Repas'),
(4, 'Survol en montgolfière', 'Aventure', 'Vol au-dessus de la palmeraie au lever du soleil avec petit-déjeuner.', '3h', 180, 16, 10, 'Facile', 'Vol 1h, Petit-déjeuner, Certificat');
-- Islande
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(5, 'Chasse aux aurores boréales', 'Nature', 'Sortie nocturne en minibus vers les meilleurs spots d\'observation.', '4h', 75, 20, 9, 'Facile', 'Guide expert, Chocolat chaud, Photos'),
(5, 'Randonnée glaciaire Sólheimajökull', 'Aventure', 'Marche sur glacier avec crampons et piolets encadrée par un guide.', '5h', 120, 10, 4, 'Difficile', 'Équipement complet, Guide certifié'),
(5, 'Blue Lagoon VIP', 'Bien-être', 'Accès prioritaire au lagon géothermal avec masque et boisson.', '3h', 95, 30, 18, 'Facile', 'Entrée VIP, Masque silice, Boisson');
-- New York
INSERT INTO activities (destination_id, name, category, description, duration, price_per_person, max_participants, current_participants, difficulty, includes) VALUES
(6, 'Comédie musicale à Broadway', 'Culture', 'Place premium pour un spectacle à l\'affiche sur Broadway.', '3h', 150, 50, 35, 'Facile', 'Billet catégorie Orchestra'),
(6, 'Survol en hélicoptère', 'Aventure', 'Vol panoramique au-dessus de Manhattan, Liberty et Brooklyn Bridge.', '30min', 220, 6, 2, 'Facile', 'Vol, Photos, Vidéo'),
(6, 'Food Tour à Chinatown', 'Gastronomie', 'Découvrez les saveurs cachées de Chinatown avec un guide local.', '3h', 65, 12, 7, 'Facile', 'Guide, 6 dégustations, Boisson');

-- 6. Insertion des Notifications initials
INSERT INTO notifications (user_id, type, title, message, is_read) VALUES
(2, 'systeme', 'Bienvenue sur VoyageVista !', 'Votre compte a été créé avec succès. Explorez nos destinations et composez votre voyage idéal.', 0),
(2, 'promotion', 'Offre spéciale Bali', 'Profitez de -15% sur les séjours à Bali réservés avant le 30 juin 2026.', 0);