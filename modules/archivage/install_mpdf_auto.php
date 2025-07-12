<?php
/**
 * Installation automatique de mPDF via Composer
 * Ce script facilite l'installation de mPDF pour l'export PDF
 */

require_once __DIR__ . '/../../includes/config.php';
requireAuth();

if (!hasPermission(ROLE_ADMIN)) {
    die(t('access_denied'));
}

$action = $_GET['action'] ?? 'show';

echo "<!DOCTYPE html>\n";
echo "<html lang='" . getCurrentLanguage() . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Installation mPDF automatique</title>\n";
echo "<link rel=\"stylesheet\" href=\"../../assets/css/style.css\">\n";
echo "<style>
.install-container { max-width: 700px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.step { margin: 20px 0; padding: 15px; border-radius: 8px; }
.step-success { background: #e8f5e8; border-left: 4px solid #27ae60; }
.step-warning { background: #fff3cd; border-left: 4px solid #f39c12; }
.step-error { background: #ffe8e8; border-left: 4px solid #e74c3c; }
.command-box { background: #f1f1f1; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; border-radius: 6px; text-decoration: none; font-weight: bold; }
.btn-primary { background: #2980b9; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
</style>\n";
echo "</head>\n";
echo "<body style='background: #f8f9fa;'>\n";

echo "<div class='install-container'>\n";
echo "<h1 style='color: #2980b9; margin-bottom: 20px;'><i class='fas fa-download'></i> Installation mPDF Automatique</h1>\n";

switch ($action) {
    case 'install':
        installMpdf();
        break;
    case 'check':
        checkInstallation();
        break;
    default:
        showInstructions();
        break;
}

function showInstructions() {
    echo "<div class='step step-info'>\n";
    echo "<h2>Méthodes d'installation de mPDF</h2>\n";
    echo "<p>Choisissez la méthode qui convient le mieux à votre environnement :</p>\n";
    echo "</div>\n";
    
    // Vérifier si Composer est disponible
    $composerAvailable = false;
    $composerCommand = '';
    
    if (function_exists('exec')) {
        $output = [];
        $return_var = 0;
        @exec('composer --version 2>&1', $output, $return_var);
        if ($return_var === 0) {
            $composerAvailable = true;
            $composerCommand = 'composer';
        } else {
            // Essayer avec composer.phar
            @exec('php composer.phar --version 2>&1', $output, $return_var);
            if ($return_var === 0) {
                $composerAvailable = true;
                $composerCommand = 'php composer.phar';
            }
        }
    }
    
    // Méthode 1: Installation automatique via Composer
    if ($composerAvailable) {
        echo "<div class='step step-success'>\n";
        echo "<h3>✅ Méthode 1: Installation automatique (Recommandée)</h3>\n";
        echo "<p>Composer est disponible sur votre système.</p>\n";
        echo "<a href='?action=install' class='btn btn-success'>Installer mPDF automatiquement</a>\n";
        echo "</div>\n";
    } else {
        echo "<div class='step step-warning'>\n";
        echo "<h3>⚠️ Méthode 1: Installation automatique (Non disponible)</h3>\n";
        echo "<p>Composer n'est pas installé ou la fonction exec() est désactivée.</p>\n";
        echo "</div>\n";
    }
    
    // Méthode 2: Installation manuelle via Composer
    echo "<div class='step'>\n";
    echo "<h3>📋 Méthode 2: Installation manuelle via Composer</h3>\n";
    echo "<p>Si vous avez accès au terminal de votre serveur :</p>\n";
    echo "<ol>\n";
    echo "<li>Naviguez vers le répertoire racine de l'application :</li>\n";
    echo "<div class='command-box'>cd " . realpath(__DIR__ . '/../../') . "</div>\n";
    echo "<li>Exécutez la commande Composer :</li>\n";
    echo "<div class='command-box'>composer require mpdf/mpdf</div>\n";
    echo "<li>mPDF sera installé dans <code>vendor/mpdf/</code></li>\n";
    echo "<li>Créez un lien symbolique vers le répertoire libs :</li>\n";
    
    if (PHP_OS_FAMILY === 'Windows') {
        echo "<div class='command-box'>mklink /D libs\\mpdf vendor\\mpdf</div>\n";
    } else {
        echo "<div class='command-box'>ln -s vendor/mpdf libs/mpdf</div>\n";
    }
    
    echo "</ol>\n";
    echo "</div>\n";
    
    // Méthode 3: Téléchargement manuel
    echo "<div class='step'>\n";
    echo "<h3>📦 Méthode 3: Téléchargement manuel</h3>\n";
    echo "<ol>\n";
    echo "<li>Téléchargez mPDF depuis <a href='https://github.com/mpdf/mpdf/releases' target='_blank'>GitHub</a></li>\n";
    echo "<li>Extrayez l'archive dans le répertoire <code>libs/mpdf/</code></li>\n";
    echo "<li>Assurez-vous que le fichier <code>libs/mpdf/autoload.php</code> existe</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    // Vérification de l'installation
    echo "<div class='step'>\n";
    echo "<h3>🔍 Vérifier l'installation</h3>\n";
    echo "<a href='?action=check' class='btn btn-primary'>Vérifier si mPDF est installé</a>\n";
    echo "</div>\n";
    
    echo "<div style='text-align: center; margin-top: 20px;'>\n";
    echo "<a href='list.php' class='btn btn-primary'>← Retour aux archives</a>\n";
    echo "</div>\n";
}

function installMpdf() {
    echo "<div class='step'>\n";
    echo "<h2>Installation en cours...</h2>\n";
    echo "</div>\n";
    
    $rootDir = realpath(__DIR__ . '/../../');
    $vendorDir = $rootDir . '/vendor';
    $libsDir = $rootDir . '/libs';
    
    // Vérifier si composer.json existe
    $composerJson = $rootDir . '/composer.json';
    if (!file_exists($composerJson)) {
        echo "<div class='step step-warning'>\n";
        echo "<h3>Création du fichier composer.json</h3>\n";
        
        $composerConfig = [
            'name' => 'minsante/dossier-management',
            'description' => 'Système de gestion des dossiers MINSANTE',
            'require' => [
                'mpdf/mpdf' => '^8.0'
            ],
            'config' => [
                'vendor-dir' => 'vendor'
            ]
        ];
        
        if (file_put_contents($composerJson, json_encode($composerConfig, JSON_PRETTY_PRINT))) {
            echo "<p>✅ Fichier composer.json créé avec succès.</p>\n";
        } else {
            echo "<p>❌ Erreur lors de la création de composer.json</p>\n";
            return;
        }
        echo "</div>\n";
    }
    
    // Exécuter composer install
    echo "<div class='step'>\n";
    echo "<h3>Installation via Composer</h3>\n";
    echo "<p>Exécution de la commande : <code>composer require mpdf/mpdf</code></p>\n";
    
    $output = [];
    $return_var = 0;
    
    // Changer vers le répertoire racine
    $originalDir = getcwd();
    chdir($rootDir);
    
    exec('composer require mpdf/mpdf 2>&1', $output, $return_var);
    
    // Retourner au répertoire original
    chdir($originalDir);
    
    if ($return_var === 0) {
        echo "<p>✅ Installation réussie !</p>\n";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>\n";
    } else {
        echo "<p>❌ Erreur lors de l'installation :</p>\n";
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>\n";
        echo "</div>\n";
        return;
    }
    echo "</div>\n";
    
    // Créer le lien symbolique
    echo "<div class='step'>\n";
    echo "<h3>Création du lien vers libs/</h3>\n";
    
    $mpdfVendorPath = $vendorDir . '/mpdf';
    $mpdfLibsPath = $libsDir . '/mpdf';
    
    if (!is_dir($libsDir)) {
        mkdir($libsDir, 0755, true);
    }
    
    if (is_dir($mpdfVendorPath)) {
        if (is_link($mpdfLibsPath)) {
            unlink($mpdfLibsPath);
        }
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Sur Windows, utiliser une copie plutôt qu'un lien symbolique
            if (copyDir($mpdfVendorPath, $mpdfLibsPath)) {
                echo "<p>✅ mPDF copié vers libs/mpdf/</p>\n";
            } else {
                echo "<p>❌ Erreur lors de la copie</p>\n";
            }
        } else {
            // Sur Unix, utiliser un lien symbolique
            if (symlink($mpdfVendorPath, $mpdfLibsPath)) {
                echo "<p>✅ Lien symbolique créé vers libs/mpdf/</p>\n";
            } else {
                echo "<p>❌ Erreur lors de la création du lien symbolique</p>\n";
            }
        }
    } else {
        echo "<p>❌ Répertoire vendor/mpdf non trouvé</p>\n";
    }
    echo "</div>\n";
    
    // Test final
    checkInstallation();
}

function checkInstallation() {
    echo "<div class='step'>\n";
    echo "<h2>Vérification de l'installation</h2>\n";
    
    $mpdfPath = __DIR__ . '/../../libs/mpdf/autoload.php';
    
    if (file_exists($mpdfPath)) {
        echo "<p>✅ Fichier autoload.php trouvé : <code>$mpdfPath</code></p>\n";
        
        try {
            require_once $mpdfPath;
            $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
            echo "<p>✅ mPDF initialisé avec succès !</p>\n";
            
            // Test de génération basique
            $testHtml = '<h1>Test mPDF</h1><p>Installation réussie le ' . date('Y-m-d H:i:s') . '</p>';
            $mpdf->WriteHTML($testHtml);
            echo "<p>✅ Test de génération HTML réussi !</p>\n";
            
            echo "<div class='step step-success'>\n";
            echo "<h3>🎉 Installation réussie !</h3>\n";
            echo "<p>mPDF est maintenant installé et fonctionnel. Vous pouvez utiliser l'export PDF.</p>\n";
            echo "<a href='test_export.php' class='btn btn-success'>Tester l'export PDF</a>\n";
            echo "</div>\n";
            
        } catch (Exception $e) {
            echo "<p>❌ Erreur lors de l'initialisation : " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    } else {
        echo "<p>❌ mPDF n'est pas installé correctement.</p>\n";
        echo "<p>Fichier manquant : <code>$mpdfPath</code></p>\n";
    }
    echo "</div>\n";
    
    echo "<div style='text-align: center; margin-top: 20px;'>\n";
    echo "<a href='install_pdf.php' class='btn btn-warning'>Configuration PDF</a>\n";
    echo "<a href='list.php' class='btn btn-primary'>← Retour aux archives</a>\n";
    echo "</div>\n";
}

function copyDir($src, $dst) {
    if (!is_dir($src)) return false;
    
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $srcFile = $src . '/' . $file;
        $dstFile = $dst . '/' . $file;
        
        if (is_dir($srcFile)) {
            copyDir($srcFile, $dstFile);
        } else {
            copy($srcFile, $dstFile);
        }
    }
    
    return true;
}

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?>
