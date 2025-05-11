<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est un étudiant
require_etudiant();

// Initialiser les variables
$absences = [];
$modules = [];
$module_id = isset($_GET['module_id']) ? (int)$_GET['module_id'] : null;
$semestre = isset($_GET['semestre']) && in_array($_GET['semestre'], ['S1', 'S2']) ? $_GET['semestre'] : null;

try {
    $pdo = connect();
    
    // Récupérer les modules de l'étudiant
    $stmt = $pdo->prepare("
        SELECT m.id, m.code, m.nom, m.semestre
        FROM inscriptions_modules im
        JOIN modules m ON im.module_id = m.id
        WHERE im.etudiant_id = :etudiant_id
        ORDER BY m.semestre, m.nom
    ");
    $stmt->execute(['etudiant_id' => $_SESSION['user_id']]);
    $modules = $stmt->fetchAll();
    
    // Construire la requête pour les absences
    $query = "
        SELECT a.justifiee, s.date_seance, s.heure_debut, s.heure_fin, s.type_seance, s.salle,
               m.nom as module_nom, m.code as module_code, m.semestre
        FROM absences a
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        WHERE a.etudiant_id = :etudiant_id
    ";
    
    $params = ['etudiant_id' => $_SESSION['user_id']];
    
    // Filtrer par module si spécifié
    if ($module_id) {
        $query .= " AND m.id = :module_id";
        $params['module_id'] = $module_id;
    }
    
    // Filtrer par semestre si spécifié
    if ($semestre) {
        $query .= " AND m.semestre = :semestre";
        $params['semestre'] = $semestre;
    }
    
    $query .= " ORDER BY s.date_seance DESC, s.heure_debut DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $absences = $stmt->fetchAll();
    
    // Calculer les statistiques
    $total_absences = count($absences);
    $absences_justifiees = 0;
    
    foreach ($absences as $absence) {
        if ($absence['justifiee']) {
            $absences_justifiees++;
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Une erreur est survenue lors de la récupération des données.';
    error_log('Erreur bilan_absences: ' . $e->getMessage());
}

// Inclure l'en-tête
$page_title = "Bilan des absences";
include '../includes/header.php';
?>

<h1 class="mb-20">Bilan des absences</h1>

<?php display_alert(); ?>

<div class="card mb-20">
    <div class="card-header">
        <h2>Filtres</h2>
    </div>
    <div class="card-body">
        <form action="bilan_absences.php" method="get" class="flex gap-10" style="flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="module_id">Module</label>
                <select class="form-select" id="module_id" name="module_id">
                    <option value="">Tous les modules</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?php echo htmlspecialchars($module['id']); ?>" 
                            <?php echo ($module_id == $module['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="semestre">Semestre</label>
                <select class="form-select" id="semestre" name="semestre">
                    <option value="">Tous les semestres</option>
                    <option value="S1" <?php echo ($semestre === 'S1') ? 'selected' : ''; ?>>Semestre 1</option>
                    <option value="S2" <?php echo ($semestre === 'S2') ? 'selected' : ''; ?>>Semestre 2</option>
                </select>
            </div>
            
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="bilan_absences.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card primary">
        <i class="fas fa-calendar-times"></i>
        <div class="stat-card-content">
            <h3><?php echo $total_absences; ?></h3>
            <p>Total absences</p>
        </div>
    </div>
    
    <div class="stat-card success">
        <i class="fas fa-check-circle"></i>
        <div class="stat-card-content">
            <h3><?php echo $absences_justifiees; ?></h3>
            <p>Absences justifiées</p>
        </div>
    </div>
    
    <div class="stat-card danger">
        <i class="fas fa-exclamation-circle"></i>
        <div class="stat-card-content">
            <h3><?php echo $total_absences - $absences_justifiees; ?></h3>
            <p>Absences non justifiées</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des absences</h2>
    </div>
    <div class="card-body">
        <?php if (empty($absences)): ?>
            <p>Aucune absence enregistrée.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Module</th>
                        <th>Type</th>
                        <th>Salle</th>
                        <th>Justifiée</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $absence): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($absence['date_seance'])); ?></td>
                            <td><?php echo date('H:i', strtotime($absence['heure_debut'])) . ' - ' . date('H:i', strtotime($absence['heure_fin'])); ?></td>
                            <td><?php echo htmlspecialchars($absence['module_code'] . ' - ' . $absence['module_nom']); ?></td>
                            <td><?php echo htmlspecialchars($absence['type_seance']); ?></td>
                            <td><?php echo htmlspecialchars($absence['salle']); ?></td>
                            <td>
                                <?php if ($absence['justifiee']): ?>
                                    <span class="badge success">Oui</span>
                                <?php else: ?>
                                    <span class="badge danger">Non</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
