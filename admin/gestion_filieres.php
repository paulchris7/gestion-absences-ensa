<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est un administrateur
require_admin();

// Initialiser les variables
$filieres = [];
$filiere = [
    'id' => '',
    'code' => '',
    'nom' => '',
    'description' => ''
];
$mode = 'create';

try {
    $pdo = connect();
    
    // Traitement de la suppression
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        
        // Vérifier si la filière existe
        $stmt = $pdo->prepare("SELECT * FROM filieres WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $filiere_to_delete = $stmt->fetch();
        
        if ($filiere_to_delete) {
            // Vérifier si des étudiants sont inscrits dans cette filière
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE filiere_id = :filiere_id");
            $stmt->execute(['filiere_id' => $id]);
            $count_etudiants = $stmt->fetchColumn();
            
            // Vérifier si des modules sont associés à cette filière
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE filiere_id = :filiere_id");
            $stmt->execute(['filiere_id' => $id]);
            $count_modules = $stmt->fetchColumn();
            
            if ($count_etudiants > 0 || $count_modules > 0) {
                $_SESSION['error'] = "Impossible de supprimer cette filière car elle est associée à des étudiants ou des modules.";
            } else {
                // Supprimer la filière
                $stmt = $pdo->prepare("DELETE FROM filieres WHERE id = :id");
                $stmt->execute(['id' => $id]);
                
                $_SESSION['success'] = "La filière a été supprimée avec succès.";
            }
        } else {
            $_SESSION['error'] = "Filière non trouvée.";
        }
        
        // Rediriger pour éviter les soumissions multiples
        header('Location: gestion_filieres.php');
        exit;
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim($_POST['code'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        // Validation des champs
        if (empty($code) || empty($nom)) {
            $_SESSION['error'] = "Le code et le nom sont obligatoires.";
        } else {
            // Vérifier si le code existe déjà (sauf pour la mise à jour)
            $query = "SELECT COUNT(*) FROM filieres WHERE code = :code";
            $params = ['code' => $code];
            
            if ($id > 0) {
                $query .= " AND id != :id";
                $params['id'] = $id;
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Ce code de filière existe déjà.";
            } else {
                if ($id === 0) {
                    // Création d'une nouvelle filière
                    $stmt = $pdo->prepare("
                        INSERT INTO filieres (code, nom, description)
                        VALUES (:code, :nom, :description)
                    ");
                    $stmt->execute([
                        'code' => $code,
                        'nom' => $nom,
                        'description' => $description
                    ]);
                    
                    $_SESSION['success'] = "La filière a été créée avec succès.";
                } else {
                    // Mise à jour d'une filière existante
                    $stmt = $pdo->prepare("
                        UPDATE filieres
                        SET code = :code, nom = :nom, description = :description
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'code' => $code,
                        'nom' => $nom,
                        'description' => $description,
                        'id' => $id
                    ]);
                    
                    $_SESSION['success'] = "La filière a été mise à jour avec succès.";
                }
                
                // Rediriger pour éviter les soumissions multiples
                header('Location: gestion_filieres.php');
                exit;
            }
        }
    }
    
    // Récupération d'une filière pour édition
    if (isset($_GET['edit']) && !empty($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        
        $stmt = $pdo->prepare("SELECT * FROM filieres WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $filiere = $stmt->fetch();
        
        if ($filiere) {
            $mode = 'edit';
        } else {
            $_SESSION['error'] = "Filière non trouvée.";
            header('Location: gestion_filieres.php');
            exit;
        }
    }
    
    // Récupération de toutes les filières
    $stmt = $pdo->query("SELECT * FROM filieres ORDER BY code");
    $filieres = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur de base de données.';
    error_log('Erreur gestion_filieres: ' . $e->getMessage());
}

// Inclure l'en-tête
$page_title = "Gestion des filières";
include '../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h1>Gestion des filières</h1>
    <button id="showFormBtn" class="btn">
        <i class="fas fa-plus"></i> Nouvelle filière
    </button>
</div>

<?php display_alert(); ?>

<div class="card mb-20" id="formCard" style="<?php echo ($mode === 'create' && !isset($_SESSION['error'])) ? 'display: none;' : ''; ?>">
    <div class="card-header">
        <h2><?php echo ($mode === 'edit') ? 'Modifier la filière' : 'Ajouter une filière'; ?></h2>
    </div>
    <div class="card-body">
        <form action="gestion_filieres.php" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($filiere['id']); ?>">
            
            <div class="form-group">
                <label for="code">Code de la filière</label>
                <input type="text" class="form-control" id="code" name="code"
                       value="<?php echo htmlspecialchars($filiere['code']); ?>" required>
                <small>Ex: GI, RSSP, GIL...</small>
            </div>
            
            <div class="form-group">
                <label for="nom">Nom de la filière</label>
                <input type="text" class="form-control" id="nom" name="nom"
                       value="<?php echo htmlspecialchars($filiere['nom']); ?>" required>
                <small>Ex: Génie Informatique, Réseaux et Systèmes de Sécurité et de Production...</small>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?php
                    echo htmlspecialchars($filiere['description']);
                ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> <?php echo ($mode === 'edit') ? 'Mettre à jour' : 'Enregistrer'; ?>
                </button>
                <a href="gestion_filieres.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des filières</h2>
    </div>
    <div class="card-body">
        <?php if (empty($filieres)): ?>
            <p>Aucune filière enregistrée.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filieres as $f): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['code']); ?></td>
                            <td><?php echo htmlspecialchars($f['nom']); ?></td>
                            <td><?php echo htmlspecialchars($f['description']); ?></td>
                            <td class="table-actions">
                                <a href="gestion_filieres.php?edit=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-danger delete-btn"
                                   data-id="<?php echo (int)$f['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($f['code'] . ' - ' . $f['nom']); ?>">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div class="modal-backdrop" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Confirmation de suppression</h3>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer la filière <strong id="filiereName"></strong> ?</p>
            <p>Cette action est irréversible.</p>
        </div>
        <div class="modal-footer">
            <a href="#" class="btn btn-danger" id="confirmDelete">Supprimer</a>
            <button class="btn btn-secondary" id="cancelDelete">Annuler</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion du formulaire
        const showFormBtn = document.getElementById('showFormBtn');
        const formCard = document.getElementById('formCard');
        
        if (showFormBtn && formCard) {
            showFormBtn.addEventListener('click', function() {
                formCard.style.display = 'block';
            });
        }
        
        // Gestion du modal de suppression
        const deleteModal = document.getElementById('deleteModal');
        const closeModal = document.getElementById('closeModal');
        const cancelDelete = document.getElementById('cancelDelete');
        const confirmDelete = document.getElementById('confirmDelete');
        const filiereName = document.getElementById('filiereName');
        const deleteButtons = document.querySelectorAll('.delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                filiereName.textContent = name;
                confirmDelete.href = `gestion_filieres.php?delete=${id}`;
                deleteModal.classList.add('active');
            });
        });
        
        if (closeModal) {
            closeModal.addEventListener('click', function() {
                deleteModal.classList.remove('active');
            });
        }
        
        if (cancelDelete) {
            cancelDelete.addEventListener('click', function() {
                deleteModal.classList.remove('active');
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
