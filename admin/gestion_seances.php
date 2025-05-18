<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_admin();

$filieres = [];
$modules = [];
$filters = [
    'filiere_id' => isset($_GET['filiere_id']) ? (int)$_GET['filiere_id'] : 0,
    'module_id' => isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0,
    'date_debut' => isset($_GET['date_debut']) ? $_GET['date_debut'] : '',
    'date_fin' => isset($_GET['date_fin']) ? $_GET['date_fin'] : ''
];

try {
    $pdo = connect();

    // Récupérer toutes les filières
    $filieres = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY code")->fetchAll();

    // Récupérer les modules en fonction de la filière sélectionnée
    $module_query = "SELECT id, code, nom FROM modules";
    $module_params = [];

    if ($filters['filiere_id'] > 0) {
        $module_query .= " WHERE filiere_id = :filiere_id";
        $module_params[':filiere_id'] = $filters['filiere_id'];
    }

    $module_query .= " ORDER BY code";
    $stmt = $pdo->prepare($module_query);
    $stmt->execute($module_params);
    $modules = $stmt->fetchAll();

    // Charger les séances avec les filtres
    $seance_query = "
        SELECT s.id, s.date_seance, s.heure_debut, s.heure_fin, s.type_seance, s.salle,
               m.code as module_code, m.nom as module_nom,
               f.code as filiere_code, f.nom as filiere_nom
        FROM seances s
        JOIN modules m ON s.module_id = m.id
        JOIN filieres f ON m.filiere_id = f.id
        WHERE 1=1
    ";

    $seance_params = [];

    if ($filters['filiere_id'] > 0) {
        $seance_query .= " AND m.filiere_id = :filiere_id";
        $seance_params[':filiere_id'] = $filters['filiere_id'];
    }

    if ($filters['module_id'] > 0) {
        $seance_query .= " AND m.id = :module_id";
        $seance_params[':module_id'] = $filters['module_id'];
    }

    if (!empty($filters['date_debut'])) {
        $seance_query .= " AND s.date_seance >= :date_debut";
        $seance_params[':date_debut'] = $filters['date_debut'];
    }

    if (!empty($filters['date_fin'])) {
        $seance_query .= " AND s.date_seance <= :date_fin";
        $seance_params[':date_fin'] = $filters['date_fin'];
    }

    $seance_query .= " ORDER BY s.date_seance DESC, s.heure_debut DESC";
    $stmt = $pdo->prepare($seance_query);
    $stmt->execute($seance_params);
    $seances = $stmt->fetchAll();

    // Vérifier si une séance est sélectionnée pour afficher les étudiants
    $etudiants_presents = [];
    $etudiants_absents = [];
    if (isset($_GET['seance_id']) && !empty($_GET['seance_id'])) {
        $seance_id = (int)$_GET['seance_id'];

        try {
            // Récupérer les étudiants présents
            $stmt = $pdo->prepare("
                SELECT e.apogee, e.nom, e.prenom
                FROM presences p
                JOIN etudiants e ON p.etudiant_id = e.id
                WHERE p.seance_id = ?
            ");
            $stmt->execute([$seance_id]);
            $etudiants_presents = $stmt->fetchAll();

            // Récupérer les étudiants absents
            $stmt = $pdo->prepare("
                SELECT e.apogee, e.nom, e.prenom
                FROM absences a
                JOIN etudiants e ON a.etudiant_id = e.id
                WHERE a.seance_id = ?
            ");
            $stmt->execute([$seance_id]);
            $etudiants_absents = $stmt->fetchAll();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erreur lors du chargement des étudiants.';
            error_log("PDO Error in gestion_seances: " . $e->getMessage());
        }
    }

    // Traitement du formulaire de création de séance
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_id'], $_POST['date_seance'], $_POST['heure_debut'], $_POST['heure_fin'], $_POST['type_seance'], $_POST['salle'])) {
        $module_id = (int)$_POST['module_id'];
        $date_seance = $_POST['date_seance'];
        $heure_debut = $_POST['heure_debut'];
        $heure_fin = $_POST['heure_fin'];
        $type_seance = $_POST['type_seance'];
        $salle = trim($_POST['salle']);
        $qr_code = uniqid('QR-', true); // Génération aléatoire du code QR

        try {
            $stmt = $pdo->prepare("
                INSERT INTO seances (module_id, date_seance, heure_debut, heure_fin, type_seance, salle, qr_code)
                VALUES (:module_id, :date_seance, :heure_debut, :heure_fin, :type_seance, :salle, :qr_code)
            ");
            $stmt->execute([
                'module_id' => $module_id,
                'date_seance' => $date_seance,
                'heure_debut' => $heure_debut,
                'heure_fin' => $heure_fin,
                'type_seance' => $type_seance,
                'salle' => $salle,
                'qr_code' => $qr_code
            ]);

            $_SESSION['success'] = "La séance a été créée avec succès.";
            header('Location: gestion_seances.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['error'] = "Erreur lors de la création de la séance.";
            error_log("PDO Error in gestion_seances: " . $e->getMessage());
        }
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'Une erreur est survenue lors du chargement des données.';
    error_log("PDO Error in gestion_seances: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="flex justify-between align-center mb-20">
    <h1>Gestion des séances</h1>
    <button id="showFormBtn" class="btn">
        <i class="fas fa-plus"></i> Nouvelle séance
    </button>
</div>

<?php display_alert(); ?>

<div class="card mb-20" id="formCard" style="display: none;">
    <div class="card-header">
        <h2>Créer une nouvelle séance</h2>
    </div>
    <div class="card-body">
        <form action="gestion_seances.php" method="post">
            <div class="form-group">
                <label for="module_id">Module</label>
                <select class="form-control" id="module_id" name="module_id" required>
                    <option value="">Sélectionnez un module</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?= $module['id'] ?>">
                            <?= htmlspecialchars($module['code'] . ' - ' . $module['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="date_seance">Date de la séance</label>
                <input type="date" class="form-control" id="date_seance" name="date_seance" required>
            </div>

            <div class="form-group">
                <label for="heure_debut">Heure de début</label>
                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
            </div>

            <div class="form-group">
                <label for="heure_fin">Heure de fin</label>
                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
            </div>

            <div class="form-group">
                <label for="type_seance">Type de séance</label>
                <select class="form-control" id="type_seance" name="type_seance" required>
                    <option value="Cours">Cours</option>
                    <option value="TD">TD</option>
                    <option value="TP">TP</option>
                </select>
            </div>

            <div class="form-group">
                <label for="salle">Salle</label>
                <input type="text" class="form-control" id="salle" name="salle" placeholder="Ex: A1, B2..." required>
            </div>

            <div class="form-group">
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button type="button" class="btn btn-secondary" id="cancelFormBtn">
                    <i class="fas fa-times"></i> Annuler
                </button>
            </div>
        </form>
    </div>
</div>

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

            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
                <a href="gestion_seances.php" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Liste des séances</h2>
    </div>
    <div class="card-body">
        <?php if (empty($seances)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune séance trouvée avec les critères sélectionnés.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Filière</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Salle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seances as $seance): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($seance['date_seance'])) ?></td>
                                <td><?= date('H:i', strtotime($seance['heure_debut'])) ?>-<?= date('H:i', strtotime($seance['heure_fin'])) ?></td>
                                <td><?= htmlspecialchars($seance['filiere_code']) ?></td>
                                <td><?= htmlspecialchars($seance['module_code'] . ' - ' . $seance['module_nom']) ?></td>
                                <td><?= htmlspecialchars($seance['type_seance']) ?></td>
                                <td><?= htmlspecialchars($seance['salle']) ?></td>
                                <td class="table-actions">
                                    <a href="export_qr_code.php?seance_id=<?= $seance['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-qrcode"></i> Exporter QR Code
                                    </a>
                                    <a href="export_seance_pdf.php?seance_id=<?= $seance['id'] ?>" class="btn btn-sm btn-secondary">
                                        <i class="fas fa-file-pdf"></i> Exporter PDF
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Liste des étudiants présents et absents -->
<?php if (isset($seance_id)): ?>
    <div class="card mb-20">
        <div class="card-header">
            <h2>Étudiants pour la séance ID <?= htmlspecialchars($seance_id) ?></h2>
        </div>
        <div class="card-body">
            <div class="flex gap-10" style="flex-wrap: wrap;">
                <!-- Étudiants présents -->
                <div class="card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3>Étudiants présents</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($etudiants_presents)): ?>
                            <p>Aucun étudiant présent.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($etudiants_presents as $etudiant): ?>
                                    <li class="list-group-item">
                                        <?= htmlspecialchars($etudiant['apogee'] . ' - ' . $etudiant['prenom'] . ' ' . $etudiant['nom']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Étudiants absents -->
                <div class="card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        <h3>Étudiants absents</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($etudiants_absents)): ?>
                            <p>Aucun étudiant absent.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($etudiants_absents as $etudiant): ?>
                                    <li class="list-group-item">
                                        <?= htmlspecialchars($etudiant['apogee'] . ' - ' . $etudiant['prenom'] . ' ' . $etudiant['nom']) ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const showFormBtn = document.getElementById('showFormBtn');
    const formCard = document.getElementById('formCard');
    const cancelFormBtn = document.getElementById('cancelFormBtn');

    if (showFormBtn && formCard) {
        showFormBtn.addEventListener('click', function() {
            formCard.style.display = 'block';
        });
    }

    if (cancelFormBtn && formCard) {
        cancelFormBtn.addEventListener('click', function() {
            formCard.style.display = 'none';
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
