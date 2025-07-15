<?php
/**
 * Gestionnaire des Préférences Utilisateur
 * Gère les thèmes, préférences et personnalisations pour chaque utilisateur
 */

class PreferencesManager {
    private $pdo;
    private $userId;
    private $preferences = [];
    
    // Thèmes prédéfinis
    private $themes = [
        'default' => [
            '--primary-color' => '#667eea',
            '--secondary-color' => '#764ba2',
            '--success-color' => '#2ed573',
            '--warning-color' => '#ffa502',
            '--danger-color' => '#ff4757',
            '--info-color' => '#3742fa',
            '--light-color' => '#f8f9fa',
            '--dark-color' => '#2d3748',
            '--background-color' => '#ffffff',
            '--text-color' => '#2d3748',
            '--border-color' => '#e2e8f0',
            '--shadow-color' => 'rgba(0,0,0,0.1)'
        ],
        'dark' => [
            '--primary-color' => '#667eea',
            '--secondary-color' => '#764ba2',
            '--success-color' => '#2ed573',
            '--warning-color' => '#ffa502',
            '--danger-color' => '#ff4757',
            '--info-color' => '#3742fa',
            '--light-color' => '#1a202c',
            '--dark-color' => '#f7fafc',
            '--background-color' => '#2d3748',
            '--text-color' => '#f7fafc',
            '--border-color' => '#4a5568',
            '--shadow-color' => 'rgba(0,0,0,0.3)'
        ],
        'blue' => [
            '--primary-color' => '#3182ce',
            '--secondary-color' => '#2c5282',
            '--success-color' => '#38a169',
            '--warning-color' => '#d69e2e',
            '--danger-color' => '#e53e3e',
            '--info-color' => '#3182ce',
            '--light-color' => '#f7fafc',
            '--dark-color' => '#1a365d',
            '--background-color' => '#ffffff',
            '--text-color' => '#1a365d',
            '--border-color' => '#cbd5e0',
            '--shadow-color' => 'rgba(49,130,206,0.1)'
        ],
        'green' => [
            '--primary-color' => '#38a169',
            '--secondary-color' => '#2f855a',
            '--success-color' => '#38a169',
            '--warning-color' => '#d69e2e',
            '--danger-color' => '#e53e3e',
            '--info-color' => '#3182ce',
            '--light-color' => '#f7fafc',
            '--dark-color' => '#1a202c',
            '--background-color' => '#ffffff',
            '--text-color' => '#1a202c',
            '--border-color' => '#e2e8f0',
            '--shadow-color' => 'rgba(56,161,105,0.1)'
        ],
        'purple' => [
            '--primary-color' => '#805ad5',
            '--secondary-color' => '#6b46c1',
            '--success-color' => '#38a169',
            '--warning-color' => '#d69e2e',
            '--danger-color' => '#e53e3e',
            '--info-color' => '#805ad5',
            '--light-color' => '#f7fafc',
            '--dark-color' => '#1a202c',
            '--background-color' => '#ffffff',
            '--text-color' => '#1a202c',
            '--border-color' => '#e2e8f0',
            '--shadow-color' => 'rgba(128,90,213,0.1)'
        ]
    ];
    
    public function __construct($pdo, $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->loadPreferences();
        $this->createTableIfNotExists();
    }
    
    /**
     * Créer la table des préférences si elle n'existe pas
     */
    private function createTableIfNotExists() {
        try {
            $sql = "
                CREATE TABLE IF NOT EXISTS user_preferences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    preference_key VARCHAR(100) NOT NULL,
                    preference_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_preference (user_id, preference_key),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Table existe déjà ou erreur de création
            error_log("Erreur création table user_preferences: " . $e->getMessage());
        }
    }
    
    /**
     * Charger les préférences de l'utilisateur
     */
    private function loadPreferences() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT preference_key, preference_value 
                FROM user_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->userId]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->preferences[$row['preference_key']] = $row['preference_value'];
            }
        } catch (PDOException $e) {
            // Table n'existe pas encore ou erreur de lecture
            $this->preferences = [];
        }
    }
    
    /**
     * Obtenir une préférence
     */
    public function getPreference($key, $default = null) {
        return isset($this->preferences[$key]) ? $this->preferences[$key] : $default;
    }
    
    /**
     * Définir une préférence
     */
    public function setPreference($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                preference_value = VALUES(preference_value),
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$this->userId, $key, $value]);
            
            $this->preferences[$key] = $value;
            return true;
        } catch (PDOException $e) {
            error_log("Erreur sauvegarde préférence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir le thème actuel
     */
    public function getCurrentTheme() {
        return $this->getPreference('theme', 'default');
    }
    
    /**
     * Définir le thème
     */
    public function setTheme($theme) {
        if (!isset($this->themes[$theme])) {
            return false;
        }
        return $this->setPreference('theme', $theme);
    }
    
    /**
     * Obtenir les variables CSS du thème actuel
     */
    public function getThemeVariables() {
        $currentTheme = $this->getCurrentTheme();
        
        if (!isset($this->themes[$currentTheme])) {
            $currentTheme = 'default';
        }
        
        return $this->themes[$currentTheme];
    }
    
    /**
     * Obtenir tous les thèmes disponibles
     */
    public function getAvailableThemes() {
        return array_keys($this->themes);
    }
    
    /**
     * Obtenir les métadonnées d'un thème
     */
    public function getThemeMetadata($theme = null) {
        if ($theme === null) {
            $theme = $this->getCurrentTheme();
        }
        
        $metadata = [
            'default' => [
                'name' => 'Thème par défaut',
                'description' => 'Thème clair moderne avec des couleurs douces',
                'preview' => '#667eea'
            ],
            'dark' => [
                'name' => 'Thème sombre',
                'description' => 'Interface sombre pour une utilisation prolongée',
                'preview' => '#2d3748'
            ],
            'blue' => [
                'name' => 'Bleu professionnel',
                'description' => 'Thème bleu pour un aspect professionnel',
                'preview' => '#3182ce'
            ],
            'green' => [
                'name' => 'Vert nature',
                'description' => 'Thème vert apaisant et naturel',
                'preview' => '#38a169'
            ],
            'purple' => [
                'name' => 'Violet créatif',
                'description' => 'Thème violet pour la créativité',
                'preview' => '#805ad5'
            ]
        ];
        
        return $metadata[$theme] ?? $metadata['default'];
    }
    
    /**
     * Obtenir la langue préférée
     */
    public function getLanguage() {
        return $this->getPreference('language', 'fr');
    }
    
    /**
     * Définir la langue
     */
    public function setLanguage($language) {
        return $this->setPreference('language', $language);
    }
    
    /**
     * Obtenir la timezone
     */
    public function getTimezone() {
        return $this->getPreference('timezone', 'Europe/Paris');
    }
    
    /**
     * Définir la timezone
     */
    public function setTimezone($timezone) {
        return $this->setPreference('timezone', $timezone);
    }
    
    /**
     * Obtenir le format de date préféré
     */
    public function getDateFormat() {
        return $this->getPreference('date_format', 'd/m/Y');
    }
    
    /**
     * Définir le format de date
     */
    public function setDateFormat($format) {
        return $this->setPreference('date_format', $format);
    }
    
    /**
     * Obtenir les préférences de notification
     */
    public function getNotificationPreferences() {
        $prefs = $this->getPreference('notifications', '{}');
        return json_decode($prefs, true) ?: [
            'email' => true,
            'browser' => true,
            'whatsapp' => false,
            'deadlines' => true,
            'updates' => true
        ];
    }
    
    /**
     * Définir les préférences de notification
     */
    public function setNotificationPreferences($preferences) {
        return $this->setPreference('notifications', json_encode($preferences));
    }
    
    /**
     * Obtenir les préférences de dashboard
     */
    public function getDashboardPreferences() {
        $prefs = $this->getPreference('dashboard', '{}');
        return json_decode($prefs, true) ?: [
            'show_stats' => true,
            'show_charts' => true,
            'show_recent' => true,
            'cards_per_row' => 3,
            'auto_refresh' => 30
        ];
    }
    
    /**
     * Définir les préférences de dashboard
     */
    public function setDashboardPreferences($preferences) {
        return $this->setPreference('dashboard', json_encode($preferences));
    }
    
    /**
     * Exporter toutes les préférences
     */
    public function exportPreferences() {
        return $this->preferences;
    }
    
    /**
     * Importer des préférences
     */
    public function importPreferences($preferences) {
        $success = 0;
        foreach ($preferences as $key => $value) {
            if ($this->setPreference($key, $value)) {
                $success++;
            }
        }
        return $success;
    }
    
    /**
     * Réinitialiser toutes les préférences
     */
    public function resetPreferences() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$this->userId]);
            
            $this->preferences = [];
            return true;
        } catch (PDOException $e) {
            error_log("Erreur reset préférences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir les statistiques d'utilisation
     */
    public function getUsageStats() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_preferences,
                    MAX(updated_at) as last_update
                FROM user_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->userId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_preferences' => 0,
                'last_update' => null
            ];
        } catch (PDOException $e) {
            return [
                'total_preferences' => 0,
                'last_update' => null
            ];
        }
    }
    
    /**
     * Générer le CSS pour le thème actuel
     */
    public function generateThemeCSS() {
        $variables = $this->getThemeVariables();
        $css = ":root {\n";
        
        foreach ($variables as $property => $value) {
            $css .= "    {$property}: {$value};\n";
        }
        
        $css .= "}\n";
        return $css;
    }
    
    /**
     * Vérifier si l'utilisateur a des préférences personnalisées
     */
    public function hasCustomPreferences() {
        return !empty($this->preferences);
    }
    
    /**
     * Obtenir la dernière activité
     */
    public function getLastActivity() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT MAX(updated_at) as last_activity 
                FROM user_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$this->userId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['last_activity'];
        } catch (PDOException $e) {
            return null;
        }
    }
}

// Fonction helper pour obtenir les préférences rapidement
function getUserPreferences($pdo, $userId) {
    static $instances = [];
    
    if (!isset($instances[$userId])) {
        $instances[$userId] = new PreferencesManager($pdo, $userId);
    }
    
    return $instances[$userId];
}

// Fonction helper pour obtenir les variables de thème
function getThemeVariables($pdo, $userId) {
    $prefs = getUserPreferences($pdo, $userId);
    return $prefs->getThemeVariables();
}
