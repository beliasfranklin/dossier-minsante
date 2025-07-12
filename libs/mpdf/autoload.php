<?php
/**
 * Autoloader simplifié pour mPDF 8.1.0
 * Évite les dépendances FPDI non installées
 */

// Chemin vers le répertoire mPDF
$mpdfBasePath = realpath(__DIR__ . '/../../mpdf-8.1.0/');

if (!$mpdfBasePath || !is_dir($mpdfBasePath)) {
    throw new Exception('mPDF non trouvé dans le répertoire attendu: ' . __DIR__ . '/../../mpdf-8.1.0/');
}

// Définir les constantes nécessaires
if (!defined('_MPDF_TEMP_PATH')) {
    define('_MPDF_TEMP_PATH', $mpdfBasePath . '/tmp/');
}

if (!defined('_MPDF_TTFONTDATAPATH')) {
    define('_MPDF_TTFONTDATAPATH', $mpdfBasePath . '/ttfonts/');
}

if (!defined('_MPDF_SYSTEM_TTFONTS')) {
    define('_MPDF_SYSTEM_TTFONTS', $mpdfBasePath . '/ttfonts/');
}

// Créer les répertoires s'ils n'existent pas
$tempPath = _MPDF_TEMP_PATH;
$fontPath = _MPDF_TTFONTDATAPATH;

if (!is_dir($tempPath)) {
    @mkdir($tempPath, 0755, true);
}

if (!is_dir($fontPath)) {
    @mkdir($fontPath, 0755, true);
}

// Créer un trait FPDI factice pour éviter l'erreur
if (!trait_exists('setasign\\Fpdi\\FpdiTrait')) {
    eval('
    namespace setasign\\Fpdi {
        trait FpdiTrait {
            // Trait factice pour éviter les erreurs FPDI
        }
    }
    ');
}

// Créer des classes factices pour les dépendances manquantes
if (!class_exists('setasign\\Fpdi\\Fpdi')) {
    eval('
    namespace setasign\\Fpdi {
        class Fpdi {
            // Classe factice
        }
    }
    ');
}

// Créer les interfaces PSR Log manquantes
if (!interface_exists('Psr\\Log\\LoggerInterface')) {
    eval('
    namespace Psr\\Log {
        interface LoggerInterface {
            public function emergency($message, array $context = array());
            public function alert($message, array $context = array());
            public function critical($message, array $context = array());
            public function error($message, array $context = array());
            public function warning($message, array $context = array());
            public function notice($message, array $context = array());
            public function info($message, array $context = array());
            public function debug($message, array $context = array());
            public function log($level, $message, array $context = array());
        }
    }
    ');
}

if (!interface_exists('Psr\\Log\\LoggerAwareInterface')) {
    eval('
    namespace Psr\\Log {
        interface LoggerAwareInterface {
            public function setLogger(LoggerInterface $logger);
        }
    }
    ');
}

// Créer NullLogger pour mPDF
if (!class_exists('Psr\\Log\\NullLogger')) {
    eval('
    namespace Psr\\Log {
        class NullLogger implements LoggerInterface {
            public function emergency($message, array $context = array()) {}
            public function alert($message, array $context = array()) {}
            public function critical($message, array $context = array()) {}
            public function error($message, array $context = array()) {}
            public function warning($message, array $context = array()) {}
            public function notice($message, array $context = array()) {}
            public function info($message, array $context = array()) {}
            public function debug($message, array $context = array()) {}
            public function log($level, $message, array $context = array()) {}
        }
    }
    ');
}

// Créer les interfaces PSR Log nécessaires
if (!interface_exists('Psr\\Log\\LoggerInterface')) {
    eval('
    namespace Psr\\Log {
        interface LoggerInterface {
            public function emergency($message, array $context = array());
            public function alert($message, array $context = array());
            public function critical($message, array $context = array());
            public function error($message, array $context = array());
            public function warning($message, array $context = array());
            public function notice($message, array $context = array());
            public function info($message, array $context = array());
            public function debug($message, array $context = array());
            public function log($level, $message, array $context = array());
        }
    }
    ');
}

if (!interface_exists('Psr\\Log\\LoggerAwareInterface')) {
    eval('
    namespace Psr\\Log {
        interface LoggerAwareInterface {
            public function setLogger(LoggerInterface $logger);
        }
    }
    ');
}

// Autoloader principal pour mPDF
spl_autoload_register(function ($className) use ($mpdfBasePath) {
    // Vérifier si c'est une classe mPDF
    if (strpos($className, 'Mpdf\\') === 0) {
        // Supprimer le namespace racine
        $classPath = substr($className, 5);
        
        // Remplacer les antislashes par des slashes
        $classPath = str_replace('\\', '/', $classPath);
        
        // Construire le chemin du fichier
        $filePath = $mpdfBasePath . '/src/' . $classPath . '.php';
        
        if (file_exists($filePath)) {
            require_once $filePath;
            return true;
        }
    }
    
    return false;
});

// Charger les dépendances essentielles dans l'ordre
$essentialFiles = [
    '/src/MpdfException.php',
    '/src/Config/ConfigVariables.php',
    '/src/Config/FontVariables.php',
    '/src/ServiceFactory.php',
    '/src/Strict.php',
    '/src/FpdiTrait.php'  // Charger notre version simplifiée du trait
];

foreach ($essentialFiles as $file) {
    $fullPath = $mpdfBasePath . $file;
    if (file_exists($fullPath)) {
        require_once $fullPath;
    }
}

// Charger la classe principale Mpdf en dernier
$mpdfMainFile = $mpdfBasePath . '/src/Mpdf.php';
if (file_exists($mpdfMainFile)) {
    require_once $mpdfMainFile;
} else {
    throw new Exception('Fichier principal mPDF non trouvé: ' . $mpdfMainFile);
}

// Vérifier que la classe est disponible
if (!class_exists('Mpdf\\Mpdf')) {
    throw new Exception('La classe Mpdf\\Mpdf n\'a pas pu être chargée. Vérifiez les dépendances.');
}
?>
