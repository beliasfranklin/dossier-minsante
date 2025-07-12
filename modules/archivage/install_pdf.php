<?php
/**
 * Script d'installation et de test des bibliothèques PDF
 * Ce script aide à installer mPDF et configurer le système d'export PDF
 */

require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérifier les permissions admin
if (!hasPermission(ROLE_ADMIN)) {
    die(t('access_denied'));
}

$action = $_GET['action'] ?? 'check';

echo "<!DOCTYPE html>\n";
echo "<html lang='" . getCurrentLanguage() . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>Installation PDF - " . t('app_name_short') . "</title>\n";
echo "<link rel=\"stylesheet\" href=\"../../assets/css/style.css\">\n";
echo "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css\">\n";
echo "</head>\n";
echo "<body style='font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa;'>\n";

echo "<div style='max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);'>\n";
echo "<h1 style='color: #2980b9; margin-bottom: 20px;'><i class='fas fa-file-pdf'></i> Configuration Export PDF</h1>\n";

switch ($action) {
    case 'install_mpdf':
        installMPDF();
        break;
    case 'download_wkhtmltopdf':
        downloadWkhtmltopdf();
        break;
    case 'test_pdf':
        testPdfGeneration();
        break;
    default:
        showStatus();
        break;
}

function showStatus() {
    echo "<h2 style='color: #2980b9;'>📋 État du système PDF</h2>\n";
    
    // Vérifier mPDF
    $mpdfPath = __DIR__ . '/../../libs/mpdf/autoload.php';
    $mpdfExists = file_exists($mpdfPath);
    
    echo "<div style='background: " . ($mpdfExists ? '#e8f5e8' : '#fff3cd') . "; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: " . ($mpdfExists ? '#27ae60' : '#856404') . ";'>" . ($mpdfExists ? '✅' : '⚠️') . " mPDF</h3>\n";
    if ($mpdfExists) {
        echo "<p>mPDF est installé et prêt à utiliser.</p>\n";
    } else {
        echo "<p>mPDF n'est pas installé. Cette bibliothèque permet de générer des PDFs de haute qualité.</p>\n";
        echo "<a href='?action=install_mpdf' style='background: #2980b9; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>Installer mPDF</a>\n";
    }
    echo "</div>\n";
    
    // Vérifier wkhtmltopdf
    $wkhtmltopdf = findWkhtmltopdfForInstall();
    echo "<div style='background: " . ($wkhtmltopdf ? '#e8f5e8' : '#fff3cd') . "; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: " . ($wkhtmltopdf ? '#27ae60' : '#856404') . ";'>" . ($wkhtmltopdf ? '✅' : '⚠️') . " wkhtmltopdf</h3>\n";
    if ($wkhtmltopdf) {
        echo "<p>wkhtmltopdf trouvé à: <code>$wkhtmltopdf</code></p>\n";
    } else {
        echo "<p>wkhtmltopdf n'est pas installé. Cet outil convertit HTML en PDF de façon très fidèle.</p>\n";
        echo "<a href='?action=download_wkhtmltopdf' style='background: #27ae60; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>Télécharger wkhtmltopdf</a>\n";
    }
    echo "</div>\n";
    
    // Test des capacités du navigateur
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #1976d2;'>🌐 Génération PDF par le navigateur</h3>\n";
    echo "<p>Le système peut toujours générer des rapports HTML optimisés que l'utilisateur peut imprimer en PDF via son navigateur.</p>\n";
    echo "<p>Cette méthode fonctionne toujours et ne nécessite aucune installation.</p>\n";
    echo "</div>\n";
    
    // Test
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #2980b9;'>🧪 Tester la génération PDF</h3>\n";
    echo "<p>Testez les différentes méthodes de génération PDF disponibles.</p>\n";
    echo "<a href='?action=test_pdf' style='background: #e74c3c; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; margin-right: 10px;'>Tester PDF</a>\n";
    echo "<a href='test_export.php' style='background: #f39c12; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>Test Export Complet</a>\n";
    echo "</div>\n";
}

function findWkhtmltopdfForInstall() {
    $possible_paths = [
        'wkhtmltopdf',
        '/usr/bin/wkhtmltopdf',
        '/usr/local/bin/wkhtmltopdf',
        'C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe',
        'C:\wkhtmltopdf\bin\wkhtmltopdf.exe'
    ];
    
    foreach ($possible_paths as $path) {
        $output = [];
        $return_var = 0;
        @exec("$path --version 2>&1", $output, $return_var);
        if ($return_var === 0) {
            return $path;
        }
    }
    return false;
}

function installMPDF() {
    echo "<h2 style='color: #2980b9;'>📦 Installation de mPDF</h2>\n";
    
    $libsDir = __DIR__ . '/../../libs';
    $mpdfDir = $libsDir . '/mpdf';
    
    // Créer le dossier libs s'il n'existe pas
    if (!is_dir($libsDir)) {
        mkdir($libsDir, 0755, true);
        echo "<p>✅ Dossier libs créé.</p>\n";
    }
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #856404;'>⚠️ Installation manuelle requise</h3>\n";
    echo "<p>Pour installer mPDF, vous devez :</p>\n";
    echo "<ol>\n";
    echo "<li>Télécharger mPDF depuis <a href='https://github.com/mpdf/mpdf/releases' target='_blank'>GitHub</a></li>\n";
    echo "<li>Extraire l'archive dans le dossier <code>" . realpath($libsDir) . "</code></li>\n";
    echo "<li>Renommer le dossier en <code>mpdf</code></li>\n";
    echo "<li>Ou utiliser Composer: <code>composer require mpdf/mpdf</code></li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    // Alternative: créer un téléchargeur simple
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #1976d2;'>🔗 Alternative: Téléchargement automatique</h3>\n";
    echo "<p>Vous pouvez aussi créer un script de téléchargement automatique.</p>\n";
    
    if (isset($_POST['auto_download'])) {
        echo "<p>Tentative de téléchargement automatique...</p>\n";
        
        $url = 'https://github.com/mpdf/mpdf/archive/refs/tags/v8.2.3.zip';
        $zipFile = $libsDir . '/mpdf.zip';
        
        try {
            $content = file_get_contents($url);
            if ($content !== false) {
                file_put_contents($zipFile, $content);
                echo "<p>✅ Archive téléchargée.</p>\n";
                
                // Tenter d'extraire
                if (class_exists('ZipArchive')) {
                    $zip = new ZipArchive;
                    if ($zip->open($zipFile) === TRUE) {
                        $zip->extractTo($libsDir);
                        $zip->close();
                        
                        // Renommer le dossier
                        $extractedDir = $libsDir . '/mpdf-8.2.3';
                        if (is_dir($extractedDir)) {
                            rename($extractedDir, $mpdfDir);
                            echo "<p>✅ mPDF installé avec succès!</p>\n";
                            unlink($zipFile);
                        }
                    }
                } else {
                    echo "<p>⚠️ Extension ZIP non disponible. Extraction manuelle requise.</p>\n";
                }
            }
        } catch (Exception $e) {
            echo "<p>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    } else {
        echo "<form method='post'>\n";
        echo "<button type='submit' name='auto_download' style='background: #2980b9; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;'>Télécharger automatiquement</button>\n";
        echo "</form>\n";
    }
    echo "</div>\n";
    
    echo "<a href='?' style='background: #95a5a6; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>← Retour</a>\n";
}

function downloadWkhtmltopdf() {
    echo "<h2 style='color: #2980b9;'>⬇️ Téléchargement de wkhtmltopdf</h2>\n";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #856404;'>📥 Instructions de téléchargement</h3>\n";
    echo "<p>Téléchargez wkhtmltopdf depuis le site officiel :</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Windows:</strong> <a href='https://wkhtmltopdf.org/downloads.html' target='_blank'>wkhtmltox-0.12.6-1.msvc2015-win64.exe</a></li>\n";
    echo "<li><strong>Linux (Ubuntu/Debian):</strong> <code>sudo apt-get install wkhtmltopdf</code></li>\n";
    echo "<li><strong>Linux (CentOS/RHEL):</strong> <code>sudo yum install wkhtmltopdf</code></li>\n";
    echo "<li><strong>macOS:</strong> <code>brew install wkhtmltopdf</code></li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    echo "<div style='background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #1976d2;'>⚙️ Installation sur Windows</h3>\n";
    echo "<ol>\n";
    echo "<li>Téléchargez l'installateur Windows</li>\n";
    echo "<li>Exécutez l'installateur en tant qu'administrateur</li>\n";
    echo "<li>Installez dans <code>C:\\wkhtmltopdf</code></li>\n";
    echo "<li>Ajoutez <code>C:\\wkhtmltopdf\\bin</code> au PATH système (optionnel)</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<a href='?' style='background: #95a5a6; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>← Retour</a>\n";
}

function testPdfGeneration() {
    echo "<h2 style='color: #2980b9;'>🧪 Test de génération PDF</h2>\n";
    
    // Créer un dossier de test factice
    $testData = [
        'id' => 999,
        'reference' => 'TEST-PDF-' . date('Ymd'),
        'titre' => 'Test de génération PDF',
        'description' => 'Ce dossier est utilisé pour tester la génération de rapports PDF.',
        'type' => 'Test',
        'service' => 'IT',
        'status' => 'archive',
        'priority' => 'normale',
        'responsable_name' => 'Utilisateur Test',
        'responsable_email' => 'test@example.com',
        'created_by_name' => 'Système',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'deadline' => null
    ];
    
    $testAttachments = [];
    $testHistory = [
        [
            'action_type' => 'Création',
            'action_details' => 'Dossier créé pour test PDF',
            'user_name' => 'Système Test',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>\n";
    echo "<h3 style='color: #27ae60;'>📄 Tests disponibles</h3>\n";
    echo "<p>Testez les différentes méthodes de génération avec des données factices :</p>\n";
    
    // Test HTML imprimable
    $htmlUrl = "export.php?id=test&format=html";
    echo "<div style='margin: 10px 0;'>\n";
    echo "<a href='#' onclick='testHtmlGeneration()' style='background: #2980b9; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; margin-right: 10px;'>📄 Test HTML</a>\n";
    echo "<small>Génère un rapport HTML optimisé pour l'impression</small>\n";
    echo "</div>\n";
    
    // Test PDF direct
    echo "<div style='margin: 10px 0;'>\n";
    echo "<a href='#' onclick='testPdfGeneration()' style='background: #e74c3c; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; margin-right: 10px;'>📄 Test PDF</a>\n";
    echo "<small>Teste la génération PDF avec les bibliothèques disponibles</small>\n";
    echo "</div>\n";
    
    echo "</div>\n";
    
    echo "<script>\n";
    echo "function testHtmlGeneration() {\n";
    echo "    window.open('test_pdf_generation.php?format=html', '_blank');\n";
    echo "}\n";
    echo "function testPdfGeneration() {\n";
    echo "    window.open('test_pdf_generation.php?format=pdf', '_blank');\n";
    echo "}\n";
    echo "</script>\n";
    
    echo "<a href='?' style='background: #95a5a6; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;'>← Retour</a>\n";
}

echo "</div>\n";
echo "</body>\n";
echo "</html>\n";
?>
