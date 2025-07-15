<?php
/**
 * Gestionnaire de préférences utilisateur
 * Version sans mode sombre (thème clair uniquement)
 */

class PreferencesManager {
    private $pdo;
    private $userId;
    private $preferences = null;
    
    public function __construct($pdo, $userId = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->ensurePreferencesTable();
    }
    
    /**
     * S'assurer que la table des préférences existe
     */
    private function ensurePreferencesTable() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preference_key VARCHAR(100) NOT NULL,
                preference_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_preference (user_id, preference_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Erreur création table préférences: " . $e->getMessage());
        }
    }
    
    /**
     * Charger les préférences de l'utilisateur
     */
    private function loadPreferences() {
        if ($this->preferences !== null || !$this->userId) {
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$this->userId]);
            
            $this->preferences = [];
            while ($row = $stmt->fetch()) {
                $this->preferences[$row['preference_key']] = $row['preference_value'];
            }
        } catch (Exception $e) {
            error_log("Erreur chargement préférences: " . $e->getMessage());
            $this->preferences = [];
        }
    }
    
    /**
     * Obtenir une préférence
     */
    public function get($key, $default = null) {
        $this->loadPreferences();
        return $this->preferences[$key] ?? $default;
    }
    
    /**
     * Définir une préférence
     */
    public function set($key, $value) {
        if (!$this->userId) {
            return false;
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE preference_value = VALUES(preference_value)
            ");
            $result = $stmt->execute([$this->userId, $key, $value]);
            
            // Mettre à jour le cache
            $this->loadPreferences();
            $this->preferences[$key] = $value;
            
            return $result;
        } catch (Exception $e) {
            error_log("Erreur sauvegarde préférence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtenir le thème (light ou auto seulement)
     */
    public function getTheme() {
        $theme = $this->get('theme', 'light');
        // S'assurer qu'on n'a que 'light' ou 'auto'
        return in_array($theme, ['light', 'auto']) ? $theme : 'light';
    }
    
    /**
     * Obtenir la langue
     */
    public function getLanguage() {
        return $this->get('language', 'fr');
    }
    
    /**
     * Obtenir le nombre d'éléments par page
     */
    public function getItemsPerPage() {
        return (int)$this->get('items_per_page', 20);
    }
    
    /**
     * Obtenir le layout du dashboard
     */
    public function getDashboardLayout() {
        return $this->get('dashboard_layout', 'grid');
    }
    
    /**
     * Générer les variables CSS pour le thème (toujours clair)
     */
    public function getThemeVariables() {
        // Toujours utiliser les variables du thème clair
        $variables = [
            '--bg-primary' => '#ffffff',
            '--bg-secondary' => '#f8f9fa',
            '--text-primary' => '#2c3e50',
            '--text-secondary' => '#6c757d',
            '--border-color' => '#dee2e6',
            '--accent-color' => '#007bff',
            '--success-color' => '#28a745',
            '--warning-color' => '#ffc107',
            '--danger-color' => '#dc3545'
        ];
        
        return $variables;
    }
    
    /**
     * Obtenir toutes les préférences
     */
    public function getAll() {
        $this->loadPreferences();
        return $this->preferences ?? [];
    }
    
    /**
     * Nettoyer les anciennes préférences de thème sombre
     */
    public function cleanDarkThemePreferences() {
        if (!$this->userId) {
            return false;
        }
        
        try {
            // Mettre à jour toutes les préférences 'dark' vers 'light'
            $stmt = $this->pdo->prepare("
                UPDATE user_preferences 
                SET preference_value = 'light' 
                WHERE user_id = ? AND preference_key = 'theme' AND preference_value = 'dark'
            ");
            return $stmt->execute([$this->userId]);
        } catch (Exception $e) {
            error_log("Erreur nettoyage préférences dark: " . $e->getMessage());
            return false;
        }
    }
}
?>
