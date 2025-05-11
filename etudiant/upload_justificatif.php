<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérification des droits d'accès
require_etudiant();

// Vérification des données POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard_etudiant.php');
    exit;
}

// Validation des données
$module_id = filter_input(INPUT_POST, 'module_id', FILTER_VALIDATE_INT);
$date_absence = filter_input(INPUT_POST, 'date_absence');
$etudiant_id = $_SESSION['user_id'];

if (!$module_id || !$date_absence || !isset($_FILES['justificatif'])) {
    $_SESSION['error'] = 'Tous les champs sont obligatoires';
    header('Location: ../dashboard_etudiant.php');
    exit;
}

try {
    $pdo = connect();
    
    // Récupérer le numéro Apogée de l'étudiant
    $stmt = $pdo->prepare("SELECT apogee FROM etudiants WHERE id = ?");
    $stmt->execute([$etudiant_id]);
    $etudiant = $stmt->fetch();
    
    if (!$etudiant) {
        throw new Exception('Étudiant introuvable');
    }
    
    $apogee = $etudiant['apogee'];
    
    // Vérifier que le module appartient bien à l'étudiant
    $stmt = $pdo->prepare("
        SELECT 1 FROM inscriptions_modules 
        WHERE etudiant_id = ? AND module_id = ?
    ");
    $stmt->execute([$etudiant_id, $module_id]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Module non autorisé');
    }
    
    // Vérifier le fichier uploadé
    $file = $_FILES['justificatif'];
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Type de fichier non autorisé');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Fichier trop volumineux (max 2MB)');
    }
    
    // Créer le dossier de l'étudiant si inexistant (avec l'apogée)
    $etudiant_dir = "../justificatifs/$apogee";
    if (!file_exists($etudiant_dir)) {
        mkdir($etudiant_dir, 0755, true);
    }
    
    // Générer un nom de fichier unique
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'justif_' . date('Y-m-d') . '_' . uniqid() . '.' . $file_ext;
    $file_path = "$etudiant_dir/$file_name";
    
    // Déplacer le fichier uploadé
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Erreur lors de l\'enregistrement du fichier');
    }
    
    // Enregistrer en base de données
    $stmt = $pdo->prepare("
        INSERT INTO justificatifs 
        (etudiant_id, module_id, date_absence, fichier_path, statut) 
        VALUES (?, ?, ?, ?, 'en attente')
    ");
    $stmt->execute([$etudiant_id, $module_id, $date_absence, $file_path]);
    
    $_SESSION['success'] = 'Justificatif envoyé avec succès';
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur de base de données';
    error_log('DB Error in upload_justificatif: ' . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../dashboard_etudiant.php');
exit;
