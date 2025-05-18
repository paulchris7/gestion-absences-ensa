<?php
require_once '../config/db.php';
require_once '../lib/endroid_qr_code/vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

if (!isset($_GET['seance_id'])) {
    die('ID de séance manquant.');
}

$seance_id = (int)$_GET['seance_id'];

try {
    $pdo = connect();
    $stmt = $pdo->prepare("SELECT qr_code FROM seances WHERE id = ?");
    $stmt->execute([$seance_id]);
    $seance = $stmt->fetch();

    if (!$seance || empty($seance['qr_code'])) {
        die('QR code introuvable pour cette séance.');
    }

    $qrCodeValue = $seance['qr_code'];

    // Générer le QR code
    $qrCode = new QrCode($qrCodeValue);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);

    // Définir les en-têtes HTTP pour le téléchargement
    header('Content-Type: ' . $result->getMimeType());
    header('Content-Disposition: attachment; filename="qr_code_seance_' . $seance_id . '.png"');

    // Afficher l'image
    echo $result->getString();
} catch (PDOException $e) {
    error_log("Erreur lors de l'exportation du QR code : " . $e->getMessage());
    die('Erreur lors de l\'exportation du QR code.');
}
