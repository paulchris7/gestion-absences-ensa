<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est un administrateur
require_admin();

// Initialiser les variables
$etudiants = [];
$filiere_filter = isset($_GET['filiere']) ? (int)$_GET['filiere'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = connect();
    
    // Récupérer toutes les filières
    $stmt = $pdo->query("SELECT * FROM filieres ORDER BY code");
    $filieres = $stmt->fetchAll();
    
    // Construire la requête pour les étudiants
    $query = "
        SELECT e.id, e.apogee, e.nom, e.prenom, e.email, e.date_inscription,
               f.code as filiere_code, f.nom as filiere_nom
        FROM etudiants e
        JOIN filieres f ON e.filiere_id = f.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtrer par filière si spécifié
    if ($filiere_filter > 0) {
        $query .= " AND e.filiere_id = :filiere_id";
        $params[':filiere_id'] = $filiere_filter;
    }
    
    // Filtrer par recherche si spécifié
    if (!empty($search)) {
        $query .= " AND (e.nom LIKE :search OR e.prenom LIKE :search OR e.apogee LIKE :search OR e.email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY e.nom, e.prenom";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $etudiants = $stmt->fetchAll();
    
    // Récupérer les modules pour chaque étudiant
    foreach ($etudiants as &$etudiant) {
        $stmt = $pdo->prepare("
            SELECT m.code, m.nom, m.semestre
            FROM inscriptions_modules im
            JOIN modules m ON im.module_id = m.id
            WHERE im.etudiant_id = :etudiant_id
            ORDER BY m.semestre, m.code
        ");
        $stmt->execute([':etudiant_id' => $etudiant['id']]);
        $etudiant['modules'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur lors de la recherche. Veuillez réessayer.';
    error_log('Erreur recherche étudiants: ' . $e->getMessage());
}

// Inclure l'en-tête
$page_title = "Gestion des étudiants";
include '../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h1>Gestion des étudiants</h1>
</div>

<?php display_alert(); ?>

<div class="card mb-20">
    <div class="card-header">
        <h2>Filtres</h2>
    </div>
    <div class="card-body">
        <form action="gestion_etudiants.php" method="get" class="flex gap-10" style="flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="filiere">Filière</label>
                <select class="form-select" id="filiere" name="filiere">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $filiere): ?>
                        <option value="<?php echo (int)$filiere['id']; ?>"
                            <?php echo ($filiere_filter == $filiere['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex: 2; min-width: 300px;">
                <label for="search">Recherche</label>
                <input type="text" class="form-control" id="search" name="search"
                       value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Nom, prénom, apogée ou email...">
            </div>
            
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="gestion_etudiants.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des étudiants</h2>
    </div>
    <div class="card-body">
        <?php if (empty($etudiants)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucun étudiant trouvé.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Apogée</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Filière</th>
                            <th>Modules</th>
                            <th>Date d'inscription</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etudiants as $etudiant): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($etudiant['apogee']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                <td><?php echo htmlspecialchars($etudiant['filiere_code']); ?></td>
                                <td>
                                    <?php if (empty($etudiant['modules'])): ?>
                                        <em>Aucun module</em>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary show-modules"
                                                data-etudiant="<?php echo (int)$etudiant['id']; ?>">
                                            Voir les modules (<?php echo count($etudiant['modules']); ?>)
                                        </button>
                                        <div class="modules-list" id="modules-<?php echo (int)$etudiant['id']; ?>" style="display: none;">
                                            <div class="form-group">
                                                <label><strong>Semestre 1</strong></label>
                                                <ul>
                                                    <?php foreach ($etudiant['modules'] as $module): ?>
                                                        <?php if ($module['semestre'] === 'S1'): ?>
                                                            <li><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label><strong>Semestre 2</strong></label>
                                                <ul>
                                                    <?php foreach ($etudiant['modules'] as $module): ?>
                                                        <?php if ($module['semestre'] === 'S2'): ?>
                                                            <li><?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?></li>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($etudiant['date_inscription'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Soumission automatique du formulaire de filtre
        const filiereSelect = document.getElementById('filiere');
        if (filiereSelect) {
            filiereSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }
        
        // Affichage des modules
        const showModulesButtons = document.querySelectorAll('.show-modules');
        showModulesButtons.forEach(button => {
            button.addEventListener('click', function() {
                const etudiantId = this.getAttribute('data-etudiant');
                const modulesList = document.getElementById(`modules-${etudiantId}`);
                
                if (modulesList.style.display === 'none') {
                    modulesList.style.display = 'block';
                    this.textContent = 'Masquer les modules';
                } else {
                    modulesList.style.display = 'none';
                    this.textContent = `Voir les modules (${modulesList.querySelectorAll('li').length})`;
                }
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
