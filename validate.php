<?php
require_once 'config/db.php'; 
require_once 'includes/header.php'; // Inclure l'en-tête pour le style global

// Connexion à la base de données
$pdo = connect();

// Vérifie si un code est présent dans l’URL
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Recherche d’un étudiant avec ce code et un compte inactif
    $stmt = $pdo->prepare("SELECT * FROM etudiants WHERE code_activation = ? AND statut = 'inactif'");
    $stmt->execute([$code]);
    $etudiant = $stmt->fetch();

    echo '<div class="container" style="margin-top: 50px;">';
    if ($etudiant) {
        // Activation du compte
        $update = $pdo->prepare("UPDATE etudiants SET statut = 'actif', code_activation = NULL WHERE id = ?");
        $update->execute([$etudiant['id']]);

        echo '<div class="card">';
        echo '<div class="card-header"><h2>Activation réussie</h2></div>';
        echo '<div class="card-body">';
        echo '<p class="text-success"><i class="fas fa-check-circle"></i> Votre compte a été activé avec succès ! 🎉</p>';
        echo '<p><a href="index.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Cliquez ici pour vous connecter</a></p>';
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="card">';
        echo '<div class="card-header"><h2>Erreur d\'activation</h2></div>';
        echo '<div class="card-body">';
        echo '<p class="text-danger"><i class="fas fa-exclamation-circle"></i> Ce lien est invalide ou le compte est déjà activé.</p>';
        echo '<p><a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Retour à l\'accueil</a></p>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="container" style="margin-top: 50px;">';
    echo '<div class="card">';
    echo '<div class="card-header"><h2>Erreur</h2></div>';
    echo '<div class="card-body">';
    echo '<p class="text-warning"><i class="fas fa-info-circle"></i> Aucun code de validation fourni.</p>';
    echo '<p><a href="index.php" class="btn btn-secondary"><i class="fas fa-home"></i> Retour à l\'accueil</a></p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

require_once 'includes/footer.php'; // Inclure le pied de page
?>
