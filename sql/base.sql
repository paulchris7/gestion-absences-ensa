-- Script SQL pour la base de données de Gestion des Absences
-- Version complète et corrigée

-- Création de la base de données avec encodage UTF-8 explicite
CREATE DATABASE IF NOT EXISTS gestion_absences 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE gestion_absences;

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS administrateurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identifiant de connexion unique',
    password VARCHAR(255) NOT NULL COMMENT 'Mot de passe hashé',
    nom VARCHAR(100) NOT NULL COMMENT 'Nom de famille',
    prenom VARCHAR(100) NOT NULL COMMENT 'Prénom',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email professionnel',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création du compte',
    INDEX idx_admin_username (username)
) ENGINE=InnoDB;

-- Table des filières
CREATE TABLE IF NOT EXISTS filieres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE COMMENT 'Code court de la filière (ex: GI)',
    nom VARCHAR(100) NOT NULL COMMENT 'Nom complet de la filière',
    description VARCHAR(500) COMMENT 'Description courte de la filière',
    INDEX idx_filiere_code (code)
) ENGINE=InnoDB;

-- Table des modules
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE COMMENT 'Code unique du module',
    nom VARCHAR(100) NOT NULL COMMENT 'Nom complet du module',
    filiere_id INT NOT NULL COMMENT 'Référence à la filière parente',
    semestre ENUM('S1', 'S2') NOT NULL COMMENT 'Semestre d\'enseignement',
    FOREIGN KEY (filiere_id) REFERENCES filieres(id) ON DELETE CASCADE,
    INDEX idx_module_filiere (filiere_id)
) ENGINE=InnoDB;

-- Table des responsables de modules
CREATE TABLE IF NOT EXISTS responsables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL COMMENT 'Nom de famille',
    prenom VARCHAR(100) NOT NULL COMMENT 'Prénom',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email professionnel',
    telephone VARCHAR(20) COMMENT 'Numéro de téléphone',
    INDEX idx_responsable_email (email)
) ENGINE=InnoDB;

-- Table de liaison entre modules et responsables
CREATE TABLE IF NOT EXISTS responsables_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL COMMENT 'Référence au module',
    responsable_id INT NOT NULL COMMENT 'Référence au responsable',
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (responsable_id) REFERENCES responsables(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_module_responsable (module_id, responsable_id)
) ENGINE=InnoDB COMMENT='Table de liaison entre modules et responsables';

-- Table des étudiants
-- Table des étudiants (version avec activation de compte)
CREATE TABLE IF NOT EXISTS etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    apogee VARCHAR(20) NOT NULL UNIQUE COMMENT 'Numéro Apogée (identifiant unique)',
    nom VARCHAR(100) NOT NULL COMMENT 'Nom de famille',
    prenom VARCHAR(100) NOT NULL COMMENT 'Prénom',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email étudiant',
    password VARCHAR(255) NOT NULL COMMENT 'Mot de passe hashé',
    filiere_id INT NOT NULL COMMENT 'Filière principale de l\'étudiant',
    photo VARCHAR(255) COMMENT 'Chemin vers la photo de profil',
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'inscription',
    code_activation VARCHAR(100) DEFAULT NULL COMMENT 'Code unique pour activer le compte',
    statut ENUM('actif', 'inactif') NOT NULL DEFAULT 'inactif' COMMENT 'État du compte étudiant',
    FOREIGN KEY (filiere_id) REFERENCES filieres(id),
    INDEX idx_etudiant_apogee (apogee),
    INDEX idx_etudiant_filiere (filiere_id)
) ENGINE=InnoDB;


-- Table des inscriptions aux modules
CREATE TABLE IF NOT EXISTS inscriptions_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL COMMENT 'Référence à l\'étudiant',
    module_id INT NOT NULL COMMENT 'Référence au module',
    annee_universitaire VARCHAR(20) NOT NULL COMMENT 'Format: "2024-2025"',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_inscription (etudiant_id, module_id, annee_universitaire),
    INDEX idx_inscription_annee (annee_universitaire)
) ENGINE=InnoDB COMMENT='Inscriptions des étudiants aux modules par année';

-- Table des séances
CREATE TABLE IF NOT EXISTS seances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL COMMENT 'Module concerné',
    date_seance DATE NOT NULL COMMENT 'Date de la séance',
    heure_debut TIME NOT NULL COMMENT 'Heure de début',
    heure_fin TIME NOT NULL COMMENT 'Heure de fin',
    type_seance ENUM('Cours', 'TD', 'TP') NOT NULL COMMENT 'Type de séance',
    salle VARCHAR(50) COMMENT 'Local où se déroule la séance',
    qr_code VARCHAR(255) UNIQUE COMMENT 'QR code unique pour la séance',
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    INDEX idx_seance_module (module_id),
    INDEX idx_seance_date (date_seance)
) ENGINE=InnoDB;

-- Table des absences
CREATE TABLE IF NOT EXISTS absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL COMMENT 'Étudiant absent',
    seance_id INT NOT NULL COMMENT 'Séance concernée',
    justifiee BOOLEAN DEFAULT FALSE COMMENT 'Absence justifiée ou non',
    date_enregistrement TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'enregistrement',
    justification_texte TEXT COMMENT 'Texte de justification fourni par l\'étudiant',
    justification_date TIMESTAMP NULL COMMENT 'Date de soumission de la justification',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_absence_etudiant_seance (etudiant_id, seance_id),
    INDEX idx_absence_etudiant (etudiant_id),
    INDEX idx_absence_seance (seance_id)
) ENGINE=InnoDB COMMENT='Enregistrement des absences des étudiants';

-- Nouvelle table pour enregistrer les présences via QR code
CREATE TABLE IF NOT EXISTS presences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL COMMENT 'Étudiant ayant scanné le QR code',
    seance_id INT NOT NULL COMMENT 'Séance concernée',
    date_presence TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date et heure de la présence',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_presence_etudiant_seance (etudiant_id, seance_id),
    INDEX idx_presence_seance (seance_id)
) ENGINE=InnoDB COMMENT='Présences enregistrées via QR code';

-- Nouvelle table pour gérer les justificatifs d'absence
CREATE TABLE IF NOT EXISTS justificatifs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL COMMENT 'Étudiant concerné',
    seance_id INT NOT NULL COMMENT 'Séance concernée',
    fichier_path VARCHAR(255) COMMENT 'Chemin vers le fichier du justificatif',
    statut ENUM('en attente', 'accepté', 'rejeté') DEFAULT 'en attente' COMMENT 'Statut de validation',
    date_soumission TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de soumission du justificatif',
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_justificatif_etudiant_seance (etudiant_id, seance_id),
    INDEX idx_justificatif_statut (statut)
) ENGINE=InnoDB COMMENT='Justificatifs d\'absence soumis par les étudiants';

-- Nouvelle table pour enregistrer les QR codes générés pour les séances
CREATE TABLE IF NOT EXISTS qr_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seance_id INT NOT NULL COMMENT 'Séance concernée',
    code_unique VARCHAR(255) NOT NULL UNIQUE COMMENT 'Valeur unique du QR Code',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de génération',
    FOREIGN KEY (seance_id) REFERENCES seances(id) ON DELETE CASCADE,
    INDEX idx_qrcode_seance (seance_id),
    INDEX idx_qrcode_code (code_unique)
) ENGINE=InnoDB COMMENT='QR codes uniques générés pour chaque séance';

-- Insertion des données initiales

-- Administrateurs
INSERT INTO administrateurs (username, password, nom, prenom, email) VALUES
('admin', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 'Administrateur', 'Système', 'admin@example.com'),
('bouarifi', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 'Bouarifi', 'Walid', 'bouarifi@ensa.ma'),
('responsable1', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 'Ahmed', 'Bennani', 'ahmed.bennani@ensa.ma'),
('responsable2', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 'Fatima', 'El Mansouri', 'fatima.mansouri@ensa.ma');

-- Filières
INSERT INTO filieres (code, nom, description) VALUES
('GI', 'Génie Informatique', 'Formation en développement logiciel, réseaux et systèmes d\'information'),
('RSSP', 'Réseaux et Systèmes de Sécurité et de Production', 'Formation en cybersécurité et administration des réseaux'),
('GIL', 'Génie Industriel et Logistique', 'Formation en optimisation des processus industriels'),
('GE', 'Génie Électrique', 'Formation en systèmes électriques et électroniques'),
('GM', 'Génie Mécanique', 'Formation en conception et fabrication mécanique'),
('GTR', 'Génie Télécommunications et Réseaux', 'Formation en réseaux de télécommunications');

-- Modules
INSERT INTO modules (code, nom, filiere_id, semestre) VALUES
-- GI S1
('GI101', 'Programmation Web', 1, 'S1'),
('GI102', 'Bases de Données', 1, 'S1'),
('GI103', 'Algorithmique Avancée', 1, 'S1'),
-- GI S2
('GI201', 'Java et POO', 1, 'S2'),
('GI202', 'Développement Mobile', 1, 'S2'),
('GI203', 'Intelligence Artificielle', 1, 'S2'),

-- RSSP S1
('RSSP101', 'Sécurité Réseaux', 2, 'S1'),
('RSSP102', 'Administration Système', 2, 'S1'),
('RSSP103', 'Cryptographie', 2, 'S1'),
-- RSSP S2
('RSSP201', 'Ethical Hacking', 2, 'S2'),
('RSSP202', 'Cloud Computing', 2, 'S2'),
('RSSP203', 'Forensique Numérique', 2, 'S2'),

-- GIL S1
('GIL101', 'Gestion de Production', 3, 'S1'),
('GIL102', 'Logistique Industrielle', 3, 'S1'),
('GIL103', 'Recherche Opérationnelle', 3, 'S1'),
-- GIL S2
('GIL201', 'Supply Chain Management', 3, 'S2'),
('GIL202', 'Qualité et Performance', 3, 'S2'),
('GIL203', 'ERP et Systèmes d\'Information', 3, 'S2'),

-- GE S1
('GE101', 'Électrotechnique', 4, 'S1'),
('GE102', 'Électronique Analogique', 4, 'S1'),
('GE103', 'Automatique', 4, 'S1'),
-- GE S2
('GE201', 'Énergies Renouvelables', 4, 'S2'),
('GE202', 'Réseaux Électriques', 4, 'S2'),
('GE203', 'Commande des Systèmes', 4, 'S2'),

-- GM S1
('GM101', 'Mécanique des Solides', 5, 'S1'),
('GM102', 'Résistance des Matériaux', 5, 'S1'),
('GM103', 'Dessin Industriel', 5, 'S1'),
-- GM S2
('GM201', 'CFAO', 5, 'S2'),
('GM202', 'Thermodynamique', 5, 'S2'),
('GM203', 'Vibrations Mécaniques', 5, 'S2'),

-- GTR S1
('GTR101', 'Réseaux de Télécom', 6, 'S1'),
('GTR102', 'Transmission de Données', 6, 'S1'),
('GTR103', 'Antennes et Propagation', 6, 'S1'),
-- GTR S2
('GTR201', '5G et Réseaux Nouvelle Génération', 6, 'S2'),
('GTR202', 'IoT et Réseaux Capteurs', 6, 'S2'),
('GTR203', 'Sécurité des Réseaux Telecom', 6, 'S2');

-- Responsables
INSERT INTO responsables (nom, prenom, email, telephone) VALUES
('Dupont', 'Jean', 'jean.dupont@example.com', '0600000000'),
('Martin', 'Claire', 'claire.martin@example.com', '0600000001'),
('El Amrani', 'Salma', 'salma.elamrani@ensa.ma', '0612345678'),
('Ouazzani', 'Karim', 'karim.ouazzani@ensa.ma', '0623456789'),
('Benali', 'Nadia', 'nadia.benali@ensa.ma', '0634567890'),
('Tazi', 'Youssef', 'youssef.tazi@ensa.ma', '0645678901'),
('Lahlou', 'Amine', 'amine.lahlou@ensa.ma', '0656789012'),
('Mouline', 'Leila', 'leila.mouline@ensa.ma', '0667890123');

-- Association responsables-modules
INSERT INTO responsables_modules (module_id, responsable_id) VALUES
-- GI
(1, 1), (2, 2), (3, 3), (4, 1), (5, 4), (6, 5),
-- RSSP
(7, 6), (8, 7), (9, 8), (10, 6), (11, 7), (12, 8),
-- GIL
(13, 1), (14, 2), (15, 3), (16, 4), (17, 5), (18, 6),
-- GE
(19, 7), (20, 8), (21, 1), (22, 2), (23, 3), (24, 4),
-- GM
(25, 5), (26, 6), (27, 7), (28, 8), (29, 1), (30, 2),
-- GTR
(31, 3), (32, 4), (33, 5), (34, 6), (35, 7), (36, 8);

-- Étudiants
INSERT INTO etudiants (apogee, nom, prenom, email, password, filiere_id, code_activation, statut) VALUES
-- GI
('E10001', 'Alaoui', 'Mohammed', 'm.alaoui@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 1, '1234567890', 'actif'),
('E10002', 'Berrada', 'Fatima', 'f.berrada@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 1, '1234567890', 'actif'),
('E10003', 'Chraibi', 'Younes', 'y.chraibi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 1, '1234567890', 'actif'),

-- RSSP
('E20001', 'Doukkali', 'Sanae', 's.doukkali@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 2, '1234567890', 'actif'),
('E20002', 'El Fassi', 'Ahmed', 'a.elfassi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 2, '1234567890', 'actif'),
('E20003', 'Fathi', 'Laila', 'l.fathi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 2, '1234567890', 'actif'),

-- GIL
('E30001', 'Ghali', 'Omar', 'o.ghali@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 3, '1234567890', 'actif'),
('E30002', 'Hassani', 'Khadija', 'k.hassani@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 3, '1234567890', 'actif'),
('E30003', 'Idrissi', 'Rachid', 'r.idrissi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 3, '1234567890', 'actif'),

-- GE
('E40001', 'Jamal', 'Zineb', 'z.jamal@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 4, '1234567890', 'actif'),
('E40002', 'Khalil', 'Mehdi', 'm.khalil@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 4, '1234567890', 'actif'),
('E40003', 'Lamrani', 'Amina', 'a.lamrani@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 4, '1234567890', 'actif'),

-- GM
('E50001', 'Mansouri', 'Youssef', 'y.mansouri@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 5, '1234567890', 'actif'),
('E50002', 'Naciri', 'Samira', 's.naciri@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 5, '1234567890', 'actif'),
('E50003', 'Ouahabi', 'Karim', 'k.ouahabi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 5, '1234567890', 'actif'),

-- GTR
('E60001', 'Qasmi', 'Hassan', 'h.qasmi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 6, '1234567890', 'actif'),
('E60002', 'Rifi', 'Nadia', 'n.rifi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 6, '1234567890', 'actif'),
('E60003', 'Saadi', 'Imane', 'i.saadi@etud.ensa.ma', '$2y$10$NH7a6tVFdJjoxFYeMx2Sk.FuaSR3T.6brnG3osNT3iX/01ApXtt7e', 6, '1234567890', 'actif');

-- Inscriptions aux modules
INSERT INTO inscriptions_modules (etudiant_id, module_id, annee_universitaire) VALUES
-- Étudiants GI (modules 1-6)
(1, 1, '2024-2025'), (1, 2, '2024-2025'), (1, 3, '2024-2025'), (1, 4, '2024-2025'), (1, 5, '2024-2025'), (1, 6, '2024-2025'),
(2, 1, '2024-2025'), (2, 2, '2024-2025'), (2, 3, '2024-2025'), (2, 4, '2024-2025'), (2, 5, '2024-2025'), (2, 6, '2024-2025'),
(3, 1, '2024-2025'), (3, 2, '2024-2025'), (3, 3, '2024-2025'), (3, 4, '2024-2025'), (3, 5, '2024-2025'), (3, 6, '2024-2025'),

-- Étudiants RSSP (modules 7-12)
(4, 7, '2024-2025'), (4, 8, '2024-2025'), (4, 9, '2024-2025'), (4, 10, '2024-2025'), (4, 11, '2024-2025'), (4, 12, '2024-2025'),
(5, 7, '2024-2025'), (5, 8, '2024-2025'), (5, 9, '2024-2025'), (5, 10, '2024-2025'), (5, 11, '2024-2025'), (5, 12, '2024-2025'),
(6, 7, '2024-2025'), (6, 8, '2024-2025'), (6, 9, '2024-2025'), (6, 10, '2024-2025'), (6, 11, '2024-2025'), (6, 12, '2024-2025'),

-- Étudiants GIL (modules 13-18)
(7, 13, '2024-2025'), (7, 14, '2024-2025'), (7, 15, '2024-2025'), (7, 16, '2024-2025'), (7, 17, '2024-2025'), (7, 18, '2024-2025'),
(8, 13, '2024-2025'), (8, 14, '2024-2025'), (8, 15, '2024-2025'), (8, 16, '2024-2025'), (8, 17, '2024-2025'), (8, 18, '2024-2025'),
(9, 13, '2024-2025'), (9, 14, '2024-2025'), (9, 15, '2024-2025'), (9, 16, '2024-2025'), (9, 17, '2024-2025'), (9, 18, '2024-2025'),

-- Étudiants GE (modules 19-24)
(10, 19, '2024-2025'), (10, 20, '2024-2025'), (10, 21, '2024-2025'), (10, 22, '2024-2025'), (10, 23, '2024-2025'), (10, 24, '2024-2025'),
(11, 19, '2024-2025'), (11, 20, '2024-2025'), (11, 21, '2024-2025'), (11, 22, '2024-2025'), (11, 23, '2024-2025'), (11, 24, '2024-2025'),
(12, 19, '2024-2025'), (12, 20, '2024-2025'), (12, 21, '2024-2025'), (12, 22, '2024-2025'), (12, 23, '2024-2025'), (12, 24, '2024-2025'),

-- Étudiants GM (modules 25-30)
(13, 25, '2024-2025'), (13, 26, '2024-2025'), (13, 27, '2024-2025'), (13, 28, '2024-2025'), (13, 29, '2024-2025'), (13, 30, '2024-2025'),
(14, 25, '2024-2025'), (14, 26, '2024-2025'), (14, 27, '2024-2025'), (14, 28, '2024-2025'), (14, 29, '2024-2025'), (14, 30, '2024-2025'),
(15, 25, '2024-2025'), (15, 26, '2024-2025'), (15, 27, '2024-2025'), (15, 28, '2024-2025'), (15, 29, '2024-2025'), (15, 30, '2024-2025'),

-- Étudiants GTR (modules 31-36)
(16, 31, '2024-2025'), (16, 32, '2024-2025'), (16, 33, '2024-2025'), (16, 34, '2024-2025'), (16, 35, '2024-2025'), (16, 36, '2024-2025'),
(17, 31, '2024-2025'), (17, 32, '2024-2025'), (17, 33, '2024-2025'), (17, 34, '2024-2025'), (17, 35, '2024-2025'), (17, 36, '2024-2025'),
(18, 31, '2024-2025'), (18, 32, '2024-2025'), (18, 33, '2024-2025'), (18, 34, '2024-2025'), (18, 35, '2024-2025'), (18, 36, '2024-2025');

-- Séances (3 séances par module)
INSERT INTO seances (module_id, date_seance, heure_debut, heure_fin, type_seance, salle) VALUES
-- GI101
(1, '2024-10-01', '08:30:00', '10:30:00', 'Cours', 'A1'),
(1, '2024-10-08', '08:30:00', '10:30:00', 'Cours', 'A1'),
(1, '2024-10-15', '08:30:00', '10:30:00', 'TD', 'B1'),

-- GI102
(2, '2024-10-02', '10:45:00', '12:45:00', 'Cours', 'A2'),
(2, '2024-10-09', '10:45:00', '12:45:00', 'Cours', 'A2'),
(2, '2024-10-16', '10:45:00', '12:45:00', 'TP', 'C1'),

-- GI103
(3, '2024-10-03', '08:30:00', '10:30:00', 'Cours', 'A3'),
(3, '2024-10-10', '08:30:00', '10:30:00', 'Cours', 'A3'),
(3, '2024-10-17', '08:30:00', '10:30:00', 'TD', 'B2'),

-- GI201
(4, '2024-03-05', '14:00:00', '16:00:00', 'Cours', 'A1'),
(4, '2024-03-12', '14:00:00', '16:00:00', 'Cours', 'A1'),
(4, '2024-03-19', '14:00:00', '16:00:00', 'TP', 'C2'),

-- GI202
(5, '2024-03-06', '08:30:00', '10:30:00', 'Cours', 'A2'),
(5, '2024-03-13', '08:30:00', '10:30:00', 'Cours', 'A2'),
(5, '2024-03-20', '08:30:00', '10:30:00', 'TD', 'B1'),

-- GI203
(6, '2024-03-07', '10:45:00', '12:45:00', 'Cours', 'A3'),
(6, '2024-03-14', '10:45:00', '12:45:00', 'Cours', 'A3'),
(6, '2024-03-21', '10:45:00', '12:45:00', 'TP', 'C3'),

-- RSSP101
(7, '2024-10-01', '14:00:00', '16:00:00', 'Cours', 'B3'),
(7, '2024-10-08', '14:00:00', '16:00:00', 'Cours', 'B3'),
(7, '2024-10-15', '14:00:00', '16:00:00', 'TD', 'B3'),

-- RSSP102
(8, '2024-10-02', '16:15:00', '18:15:00', 'Cours', 'B4'),
(8, '2024-10-09', '16:15:00', '18:15:00', 'Cours', 'B4'),
(8, '2024-10-16', '16:15:00', '18:15:00', 'TP', 'C4'),

-- RSSP103
(9, '2024-10-03', '14:00:00', '16:00:00', 'Cours', 'B5'),
(9, '2024-10-10', '14:00:00', '16:00:00', 'Cours', 'B5'),
(9, '2024-10-17', '14:00:00', '16:00:00', 'TD', 'B5'),

-- RSSP201
(10, '2024-03-05', '08:30:00', '10:30:00', 'Cours', 'B3'),
(10, '2024-03-12', '08:30:00', '10:30:00', 'Cours', 'B3'),
(10, '2024-03-19', '08:30:00', '10:30:00', 'TP', 'C3'),

-- RSSP202
(11, '2024-03-06', '10:45:00', '12:45:00', 'Cours', 'B4'),
(11, '2024-03-13', '10:45:00', '12:45:00', 'Cours', 'B4'),
(11, '2024-03-20', '10:45:00', '12:45:00', 'TD', 'B4'),

-- RSSP203
(12, '2024-03-07', '14:00:00', '16:00:00', 'Cours', 'B5'),
(12, '2024-03-14', '14:00:00', '16:00:00', 'Cours', 'B5'),
(12, '2024-03-21', '14:00:00', '16:00:00', 'TP', 'C5'),

-- GIL101
(13, '2024-10-01', '08:30:00', '10:30:00', 'Cours', 'D1'),
(13, '2024-10-08', '08:30:00', '10:30:00', 'Cours', 'D1'),
(13, '2024-10-15', '08:30:00', '10:30:00', 'TD', 'E1'),

-- GIL102
(14, '2024-10-02', '10:45:00', '12:45:00', 'Cours', 'D2'),
(14, '2024-10-09', '10:45:00', '12:45:00', 'Cours', 'D2'),
(14, '2024-10-16', '10:45:00', '12:45:00', 'TP', 'E2'),

-- GIL103
(15, '2024-10-03', '14:00:00', '16:00:00', 'Cours', 'D3'),
(15, '2024-10-10', '14:00:00', '16:00:00', 'Cours', 'D3'),
(15, '2024-10-17', '14:00:00', '16:00:00', 'TD', 'E3'),

-- GIL201
(16, '2024-03-05', '08:30:00', '10:30:00', 'Cours', 'D1'),
(16, '2024-03-12', '08:30:00', '10:30:00', 'Cours', 'D1'),
(16, '2024-03-19', '08:30:00', '10:30:00', 'TP', 'E1'),

-- GIL202
(17, '2024-03-06', '10:45:00', '12:45:00', 'Cours', 'D2'),
(17, '2024-03-13', '10:45:00', '12:45:00', 'Cours', 'D2'),
(17, '2024-03-20', '10:45:00', '12:45:00', 'TD', 'E2'),

-- GIL203
(18, '2024-03-07', '14:00:00', '16:00:00', 'Cours', 'D3'),
(18, '2024-03-14', '14:00:00', '16:00:00', 'Cours', 'D3'),
(18, '2024-03-21', '14:00:00', '16:00:00', 'TP', 'E3'),

-- GE101
(19, '2024-10-01', '08:30:00', '10:30:00', 'Cours', 'F1'),
(19, '2024-10-08', '08:30:00', '10:30:00', 'Cours', 'F1'),
(19, '2024-10-15', '08:30:00', '10:30:00', 'TD', 'G1'),

-- GE102
(20, '2024-10-02', '10:45:00', '12:45:00', 'Cours', 'F2'),
(20, '2024-10-09', '10:45:00', '12:45:00', 'Cours', 'F2'),
(20, '2024-10-16', '10:45:00', '12:45:00', 'TP', 'G2'),

-- GE103
(21, '2024-10-03', '14:00:00', '16:00:00', 'Cours', 'F3'),
(21, '2024-10-10', '14:00:00', '16:00:00', 'Cours', 'F3'),
(21, '2024-10-17', '14:00:00', '16:00:00', 'TD', 'G3'),

-- GE201
(22, '2024-03-05', '08:30:00', '10:30:00', 'Cours', 'F1'),
(22, '2024-03-12', '08:30:00', '10:30:00', 'Cours', 'F1'),
(22, '2024-03-19', '08:30:00', '10:30:00', 'TP', 'G1'),

-- GE202
(23, '2024-03-06', '10:45:00', '12:45:00', 'Cours', 'F2'),
(23, '2024-03-13', '10:45:00', '12:45:00', 'Cours', 'F2'),
(23, '2024-03-20', '10:45:00', '12:45:00', 'TD', 'G2'),

-- GE203
(24, '2024-03-07', '14:00:00', '16:00:00', 'Cours', 'F3'),
(24, '2024-03-14', '14:00:00', '16:00:00', 'Cours', 'F3'),
(24, '2024-03-21', '14:00:00', '16:00:00', 'TP', 'G3'),

-- GM101
(25, '2024-10-01', '08:30:00', '10:30:00', 'Cours', 'H1'),
(25, '2024-10-08', '08:30:00', '10:30:00', 'Cours', 'H1'),
(25, '2024-10-15', '08:30:00', '10:30:00', 'TD', 'I1'),

-- GM102
(26, '2024-10-02', '10:45:00', '12:45:00', 'Cours', 'H2'),
(26, '2024-10-09', '10:45:00', '12:45:00', 'Cours', 'H2'),
(26, '2024-10-16', '10:45:00', '12:45:00', 'TP', 'I2'),

-- GM103
(27, '2024-10-03', '14:00:00', '16:00:00', 'Cours', 'H3'),
(27, '2024-10-10', '14:00:00', '16:00:00', 'Cours', 'H3'),
(27, '2024-10-17', '14:00:00', '16:00:00', 'TD', 'I3'),

-- GM201
(28, '2024-03-05', '08:30:00', '10:30:00', 'Cours', 'H1'),
(28, '2024-03-12', '08:30:00', '10:30:00', 'Cours', 'H1'),
(28, '2024-03-19', '08:30:00', '10:30:00', 'TP', 'I1'),

-- GM202
(29, '2024-03-06', '10:45:00', '12:45:00', 'Cours', 'H2'),
(29, '2024-03-13', '10:45:00', '12:45:00', 'Cours', 'H2'),
(29, '2024-03-20', '10:45:00', '12:45:00', 'TD', 'I2'),

-- GM203
(30, '2024-03-07', '14:00:00', '16:00:00', 'Cours', 'H3'),
(30, '2024-03-14', '14:00:00', '16:00:00', 'Cours', 'H3'),
(30, '2024-03-21', '14:00:00', '16:00:00', 'TP', 'I3'),

-- GTR101
(31, '2024-10-01', '08:30:00', '10:30:00', 'Cours', 'J1'),
(31, '2024-10-08', '08:30:00', '10:30:00', 'Cours', 'J1'),
(31, '2024-10-15', '08:30:00', '10:30:00', 'TD', 'K1'),

-- GTR102
(32, '2024-10-02', '10:45:00', '12:45:00', 'Cours', 'J2'),
(32, '2024-10-09', '10:45:00', '12:45:00', 'Cours', 'J2'),
(32, '2024-10-16', '10:45:00', '12:45:00', 'TP', 'K2'),

-- GTR103
(33, '2024-10-03', '14:00:00', '16:00:00', 'Cours', 'J3'),
(33, '2024-10-10', '14:00:00', '16:00:00', 'Cours', 'J3'),
(33, '2024-10-17', '14:00:00', '16:00:00', 'TD', 'K3'),

-- GTR201
(34, '2024-03-05', '08:30:00', '10:30:00', 'Cours', 'J1'),
(34, '2024-03-12', '08:30:00', '10:30:00', 'Cours', 'J1'),
(34, '2024-03-19', '08:30:00', '10:30:00', 'TP', 'K1'),

-- GTR202
(35, '2024-03-06', '10:45:00', '12:45:00', 'Cours', 'J2'),
(35, '2024-03-13', '10:45:00', '12:45:00', 'Cours', 'J2'),
(35, '2024-03-20', '10:45:00', '12:45:00', 'TD', 'K2'),

-- GTR203
(36, '2024-03-07', '14:00:00', '16:00:00', 'Cours', 'J3'),
(36, '2024-03-14', '14:00:00', '16:00:00', 'Cours', 'J3'),
(36, '2024-03-21', '14:00:00', '16:00:00', 'TP', 'K3');

-- Mise à jour des QR codes pour les séances
UPDATE seances
SET qr_code = CONCAT('QR-', id, '-', DATE_FORMAT(date_seance, '%Y%m%d'), '-', TIME_FORMAT(heure_debut, '%H%i%s'))
WHERE qr_code IS NULL;

-- Insertion des présences (exemple de données)
INSERT INTO presences (etudiant_id, seance_id, date_presence) VALUES
(1, 1, '2024-10-01 08:35:00'),
(2, 2, '2024-10-02 10:50:00'),
(3, 3, '2024-10-03 08:40:00'),
(4, 7, '2024-10-01 14:05:00'),
(5, 8, '2024-10-02 16:20:00');

-- Mise à jour des absences (exemple de données)
INSERT INTO absences (etudiant_id, seance_id, justifiee, justification_texte, justification_date) VALUES
(1, 4, FALSE, NULL, NULL),
(2, 5, TRUE, 'Maladie avec certificat médical.', '2024-10-10 09:00:00'),
(3, 6, FALSE, NULL, NULL),
(4, 10, TRUE, 'Problème familial urgent.', '2024-10-12 15:30:00'),
(5, 11, FALSE, NULL, NULL);

-- Insertion des justificatifs (exemple de données)
INSERT INTO justificatifs (etudiant_id, seance_id, fichier_path, statut, date_soumission) VALUES
(2, 5, 'justificatifs/E10002/justif_2024-10-10.pdf', 'accepté', '2024-10-10 09:00:00'),
(4, 10, 'justificatifs/E20004/justif_2024-10-12.pdf', 'accepté', '2024-10-12 15:30:00'),
(3, 6, 'justificatifs/E10003/justif_2024-10-11.pdf', 'en attente', '2024-10-11 10:00:00'),
(5, 11, 'justificatifs/E20005/justif_2024-10-13.pdf', 'rejeté', '2024-10-13 14:00:00');

-- Mise à jour des QR codes générés pour les séances (exemple de données)
INSERT INTO qr_codes (seance_id, code_unique, date_creation) VALUES
(1, 'QR-1-20241001-083000', '2024-09-30 12:00:00'),
(2, 'QR-2-20241002-104500', '2024-09-30 12:00:00'),
(3, 'QR-3-20241003-083000', '2024-09-30 12:00:00'),
(4, 'QR-4-20241005-140000', '2024-09-30 12:00:00'),
(5, 'QR-5-20241006-083000', '2024-09-30 12:00:00');

-- ...existing code...