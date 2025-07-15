<?php
/**
 * Gestionnaire central des préférences utilisateur
 * Ce fichier charge et applique les préférences de l'utilisateur connecté
 */

class PreferencesManager {
    private $pdo;
    private $userId;
    private $preferences = [];
    
    public function __construct($pdo, $userId = null) {
        $this->pdo = $pdo;
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->loadPreferences();
    }
    
    /**
     * Charger les préférences depuis la base de données
     */
    private function loadPreferences() {
        if (!$this->userId) {
            $this->setDefaultPreferences();
            return;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT preference_key, preference_value FROM user_preferences WHERE user_id = ?");
            $stmt->execute([$this->userId]);
            
            while ($row = $stmt->fetch()) {
                $this->preferences[$row['preference_key']] = $row['preference_value'];
            }
            
            // Fusionner avec les valeurs par défaut pour les clés manquantes
            $this->preferences = array_merge($this->getDefaultPreferences(), $this->preferences);
            
        } catch (PDOException $e) {
            $this->setDefaultPreferences();
        }
    }
    
    /**
     * Obtenir les préférences par défaut
     */
    private function getDefaultPreferences() {
        return [
            'theme' => 'light',
            'language' => 'fr',
            'dashboard_layout' => 'grid',
            'timezone' => 'Africa/Douala',
            'items_per_page' => 20,
            'notifications_email' => 1,
            'notifications_browser' => 1
        ];
    }
    
    /**
     * Définir les préférences par défaut
     */
    private function setDefaultPreferences() {
        $this->preferences = $this->getDefaultPreferences();
    }
    
    /**
     * Obtenir une préférence spécifique
     */
    public function get($key, $default = null) {
        return $this->preferences[$key] ?? $default;
    }
    
    /**
     * Obtenir toutes les préférences
     */
    public function getAll() {
        return $this->preferences;
    }
    
    /**
     * Obtenir le thème actuel
     */
    public function getTheme() {
        $theme = $this->get('theme', 'light');
        
        // Si auto, déterminer selon l'heure
        if ($theme === 'auto') {
            $hour = (int)date('H');
            return ($hour >= 18 || $hour <= 6) ? 'dark' : 'light';
        }
        
        return $theme;
    }
    
    /**
     * Obtenir les variables CSS pour le thème
     */
    public function getThemeCSS() {
        $theme = $this->getTheme();
        
        if ($theme === 'dark') {
            return [
                '--bg-primary' => '#1a1a1a',
                '--bg-secondary' => '#2d2d2d',
                '--bg-card' => '#3a3a3a',
                '--text-primary' => '#ffffff',
                '--text-secondary' => '#cccccc',
                '--text-muted' => '#999999',
                '--border-color' => '#555555',
                '--accent-color' => '#4a9eff',
                '--success-color' => '#28a745',
                '--warning-color' => '#ffc107',
                '--danger-color' => '#dc3545',
                '--gradient-primary' => 'linear-gradient(135deg, #2c3e50, #34495e)',
                '--gradient-accent' => 'linear-gradient(135deg, #4a9eff, #357abd)',
                '--shadow' => '0 4px 20px rgba(0,0,0,0.3)'
            ];
        } else {
            return [
                '--bg-primary' => '#ffffff',
                '--bg-secondary' => '#f8f9fa',
                '--bg-card' => '#ffffff',
                '--text-primary' => '#2c3e50',
                '--text-secondary' => '#34495e',
                '--text-muted' => '#6c757d',
                '--border-color' => '#e1e8ed',
                '--accent-color' => '#16a085',
                '--success-color' => '#27ae60',
                '--warning-color' => '#f39c12',
                '--danger-color' => '#e74c3c',
                '--gradient-primary' => 'linear-gradient(135deg, #16a085, #138d75)',
                '--gradient-accent' => 'linear-gradient(135deg, #3498db, #2980b9)',
                '--shadow' => '0 4px 20px rgba(0,0,0,0.1)'
            ];
        }
    }
    
    /**
     * Obtenir le CSS inline pour le thème
     */
    public function getThemeStyles() {
        $css = $this->getThemeCSS();
        $styles = [];
        
        foreach ($css as $property => $value) {
            $styles[] = "$property: $value";
        }
        
        return implode('; ', $styles);
    }
    
    /**
     * Obtenir la langue actuelle
     */
    public function getLanguage() {
        return $this->get('language', 'fr');
    }
    
    /**
     * Obtenir le fuseau horaire
     */
    public function getTimezone() {
        return $this->get('timezone', 'Africa/Douala');
    }
    
    /**
     * Obtenir le nombre d'éléments par page
     */
    public function getItemsPerPage() {
        return (int)$this->get('items_per_page', 20);
    }
    
    /**
     * Vérifier si les notifications email sont activées
     */
    public function isEmailNotificationsEnabled() {
        return (bool)$this->get('notifications_email', 1);
    }
    
    /**
     * Vérifier si les notifications navigateur sont activées
     */
    public function isBrowserNotificationsEnabled() {
        return (bool)$this->get('notifications_browser', 1);
    }
    
    /**
     * Obtenir la disposition du dashboard
     */
    public function getDashboardLayout() {
        return $this->get('dashboard_layout', 'grid');
    }
}

// Initialiser le gestionnaire de préférences global
if (!isset($preferencesManager) && isset($pdo)) {
    $preferencesManager = new PreferencesManager($pdo);
}
?>
