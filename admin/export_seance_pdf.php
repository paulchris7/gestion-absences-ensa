<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../config/mpdf_config.php';
require_once '../includes/pdf_template_seance.php';
require_admin();

if (!isset($_GET['seance_id'])) {
    $_SESSION['error'] = "Séance introuvable.";
    header('Location: gestion_seances.php');
    exit;
}

$seance_id = (int)$_GET['seance_id'];

try {
    $pdo = connect();

    // Récupérer les informations de la séance, du module et de la filière
    $stmt = $pdo->prepare("
        SELECT s.*, 
               m.nom as module_nom, 
               f.nom as filiere_nom, 
               f.code as filiere_code
        FROM seances s
        JOIN modules m ON s.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        WHERE s.id = ?
    ");
    $stmt->execute([$seance_id]);
    $seance = $stmt->fetch();

    if (!$seance) {
        $_SESSION['error'] = "Séance introuvable.";
        header('Location: gestion_seances.php');
        exit;
    }

    // Récupérer les étudiants inscrits avec leur statut
    $stmt = $pdo->prepare("
        SELECT e.apogee, e.nom, e.prenom,
               IF(p.id IS NOT NULL, 1, 0) as present,
               IF(a.justifiee = 1, 1, 0) as justifiee
        FROM inscriptions_modules im
        JOIN etudiants e ON im.etudiant_id = e.id
        LEFT JOIN presences p ON p.etudiant_id = e.id AND p.seance_id = ?
        LEFT JOIN absences a ON a.etudiant_id = e.id AND a.seance_id = ?
        WHERE im.module_id = ?
    ");
    $stmt->execute([$seance_id, $seance_id, $seance['module_id']]);
    $etudiants = $stmt->fetchAll();

    // Générer le contenu du PDF
    $content = generateSeancePDFContent($seance, $etudiants);

    // Initialiser mPDF et générer le PDF
    $mpdf = initMPDF();
    $mpdf->WriteHTML($content);

    // Afficher le PDF dans le navigateur
    $mpdf->Output("Liste_Etudiants_Seance_{$seance_id}.pdf", 'I'); // 'I' pour afficher dans le navigateur
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la génération du PDF.";
    error_log("PDO Error in export_seance_pdf: " . $e->getMessage());
    header('Location: gestion_seances.php');
    exit;
}
