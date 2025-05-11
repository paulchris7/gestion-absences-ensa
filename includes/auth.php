<?php
session_start();

/**
 * Vérifie si l'utilisateur est connecté en tant qu'administrateur
 * Redirige vers la page de connexion si ce n'est pas le cas
 */
function require_admin() {
    if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        $_SESSION['error'] = "Accès refusé : authentification administrateur requise";
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur est connecté en tant qu'étudiant
 * Redirige vers la page de connexion si ce n'est pas le cas
 */
function require_etudiant() {
    if (!isset($_SESSION['user_id'], $_SESSION['user_type']) || $_SESSION['user_type'] !== 'etudiant') {
        $_SESSION['error'] = "Accès réservé aux étudiants";
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur est déjà connecté
 * Redirige vers le tableau de bord approprié si c'est le cas
 */
function redirect_if_logged_in() {
    if (isset($_SESSION['user_id'], $_SESSION['user_type'])) {
        $dashboard = ($_SESSION['user_type'] === 'admin') 
            ? '../dashboard_admin.php' 
            : '../dashboard_etudiant.php';
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
