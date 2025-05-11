<?php
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier les droits d'accès
require_admin();

// Initialisation des statistiques
$stats = [
    'filieres' => 0,
    'modules' => 0,
    'etudiants' => 0,
    'absences' => 0
];

$derniers_etudiants = [];
$dernieres_absences = [];

try {
    $pdo = connect();
    
    // Requêtes pour les statistiques
    $stats['filieres'] = $pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();
    $stats['modules'] = $pdo->query("SELECT COUNT(*) FROM modules")->fetchColumn();
    $stats['etudiants'] = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
    $stats['absences'] = $pdo->query("SELECT COUNT(*) FROM absences")->fetchColumn();
    
    // Derniers étudiants inscrits
    $derniers_etudiants = $pdo->query("
        SELECT e.apogee, e.nom, e.prenom, f.nom as filiere_nom, e.date_inscription
        FROM etudiants e
        JOIN filieres f ON e.filiere_id = f.id
        ORDER BY e.date_inscription DESC
        LIMIT 5
    ")->fetchAll();
    
    // Dernières absences
    $dernieres_absences = $pdo->query("
        SELECT
            e.nom as etudiant_nom, e.prenom as etudiant_prenom,
            m.nom as module_nom,
            s.date_seance, s.type_seance,
            a.justifiee
        FROM absences a
        JOIN etudiants e ON a.etudiant_id = e.id
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        ORDER BY a.date_enregistrement DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur de base de données : ' . $e->getMessage();
}

include 'includes/header.php';
?>

<h1 class="mb-20">Tableau de bord administrateur</h1>

<?php display_alert(); ?>

<!-- Statistiques -->
<div class="dashboard-stats">
    <div class="stat-card primary">
        <i class="fas fa-graduation-cap"></i>
        <div class="stat-card-content">
            <h3><?= htmlspecialchars($stats['filieres']) ?></h3>
            <p>Filières</p>
        </div>
    </div>
    
    <div class="stat-card success">
        <i class="fas fa-book"></i>
        <div class="stat-card-content">
            <h3><?= htmlspecialchars($stats['modules']) ?></h3>
            <p>Modules</p>
        </div>
    </div>
    
    <div class="stat-card warning">
        <i class="fas fa-user-graduate"></i>
        <div class="stat-card-content">
            <h3><?= htmlspecialchars($stats['etudiants']) ?></h3>
            <p>Étudiants</p>
        </div>
    </div>
    
    <div class="stat-card danger">
        <i class="fas fa-calendar-times"></i>
        <div class="stat-card-content">
            <h3><?= htmlspecialchars($stats['absences']) ?></h3>
            <p>Absences</p>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="card mt-20">
    <div class="card-header">
        <h2>Actions rapides</h2>
    </div>
    <div class="card-body">
        <div class="flex gap-10" style="flex-wrap: wrap;">
            <a href="admin/gestion_filieres.php" class="btn">
                <i class="fas fa-graduation-cap"></i> Gérer les filières
            </a>
            <a href="admin/gestion_modules.php" class="btn">
                <i class="fas fa-book"></i> Gérer les modules
            </a>
            <a href="admin/gestion_etudiants.php" class="btn">
                <i class="fas fa-user-graduate"></i> Gérer les étudiants
            </a>
            <a href="admin/gestion_absences.php" class="btn">
                <i class="fas fa-calendar-times"></i> Gérer les absences
            </a>
        </div>
    </div>
</div>

<!-- Tableaux -->
<div class="flex gap-10" style="flex-wrap: wrap;">
    <!-- Derniers étudiants -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h2>Derniers étudiants inscrits</h2>
            <a href="admin/gestion_etudiants.php" class="btn btn-sm">Voir tous</a>
        </div>
        <div class="card-body">
            <?php if (empty($derniers_etudiants)): ?>
                <p>Aucun étudiant inscrit.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Apogée</th>
                            <th>Nom</th>
                            <th>Filière</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($derniers_etudiants as $etudiant): ?>
                            <tr>
                                <td><?= htmlspecialchars($etudiant['apogee']) ?></td>
                                <td><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></td>
                                <td><?= htmlspecialchars($etudiant['filiere_nom']) ?></td>
                                <td><?= date('d/m/Y', strtotime($etudiant['date_inscription'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Dernières absences -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <div class="card-header">
            <h2>Dernières absences</h2>
            <a href="admin/gestion_absences.php" class="btn btn-sm">Voir toutes</a>
        </div>
        <div class="card-body">
            <?php if (empty($dernieres_absences)): ?>
                <p>Aucune absence enregistrée.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Module</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Justifiée</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernieres_absences as $absence): ?>
                            <tr>
                                <td><?= htmlspecialchars($absence['etudiant_prenom'] . ' ' . $absence['etudiant_nom']) ?></td>
                                <td><?= htmlspecialchars($absence['module_nom']) ?></td>
                                <td><?= date('d/m/Y', strtotime($absence['date_seance'])) ?></td>
                                <td><?= htmlspecialchars($absence['type_seance']) ?></td>
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
</div>

<?php include 'includes/footer.php'; ?>
