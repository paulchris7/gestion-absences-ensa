<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

// Rediriger si déjà connecté
redirect_if_logged_in();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if (empty($identifier) || empty($password) || empty($user_type)) {
        $error = 'Tous les champs sont obligatoires.';
    } else {
        try {
            $pdo = connect();
            
            if ($user_type === 'admin') {
                $stmt = $pdo->prepare("SELECT id, password, nom, prenom FROM administrateurs WHERE username = ?");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    header('Location: dashboard_admin.php');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare("SELECT id, password, nom, prenom, filiere_id FROM etudiants WHERE apogee = ?");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Vérifie si le compte étudiant est activé
                    require_etudiant_active($user['id']);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_type'] = 'etudiant';
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    $_SESSION['user_filiere'] = $user['filiere_id'];
                    header('Location: dashboard_etudiant.php');
                    exit;
                }
            }
            
            $error = 'Identifiants incorrects.';
            
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = 'Erreur de connexion. Veuillez réessayer.';
        }
    }
}

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-sign-in-alt"></i> Connexion</h2>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php display_alert(); ?>
            
            <form method="post" autocomplete="off">
                <div class="auth-tabs">
                    <div class="auth-tab <?= (!isset($_POST['user_type']) || $_POST['user_type'] === 'admin' ? 'active' : '' )?>" data-type="admin">
                        <i class="fas fa-user-shield"></i> Administrateur
                    </div>
                    <div class="auth-tab <?= isset($_POST['user_type']) && $_POST['user_type'] === 'etudiant' ? 'active' : '' ?>" data-type="etudiant">
                        <i class="fas fa-user-graduate"></i> Étudiant
                    </div>
                </div>
                
                <input type="hidden" name="user_type" id="user_type"
                       value="<?= isset($_POST['user_type']) && $_POST['user_type'] === 'etudiant' ? 'etudiant' : 'admin' ?>">
                
                <div class="form-group">
                    <label for="identifier" id="identifier_label">
                        <?= (isset($_POST['user_type']) && $_POST['user_type'] === 'etudiant') ? 'Numéro Apogée' : 'Nom d\'utilisateur' ?>
                    </label>
                    <input type="text" class="form-control" id="identifier" name="identifier"
                           value="<?= isset($_POST['identifier']) ? htmlspecialchars($_POST['identifier']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-block">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>
            
            <div class="text-center mt-20">
                <p>Vous êtes étudiant et vous n'avez pas de compte ?</p>
                <a href="register.php" class="btn btn-secondary">
                    <i class="fas fa-user-plus"></i> S'inscrire
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const userTypeInput = document.getElementById('user_type');
    const identifierLabel = document.getElementById('identifier_label');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const userType = this.dataset.type;
            userTypeInput.value = userType;
            identifierLabel.textContent = userType === 'etudiant' ? 'Numéro Apogée' : 'Nom d\'utilisateur';
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
