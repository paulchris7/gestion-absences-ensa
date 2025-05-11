<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

// Rediriger si déjà connecté
redirect_if_logged_in();

// Initialiser les variables
$error = '';
$success = '';
$filieres = [];
$modules = [];

// Fonction de validation de photo
function validatePhoto($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return "Veuillez sélectionner une photo valide.";
    }
    
    $allowedTypes = ['image/jpeg', 'image/png'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return "Le format de l'image doit être JPEG ou PNG.";
    }
    
    if ($file['size'] > $maxSize) {
        return "La taille de l'image ne doit pas dépasser 2MB.";
    }
    
    return true;
}

try {
    $pdo = connect();
    
    // Récupérer toutes les filières
    $stmt = $pdo->query("SELECT * FROM filieres ORDER BY nom");
    $filieres = $stmt->fetchAll();
    
    // Si une filière est sélectionnée, récupérer ses modules
    if (isset($_POST['filiere_id']) && !empty($_POST['filiere_id'])) {
        $filiere_id = (int)$_POST['filiere_id'];
        $stmt = $pdo->prepare("SELECT * FROM modules WHERE filiere_id = :filiere_id ORDER BY semestre, nom");
        $stmt->execute(['filiere_id' => $filiere_id]);
        $modules = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Erreur de connexion à la base de données.';
    error_log($e->getMessage());
}

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Nettoyer les entrées
    $apogee = trim($_POST['apogee'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $filiere_id = (int)($_POST['filiere_id'] ?? 0);
    $module_ids = array_map('intval', $_POST['module_ids'] ?? []);
    
    // Validation des champs
    if (empty($apogee) || empty($nom) || empty($prenom) || empty($email) || empty($password) || $filiere_id <= 0 || empty($module_ids)) {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($password !== $confirm_password) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        try {
            $pdo = connect();
            
            // Validation de la photo
            $photoValidation = validatePhoto($_FILES['photo'] ?? null);
            if ($photoValidation !== true) {
                $error = $photoValidation;
            } else {
                $pdo->beginTransaction();
                
                // Vérifier si l'apogée existe déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE apogee = :apogee");
                $stmt->execute(['apogee' => $apogee]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Ce numéro Apogée est déjà utilisé.';
                    $pdo->rollBack();
                } else {
                    // Vérifier si l'email existe déjà
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE email = :email");
                    $stmt->execute(['email' => $email]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = 'Cette adresse email est déjà utilisée.';
                        $pdo->rollBack();
                    } else {
                        // Créer le répertoire pour la photo
                        $photoDir = 'photos';
                        if (!is_dir($photoDir)) {
                            mkdir($photoDir, 0755, true);
                        }
                        
                        // Enregistrer la photo
                        $photoExt = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                        $photoPath = $photoDir . '/' . $apogee . '.' . $photoExt;
                        
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                            // Hachage du mot de passe
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insertion de l'étudiant
                            $stmt = $pdo->prepare("
                                INSERT INTO etudiants (apogee, nom, prenom, email, password, filiere_id, photo)
                                VALUES (:apogee, :nom, :prenom, :email, :password, :filiere_id, :photo)
                            ");
                            $stmt->execute([
                                'apogee' => $apogee,
                                'nom' => $nom,
                                'prenom' => $prenom,
                                'email' => $email,
                                'password' => $hashed_password,
                                'filiere_id' => $filiere_id,
                                'photo' => $photoPath,
                            ]);
                            
                            $etudiant_id = $pdo->lastInsertId();
                            
                            // Inscription aux modules sélectionnés
                            $annee_universitaire = date('Y') . '-' . (date('Y') + 1);
                            
                            foreach ($module_ids as $module_id) {
                                $stmt = $pdo->prepare("
                                    INSERT INTO inscriptions_modules (etudiant_id, module_id, annee_universitaire)
                                    VALUES (:etudiant_id, :module_id, :annee_universitaire)
                                ");
                                $stmt->execute([
                                    'etudiant_id' => $etudiant_id,
                                    'module_id' => $module_id,
                                    'annee_universitaire' => $annee_universitaire
                                ]);
                            }
                            
                            $pdo->commit();
                            $_SESSION['success'] = 'Inscription réussie ! Vous pouvez maintenant vous connecter.';
                            header("Location: index.php");
                            exit;
                        } else {
                            $error = 'Erreur lors de l\'enregistrement de la photo.';
                            $pdo->rollBack();
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Erreur lors de l\'inscription.';
            error_log($e->getMessage());
        }
    }
}

// Inclure l'en-tête
$page_title = "Inscription Étudiant";
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-user-plus"></i> Inscription Étudiant</h2>
        </div>
        <div class="card-body">
            <!-- [Les messages d'erreur/succès restent au même endroit] -->
            
            <form action="register.php" method="post" enctype="multipart/form-data">
                <!-- Section informations personnelles -->
                <div class="form-group">
                    <label for="apogee">Numéro Apogée</label>
                    <input type="text" class="form-control" id="apogee" name="apogee" value="<?php echo htmlspecialchars($_POST['apogee'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <!-- Section filière et modules -->
                <div class="form-group">
                    <label for="filiere_id">Filière</label>
                    <select class="form-select" id="filiere_id" name="filiere_id" required>
                        <option value="">Sélectionner une filière</option>
                        <?php foreach ($filieres as $filiere): ?>
                            <option value="<?php echo htmlspecialchars($filiere['id']); ?>" <?php echo (isset($_POST['filiere_id']) && $_POST['filiere_id'] == $filiere['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($modules)): ?>
                <div class="form-group">
                    <label>Modules</label>
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label><strong>Semestre 1</strong></label>
                                <?php foreach ($modules as $module): ?>
                                    <?php if ($module['semestre'] === 'S1'): ?>
                                    <div class="form-check">
                                        <input type="checkbox" id="module_<?php echo htmlspecialchars($module['id']); ?>" name="module_ids[]" value="<?php echo htmlspecialchars($module['id']); ?>" 
                                            <?php echo (isset($_POST['module_ids']) && in_array($module['id'], $_POST['module_ids'])) ? 'checked' : ''; ?>>
                                        <label for="module_<?php echo htmlspecialchars($module['id']); ?>"><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></label>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Semestre 2</strong></label>
                                <?php foreach ($modules as $module): ?>
                                    <?php if ($module['semestre'] === 'S2'): ?>
                                    <div class="form-check">
                                        <input type="checkbox" id="module_<?php echo htmlspecialchars($module['id']); ?>" name="module_ids[]" value="<?php echo htmlspecialchars($module['id']); ?>"
                                            <?php echo (isset($_POST['module_ids']) && in_array($module['id'], $_POST['module_ids'])) ? 'checked' : ''; ?>>
                                        <label for="module_<?php echo htmlspecialchars($module['id']); ?>"><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></label>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Section photo -->
                <div class="form-group">
                    <label for="photo">Photo </label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="photo" name="photo" accept="image/jpeg,image/png" required>
                        <label class="custom-file-label" for="photo">Choisir un fichier</label>
                    </div>
                    <small class="form-text text-muted">Format JPEG ou PNG, max 2MB</small>
                </div>
                
                <!-- Section mot de passe (déplacée à la fin) -->
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <!-- Bouton de soumission -->
                <div class="form-group">
                    <button type="submit" name="register" class="btn btn-block">
                        <i class="fas fa-user-plus"></i> S'inscrire
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-20">
                <p>Vous avez déjà un compte ?</p>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const filiereSelect = document.getElementById('filiere_id');
        
        filiereSelect.addEventListener('change', function() {
            this.form.submit();
        });

        // Mise à jour du label du fichier photo
        document.getElementById('photo').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'Choisir un fichier';
            document.querySelector('.custom-file-label').textContent = fileName;
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
