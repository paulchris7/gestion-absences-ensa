<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérification des droits d'accès
require_etudiant();

// Initialisation des variables
$etudiant = null;
$modules = [];
$absences = [];
$total_absences = 0;
$absences_justifiees = 0;

try {
    $pdo = connect();

    // Récupération des informations étudiant
    $stmt = $pdo->prepare("
        SELECT e.*, f.nom as filiere_nom, f.code as filiere_code
        FROM etudiants e
        JOIN filieres f ON e.filiere_id = f.id
        WHERE e.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $etudiant = $stmt->fetch();

    if (!$etudiant) {
        throw new Exception('Profil étudiant introuvable');
    }

    // Modules inscrits
    $stmt = $pdo->prepare("
        SELECT m.*, im.annee_universitaire
        FROM inscriptions_modules im
        JOIN modules m ON im.module_id = m.id
        WHERE im.etudiant_id = ?
        ORDER BY m.semestre, m.nom
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $modules = $stmt->fetchAll();

    // Dernières absences
    $stmt = $pdo->prepare("
        SELECT a.*, s.date_seance, s.heure_debut, s.heure_fin, s.type_seance,
               m.nom as module_nom, m.code as module_code
        FROM absences a
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        WHERE a.etudiant_id = ?
        ORDER BY s.date_seance DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $absences = $stmt->fetchAll();

    // Statistiques des absences
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE etudiant_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_absences = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE etudiant_id = ? AND justifiee = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $absences_justifiees = $stmt->fetchColumn();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur de base de données';
    error_log('DB Error in dashboard_etudiant: ' . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

include 'includes/header.php';
?>

<h1 class="mb-20">Tableau de bord étudiant</h1>

<?php display_alert(); ?>

<?php if ($etudiant): ?>
<div class="card mb-20">
    <div class="card-header">
        <h2>Informations personnelles</h2>
    </div>
    <div class="card-body">
        <div class="flex" style="flex-wrap: wrap; gap: 20px;">
            <div style="flex: 1; min-width: 300px;">
                <p><strong>Nom:</strong> <?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></p>
                <p><strong>Numéro Apogée:</strong> <?= htmlspecialchars($etudiant['apogee']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($etudiant['email']) ?></p>
                <p><strong>Filière:</strong> <?= htmlspecialchars($etudiant['filiere_code'] . ' - ' . $etudiant['filiere_nom']) ?></p>
                <p><strong>Date d'inscription:</strong> <?= date('d/m/Y', strtotime($etudiant['date_inscription'])) ?></p>
            </div>
            <?php if (!empty($etudiant['photo'])): ?>
            <div style="flex: 0 0 150px;">
                <img src="<?= htmlspecialchars($etudiant['photo']) ?>" alt="Photo de profil" class="profile-photo" style="width: 150px; height: 150px; object-fit: cover; border-radius: 5px;">
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="dashboard-stats">
    <div class="stat-card primary">
        <i class="fas fa-book"></i>
        <div class="stat-card-content">
            <h3><?= count($modules) ?></h3>
            <p>Modules</p>
        </div>
    </div>
    <div class="stat-card danger">
        <i class="fas fa-calendar-times"></i>
        <div class="stat-card-content">
            <h3><?= $total_absences ?></h3>
            <p>Absences</p>
        </div>
    </div>
    <div class="stat-card success">
        <i class="fas fa-check-circle"></i>
        <div class="stat-card-content">
            <h3><?= $absences_justifiees ?></h3>
            <p>Justifiées</p>
        </div>
    </div>
    <div class="stat-card warning">
        <i class="fas fa-exclamation-circle"></i>
        <div class="stat-card-content">
            <h3><?= $total_absences - $absences_justifiees ?></h3>
            <p>Non justifiées</p>
        </div>
    </div>
</div>

<!-- Modules et absences -->
<div class="flex gap-10" style="flex-wrap: wrap; margin-top: 20px;">
    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h2>Mes modules</h2>
        </div>
        <div class="card-body">
            <?php if (empty($modules)): ?>
                <p>Aucun module trouvé.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Module</th>
                            <th>Semestre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modules as $module): ?>
                        <tr>
                            <td><?= htmlspecialchars($module['code']) ?></td>
                            <td><?= htmlspecialchars($module['nom']) ?></td>
                            <td><?= htmlspecialchars($module['semestre']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h2>Dernières absences</h2>
            <a href="etudiant/bilan_absences.php" class="btn btn-sm">Voir tout</a>
        </div>
        <div class="card-body">
            <?php if (empty($absences)): ?>
                <p>Aucune absence enregistrée.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Module</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absences as $absence): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($absence['date_seance'])) ?></td>
                            <td><?= htmlspecialchars($absence['module_nom']) ?></td>
                            <td>
                                <?php if ($absence['justifiee']): ?>
                                    <span class="badge success">Justifiée</span>
                                <?php else: ?>
                                    <span class="badge danger">Non justifiée</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section pour justifier une absence -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h2>Justifier une absence</h2>
        </div>
        <div class="card-body">
            <form action="etudiant/upload_justificatif.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="module">Module concerné</label>
                    <select class="form-control" id="module" name="module_id" required>
                        <option value="">Sélectionnez un module</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?= $module['id'] ?>">
                                <?= htmlspecialchars($module['code'] . ' - ' . $module['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_absence">Date de l'absence</label>
                    <input type="date" class="form-control" id="date_absence" name="date_absence" required>
                </div>
                
                <div class="form-group">
                    <label for="justificatif">Fichier justificatif</label>
                    <input type="file" class="form-control" id="justificatif" name="justificatif" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small>Formats acceptés : PDF, JPG, PNG (taille max : 2MB)</small>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-paper-plane"></i> Envoyer le justificatif
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
