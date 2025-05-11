<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

require_admin();

// Initialisation des variables
$absences = [];
$filieres = [];
$modules = [];
$filters = [
    'filiere_id' => isset($_GET['filiere_id']) ? (int)$_GET['filiere_id'] : 0,
    'module_id' => isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0,
    'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
    'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : '',
    'justifiee' => isset($_GET['justifiee']) ? $_GET['justifiee'] : ''
];

try {
    $pdo = connect();
    
    // Récupérer toutes les filières
    $filieres = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY code")->fetchAll();
    
    // Récupérer les modules en fonction de la filière sélectionnée
    $module_query = "SELECT id, code, nom, semestre FROM modules";
    $module_params = [];
    
    if ($filters['filiere_id'] > 0) {
        $module_query .= " WHERE filiere_id = :filiere_id";
        $module_params[':filiere_id'] = $filters['filiere_id'];
    }
    
    $module_query .= " ORDER BY semestre, code";
    $stmt = $pdo->prepare($module_query);
    $stmt->execute($module_params);
    $modules = $stmt->fetchAll();
    
    // Charger les absences avec jointure optimisée
    $absence_query = "
        SELECT a.id, a.justifiee, a.date_enregistrement,
               e.id as etudiant_id, e.apogee, e.nom as etudiant_nom, e.prenom as etudiant_prenom,
               s.id as seance_id, s.date_seance, s.heure_debut, s.heure_fin, s.type_seance, s.salle,
               m.id as module_id, m.code as module_code, m.nom as module_nom,
               f.id as filiere_id, f.code as filiere_code, f.nom as filiere_nom,
               j.id as justificatif_id, j.fichier_path, j.statut as justificatif_statut
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN justificatifs j ON (j.etudiant_id = e.id AND j.module_id = m.id AND j.date_absence = s.date_seance)
        WHERE 1=1
    ";
    
    $absence_params = [];
    
    if ($filters['filiere_id'] > 0) {
        $absence_query .= " AND m.filiere_id = :filiere_id";
        $absence_params[':filiere_id'] = $filters['filiere_id'];
    }
    
    if ($filters['module_id'] > 0) {
        $absence_query .= " AND m.id = :module_id";
        $absence_params[':module_id'] = $filters['module_id'];
    }
    
    if (!empty($filters['date_debut'])) {
        $absence_query .= " AND s.date_seance >= :date_debut";
        $absence_params[':date_debut'] = $filters['date_debut'];
    }
    
    if (!empty($filters['date_fin'])) {
        $absence_query .= " AND s.date_seance <= :date_fin";
        $absence_params[':date_fin'] = $filters['date_fin'];
    }
    
    if ($filters['justifiee'] !== '') {
        $absence_query .= " AND a.justifiee = :justifiee";
        $absence_params[':justifiee'] = (bool)$filters['justifiee'];
    }
    
    $absence_query .= " ORDER BY s.date_seance DESC, s.heure_debut DESC, e.nom, e.prenom";
    
    $stmt = $pdo->prepare($absence_query);
    $stmt->execute($absence_params);
    $absences = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Une erreur est survenue lors du chargement des données.';
    error_log("PDO Error in gestion_absences: " . $e->getMessage());
}

$page_title = "Gestion des absences";
include '../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h1>Gestion des absences</h1>
    <?php if (!empty($absences)): ?>
        <a href="generer_pdf.php?<?= http_build_query($filters) ?>" class="btn" target="_blank">
            <i class="fas fa-file-pdf"></i> Générer PDF
        </a>
    <?php endif; ?>
</div>

<?php display_alert(); ?>

<div class="card mb-20">
    <div class="card-header">
        <h2>Filtres</h2>
    </div>
    <div class="card-body">
        <form method="get" class="flex gap-10" style="flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="filiere_id">Filière</label>
                <select class="form-select" id="filiere_id" name="filiere_id">
                    <option value="">Toutes les filières</option>
                    <?php foreach ($filieres as $filiere): ?>
                        <option value="<?= $filiere['id'] ?>" <?= ($filters['filiere_id'] == $filiere['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="module_id">Module</label>
                <select class="form-select" id="module_id" name="module_id">
                    <option value="">Tous les modules</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>" <?= ($filters['module_id'] == $module['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($module['code'] . ' - ' . $module['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="date_debut">Date début</label>
                <input type="date" class="form-control" id="date_debut" name="date_debut" 
                       value="<?= htmlspecialchars($filters['date_debut']) ?>">
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="date_fin">Date fin</label>
                <input type="date" class="form-control" id="date_fin" name="date_fin" 
                       value="<?= htmlspecialchars($filters['date_fin']) ?>">
            </div>
            
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="justifiee">Justifiée</label>
                <select class="form-select" id="justifiee" name="justifiee">
                    <option value="">Toutes</option>
                    <option value="1" <?= ($filters['justifiee'] === '1') ? 'selected' : '' ?>>Oui</option>
                    <option value="0" <?= ($filters['justifiee'] === '0') ? 'selected' : '' ?>>Non</option>
                </select>
            </div>
            
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="gestion_absences.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des absences</h2>
    </div>
    <div class="card-body">
        <?php if (empty($absences)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune absence trouvée avec les critères sélectionnés.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Étudiant</th>
                            <th>Filière</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Salle</th>
                            <th>Statut</th>
                            <th>Justificatif</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($absence['date_seance'])) ?></td>
                                <td><?= date('H:i', strtotime($absence['heure_debut'])) ?>-<?= date('H:i', strtotime($absence['heure_fin'])) ?></td>
                                <td><?= htmlspecialchars($absence['apogee'] . ' - ' . $absence['etudiant_prenom'] . ' ' . $absence['etudiant_nom']) ?></td>
                                <td><?= htmlspecialchars($absence['filiere_code']) ?></td>
                                <td><?= htmlspecialchars($absence['module_code']) ?></td>
                                <td><?= htmlspecialchars($absence['type_seance']) ?></td>
                                <td><?= htmlspecialchars($absence['salle']) ?></td>
                                <td>
                                    <span class="badge <?= $absence['justifiee'] ? 'success' : 'danger' ?>">
                                        <?= $absence['justifiee'] ? 'Oui' : 'Non' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($absence['justificatif_id'])): ?>
                                        <a href="download_justificatif.php?id=<?= $absence['justificatif_id'] ?>" 
                                           target="_blank" 
                                           class="btn btn-sm">
                                            <i class="fas fa-file-pdf"></i> Voir
                                        </a>
                                        <span class="badge <?= 
                                            $absence['justificatif_statut'] === 'accepté' ? 'success' : 
                                            ($absence['justificatif_statut'] === 'rejeté' ? 'danger' : 'warning') ?>">
                                            <?= htmlspecialchars($absence['justificatif_statut']) ?>
                                        </span>
                                    <?php else: ?>
                                        <em>Aucun</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
