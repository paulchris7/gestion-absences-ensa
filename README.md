# 🎓 Système de Gestion des Absences

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Une application web complète pour la gestion des absences étudiantes avec interfaces administrateur/étudiant et futures fonctionnalités avancées (QR Code, justificatifs).

## ✨ Fonctionnalités

### 👨‍💼 Administration
| Fonctionnalité | Description |
|---------------|-------------|
| 🔐 Auth sécurisée | Login avec mot de passe hashé |
| 🏛️ Gestion filières | CRUD complet des filières (GI, GIL, etc.) |
| 📚 Modules | 12 modules/filière (S1/S2) avec responsables |
| 📊 Tableaux de bord | Visualisation des stats d'absences |

### 👩‍🎓 Espace étudiant
| Fonctionnalité | Description |
|---------------|-------------|
| 📝 Inscription | Formulaire avec validation |
| 🔍 Consultation | Bilan personnalisé des absences |
| 📤 Justificatifs | Upload de fichiers (PDF/image) |

### 🚧 Évolutions prévues
- 🖼️ Upload photo profil
- 📧 Notification par email
- 📱 Scan QR Code
- 📈 Stats AJAX/PDF

## 🛠️ Installation

### Prérequis
- PHP ≥ 7.4
- MySQL ≥ 5.7
- Apache/Nginx
- Composer (pour mPDF)

### 🖥️ Configuration locale
1. Cloner le dépôt :
```bash
git clone https://github.com/votre-utilisateur/gestion-absences.git
cd gestion-absences
```

2. Importer la base de données :
```bash
mysql -u username -p < sql/base.sql
```
3. Configurer les accès DB :
```bash
// config/db.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_absences');
define('DB_USER', 'root');
define('DB_PASS', '');
```

4. Démarrer le serveur :
```bash
php -S localhost:8000
```

## 🏗️ Structure du projet
```
📁 gestion-absences/
│
├── 📄 index.php
├── 📄 register.php
├── 📄 dashboard_admin.php
├── 📄 dashboard_etudiant.php
├── 📄 logout.php
│
├── 📁 config/
│   ├── 📄 mpdf_config.php
│   └── 📄 db.php
│
├── 📁 includes/
│   ├── 📄 header.php
│   ├── 📄 footer.php
│   ├── 📄 auth.php
│   └── 📄 pdf_template.php
│
├── 📁 admin/
│   ├── 📄 gestion_filieres.php
│   ├── 📄 gestion_modules.php
│   ├── 📄 gestion_etudiants.php
│   ├── 📄 gestion_absences.php
│   └── 📄 generer_rapport.php     
│
├── 📁 etudiant/
│   ├── 📄 bilan_absences.php
│   └── 📄 upload_justificatif.php
│
├── 📁 sql/
│   └── 📄 base.sql
│
├── 📁 assets/
│   ├── 📁 css/
│   │   ├── 📄 styles.css
│   │   └── 📄 pdf_styles.css
│   └── 📁 img/
│       ├── 📄 ensa-logo.png
│       └── 📄 ac-logo.png
│
├── 📁 pdf/                        
│       └── 📁 rapport/
│
└── 📁 justificatifs/              
    └── 📁 {apogee}/

```

## 👨‍🏫 Encadrement
BOUARIFI Walid - Responsable module
ENSA Marrakech - Université Cadi AYYAD

## 🤝 Contributeurs
- Safia AIT HAMMOUD  
- Azar AGHRIB  
- Paul Christopher AIMÉ  
- Mohamed YOUSSOUFI HABIB