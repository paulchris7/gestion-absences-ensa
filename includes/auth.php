<?php
session_start();

$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/etudiant/') !== false)
    ? '../'
    : '';

/**
 * Vérifie si l'utilisateur est connecté en tant qu'administrateur
 * Redirige vers la page de connexion si ce n'est pas le cas
 */
function require_admin() {
    global $base_path; // Utiliser le chemin absolu défini en haut du fichier
    if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        $_SESSION['error'] = "Accès refusé : authentification administrateur requise";
        header("Location: {$base_path}index.php"); // Chemin absolu
        exit;
    }
}

/**
 * Vérifie si l'utilisateur est connecté en tant qu'étudiant
 * Redirige vers la page de connexion si ce n'est pas le cas
 */
function require_etudiant() {
    global $base_path; // Utiliser le chemin absolu défini en haut du fichier
    if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'etudiant') {
        $_SESSION['error'] = "Accès réservé aux étudiants";
        header("Location: {$base_path}index.php"); // Chemin absolu
        exit;
    }
}

/**
 * Vérifie si le compte de l'étudiant est activé
 * Redirige vers la page de connexion avec un message d'erreur si ce n'est pas le cas
 */
function require_etudiant_active($etudiant_id) {
    global $base_path; // Utiliser le chemin absolu défini en haut du fichier
    $pdo = connect();
    $stmt = $pdo->prepare("SELECT statut FROM etudiants WHERE id = ?");
    $stmt->execute([$etudiant_id]);
    $etudiant = $stmt->fetch();

    if (!$etudiant || $etudiant['statut'] !== 'actif') {
        $_SESSION['error'] = "Votre compte n'est pas encore activé. Veuillez vérifier votre email pour l'activer.";
        header("Location: {$base_path}index.php"); // Chemin absolu
        exit;
    }
}

/**
 * Vérifie si l'utilisateur est déjà connecté
 * Redirige vers le tableau de bord approprié si c'est le cas
 */
function redirect_if_logged_in() {
    global $base_path; // Utiliser le chemin absolu défini en haut du fichier
    if (isset($_SESSION['user_id'], $_SESSION['user_type'])) {
        $dashboard = ($_SESSION['user_type'] === 'admin') 
            ? "{$base_path}dashboard_admin.php" 
            : "{$base_path}dashboard_etudiant.php"; // Chemins absolus
        header("Location: $dashboard");
        exit;
    }
}

/**
 * Affiche les messages d'alerte stockés en session
 */
function display_alert() {
    if (!isset($_SESSION)) return;

    $alert_types = [
        'success' => 'check-circle',
        'error' => 'exclamation-circle', 
        'info' => 'info-circle'
    ];

    foreach ($alert_types as $type => $icon) {
        if (isset($_SESSION[$type])) {
            echo sprintf(
                '<div class="alert %s"><i class="fas fa-%s"></i> %s</div>',
                htmlspecialchars($type),
                htmlspecialchars($icon),
                htmlspecialchars($_SESSION[$type])
            );
            unset($_SESSION[$type]);
        }
    }
}
