<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../config/mpdf_config.php';
require_once '../includes/pdf_template.php';

try {
    // Établir la connexion
    $conn = connect();

    // Vérifier les paramètres de filtre
    $filters = [
        'filiere_id' => isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0,
        'module_id' => isset($_GET['module_id']) ? intval($_GET['module_id']) : 0,
        'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
        'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
        'justifiee' => isset($_GET['justifiee']) ? $_GET['justifiee'] : ''
    ];

    // Requête pour récupérer les absences
    $absence_query = "
        SELECT a.id, a.justifiee, a.date_enregistrement,
               e.id as etudiant_id, e.apogee, e.nom as etudiant_nom, e.prenom as etudiant_prenom,
               s.id as seance_id, s.date_seance, s.heure_debut, s.heure_fin, s.type_seance, s.salle,
               m.id as module_id, m.code as module_code, m.nom as module_nom,
               f.id as filiere_id, f.code as filiere_code, f.nom as filiere_nom,
               j.id as justificatif_id, j.statut as justificatif_statut
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN justificatifs j ON (j.etudiant_id = e.id AND j.module_id = m.id AND j.date_absence = s.date_seance)
        WHERE 1=1
    ";
    
    $absence_params = [];
    
    if ($filters['filiere_id'] > 0) {
        $absence_query .= " AND m.filiere_id = ?";
        $absence_params[] = $filters['filiere_id'];
    }
    
    if ($filters['module_id'] > 0) {
        $absence_query .= " AND m.id = ?";
        $absence_params[] = $filters['module_id'];
    }
    
    if (!empty($filters['date_debut'])) {
        $absence_query .= " AND s.date_seance >= ?";
        $absence_params[] = $filters['date_debut'];
    }
    
    if (!empty($filters['date_fin'])) {
        $absence_query .= " AND s.date_seance <= ?";
        $absence_params[] = $filters['date_fin'];
    }
    
    if ($filters['justifiee'] !== '') {
        $absence_query .= " AND a.justifiee = ?";
        $absence_params[] = (bool)$filters['justifiee'];
    }
    
    $absence_query .= " ORDER BY s.date_seance DESC, s.heure_debut DESC, e.nom, e.prenom";
    
    $stmt = $conn->prepare($absence_query);
    $stmt->execute($absence_params);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($absences)) {
        $_SESSION['error'] = "Aucune absence trouvée pour les critères sélectionnés.";
        header('Location: gestion_absences.php');
        exit;
    }
    
    // Générer le PDF
    $mpdf = generateAbsencesPDF($absences, "Rapport des Absences");
    $html = generatePDFContent($absences, $filters);
    $mpdf->WriteHTML($html);

    // Sauvegarde sur le serveur
    $pdfDir = '../pdf/rapports/';
    if (!file_exists($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }
    $filename = 'absences_' . date('Ymd_His') . '.pdf';
    $filepath = $pdfDir . $filename;
    $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

    // Affichage dans le navigateur
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);

} catch (Exception $e) {
    $_SESSION['error'] = "Erreur lors de la génération du PDF: " . $e->getMessage();
    error_log("PDF Generation Error: " . $e->getMessage());
    header('Location: gestion_absences.php');
    exit;
}
