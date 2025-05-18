<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

require_etudiant();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qr_code = $_POST['qr_code'] ?? '';
    $message = '';

    try {
        $pdo = connect();

        // Vérifier si le QR code correspond à une séance valide
        $stmt = $pdo->prepare("
            SELECT id 
            FROM seances 
            WHERE qr_code = ?
        ");
        $stmt->execute([$qr_code]);
        $seance = $stmt->fetch();

        if ($seance) {
            $seance_id = $seance['id'];

            // Vérifier si la séance est toujours valide pour la validation
            $stmt = $pdo->prepare("
                SELECT id 
                FROM seances
                WHERE id = ?
                AND TIMESTAMP(date_seance, heure_debut) <= NOW()
                AND TIMESTAMP(date_seance, heure_debut) + INTERVAL 10 MINUTE > NOW()
            ");
            $stmt->execute([$seance_id]);
            $valid_seance = $stmt->fetch();

            if ($valid_seance) {
                // Enregistrer la présence
                $stmt = $pdo->prepare("
                    INSERT INTO presences (etudiant_id, seance_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $seance_id]);
                $message = "Présence validée avec succès.";
            } else {
                $message = "La séance n'est plus valide pour la validation.";
            }
        } else {
            $message = "QR Code invalide.";
        }
    } catch (PDOException $e) {
        $message = "Erreur : " . $e->getMessage();
    }

    $_SESSION['info'] = $message;
    header('Location: ../dashboard_etudiant.php');
    exit;
}
