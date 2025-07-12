# Configuration Email pour le Système MINSANTE

## Vue d'ensemble

Le système de gestion des dossiers MINSANTE inclut un système d'envoi d'emails robuste avec support SMTP et fallback automatique. Cette documentation explique comment configurer et utiliser le système d'emails.

## Configurations Disponibles

### 1. Configuration MINSANTE Officielle (Recommandée)

```php
define('SMTP_HOST', 'mail.minsante.gov.cm');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'dossiers@minsante.gov.cm');
define('SMTP_PASS', 'mot_de_passe_securise');
define('SMTP_FROM', 'dossiers@minsante.gov.cm');
define('SMTP_FROM_NAME', 'Système de Gestion des Dossiers - MINSANTE');
```

### 2. Configuration Gmail (Test/Développement)

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'votre-email@gmail.com');
define('SMTP_PASS', 'mot-de-passe-application'); // Pas le mot de passe habituel !
define('SMTP_FROM', 'votre-email@gmail.com');
define('SMTP_FROM_NAME', 'MINSANTE - Test');
```

**Important pour Gmail :**
- Activez l'authentification à 2 facteurs
- Générez un "mot de passe d'application" dans les paramètres de sécurité
- Utilisez ce mot de passe d'application, pas votre mot de passe normal

### 3. Configuration Office 365

```php
define('SMTP_HOST', 'smtp.office365.com');
define('SMTP_PORT', 587);
define('SMTP_SECURITY', 'tls');
define('SMTP_USER', 'dossiers@minsante.gov.cm');
define('SMTP_PASS', 'mot_de_passe');
define('SMTP_FROM', 'dossiers@minsante.gov.cm');
define('SMTP_FROM_NAME', 'MINSANTE - Gestion des Dossiers');
```

## Installation et Configuration

### Étape 1: Installation de PHPMailer (Recommandée)

#### Option A: Avec Composer (Recommandée)
```bash
# Dans le dossier racine du projet
composer require phpmailer/phpmailer
```

#### Option B: Installation Web
Accédez à `install_phpmailer.php` dans votre navigateur et suivez les instructions.

#### Option C: Sans PHPMailer
Le système fonctionne automatiquement avec la fonction `mail()` native de PHP si PHPMailer n'est pas disponible.

### Étape 2: Configuration via Interface Web

1. **Configuration Simple :** Accédez à `setup_email.php`
2. **Configuration Avancée :** Accédez à `setup_email_advanced.php`
3. **Test et Diagnostic :** Accédez à `test_email_config.php`

### Étape 3: Configuration Manuelle

Modifiez le fichier `includes/config.php` et ajoutez vos paramètres SMTP.

## Types d'Emails Envoyés

### 1. Récupération de Mot de Passe
- **Déclencheur :** Utilisateur demande une réinitialisation
- **Contenu :** Lien sécurisé avec token temporaire
- **Expiration :** 1 heure

### 2. Notifications de Dossier
- **Déclencheur :** Changement de statut, nouveau commentaire
- **Destinataires :** Utilisateurs assignés au dossier
- **Contenu :** Détails de la modification

### 3. Alertes d'Échéance
- **Déclencheur :** Cron job quotidien
- **Timing :** 30, 15, 7, 3, 1 jours avant échéance
- **Destinataires :** Responsables du dossier

### 4. Notifications Administratives
- **Déclencheur :** Nouvelle inscription, erreurs système
- **Destinataires :** Administrateurs
- **Contenu :** Informations de gestion

## Utilisation dans le Code

### Envoi d'Email Simple

```php
require_once 'sendmail.php';

$result = sendMail(
    'destinataire@email.com',
    'Sujet de l\'email',
    'Corps du message en texte brut',
    '<h1>Corps du message en HTML</h1>'
);

if ($result) {
    echo "Email envoyé avec succès";
} else {
    echo "Échec de l'envoi";
}
```

### Envoi avec l'API de Notifications

```php
require_once 'api/notifications/send_email.php';

$result = sendNotificationEmail([
    'to' => 'destinataire@email.com',
    'subject' => 'Notification MINSANTE',
    'message' => 'Votre dossier a été mis à jour',
    'type' => 'dossier_update',
    'user_id' => 123,
    'dossier_id' => 456
]);
```

## Résolution des Problèmes

### Problème : Email non reçu

1. **Vérifiez la configuration SMTP**
   - Accédez à `test_email_config.php`
   - Lancez le diagnostic complet
   - Testez la connectivité SMTP

2. **Vérifiez les logs**
   ```bash
   tail -f logs/error.log
   ```

3. **Vérifiez le dossier spam** du destinataire

### Problème : Erreur de connexion SMTP

1. **Vérifiez les paramètres réseau**
   - Port SMTP (587 pour TLS, 465 pour SSL)
   - Sécurité (TLS recommandé)

2. **Vérifiez les credentials**
   - Nom d'utilisateur correct
   - Mot de passe valide (mot de passe d'application pour Gmail)

3. **Testez la connectivité**
   ```bash
   telnet smtp.gmail.com 587
   ```

### Problème : PHPMailer non trouvé

1. **Fallback automatique** : Le système utilise automatiquement `mail()`
2. **Installation :** Suivez les étapes d'installation PHPMailer
3. **Configuration PHP** : Vérifiez que `sendmail_path` est configuré

## Sécurité

### Bonnes Pratiques

1. **Mots de passe sécurisés**
   - Utilisez des mots de passe d'application
   - Ne stockez jamais les mots de passe en clair

2. **Chiffrement**
   - Utilisez TLS (port 587) ou SSL (port 465)
   - Évitez les connexions non chiffrées

3. **Validation**
   - Validez toujours les adresses email
   - Échappez le contenu HTML

4. **Limitation**
   - Implémentez un rate limiting
   - Limitez le nombre d'emails par utilisateur

### Configuration Firewall

Assurez-vous que les ports suivants sont ouverts :
- **Port 587** : SMTP avec STARTTLS
- **Port 465** : SMTP avec SSL
- **Port 25** : SMTP non chiffré (déconseillé)

## Surveillance et Maintenance

### Monitoring

1. **Logs d'email** : Consultez `logs/error.log`
2. **Tests réguliers** : Utilisez `test_email_config.php`
3. **Surveillance des taux de livraison**

### Maintenance

1. **Nettoyage des logs** : Rotation automatique
2. **Mise à jour PHPMailer** : Via Composer
3. **Révision des configurations** : Trimestrielle

## Support et Contact

- **Support Technique :** support.dossiers@minsante.gov.cm
- **Administration :** admin.dossiers@minsante.gov.cm
- **Direction :** directeur.etudes@minsante.gov.cm

## Changelog

- **v1.0.0** : Configuration initiale avec support PHPMailer et fallback
- **v1.1.0** : Ajout interface de configuration web
- **v1.2.0** : Système de diagnostic et test automatisé
