<?php
/**
 * Installation automatique de mPDF
 * Ce script télécharge et installe mPDF automatiquement
 */

require_once __DIR__ . '/../../includes/config.php';
requireAuth();

if (!hasPermission(ROLE_ADMIN)) {
    die(t('access_denied'));
}

echo "<!DOCTYPE html>\n";
echo "<html><head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Installation mPDF - MINSANTE</title>\n";
echo "<link rel=\"stylesheet\" href=\"../../assets/css/style.css\">\n";
echo "<style>
.install-container { max-width: 700px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.step { margin: 20px 0; padding: 15px; border-radius: 8px; }
.step-success { background: #e8f5e8; border-left: 4px solid #27ae60; }
.step-warning { background: #fff3cd; border-left: 4px solid #f39c12; }
.step-error { background: #ffe8e8; border-left: 4px solid #e74c3c; }
.step-info { background: #e3f2fd; border-left: 4px solid #2980b9; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; border-radius: 6px; text-decoration: none; font-weight: bold; }
.btn-primary { background: #2980b9; color: white; }
.btn-success { background: #27ae60; color: white; }
</style>\n";
echo "</head><body>\n";

echo "<div class='install-container'>\n";
echo "<h1>🚀 Installation automatique de mPDF</h1>\n";

$action = $_GET['action'] ?? 'check';

switch ($action) {
    case 'install':
        installMpdf();
        break;
    case 'download':
        downloadMpdf();
        break;
    default:
        checkStatus();
        break;
}

function checkStatus() {
    echo "<div class='step step-info'>\n";
    echo "<h2>Vérification de l'installation mPDF</h2>\n";
    
    $mpdfPath = __DIR__ . '/../../libs/mpdf-8.1.0/';
    $autoloaderPath = __DIR__ . '/autoload.php';
    
    // Vérifier les fichiers mPDF
    $mpdfFiles = [
        $mpdfPath . 'mpdf.php',
        $mpdfPath . 'src/Mpdf.php',
        $mpdfPath . 'classes/mpdf.php'
    ];
    
    $mpdfFound = false;
    foreach ($mpdfFiles as $file) {
        if (file_exists($file)) {
            echo "<p style='color: green;'>✅ Fichier mPDF trouvé : $file</p>\n";
            $mpdfFound = true;
            break;
        }
    }
    
    if (!$mpdfFound) {
        echo "<p style='color: red;'>❌ mPDF n'est pas installé correctement</p>\n";
        echo "<div class='step step-warning'>\n";
        echo "<h3>Options d'installation :</h3>\n";
        echo "<p><a href='?action=download' class='btn btn-primary'>Télécharger mPDF 8.1.0</a></p>\n";
        echo "<p><a href='?action=install' class='btn btn-success'>Installation manuelle guidée</a></p>\n";
        echo "</div>\n";
    } else {
        echo "<div class='step step-success'>\n";
        echo "<h3>✅ mPDF est installé</h3>\n";
        echo "<p><a href='test_mpdf.php' class='btn btn-primary'>Tester mPDF</a></p>\n";
        echo "</div>\n";
    }
    echo "</div>\n";
}

function downloadMpdf() {
    echo "<div class='step step-info'>\n";
    echo "<h2>Téléchargement de mPDF 8.1.0</h2>\n";
    
    $mpdfUrl = 'https://github.com/mpdf/mpdf/archive/refs/tags/v8.1.0.zip';
    $zipFile = __DIR__ . '/../../libs/mpdf-8.1.0.zip';
    $extractPath = __DIR__ . '/../../libs/';
    
    echo "<p>Téléchargement depuis : $mpdfUrl</p>\n";
    
    // Vérifier que les fonctions nécessaires sont disponibles
    if (!function_exists('file_get_contents')) {
        echo "<p style='color: red;'>❌ La fonction file_get_contents n'est pas disponible</p>\n";
        return;
    }
    
    if (!class_exists('ZipArchive')) {
        echo "<p style='color: red;'>❌ L'extension ZIP n'est pas disponible</p>\n";
        return;
    }
    
    try {
        // Télécharger le fichier
        echo "<p>📥 Téléchargement en cours...</p>\n";
        $zipContent = file_get_contents($mpdfUrl);
        
        if ($zipContent === false) {
            throw new Exception("Échec du téléchargement");
        }
        
        file_put_contents($zipFile, $zipContent);
        echo "<p style='color: green;'>✅ Fichier téléchargé (" . formatBytes(strlen($zipContent)) . ")</p>\n";
        
        // Extraire le fichier
        echo "<p>📦 Extraction en cours...</p>\n";
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
            echo "<p style='color: green;'>✅ Fichiers extraits</p>\n";
            
            // Supprimer le fichier ZIP
            unlink($zipFile);
            
            // Renommer le répertoire si nécessaire
            $extractedDir = $extractPath . 'mpdf-8.1.0';
            $finalDir = $extractPath . 'mpdf-8.1.0';
            
            if (is_dir($extractedDir . '-master')) {
                rename($extractedDir . '-master', $finalDir);
            }
            
            echo "<div class='step step-success'>\n";
            echo "<h3>🎉 Installation réussie !</h3>\n";
            echo "<p>mPDF a été installé dans : $finalDir</p>\n";
            echo "<p><a href='test_mpdf.php' class='btn btn-success'>Tester l'installation</a></p>\n";
            echo "</div>\n";
            
        } else {
            throw new Exception("Impossible d'extraire le fichier ZIP");
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Erreur : " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "<div class='step step-warning'>\n";
        echo "<h3>Installation manuelle</h3>\n";
        echo "<p>Veuillez télécharger manuellement mPDF :</p>\n";
        echo "<ol>\n";
        echo "<li>Télécharger <a href='$mpdfUrl' target='_blank'>mPDF 8.1.0</a></li>\n";
        echo "<li>Extraire dans le répertoire libs/mpdf-8.1.0/</li>\n";
        echo "<li>Revenir sur cette page pour tester</li>\n";
        echo "</ol>\n";
        echo "</div>\n";
    }
    echo "</div>\n";
}

function installMpdf() {
    echo "<div class='step step-info'>\n";
    echo "<h2>Instructions d'installation manuelle</h2>\n";
    
    echo "<h3>Méthode 1: Téléchargement direct</h3>\n";
    echo "<ol>\n";
    echo "<li>Télécharger mPDF depuis <a href='https://github.com/mpdf/mpdf/releases/tag/v8.1.0' target='_blank'>GitHub</a></li>\n";
    echo "<li>Extraire l'archive dans le répertoire <code>libs/mpdf-8.1.0/</code></li>\n";
    echo "<li>S'assurer que le fichier <code>libs/mpdf-8.1.0/mpdf.php</code> existe</li>\n";
    echo "</ol>\n";
    
    echo "<h3>Méthode 2: Via Composer</h3>\n";
    echo "<ol>\n";
    echo "<li>Ouvrir un terminal dans le répertoire racine de l'application</li>\n";
    echo "<li>Exécuter : <code>composer require mpdf/mpdf:^8.1</code></li>\n";
    echo "<li>Créer un lien symbolique : <code>ln -s vendor/mpdf/mpdf libs/mpdf-8.1.0</code></li>\n";
    echo "</ol>\n";
    
    echo "<h3>Méthode 3: Installation automatique</h3>\n";
    echo "<p><a href='?action=download' class='btn btn-primary'>Télécharger automatiquement</a></p>\n";
    
    echo "</div>\n";
}

function formatBytes($size) {
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $factor = floor((strlen($size) - 1) / 3);
    return sprintf("%.2f", $size / pow(1024, $factor)) . ' ' . $units[$factor];
}

echo "<div style='text-align: center; margin-top: 20px;'>\n";
echo "<a href='../export/export.php' class='btn btn-primary'>← Retour à l'export</a>\n";
echo "<a href='../archivage/list.php' class='btn btn-primary'>Archives</a>\n";
echo "</div>\n";

echo "</div>\n";
echo "</body></html>\n";
?>
