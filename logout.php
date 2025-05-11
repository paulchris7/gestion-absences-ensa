<?php
// Initialisation de la session
session_start();

// Destruction complète de la session
$_SESSION = array(); // Vide le tableau de session

// Suppression du cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruction finale de la session
session_destroy();

// Redirection vers la page de connexion
header('Location: index.php');
exit;