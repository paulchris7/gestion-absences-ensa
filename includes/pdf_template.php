<?php
function generatePDFContent($absences, $filters) {
    $filiere = $filters['filiere_id'] > 0 ? getFiliereById($filters['filiere_id']) : null;
    $module = $filters['module_id'] > 0 ? getModuleById($filters['module_id']) : null;
    
    $stats = [
        'total' => count($absences),
        'justified' => array_sum(array_column($absences, 'justifiee')),
        'unjustified' => count($absences) - array_sum(array_column($absences, 'justifiee'))
    ];
    $stats['rate'] = $stats['total'] > 0 ? round(($stats['justified']/$stats['total'])*100, 2) : 0;
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Rapport des Absences - ENSA Marrakech</title>
        <style>
            /* CSS intégré pour garantir le chargement */
            <?php include '../assets/css/pdf_styles.css'; ?>
        </style>
    </head>
    <body>

        <!-- En-tête avec logos positionnés correctement -->
        <table class="header-table" width="100%">
            <tr>
                <td width="25%" align="left">
                    <img src="<?= '../assets/img/ca-logo.png' ?>" class="logo" alt="Logo Université" style="height:45pt;">
                </td>
                <td width="50%" align="center" class="header-text">
                    <div class="university-name">Université Cadi AYYAD</div>
                    <div class="school-name">École Nationale des Sciences Appliquées Marrakech</div>
                    <div class="department">
                        Département : Techniques de l'Information et Mathématiques<br>
                        Filière : <?= htmlspecialchars($filiere['nom'] ?? 'Toutes filières') ?> | 
                        Année : <?= date('Y') . '/' . (date('Y')+1) ?>
                    </div>
                </td>
                <td width="25%" align="right">
                    <img src="<?= '../assets/img/ensa-logo.png' ?>" class="logo" alt="Logo ENSA" style="height:45pt;">
                </td>
            </tr>
        </table>

        <h1 class="report-title">
            Rapport des Absences
            <?= isset($module['code']) ? ' - '.htmlspecialchars($module['code']) : '' ?>
        </h1>

        <div class="report-meta">
            <div class="meta-item">
                <span class="meta-label">Période :</span>
                <span class="meta-value">
                    <?= !empty($filters['date_debut']) ? date('d/m/Y', strtotime($filters['date_debut'])) : 'Début' ?> 
                    au 
                    <?= !empty($filters['date_fin']) ? date('d/m/Y', strtotime($filters['date_fin'])) : date('d/m/Y') ?>
                </span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label">Module :</span>
                <span class="meta-value">
                    <?= isset($module['code']) ? htmlspecialchars($module['code'].' - '.$module['nom']) : 'Tous modules' ?>
                </span>
            </div>
            
            <div class="meta-item">
                <span class="meta-label">Généré le :</span>
                <span class="meta-value"><?= date('d/m/Y à H:i') ?></span>
            </div>
        </div>

        <!-- Statistiques en ligne avec tableau -->
        <table class="stats-table" width="100%">
            <tr>
                <td width="25%" class="stat-card total">
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Absences totales</div>
                </td>
                <td width="25%" class="stat-card justified">
                    <div class="stat-value"><?= $stats['justified'] ?></div>
                    <div class="stat-label">Justifiées</div>
                </td>
                <td width="25%" class="stat-card unjustified">
                    <div class="stat-value"><?= $stats['unjustified'] ?></div>
                    <div class="stat-label">Non justifiées</div>
                </td>
                <td width="25%" class="stat-card rate">
                    <div class="stat-value"><?= $stats['rate'] ?>%</div>
                    <div class="stat-label">Taux de justification</div>
                </td>
            </tr>
        </table>

        <!-- Tableau des absences -->
        <table class="absence-table">
            <thead>
                <tr>
                    <th width="5%">N°</th>
                    <th width="10%">Apogée</th>
                    <th width="18%">Étudiant</th>
                    <th width="12%">Filière</th>
                    <th width="20%">Module</th>
                    <th width="10%">Date</th>
                    <th width="10%">Séance</th>
                    <th width="15%">Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($absences as $index => $absence): ?>
                <tr>
                    <td class="text-center"><?= $index + 1 ?></td>
                    <td><?= htmlspecialchars($absence['apogee']) ?></td>
                    <td><?= htmlspecialchars($absence['etudiant_prenom'] . ' ' . $absence['etudiant_nom']) ?></td>
                    <td><?= htmlspecialchars($absence['filiere_code']) ?></td>
                    <td><?= htmlspecialchars($absence['module_code']) ?></td>
                    <td><?= date('d/m/Y', strtotime($absence['date_seance'])) ?></td>
                    <td class="text-center"><?= date('H:i', strtotime($absence['heure_debut'])) ?>-<?= date('H:i', strtotime($absence['heure_fin'])) ?></td>
                    <td class="status-cell <?= $absence['justifiee'] ? 'justified' : 'unjustified' ?>">
                        <?= $absence['justifiee'] ? 'Justifiée' : 'Non justifiée' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="notes">
            <h3>Observations :</h3>
            <ul>
                <li>Les absences justifiées nécessitent un justificatif validé par l'administration</li>
                <li>Toute absence non justifiée supérieure à 3 séances peut entraîner des sanctions</li>
                <li>Ce document fait foi officielle pour le suivi des absences</li>
            </ul>
        </div>

        <table class="signatures" width="100%">
            <tr>
                <td width="33%" align="center">
                    <div class="signature-line"></div>
                    <div class="signature-label">Responsable de filière</div>
                </td>
                <td width="33%" align="center">
                    <div class="signature-line"></div>
                    <div class="signature-label">Responsable de module</div>
                </td>
                <td width="33%" align="center">
                    <div class="signature-line"></div>
                    <div class="signature-label">Service de scolarité</div>
                </td>
            </tr>
        </table>

        <footer class="footer">
            <table width="100%">
                <tr>
                    <td width="50%">
                        Système de Gestion des Absences - ENSA Marrakech<br>
                        Version 2.0 | <?= date('Y') ?>
                    </td>
                    <td width="50%" align="right">
                        Page {PAGENO} sur {nbpg}<br>
                        Généré le <?= date('d/m/Y à H:i:s') ?>
                    </td>
                </tr>
            </table>
        </footer>

    </body>
    </html>
    <?php
    return ob_get_clean();
}

function getFiliereById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, code, nom FROM filieres WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getModuleById($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, code, nom FROM modules WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
