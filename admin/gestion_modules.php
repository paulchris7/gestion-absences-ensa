<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est un administrateur
require_admin();

// Initialiser les variables
$filieres = [];
$responsables = [];
$modules = [];
$module = [
    'id' => '',
    'code' => '',
    'nom' => '',
    'filiere_id' => '',
    'semestre' => '',
    'responsables' => []
];
$mode = 'create';
$filiere_filter = isset($_GET['filiere']) ? (int)$_GET['filiere'] : 0;

try {
    $pdo = connect();
    
    // Récupérer toutes les filières
    $stmt = $pdo->query("SELECT * FROM filieres ORDER BY code");
    $filieres = $stmt->fetchAll();
    
    // Récupérer tous les responsables
    $stmt = $pdo->query("SELECT * FROM responsables ORDER BY nom, prenom");
    $responsables = $stmt->fetchAll();
    
    // Traitement de la suppression
    if (isset($_GET['delete']) && !empty($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        
        // Vérifier si le module existe
        $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $module_to_delete = $stmt->fetch();
        
        if ($module_to_delete) {
            // Vérifier si des étudiants sont inscrits à ce module
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions_modules WHERE module_id = :module_id");
            $stmt->execute(['module_id' => $id]);
            $count_inscriptions = $stmt->fetchColumn();
            
            // Vérifier si des séances sont associées à ce module
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM seances WHERE module_id = :module_id");
            $stmt->execute(['module_id' => $id]);
            $count_seances = $stmt->fetchColumn();
            
            if ($count_inscriptions > 0 || $count_seances > 0) {
                $_SESSION['error'] = "Impossible de supprimer ce module car il est associé à des étudiants ou des séances.";
            } else {
                // Supprimer les associations avec les responsables
                $stmt = $pdo->prepare("DELETE FROM responsables_modules WHERE module_id = :module_id");
                $stmt->execute(['module_id' => $id]);
                
                // Supprimer le module
                $stmt = $pdo->prepare("DELETE FROM modules WHERE id = :id");
                $stmt->execute(['id' => $id]);
                
                $_SESSION['success'] = "Le module a été supprimé avec succès.";
            }
        } else {
            $_SESSION['error'] = "Module non trouvé.";
        }
        
        // Rediriger pour éviter les soumissions multiples
        header('Location: gestion_modules.php' . ($filiere_filter ? "?filiere=$filiere_filter" : ''));
        exit;
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $code = trim($_POST['code'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $filiere_id = (int)($_POST['filiere_id'] ?? 0);
        $semestre = in_array($_POST['semestre'] ?? '', ['S1', 'S2']) ? $_POST['semestre'] : '';
        $responsable_ids = isset($_POST['responsable_ids']) ? array_map('intval', $_POST['responsable_ids']) : [];
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        // Validation des champs
        if (empty($code) || empty($nom) || $filiere_id <= 0 || empty($semestre)) {
            $_SESSION['error'] = "Tous les champs sont obligatoires.";
        } else {
            // Vérifier si le code existe déjà (sauf pour la mise à jour)
            $query = "SELECT COUNT(*) FROM modules WHERE code = :code";
            $params = ['code' => $code];
            
            if ($id > 0) {
                $query .= " AND id != :id";
                $params['id'] = $id;
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Ce code de module existe déjà.";
            } else {
                $pdo->beginTransaction();
                
                try {
                    if ($id === 0) {
                        // Création d'un nouveau module
                        $stmt = $pdo->prepare("
                            INSERT INTO modules (code, nom, filiere_id, semestre)
                            VALUES (:code, :nom, :filiere_id, :semestre)
                        ");
                        $stmt->execute([
                            'code' => $code,
                            'nom' => $nom,
                            'filiere_id' => $filiere_id,
                            'semestre' => $semestre
                        ]);
                        
                        $module_id = $pdo->lastInsertId();
                        $_SESSION['success'] = "Le module a été créé avec succès.";
                    } else {
                        // Mise à jour d'un module existant
                        $stmt = $pdo->prepare("
                            UPDATE modules
                            SET code = :code, nom = :nom, filiere_id = :filiere_id, semestre = :semestre
                            WHERE id = :id
                        ");
                        $stmt->execute([
                            'code' => $code,
                            'nom' => $nom,
                            'filiere_id' => $filiere_id,
                            'semestre' => $semestre,
                            'id' => $id
                        ]);
                        
                        $module_id = $id;
                        $_SESSION['success'] = "Le module a été mis à jour avec succès.";
                        
                        // Supprimer les anciennes associations avec les responsables
                        $stmt = $pdo->prepare("DELETE FROM responsables_modules WHERE module_id = :module_id");
                        $stmt->execute(['module_id' => $module_id]);
                    }
                    
                    // Ajouter les nouvelles associations avec les responsables
                    if (!empty($responsable_ids)) {
                        foreach ($responsable_ids as $responsable_id) {
                            $stmt = $pdo->prepare("
                                INSERT INTO responsables_modules (module_id, responsable_id)
                                VALUES (:module_id, :responsable_id)
                            ");
                            $stmt->execute([
                                'module_id' => $module_id,
                                'responsable_id' => $responsable_id
                            ]);
                        }
                    }
                    
                    $pdo->commit();
                    
                    // Rediriger pour éviter les soumissions multiples
                    header('Location: gestion_modules.php' . ($filiere_filter ? "?filiere=$filiere_filter" : ''));
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Erreur lors de l'enregistrement.";
                    error_log('Erreur gestion_modules: ' . $e->getMessage());
                }
            }
        }
    }
    
    // Récupération d'un module pour édition
    if (isset($_GET['edit']) && !empty($_GET['edit'])) {
        $id = (int)$_GET['edit'];
        
        $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $module = $stmt->fetch();
        
        if ($module) {
            // Récupérer les responsables associés à ce module
            $stmt = $pdo->prepare("
                SELECT responsable_id FROM responsables_modules WHERE module_id = :module_id
            ");
            $stmt->execute(['module_id' => $id]);
            $module_responsables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $module['responsables'] = $module_responsables;
            $mode = 'edit';
        } else {
            $_SESSION['error'] = "Module non trouvé.";
            header('Location: gestion_modules.php' . ($filiere_filter ? "?filiere=$filiere_filter" : ''));
            exit;
        }
    }
    
    // Récupération de tous les modules (avec filtre optionnel)
    $query = "
        SELECT m.*, f.code as filiere_code, f.nom as filiere_nom
        FROM modules m
        JOIN filieres f ON m.filiere_id = f.id
    ";
    
    if ($filiere_filter > 0) {
        $query .= " WHERE m.filiere_id = :filiere_id";
    }
    
    $query .= " ORDER BY f.code, m.semestre, m.code";
    
    $stmt = $pdo->prepare($query);
    
    if ($filiere_filter > 0) {
        $stmt->execute(['filiere_id' => $filiere_filter]);
    } else {
        $stmt->execute();
    }
    
    $modules = $stmt->fetchAll();
    
    // Récupérer les responsables pour chaque module
    foreach ($modules as &$m) {
        $stmt = $pdo->prepare("
            SELECT r.nom, r.prenom
            FROM responsables_modules mr
            JOIN responsables r ON mr.responsable_id = r.id
            WHERE mr.module_id = :module_id
        ");
        $stmt->execute(['module_id' => $m['id']]);
        $m['responsables'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur de base de données.';
    error_log('Erreur gestion_modules: ' . $e->getMessage());
}

// Inclure l'en-tête
$page_title = "Gestion des modules";
include '../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h1>Gestion des modules</h1>
    <button id="showFormBtn" class="btn">
        <i class="fas fa-plus"></i> Nouveau module
    </button>
</div>

<?php display_alert(); ?>

<div class="card mb-20" id="formCard" style="<?php echo ($mode === 'create' && !isset($_SESSION['error'])) ? 'display: none;' : ''; ?>">
    <div class="card-header">
        <h2><?php echo ($mode === 'edit') ? 'Modifier le module' : 'Ajouter un module'; ?></h2>
    </div>
    <div class="card-body">
        <form action="gestion_modules.php<?php echo $filiere_filter ? "?filiere=$filiere_filter" : ''; ?>" method="post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($module['id']); ?>">
            
            <div class="form-group">
                <label for="code">Code du module</label>
                <input type="text" class="form-control" id="code" name="code"
                       value="<?php echo htmlspecialchars($module['code']); ?>" required>
                <small>Ex: GI101, RSSP201...</small>
            </div>
            
            <div class="form-group">
                <label for="nom">Nom du module</label>
                <input type="text" class="form-control" id="nom" name="nom"
                       value="<?php echo htmlspecialchars($module['nom']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="filiere_id">Filière</label>
                <select class="form-select" id="filiere_id" name="filiere_id" required>
                    <option value="">Sélectionner une filière</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>"
                            <?php echo ($module['filiere_id'] == $f['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['code'] . ' - ' . $f['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="semestre">Semestre</label>
                <select class="form-select" id="semestre" name="semestre" required>
                    <option value="">Sélectionner un semestre</option>
                    <option value="S1" <?php echo ($module['semestre'] === 'S1') ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="S2" <?php echo ($module['semestre'] === 'S2') ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Responsables du module</label>
                <div class="card">
                    <div class="card-body">
                        <?php foreach ($responsables as $r): ?>
                            <div class="form-check">
                                <input type="checkbox" id="responsable_<?php echo (int)$r['id']; ?>"
                                       name="responsable_ids[]" value="<?php echo (int)$r['id']; ?>"
                                    <?php echo (in_array($r['id'], $module['responsables'] ?? [])) ? 'checked' : ''; ?>>
                                <label for="responsable_<?php echo (int)$r['id']; ?>">
                                    <?php echo htmlspecialchars($r['prenom'] . ' ' . $r['nom']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> <?php echo ($mode === 'edit') ? 'Mettre à jour' : 'Enregistrer'; ?>
                </button>
                <a href="gestion_modules.php<?php echo $filiere_filter ? "?filiere=$filiere_filter" : ''; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card mb-20">
    <div class="card-header">
        <h2>Filtres</h2>
    </div>
    <div class="card-body">
        <form action="gestion_modules.php" method="get" class="flex gap-10">
            <div class="form-group" style="flex: 1;">
                <label for="filiere">Filière</label>
                <select class="form-select" id="filiere" name="filiere">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $f): ?>
                        <option value="<?php echo (int)$f['id']; ?>"
                            <?php echo ($filiere_filter == $f['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['code'] . ' - ' . $f['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="gestion_modules.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des modules</h2>
    </div>
    <div class="card-body">
        <?php if (empty($modules)): ?>
            <p>Aucun module enregistré.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Filière</th>
                        <th>Semestre</th>
                        <th>Responsables</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['code']); ?></td>
                            <td><?php echo htmlspecialchars($m['nom']); ?></td>
                            <td><?php echo htmlspecialchars($m['filiere_code'] . ' - ' . $m['filiere_nom']); ?></td>
                            <td><?php echo htmlspecialchars($m['semestre']); ?></td>
                            <td>
                                <?php if (empty($m['responsables'])): ?>
                                    <em>Aucun responsable</em>
                                <?php else: ?>
                                    <ul>
                                        <?php foreach ($m['responsables'] as $resp): ?>
                                            <li><?php echo htmlspecialchars($resp['prenom'] . ' ' . $resp['nom']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <a href="gestion_modules.php?edit=<?php echo (int)$m['id']; ?><?php echo $filiere_filter ? "&filiere=$filiere_filter" : ''; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="#" class="btn btn-sm btn-danger delete-btn"
                                   data-id="<?php echo (int)$m['id']; ?>"
                                   data-name="<?php echo htmlspecialchars($m['code'] . ' - ' . $m['nom']); ?>">
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
            <p>Êtes-vous sûr de vouloir supprimer le module <strong id="moduleName"></strong> ?</p>
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
        const moduleName = document.getElementById('moduleName');
        const deleteButtons = document.querySelectorAll('.delete-btn');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                moduleName.textContent = name;
                confirmDelete.href = `gestion_modules.php?delete=${id}<?php echo $filiere_filter ? "&filiere=$filiere_filter" : ''; ?>`;
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
        
        // Soumission automatique du formulaire de filtre
        const filiereSelect = document.getElementById('filiere');
        if (filiereSelect) {
            filiereSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
