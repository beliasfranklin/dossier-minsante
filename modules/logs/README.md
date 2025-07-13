# Module Logs Système - MINSANTE

## Description
Module de gestion et visualisation des journaux système pour l'application MINSANTE. Permet la surveillance en temps réel des activités système, la consultation des logs et leur téléchargement.

## Fonctionnalités

### 1. Dashboard de Surveillance
- **Statistiques du jour** : Nombre d'erreurs, logs d'information, connexions, statut de la base de données
- **Affichage temps réel** : Mise à jour automatique des compteurs
- **Indicateurs visuels** : Codes couleur pour identifier rapidement les problèmes

### 2. Visualisation des Logs
- **Logs récents** : Affichage des 15 dernières entrées
- **Types de logs** : Intégration, Workflow, Système, Authentification
- **Niveaux** : Info, Warning, Error, Success
- **Détails** : Horodatage, source, message, niveau de gravité

### 3. Téléchargement des Logs
- **Formats disponibles** : TXT, CSV
- **Filtres par type** :
  - Tous les logs
  - Erreurs uniquement
  - Logs d'intégration
  - Logs de workflow
- **Périodes** : 7 jours, 30 jours
- **Compression automatique** pour les gros volumes

## Structure des Fichiers

```
modules/logs/
├── index.php          # Page principale du module
├── download.php       # Gestionnaire de téléchargement
├── logs.css          # Styles spécifiques
└── README.md         # Documentation
```

## Usage

### Accès au Module
1. Connectez-vous avec des privilèges administrateur
2. Naviguer vers `Système > Logs` dans le menu principal
3. Consulter les statistiques et logs récents

### Téléchargement des Logs
1. Cliquer sur "Télécharger les logs"
2. Sélectionner le type de logs souhaité
3. Choisir le format (TXT ou CSV)
4. Le fichier sera automatiquement téléchargé

### Types de Logs Disponibles

#### Logs d'Intégration
- Actions de synchronisation
- Import/Export de données
- Erreurs d'API
- Statistiques de traitement

#### Logs de Workflow
- Actions utilisateur sur les workflows
- Signatures électroniques
- Approbations et rejets
- Audit trail complet

#### Logs Système
- Erreurs PHP
- Événements de sécurité
- Performances système
- Maintenance automatique

## Configuration

### Variables d'Environnement
- `LOG_LEVEL` : Niveau minimum de log (DEBUG, INFO, WARNING, ERROR)
- `LOG_RETENTION_DAYS` : Nombre de jours de rétention (défaut: 30)
- `LOG_MAX_SIZE` : Taille maximale des fichiers de log (défaut: 50MB)

### Personnalisation
- Modifier `logs.css` pour personnaliser l'apparence
- Ajuster les requêtes dans `index.php` pour d'autres sources de logs
- Configurer les formats d'export dans `download.php`

## Sécurité

### Contrôle d'Accès
- Accès restreint aux administrateurs (ROLE_ADMIN)
- Vérification des permissions sur tous les endpoints
- Validation des paramètres d'entrée

### Protection des Données
- Anonymisation des données sensibles dans les exports
- Limitation de la taille des téléchargements
- Audit des accès aux logs

## Maintenance

### Nettoyage Automatique
- Rotation automatique des logs après 30 jours
- Compression des anciens logs
- Nettoyage des fichiers temporaires

### Monitoring
- Surveillance de l'espace disque
- Alertes en cas d'erreurs critiques
- Métriques de performance

## API

### Endpoints Disponibles
- `GET /modules/logs/` : Interface principale
- `GET /modules/logs/download.php` : Téléchargement des logs

### Paramètres de Téléchargement
- `type` : all, error, integration, workflow, auth
- `format` : txt, csv
- `days` : 1-365 (nombre de jours)

## Dépannage

### Problèmes Courants
1. **Erreur 500 lors du téléchargement**
   - Vérifier les permissions sur le dossier logs/
   - Contrôler l'espace disque disponible

2. **Logs vides**
   - Vérifier la configuration des sources de logs
   - Contrôler les tables de base de données

3. **Lenteur d'affichage**
   - Optimiser les requêtes de base de données
   - Réduire la période d'affichage

### Logs de Debug
Pour activer le debug :
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Versions

### v1.0 (Actuel)
- Interface de base
- Téléchargement TXT/CSV
- Statistiques temps réel
- Support multi-types de logs

### Roadmap v1.1
- Filtres avancés par date
- Graphiques de tendances
- Alertes configurables
- Export JSON
- API REST complète

## Support
Pour toute question ou problème, contacter l'équipe de développement MINSANTE.
