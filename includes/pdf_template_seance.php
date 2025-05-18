<?php
function generateSeancePDFContent($seance, $etudiants) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Liste des étudiants - Séance <?= htmlspecialchars($seance['module_nom']) ?></title>
        <link rel="stylesheet" href="../assets/css/pdf_styles.css">
    </head>
    <body>
        <!-- En-tête -->
        <table class="header-table">
            <tr>
                <td><img src="../assets/img/ca-logo.png" alt="Université Cadi Ayyad" style="height: 50px;"></td>
                <td class="header-text">
                    <div class="university-name">Université Cadi Ayyad</div>
                    <div class="school-name">École Nationale des Sciences Appliquées</div>
                    <div class="department">Gestion des Absences</div>
                </td>
                <td><img src="../assets/img/ensa-logo.png" alt="ENSA Marrakech" style="height: 50px;"></td>
            </tr>
        </table>

        <!-- Titre -->
        <div class="report-title">Liste des étudiants - Séance</div>

        <!-- Informations de la séance -->
        <table class="info-table">
            <tr>
                <th>Module</th>
                <td><?= htmlspecialchars($seance['module_nom']) ?></td>
            </tr>
            <tr>
                <th>Filière</th>
                <td><?= htmlspecialchars($seance['filiere_code'] . ' - ' . $seance['filiere_nom']) ?></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><?= date('d/m/Y', strtotime($seance['date_seance'])) ?></td>
            </tr>
            <tr>
                <th>Horaire</th>
                <td><?= date('H:i', strtotime($seance['heure_debut'])) ?> - <?= date('H:i', strtotime($seance['heure_fin'])) ?></td>
            </tr>
            <tr>
                <th>Salle</th>
                <td><?= htmlspecialchars($seance['salle']) ?></td>
            </tr>
            <tr>
                <th>Total étudiants</th>
                <td><?= count($etudiants) ?></td>
            </tr>
            <tr>
                <th>Présents</th>
                <td><?= count(array_filter($etudiants, fn($e) => $e['present'])) ?></td>
            </tr>
            <tr>
                <th>Absents</th>
                <td><?= count(array_filter($etudiants, fn($e) => !$e['present'])) ?></td>
            </tr>
        </table>

        <!-- Liste des étudiants -->
        <div class="report-title">Liste des étudiants</div>
        <table class="absence-table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Apogée</th>
                    <th>Nom</th>
                    <th>Prénom</th>
                    <th>Statut</th>
                    <th>Justification</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etudiants as $index => $etudiant): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($etudiant['apogee']) ?></td>
                    <td><?= htmlspecialchars($etudiant['nom']) ?></td>
                    <td><?= htmlspecialchars($etudiant['prenom']) ?></td>
                    <td class="status-cell <?= $etudiant['present'] ? 'justified' : 'unjustified' ?>">
                        <?= $etudiant['present'] ? 'Présent' : 'Absent' ?>
                    </td>
                    <td class="status-cell">
                        <?php if (!$etudiant['present']): ?>
                            <span class="<?= $etudiant['justifiee'] ? 'justified' : 'unjustified' ?>">
                                <?= $etudiant['justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pied de page -->
        <div class="footer">
            Document généré automatiquement par le système de gestion des absences - ENSA Marrakech
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
