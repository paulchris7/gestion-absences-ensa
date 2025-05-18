<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config/db.php';
require_once 'includes/auth.php';
require_once 'lib/PHPMailer/vendor/autoload.php';

$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/etudiant/') !== false)
    ? '../'
    : '';
    
// Rediriger si déjà connecté
redirect_if_logged_in();

// Initialiser les variables
$error = '';
$success = '';
$filieres = [];
$modules = [];

// Récupérer le message de succès s'il existe
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

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
    $module_ids = isset($_POST['module_ids']) ? array_map('intval', $_POST['module_ids']) : [];
    
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

                            // Génération du code de validation
                            $code_activation = bin2hex(random_bytes(16)); // 32 caractères aléatoires

                            // Insertion de l'étudiant
                            $stmt = $pdo->prepare("
                                INSERT INTO etudiants (apogee, nom, prenom, email, password, filiere_id, photo, code_activation)
                                VALUES (:apogee, :nom, :prenom, :email, :password, :filiere_id, :photo, :code_activation)
                            ");
                            $stmt->execute([
                                'apogee' => $apogee,
                                'nom' => $nom,
                                'prenom' => $prenom,
                                'email' => $email,
                                'password' => $hashed_password,
                                'filiere_id' => $filiere_id,
                                'photo' => $photoPath,
                                'code_activation' => $code_activation,
                            ]);

                            
                            $etudiant_id = $pdo->lastInsertId();

                            $mail = new PHPMailer(true);

                            // Envoi de l'e-mail de validation
                            try {
                                // Paramètres serveur SMTP
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.gmail.com';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = ''; 
                                $mail->Password   = ''; 
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = 587;

                                // Expéditeur et destinataire
                                $mail->setFrom('tonadresse@gmail.com', 'Gestion des absences');
                                $mail->addAddress($email, "$prenom $nom"); // étudiant

                                // Contenu de l'e-mail
                                $mail->isHTML(true);
                                $mail->Subject = 'Validation de votre inscription';
                                $mail->Body    = "
                                    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #f9f9f9;'>
                                        <h2 style='color: #4361ee; text-align: center;'>Bienvenue, <strong>$prenom $nom</strong> !</h2>
                                        <p style='font-size: 16px;'>Merci pour votre inscription. Pour activer votre compte, veuillez cliquer sur le bouton ci-dessous :</p>
                                        <div style='text-align: center; margin: 20px 0;'>
                                            <a href='{$base_path}validate.php?code=$code_activation' 
                                               style='display: inline-block; padding: 12px 20px; font-size: 16px; color: #fff; background-color: #4361ee; text-decoration: none; border-radius: 5px;'>
                                               Activer mon compte
                                            </a>
                                        </div>
                                        <p style='font-size: 14px; color: #555;'>Si vous n'avez pas demandé cette inscription, ignorez ce message.</p>
                                        <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>
                                        <p style='font-size: 12px; color: #888; text-align: center;'>--<br>L'équipe de gestion des absences</p>
                                    </div>
                                ";
                                $mail->AltBody = "Bonjour $prenom $nom,\n\nVoici votre lien de validation : {$base_path}validate.php?code=$code_activation";

                                $mail->send();
                            } catch (Exception $e) {
                                error_log("Erreur lors de l'envoi du mail : {$mail->ErrorInfo}");
                            }

                            
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
                            $_SESSION['success'] = 'Inscription réussie ! Vous pouvez maintenant vous connecter après avoir validé votre email.';
                            header("Location: {$base_path}index.php");
                            exit();
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
            $error = 'Erreur lors de l\'inscription: ' . $e->getMessage();
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
            <!-- Messages d'erreur/succès -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
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
                                <?php 
                                $hasSemestre1 = false;
                                foreach ($modules as $module) {
                                    if ($module['semestre'] === 'S1') {
                                        $hasSemestre1 = true;
                                        break;
                                    }
                                }
                                
                                if ($hasSemestre1): 
                                ?>
                                    <?php foreach ($modules as $module): ?>
                                        <?php if ($module['semestre'] === 'S1'): ?>
                                        <div class="form-check">
                                            <input type="checkbox" id="module_<?php echo htmlspecialchars($module['id']); ?>" name="module_ids[]" value="<?php echo htmlspecialchars($module['id']); ?>" 
                                                <?php echo (isset($_POST['module_ids']) && in_array($module['id'], $_POST['module_ids'])) ? 'checked' : ''; ?>>
                                            <label for="module_<?php echo htmlspecialchars($module['id']); ?>"><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></label>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">Aucun module pour le semestre 1</p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label><strong>Semestre 2</strong></label>
                                <?php 
                                $hasSemestre2 = false;
                                foreach ($modules as $module) {
                                    if ($module['semestre'] === 'S2') {
                                        $hasSemestre2 = true;
                                        break;
                                    }
                                }
                                
                                if ($hasSemestre2): 
                                ?>
                                    <?php foreach ($modules as $module): ?>
                                        <?php if ($module['semestre'] === 'S2'): ?>
                                        <div class="form-check">
                                            <input type="checkbox" id="module_<?php echo htmlspecialchars($module['id']); ?>" name="module_ids[]" value="<?php echo htmlspecialchars($module['id']); ?>"
                                                <?php echo (isset($_POST['module_ids']) && in_array($module['id'], $_POST['module_ids'])) ? 'checked' : ''; ?>>
                                            <label for="module_<?php echo htmlspecialchars($module['id']); ?>"><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></label>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">Aucun module pour le semestre 2</p>
                                <?php endif; ?>
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
                <a href="<?php echo $base_path; ?>index.php" class="btn btn-secondary">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Événement pour soumettre le formulaire lors du changement de filière
        const filiereSelect = document.getElementById('filiere_id');
        if (filiereSelect) {
            filiereSelect.addEventListener('change', function() {
                // Créer un formulaire temporaire pour soumettre uniquement la sélection de filière
                const tempForm = document.createElement('form');
                tempForm.method = 'POST';
                tempForm.action = 'register.php';
                
                // Ajouter le champ filière_id
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'filiere_id';
                input.value = this.value;
                tempForm.appendChild(input);
                
                // Ajouter les autres champs du formulaire si nécessaire
                const formInputs = document.querySelectorAll('form input:not([type="checkbox"]), form select');
                formInputs.forEach(function(originalInput) {
                    if (originalInput.name && originalInput.name !== 'filiere_id' && originalInput.name !== 'register') {
                        const cloneInput = document.createElement('input');
                        cloneInput.type = 'hidden';
                        cloneInput.name = originalInput.name;
                        cloneInput.value = originalInput.value;
                        tempForm.appendChild(cloneInput);
                    }
                });
                
                // Soumettre le formulaire
                document.body.appendChild(tempForm);
                tempForm.submit();
            });
        }

        // Mise à jour du label du fichier photo
        const photoInput = document.getElementById('photo');
        if (photoInput) {
            photoInput.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Choisir un fichier';
                const fileLabel = document.querySelector('.custom-file-label');
                if (fileLabel) {
                    fileLabel.textContent = fileName;
                }
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
