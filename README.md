# 🏥 MINSANTE - Système de Gestion de Dossiers

Application web moderne pour la gestion des dossiers médicaux et administratifs du Ministère de la Santé.

## ✨ Fonctionnalités

### 📋 Gestion des Dossiers
- ✅ Création, modification et visualisation des dossiers
- ✅ Système de statuts avancé (brouillon, en cours, validé, rejeté, archivé)
- ✅ Gestion des priorités et échéances
- ✅ Attribution et suivi des responsables
- ✅ Recherche et filtrage avancés

### 📊 Reporting et Analytics
- ✅ Dashboard interactif avec statistiques en temps réel
- ✅ Graphiques et visualisations avec Chart.js
- ✅ Exports personnalisés (PDF, Excel, CSV)
- ✅ Rapports avancés et analytics

### 💌 Communication
- ✅ Système de messagerie interne
- ✅ Notifications automatiques
- ✅ Gestion des échéances et alertes
- ✅ Envoi d'emails automatisés

### 🗂️ Organisation
- ✅ Gestion des catégories et types de dossiers
- ✅ Système d'archivage intelligent
- ✅ Gestion des services et départements
- ✅ Workflow personnalisables

### 🔐 Sécurité et Administration
- ✅ Authentification sécurisée
- ✅ Gestion des rôles et permissions
- ✅ Logs d'audit complets
- ✅ Interface d'administration

### 🌐 Interface Moderne
- ✅ Design responsive et moderne
- ✅ Effets glassmorphisme
- ✅ Animations fluides
- ✅ Support multilingue (FR/EN)
- ✅ Dark/Light mode

## 🚀 Installation

### Prérequis
- PHP 7.4+ 
- MySQL 5.7+ ou MariaDB 10.3+
- Apache/Nginx
- Composer

### Installation rapide

1. **Cloner le repository**
```bash
git clone https://github.com/beliasfranklin/dossier-minsante.git
cd dossier-minsante
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configuration**
```bash
cp config.php.example config.php
# Éditer config.php avec vos paramètres de base de données
```

4. **Base de données**
```bash
# Importer la structure de base
mysql -u root -p < sql_updates/schema.sql
```

5. **Permissions**
```bash
chmod 755 uploads/ cache/ logs/
```

## 📁 Structure du Projet

```
dossier-minsante/
├── 📂 assets/           # CSS, JS, images
├── 📂 includes/         # Fichiers PHP communs
├── 📂 modules/          # Modules fonctionnels
│   ├── 📂 dossiers/     # Gestion des dossiers
│   ├── 📂 archivage/    # Système d'archivage
│   ├── 📂 messagerie/   # Communication
│   ├── 📂 reporting/    # Rapports et stats
│   ├── 📂 export/       # Exports de données
│   ├── 📂 users/        # Gestion utilisateurs
│   └── 📂 workflow/     # Workflows
├── 📂 lang/             # Fichiers de langue
├── 📂 uploads/          # Fichiers uploadés
├── 📂 cache/            # Cache système
├── 📂 logs/             # Logs applicatifs
└── 📂 api/              # API REST
```

## 🔧 Configuration

### Base de données
Éditer `config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'minsante_dossiers');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
```

### Email (optionnel)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'votre@email.com');
define('SMTP_PASS', 'votre_mot_de_passe');
define('SMTP_PORT', 587);
```

## 👥 Utilisation

### Accès par défaut
- **URL :** `http://votre-domaine/dossier-minsante`
- **Admin :** admin / admin123
- **Utilisateur :** user / user123

### Modules principaux

#### 📋 Gestion des Dossiers
- Liste avec filtres avancés
- Création avec formulaire moderne
- Édition collaborative
- Système de validation

#### 📊 Dashboard
- KPIs en temps réel
- Graphiques interactifs
- Dossiers récents
- Alertes échéances

#### 💌 Messagerie
- Messages internes
- Notifications push
- Pièces jointes
- Historique complet

#### 🗂️ Archives
- Archivage automatique
- Recherche dans archives
- Restauration de dossiers
- Purge programmée

## 🎨 Personnalisation

### Thèmes
- Modification des variables CSS dans `assets/css/style.css`
- Support des thèmes personnalisés
- Mode sombre/clair

### Workflow
- Configuration dans `modules/workflow/`
- Statuts personnalisables
- Automatisations configurables

## 📈 Performance

### Optimisations incluses
- ✅ Cache intelligent
- ✅ Compression gzip
- ✅ Lazy loading
- ✅ Minification CSS/JS
- ✅ Optimisation base de données

### Monitoring
- Logs détaillés dans `/logs/`
- Métriques de performance
- Alertes système

## 🔒 Sécurité

### Mesures implémentées
- ✅ Protection CSRF
- ✅ Validation des entrées
- ✅ Échappement XSS
- ✅ Sessions sécurisées
- ✅ Logs d'audit
- ✅ Chiffrement des mots de passe

## 🌍 Support Navigateurs

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ✅ Mobile responsive

## 📱 API REST

### Endpoints principaux
```
GET    /api/dossiers          # Liste des dossiers
POST   /api/dossiers          # Créer un dossier
GET    /api/dossiers/{id}     # Détail d'un dossier
PUT    /api/dossiers/{id}     # Modifier un dossier
DELETE /api/dossiers/{id}     # Supprimer un dossier

GET    /api/stats             # Statistiques
GET    /api/notifications     # Notifications
POST   /api/messages          # Envoyer message
```

## 🛠️ Développement

### Environnement de dev
```bash
# Mode debug
define('DEBUG_MODE', true);

# Logs verbeux
define('LOG_LEVEL', 'DEBUG');
```

### Tests
```bash
# Tests unitaires
php test_system_complet.php

# Test base de données
php test_db_connection.php
```

## 📝 Changelog

### Version 2.0.0 (Actuelle)
- ✨ Interface complètement redesignée
- ✨ Système de messagerie intégré
- ✨ Reporting avancé avec graphiques
- ✨ API REST complète
- ✨ Workflow personnalisables
- ✨ Support multilingue
- 🐛 Corrections de sécurité
- ⚡ Optimisations de performance

### Version 1.0.0
- 🎉 Version initiale
- 📋 Gestion de base des dossiers
- 👥 Système d'authentification
- 📊 Dashboard simple

## 🤝 Contribution

1. Fork du repository
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit des changements (`git commit -am 'Ajouter nouvelle fonctionnalité'`)
4. Push de la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

## 📞 Support

- **Documentation :** [Wiki du projet](https://github.com/beliasfranklin/dossier-minsante/wiki)
- **Issues :** [GitHub Issues](https://github.com/beliasfranklin/dossier-minsante/issues)
- **Email :** support@minsante.gov

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

## 🙏 Remerciements

- **Chart.js** pour les graphiques
- **Font Awesome** pour les icônes
- **mPDF** pour la génération PDF
- **PHPMailer** pour l'envoi d'emails
- **Communauté PHP** pour le support

---

**Développé avec ❤️ pour le Ministère de la Santé**

*Version 2.0.0 - Application de Gestion de Dossiers Moderne*
