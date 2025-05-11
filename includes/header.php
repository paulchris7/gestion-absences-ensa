<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Système de gestion des absences - École Nationale des Sciences Appliquées">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Gestion des Absences - ENSA'; ?></title>
    
    <!-- Chemins relatifs dynamiques pour les assets -->
    <?php
    $base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || strpos($_SERVER['PHP_SELF'], '/etudiant/') !== false)
        ? '../'
        : '';
    ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="<?php echo $base_path; ?>assets/favicon.ico" type="image/x-icon">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="logo">
                <h1>
                    <a href="<?php echo $base_path; ?>index.php">
                        ENSA <span>Gestion des Absences</span>
                    </a>
                </h1>
            </div>
            <nav class="main-nav" aria-label="Navigation principale">
                <?php if(isset($_SESSION['user_type'])): ?>
                    <?php if($_SESSION['user_type'] === 'admin'): ?>
                        <ul>
                            <li><a href="<?php echo $base_path; ?>dashboard_admin.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/gestion_filieres.php"><i class="fas fa-graduation-cap"></i> Filières</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/gestion_modules.php"><i class="fas fa-book"></i> Modules</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/gestion_etudiants.php"><i class="fas fa-user-graduate"></i> Étudiants</a></li>
                            <li><a href="<?php echo $base_path; ?>admin/gestion_absences.php"><i class="fas fa-calendar-times"></i> Absences</a></li>
                            <li><a href="<?php echo $base_path; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                        </ul>
                    <?php else: ?>
                        <ul>
                            <li><a href="<?php echo $base_path; ?>dashboard_etudiant.php"><i class="fas fa-tachometer-alt"></i> Tableau de bord</a></li>
                            <li><a href="<?php echo $base_path; ?>etudiant/bilan_absences.php"><i class="fas fa-calendar-times"></i> Mes absences</a></li>
                            <li><a href="<?php echo $base_path; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <ul>
                        <li><a href="<?php echo $base_path; ?>index.php"><i class="fas fa-home"></i> Accueil</a></li>
                        <li><a href="<?php echo $base_path; ?>register.php"><i class="fas fa-user-plus"></i> Inscription</a></li>
                        <li><a href="<?php echo $base_path; ?>index.php#contact"><i class="fas fa-envelope"></i> Contact</a></li>
                    </ul>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
