<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Vérification des permissions (seuls les admins peuvent exporter)
if (!hasPermission(ROLE_ADMIN)) {
    die(t('access_denied'));
}

$dossierId = (int)($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'pdf';
$forceRegenerate = isset($_GET['force_regenerate']);

if (!$dossierId) {
    logExportError("ID dossier manquant", ['request' => $_GET]);
    die(t('missing_file_id'));
}

// Configuration du cache
$cacheDir = __DIR__ . '/../../cache/exports/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Récupérer les informations du dossier archivé avec optimisation
$dossier = fetchOne("
    SELECT d.*, u.name as responsable_name, u.email as responsable_email,
           c.name as created_by_name
    FROM dossiers d
    JOIN users u ON d.responsable_id = u.id
    LEFT JOIN users c ON d.created_by = c.id
    WHERE d.id = ? AND d.status = 'archive'
", [$dossierId]);

if (!$dossier) {
    logExportError("Dossier archivé non trouvé", ['dossier_id' => $dossierId]);
    die(t('archived_file_not_found'));
}

// Vérifier le cache si force_regenerate n'est pas activé
$cacheKey = "export_{$dossierId}_{$format}_" . md5($dossier['updated_at']);
$cacheFile = $cacheDir . $cacheKey . '.' . ($format === 'pdf' ? 'pdf' : 'html');

if (!$forceRegenerate && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    // Servir depuis le cache (1 heure de validité)
    serveCachedFile($cacheFile, $dossier['reference'], $format);
    exit;
}

// Récupérer les données avec requêtes optimisées
$attachments = fetchAll("
    SELECT id, nom_fichier, uploaded_at 
    FROM pieces_jointes 
    WHERE dossier_id = ? 
    ORDER BY uploaded_at DESC
", [$dossierId]);

$history = fetchAll("
    SELECT h.action_type, h.action_details, h.created_at, u.name as user_name
    FROM historiques h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.dossier_id = ?
    ORDER BY h.created_at DESC
", [$dossierId]);

// Générer l'export selon le format
try {
    if ($format === 'pdf') {
        generatePdfExport($dossier, $attachments, $history, $cacheFile);
    } else {
        generateHtmlExport($dossier, $attachments, $history, $cacheFile);
    }
    
    logExportSuccess($dossierId, $format, $_SESSION['user_id'] ?? 0);
} catch (Exception $e) {
    logExportError("Erreur lors de l'export", [
        'dossier_id' => $dossierId,
        'format' => $format,
        'error' => $e->getMessage()
    ]);
    
    // Fallback vers HTML imprimable
    if ($format === 'pdf') {
        generatePrintableHtml($dossier, $attachments, $history);
    } else {
        die(t('export_error') . ': ' . $e->getMessage());
    }
}

function generatePdfExport($dossier, $attachments, $history, $cacheFile) {
    $pdfGenerated = false;
    $errors = [];
    
    // Méthode 1: Essayer mPDF avec gestion d'erreurs améliorée
    $mpdfPath = __DIR__ . '/../../libs/mpdf/autoload.php';
    if (file_exists($mpdfPath)) {
        try {
            require_once $mpdfPath;
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 20,
                'tempDir' => sys_get_temp_dir(),
                'default_font' => 'arial'
            ]);
            
            // Configuration optimisée
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            
            $html = generatePdfContent($dossier, $attachments, $history);
            $mpdf->WriteHTML($html);
            
            // Sauvegarder dans le cache
            $mpdf->Output($cacheFile, 'F');
            
            // Servir le fichier
            $filename = 'dossier_archive_' . $dossier['reference'] . '_' . date('Ymd') . '.pdf';
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($cacheFile));
            readfile($cacheFile);
            
            $pdfGenerated = true;
            logExportMethod('mPDF', true, $dossier['id']);
        } catch (Exception $e) {
            $errors[] = 'mPDF: ' . $e->getMessage();
            logExportMethod('mPDF', false, $dossier['id'], $e->getMessage());
        }
    }
    
    // Méthode 2: Essayer wkhtmltopdf si mPDF a échoué
    if (!$pdfGenerated && function_exists('exec')) {
        $wkhtmltopdf = findWkhtmltopdf();
        if ($wkhtmltopdf) {
            try {
                if (generatePdfWithWkhtmltopdf($dossier, $attachments, $history, $wkhtmltopdf, $cacheFile)) {
                    $pdfGenerated = true;
                    logExportMethod('wkhtmltopdf', true, $dossier['id']);
                }
            } catch (Exception $e) {
                $errors[] = 'wkhtmltopdf: ' . $e->getMessage();
                logExportMethod('wkhtmltopdf', false, $dossier['id'], $e->getMessage());
            }
        }
    }
    
    // Méthode 3: Générer un HTML optimisé pour l'impression PDF si tout a échoué
    if (!$pdfGenerated) {
        logExportMethod('fallback_html', true, $dossier['id'], 'PDF methods failed: ' . implode(', ', $errors));
        generatePrintableHtml($dossier, $attachments, $history);
    }
}

function generateHtmlExport($dossier, $attachments, $history, $cacheFile) {
    $html = generateHtmlReport($dossier, $attachments, $history, true);
    
    // Sauvegarder dans le cache
    file_put_contents($cacheFile, $html);
    
    // Servir le fichier HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function serveCachedFile($cacheFile, $reference, $format) {
    if ($format === 'pdf') {
        $filename = 'dossier_archive_' . $reference . '_' . date('Ymd') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }
    
    header('Content-Length: ' . filesize($cacheFile));
    header('Cache-Control: public, max-age=3600');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($cacheFile)) . ' GMT');
    
    readfile($cacheFile);
}

function logExportError($message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'ERROR',
        'message' => $message,
        'context' => $context,
        'user_id' => $_SESSION['user_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logFile = __DIR__ . '/../../logs/export.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

function logExportSuccess($dossierId, $format, $userId) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'SUCCESS',
        'message' => 'Export successful',
        'dossier_id' => $dossierId,
        'format' => $format,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logFile = __DIR__ . '/../../logs/export.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

function logExportMethod($method, $success, $dossierId, $error = null) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $success ? 'INFO' : 'WARNING',
        'message' => "Export method: $method " . ($success ? 'succeeded' : 'failed'),
        'dossier_id' => $dossierId,
        'method' => $method,
        'success' => $success,
        'error' => $error
    ];
    
    $logFile = __DIR__ . '/../../logs/export.log';
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

function findWkhtmltopdf() {
    $possible_paths = [
        'wkhtmltopdf',  // Si dans le PATH
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

function generatePdfWithWkhtmltopdf($dossier, $attachments, $history, $wkhtmltopdf, $cacheFile) {
    // Créer un fichier HTML temporaire
    $tempHtml = tempnam(sys_get_temp_dir(), 'archive_') . '.html';
    $tempPdf = tempnam(sys_get_temp_dir(), 'archive_') . '.pdf';
    
    $html = generatePrintableHtmlContent($dossier, $attachments, $history, true);
    file_put_contents($tempHtml, $html);
    
    // Options wkhtmltopdf
    $options = [
        '--page-size A4',
        '--margin-top 20mm',
        '--margin-bottom 20mm',
        '--margin-left 15mm',
        '--margin-right 15mm',
        '--encoding UTF-8',
        '--enable-local-file-access',
        '--print-media-type'
    ];
    
    $command = escapeshellarg($wkhtmltopdf) . ' ' . implode(' ', $options) . ' ' . escapeshellarg($tempHtml) . ' ' . escapeshellarg($tempPdf);
    
    $output = [];
    $return_var = 0;
    exec($command . ' 2>&1', $output, $return_var);
    
    if ($return_var === 0 && file_exists($tempPdf)) {
        // Sauvegarder dans le cache
        copy($tempPdf, $cacheFile);
        
        $filename = 'dossier_archive_' . $dossier['reference'] . '_' . date('Ymd') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($cacheFile));
        
        readfile($cacheFile);
        
        unlink($tempHtml);
        unlink($tempPdf);
        
        return true;
    } else {
        unlink($tempHtml);
        if (file_exists($tempPdf)) unlink($tempPdf);
        
        // Fallback vers HTML imprimable
        generatePrintableHtml($dossier, $attachments, $history);
        return false;
    }
}

function generatePrintableHtml($dossier, $attachments, $history) {
    $html = generatePrintableHtmlContent($dossier, $attachments, $history, false);
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function generatePrintableHtmlContent($dossier, $attachments, $history, $isPdfMode = false) {
    $statusColors = [
        'en_attente' => '#f39c12',
        'en_cours' => '#3498db', 
        'valide' => '#27ae60',
        'rejete' => '#e74c3c',
        'archive' => '#95a5a6'
    ];
    
    $priorityColors = [
        'basse' => '#95a5a6',
        'normale' => '#3498db',
        'haute' => '#f39c12',
        'urgente' => '#e74c3c'
    ];
    
    $printControls = $isPdfMode ? '' : '
        <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
            <button onclick="window.print()" style="background: #27ae60; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; margin-right: 10px;">
                <i class="fas fa-print"></i> ' . t('print') . '
            </button>
            <button onclick="downloadAsPdf()" style="background: #e74c3c; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-file-pdf"></i> Télécharger PDF
            </button>
        </div>';
    
    $backButton = $isPdfMode ? '' : '
        <div class="no-print" style="text-align: center; margin: 20px; color: #666;">
            <a href="list.php" style="background: #95a5a6; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> ' . t('back_to_list') . '
            </a>
        </div>';
    
    $javascript = $isPdfMode ? '' : '
        <script>
        function downloadAsPdf() {
            // Ouvrir une nouvelle fenêtre pour la génération PDF via navigateur
            const url = window.location.href.replace("format=html", "format=pdf");
            window.open(url, "_blank");
        }
        
        // Optimiser pour l\'impression
        window.addEventListener("beforeprint", function() {
            document.querySelectorAll(".no-print").forEach(el => el.style.display = "none");
        });
        
        window.addEventListener("afterprint", function() {
            document.querySelectorAll(".no-print").forEach(el => el.style.display = "block");
        });
        </script>';
    
    return '<!DOCTYPE html>
    <html lang="' . getCurrentLanguage() . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . t('archived_file_report') . ' - ' . htmlspecialchars($dossier['reference']) . '</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            * { box-sizing: border-box; }
            body { 
                font-family: "Arial", "Helvetica", sans-serif; 
                margin: 0; 
                padding: 20px; 
                background: ' . ($isPdfMode ? '#fff' : '#f8f9fa') . '; 
                color: #333;
                line-height: 1.6;
            }
            
            .container { 
                max-width: 800px; 
                margin: 0 auto; 
                background: white; 
                border-radius: ' . ($isPdfMode ? '0' : '12px') . '; 
                box-shadow: ' . ($isPdfMode ? 'none' : '0 4px 20px rgba(0,0,0,0.1)') . '; 
                overflow: hidden; 
            }
            
            .header { 
                background: linear-gradient(135deg, #2980b9, #3498db); 
                color: white; 
                padding: 30px; 
                text-align: center; 
            }
            
            .header h1 { 
                margin: 0 0 10px 0; 
                font-size: 28px; 
                font-weight: bold; 
            }
            
            .header p { 
                margin: 5px 0; 
                font-size: 16px; 
            }
            
            .archived-notice {
                background: rgba(231, 76, 60, 0.2); 
                padding: 10px; 
                border-radius: 8px; 
                margin-top: 15px;
                border: 2px solid #e74c3c;
            }
            
            .content { 
                padding: 30px; 
            }
            
            .section { 
                margin-bottom: 30px; 
                page-break-inside: avoid;
            }
            
            .section-title { 
                background: #f8f9fa; 
                padding: 15px; 
                border-left: 4px solid #2980b9; 
                font-weight: bold; 
                font-size: 18px;
                margin-bottom: 20px; 
                border-radius: 0 8px 8px 0; 
            }
            
            .info-grid { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
            }
            
            .info-grid td { 
                padding: 12px; 
                border-bottom: 1px solid #eee; 
                vertical-align: top;
            }
            
            .info-grid .label { 
                font-weight: bold; 
                background: #f8f9fa; 
                width: 30%; 
            }
            
            .status-badge { 
                padding: 6px 12px; 
                border-radius: 20px; 
                color: white; 
                font-weight: bold; 
                font-size: 0.9em; 
                display: inline-block;
            }
            
            .attachment-item, .history-item { 
                padding: 12px; 
                margin-bottom: 8px; 
                background: #f8f9fa; 
                border-radius: 8px; 
                border-left: 4px solid #2980b9;
            }
            
            .attachment-item strong, .history-item strong {
                color: #2980b9;
            }
            
            .footer {
                margin-top: 40px; 
                text-align: center; 
                color: #666; 
                font-size: 12px; 
                border-top: 1px solid #ddd; 
                padding-top: 20px;
            }
            
            @media print {
                body { 
                    background: white; 
                    font-size: 12px; 
                }
                .container { 
                    box-shadow: none; 
                    border-radius: 0; 
                }
                .no-print { 
                    display: none !important; 
                }
                .section { 
                    page-break-inside: avoid; 
                }
                .header { 
                    page-break-after: avoid; 
                }
            }
        </style>
    </head>
    <body>
        ' . $printControls . '
        
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-archive"></i> ' . t('archived_file_report') . '</h1>
                <p><strong>' . t('reference') . ':</strong> ' . htmlspecialchars($dossier['reference']) . '</p>
                <p>' . t('generated_on') . ' ' . date('d/m/Y à H:i') . '</p>
                <div class="archived-notice">
                    <strong><i class="fas fa-exclamation-triangle"></i> ' . t('archived_document') . '</strong>
                </div>
            </div>
            
            <div class="content">
                <div class="section">
                    <div class="section-title">
                        <i class="fas fa-folder"></i> ' . t('general_info') . '
                    </div>
                    <table class="info-grid">
                        <tr>
                            <td class="label">' . t('reference') . '</td>
                            <td><strong>' . htmlspecialchars($dossier['reference']) . '</strong></td>
                        </tr>
                        <tr>
                            <td class="label">' . t('title') . '</td>
                            <td>' . htmlspecialchars($dossier['titre']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('description') . '</td>
                            <td>' . nl2br(htmlspecialchars($dossier['description'])) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('type') . '</td>
                            <td>' . htmlspecialchars($dossier['type']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('service') . '</td>
                            <td>' . htmlspecialchars($dossier['service']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('status') . '</td>
                            <td><span class="status-badge" style="background: ' . ($statusColors[$dossier['status']] ?? '#95a5a6') . ';">' . strtoupper($dossier['status']) . '</span></td>
                        </tr>
                        <tr>
                            <td class="label">' . t('priority') . '</td>
                            <td><span class="status-badge" style="background: ' . ($priorityColors[$dossier['priority']] ?? '#95a5a6') . ';">' . strtoupper($dossier['priority']) . '</span></td>
                        </tr>
                        <tr>
                            <td class="label">' . t('responsible') . '</td>
                            <td>' . htmlspecialchars($dossier['responsable_name']) . ' (' . htmlspecialchars($dossier['responsable_email']) . ')</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('created_by') . '</td>
                            <td>' . htmlspecialchars($dossier['created_by_name'] ?? t('not_specified')) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('creation_date') . '</td>
                            <td>' . formatDate($dossier['created_at']) . '</td>
                        </tr>
                        <tr>
                            <td class="label">' . t('last_modified') . '</td>
                            <td>' . formatDate($dossier['updated_at']) . '</td>
                        </tr>' . 
                        ($dossier['deadline'] ? '<tr>
                            <td class="label">' . t('deadline') . '</td>
                            <td>' . formatDate($dossier['deadline']) . '</td>
                        </tr>' : '') . '
                    </table>
                </div>';

    // Pièces jointes
    if (!empty($attachments)) {
        $html .= '<div class="section">
                    <div class="section-title">
                        <i class="fas fa-paperclip"></i> ' . t('attachments') . ' (' . count($attachments) . ')
                    </div>';
        
        foreach ($attachments as $attachment) {
            $html .= '<div class="attachment-item">
                        <strong><i class="fas fa-file"></i> ' . htmlspecialchars($attachment['nom_fichier']) . '</strong><br>
                        <small><i class="fas fa-info-circle"></i> ' . t('added_on') . ': ' . formatDate($attachment['uploaded_at']) . '</small>
                    </div>';
        }
        
        $html .= '</div>';
    }
    
    // Historique
    if (!empty($history)) {
        $html .= '<div class="section">
                    <div class="section-title">
                        <i class="fas fa-history"></i> ' . t('modification_history') . ' (' . count($history) . ')
                    </div>';
        
        foreach ($history as $entry) {
            $html .= '<div class="history-item">
                        <strong>' . htmlspecialchars($entry['action_type']) . '</strong> 
                        ' . t('by_user') . ' ' . htmlspecialchars($entry['user_name'] ?? 'Système') . ' 
                        ' . t('on_date') . ' ' . formatDate($entry['created_at']) . '<br>';
                        
            if ($entry['action_details']) {
                $html .= '<small>' . htmlspecialchars($entry['action_details']) . '</small>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '      <div class="footer">
                        ' . t('app_name') . '<br>
                        ' . BASE_URL . ' | ' . date('d/m/Y à H:i:s') . '
                    </div>
                </div>
            </div>
            
            ' . $backButton . '
            ' . $javascript . '
        </body>
    </html>';
    
    return $html;
}

function generatePdfContent($dossier, $attachments, $history) {
    $statusColors = [
        'en_attente' => '#f39c12',
        'en_cours' => '#3498db', 
        'valide' => '#27ae60',
        'rejete' => '#e74c3c',
        'archive' => '#95a5a6'
    ];
    
    $priorityColors = [
        'basse' => '#95a5a6',
        'normale' => '#3498db',
        'haute' => '#f39c12',
        'urgente' => '#e74c3c'
    ];
    
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #2980b9; padding-bottom: 20px; }
        .header h1 { color: #2980b9; margin: 0; font-size: 24px; }
        .header p { color: #666; margin: 10px 0 0 0; }
        .section { margin-bottom: 25px; }
        .section-title { background: #f8f9fa; padding: 10px; border-left: 4px solid #2980b9; font-weight: bold; margin-bottom: 15px; }
        .info-grid { width: 100%; border-collapse: collapse; }
        .info-grid td { padding: 8px; border-bottom: 1px solid #eee; }
        .info-grid .label { font-weight: bold; background: #f8f9fa; width: 30%; }
        .status-badge { padding: 4px 8px; border-radius: 4px; color: white; font-weight: bold; }
        .attachment-item { padding: 5px 0; border-bottom: 1px solid #eee; }
        .history-item { padding: 8px; margin-bottom: 5px; border-left: 3px solid #2980b9; background: #f8f9fa; }
    </style>
    
    <div class="header">
        <h1>' . t('archived_file_report') . '</h1>
        <p>' . t('generated_on') . ' ' . date('d/m/Y à H:i') . '</p>
        <p style="color: #e74c3c; font-weight: bold;">' . t('archived_document') . '</p>
    </div>
    
    <div class="section">
        <div class="section-title">📁 ' . t('general_info') . '</div>
        <table class="info-grid">
            <tr>
                <td class="label">Référence</td>
                <td><strong>' . htmlspecialchars($dossier['reference']) . '</strong></td>
            </tr>
            <tr>
                <td class="label">Titre</td>
                <td>' . htmlspecialchars($dossier['titre']) . '</td>
            </tr>
            <tr>
                <td class="label">Description</td>
                <td>' . nl2br(htmlspecialchars($dossier['description'])) . '</td>
            </tr>
            <tr>
                <td class="label">Type</td>
                <td>' . htmlspecialchars($dossier['type']) . '</td>
            </tr>
            <tr>
                <td class="label">Service</td>
                <td>' . htmlspecialchars($dossier['service']) . '</td>
            </tr>
            <tr>
                <td class="label">Statut</td>
                <td><span class="status-badge" style="background: ' . ($statusColors[$dossier['status']] ?? '#95a5a6') . ';">' . strtoupper($dossier['status']) . '</span></td>
            </tr>
            <tr>
                <td class="label">Priorité</td>
                <td><span class="status-badge" style="background: ' . ($priorityColors[$dossier['priority']] ?? '#95a5a6') . ';">' . strtoupper($dossier['priority']) . '</span></td>
            </tr>
            <tr>
                <td class="label">Responsable</td>
                <td>' . htmlspecialchars($dossier['responsable_name']) . ' (' . htmlspecialchars($dossier['responsable_email']) . ')</td>
            </tr>
            <tr>
                <td class="label">Créé par</td>
                <td>' . htmlspecialchars($dossier['created_by_name'] ?? 'Non spécifié') . '</td>
            </tr>
            <tr>
                <td class="label">Date de création</td>
                <td>' . formatDate($dossier['created_at']) . '</td>
            </tr>
            <tr>
                <td class="label">Dernière modification</td>
                <td>' . formatDate($dossier['updated_at']) . '</td>
            </tr>';
            
    if ($dossier['deadline']) {
        $html .= '<tr>
                <td class="label">Échéance</td>
                <td>' . formatDate($dossier['deadline']) . '</td>
            </tr>';
    }
    
    $html .= '</table>
    </div>';
    
    // Pièces jointes
    if (!empty($attachments)) {
        $html .= '
        <div class="section">
            <div class="section-title">📎 Pièces Jointes (' . count($attachments) . ')</div>';
        
        foreach ($attachments as $attachment) {
            $html .= '<div class="attachment-item">
                <strong>' . htmlspecialchars($attachment['nom_fichier']) . '</strong><br>
                <small>Ajouté le: ' . formatDate($attachment['uploaded_at']) . '</small>
            </div>';
        }
        
        $html .= '</div>';
    }
    
    // Historique
    if (!empty($history)) {
        $html .= '
        <div class="section">
            <div class="section-title">📋 Historique des Modifications (' . count($history) . ')</div>';
        
        foreach ($history as $entry) {
            $html .= '<div class="history-item">
                <strong>' . htmlspecialchars($entry['action_type']) . '</strong> 
                par ' . htmlspecialchars($entry['user_name'] ?? 'Système') . ' 
                le ' . formatDate($entry['created_at']) . '<br>';
                
            if ($entry['action_details']) {
                $html .= '<small>' . htmlspecialchars($entry['action_details']) . '</small>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
    <div style="margin-top: 40px; text-align: center; color: #666; font-size: 10px; border-top: 1px solid #ddd; padding-top: 20px;">
        Document généré automatiquement par le Système de Gestion des Dossiers - MINSANTE<br>
        ' . BASE_URL . ' | ' . date('d/m/Y à H:i:s') . '
    </div>';
    
    return $html;
}

function generateHtmlReport($dossier, $attachments, $history) {
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Rapport Dossier Archivé - ' . htmlspecialchars($dossier['reference']) . '</title>
        <link rel="stylesheet" href="../../assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            body { background: #f8f9fa; }
            .report-container { max-width: 800px; margin: 20px auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
            .report-header { background: linear-gradient(135deg, #2980b9, #3498db); color: white; padding: 30px; text-align: center; }
            .report-content { padding: 30px; }
            .section { margin-bottom: 30px; }
            .section-title { background: #f8f9fa; padding: 15px; border-left: 4px solid #2980b9; font-weight: bold; margin-bottom: 20px; border-radius: 0 8px 8px 0; }
            .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .info-grid td { padding: 12px; border-bottom: 1px solid #eee; }
            .info-grid .label { font-weight: bold; background: #f8f9fa; width: 30%; }
            .status-badge { padding: 6px 12px; border-radius: 20px; color: white; font-weight: bold; font-size: 0.9em; }
            .attachment-item { padding: 10px; margin-bottom: 8px; background: #f8f9fa; border-radius: 8px; }
            .history-item { padding: 12px; margin-bottom: 8px; border-left: 4px solid #2980b9; background: #f8f9fa; border-radius: 0 8px 8px 0; }
            .print-btn { position: fixed; top: 20px; right: 20px; background: #27ae60; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; z-index: 1000; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimer</button>
        
        <div class="report-container">
            <div class="report-header">
                <h1><i class="fas fa-archive"></i> ' . t('archived_file_report') . '</h1>
                <p>' . t('reference') . ': ' . htmlspecialchars($dossier['reference']) . '</p>
                <p>' . t('generated_on') . ' ' . date('d/m/Y à H:i') . '</p>
                <div style="background: rgba(231, 76, 60, 0.2); padding: 10px; border-radius: 8px; margin-top: 15px;">
                    <strong><i class="fas fa-exclamation-triangle"></i> ' . t('archived_document') . '</strong>
                </div>
            </div>
            
            <div class="report-content">';
    
    // Contenu identique à la version PDF mais en HTML
    echo generateHtmlContent($dossier, $attachments, $history);
    
    echo '    </div>
        </div>
        
        <div style="text-align: center; margin: 20px; color: #666;">
            <a href="list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> ' . t('back_to_list') . '</a>
        </div>
    </body>
    </html>';
}

function generateHtmlContent($dossier, $attachments, $history) {
    $statusColors = [
        'en_attente' => '#f39c12',
        'en_cours' => '#3498db', 
        'valide' => '#27ae60',
        'rejete' => '#e74c3c',
        'archive' => '#95a5a6'
    ];
    
    $priorityColors = [
        'basse' => '#95a5a6',
        'normale' => '#3498db',
        'haute' => '#f39c12',
        'urgente' => '#e74c3c'
    ];
    
    echo '<div class="section">
            <div class="section-title"><i class="fas fa-folder"></i> Informations Générales</div>
            <table class="info-grid">
                <tr>
                    <td class="label">Référence</td>
                    <td><strong>' . htmlspecialchars($dossier['reference']) . '</strong></td>
                </tr>
                <tr>
                    <td class="label">Titre</td>
                    <td>' . htmlspecialchars($dossier['titre']) . '</td>
                </tr>
                <tr>
                    <td class="label">Description</td>
                    <td>' . nl2br(htmlspecialchars($dossier['description'])) . '</td>
                </tr>
                <tr>
                    <td class="label">Type</td>
                    <td>' . htmlspecialchars($dossier['type']) . '</td>
                </tr>
                <tr>
                    <td class="label">Service</td>
                    <td>' . htmlspecialchars($dossier['service']) . '</td>
                </tr>
                <tr>
                    <td class="label">Statut</td>
                    <td><span class="status-badge" style="background: ' . ($statusColors[$dossier['status']] ?? '#95a5a6') . ';">' . strtoupper($dossier['status']) . '</span></td>
                </tr>
                <tr>
                    <td class="label">Priorité</td>
                    <td><span class="status-badge" style="background: ' . ($priorityColors[$dossier['priority']] ?? '#95a5a6') . ';">' . strtoupper($dossier['priority']) . '</span></td>
                </tr>
                <tr>
                    <td class="label">Responsable</td>
                    <td>' . htmlspecialchars($dossier['responsable_name']) . ' (' . htmlspecialchars($dossier['responsable_email']) . ')</td>
                </tr>
                <tr>
                    <td class="label">Créé par</td>
                    <td>' . htmlspecialchars($dossier['created_by_name'] ?? 'Non spécifié') . '</td>
                </tr>
                <tr>
                    <td class="label">Date de création</td>
                    <td>' . formatDate($dossier['created_at']) . '</td>
                </tr>
                <tr>
                    <td class="label">Dernière modification</td>
                    <td>' . formatDate($dossier['updated_at']) . '</td>
                </tr>';
                
    if ($dossier['deadline']) {
        echo '<tr>
                <td class="label">Échéance</td>
                <td>' . formatDate($dossier['deadline']) . '</td>
            </tr>';
    }
    
    echo '</table>
        </div>';
    
    // Pièces jointes
    if (!empty($attachments)) {
        echo '<div class="section">
                <div class="section-title"><i class="fas fa-paperclip"></i> Pièces Jointes (' . count($attachments) . ')</div>';
        
        foreach ($attachments as $attachment) {
            echo '<div class="attachment-item">
                    <strong><i class="fas fa-file"></i> ' . htmlspecialchars($attachment['nom_fichier']) . '</strong><br>
                    <small><i class="fas fa-info-circle"></i> Ajouté le: ' . formatDate($attachment['uploaded_at']) . '</small>
                </div>';
        }
        
        echo '</div>';
    }
    
    // Historique
    if (!empty($history)) {
        echo '<div class="section">
                <div class="section-title"><i class="fas fa-history"></i> Historique des Modifications (' . count($history) . ')</div>';
        
        foreach ($history as $entry) {
            echo '<div class="history-item">
                    <strong>' . htmlspecialchars($entry['action_type']) . '</strong> 
                    par ' . htmlspecialchars($entry['user_name'] ?? 'Système') . ' 
                    le ' . formatDate($entry['created_at']) . '<br>';
                    
            if ($entry['action_details']) {
                echo '<small>' . htmlspecialchars($entry['action_details']) . '</small>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
}
?>
