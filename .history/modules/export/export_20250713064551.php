<?php
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/filter_functions.php';
requireAuth();

// Interface utilisateur moderne pour l'export
if (!isset($_GET['format'])): ?>
<?php include __DIR__.'/../../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<div class="container" style="max-width:500px;margin:48px auto;">
    <div class="card" style="background:#fff;border-radius:16px;box-shadow:0 2px 16px #2980b91a;padding:32px 28px;">
        <h2 style="color:#2980b9;font-weight:700;margin-bottom:18px;display:flex;align-items:center;gap:10px;"><i class="fa fa-download"></i> Exporter les dossiers</h2>
        <p style="color:#636e72;margin-bottom:24px;">Choisissez le format d'export souhaité pour télécharger la liste filtrée des dossiers.</p>
        <div style="display:flex;gap:18px;flex-wrap:wrap;justify-content:center;">
            <a href="?format=csv" class="btn btn-download" style="font-size:1.1em;padding:14px 28px;border-radius:10px;background:linear-gradient(90deg,#16a085 80%,#2980b9 100%);color:#fff;display:flex;align-items:center;gap:10px;"><i class="fa fa-file-csv"></i> Export CSV</a>
            <a href="?format=excel" class="btn btn-success" style="font-size:1.1em;padding:14px 28px;border-radius:10px;background:linear-gradient(90deg,#27ae60 80%,#2980b9 100%);color:#fff;display:flex;align-items:center;gap:10px;"><i class="fa fa-file-excel"></i> Export Excel</a>
            <a href="?format=pdf" class="btn btn-danger" style="font-size:1.1em;padding:14px 28px;border-radius:10px;background:linear-gradient(90deg,#e74c3c 80%,#c0392b 100%);color:#fff;display:flex;align-items:center;gap:10px;"><i class="fa fa-file-pdf"></i> Export PDF</a>
        </div>
        <p style="margin-top:28px;color:#b2bec3;font-size:0.98em;text-align:center;">L'export prend en compte les filtres appliqués sur la liste des dossiers.</p>
    </div>
</div>
<?php include __DIR__.'/../../includes/footer.php'; ?>
<?php exit; endif;

// Récupérer les mêmes filtres que la liste
$filters = $_SESSION['last_dossier_filters'] ?? [];
$queryData = buildDossierQuery($filters);

// Déterminer le format
$format = $_GET['format'] ?? 'csv';
$filename = 'dossiers_' . date('Ymd_His') . '.' . $format;

// Exécuter la requête sans limite
if (isset($filters['limit'])) {
    $limitKey = array_search($filters['limit'], $queryData['params']);
    if ($limitKey !== false) unset($queryData['params'][$limitKey]);
}

// Supprimer la clause LIMIT de la requête
$query = $queryData['query'];
$query = preg_replace('/\s+LIMIT\s+\?\s*$/i', '', $query);

$dossiers = fetchAll($query, $queryData['params']);

// Debug : vérifier si nous avons des données
if (empty($dossiers) && isset($_GET['debug'])) {
    echo "<h3>Debug Export</h3>";
    echo "<p>Nombre de dossiers trouvés : " . count($dossiers) . "</p>";
    echo "<p>Requête : " . htmlspecialchars($query) . "</p>";
    echo "<p>Paramètres : " . print_r($queryData['params'], true) . "</p>";
    echo "<p>Filtres : " . print_r($filters, true) . "</p>";
    exit;
}

// Si aucun dossier trouvé, afficher un message
if (empty($dossiers)) {
    echo '<div style="max-width:600px;margin:48px auto;padding:32px 24px;background:#fff;border-radius:14px;box-shadow:0 2px 16px #f39c1222;">';
    echo '<h3 style="color:#f39c12;margin-bottom:16px;">⚠️ Aucun dossier à exporter</h3>';
    echo '<p style="color:#666;margin-bottom:20px;">Aucun dossier ne correspond aux filtres appliqués.</p>';
    echo '<div style="margin-bottom:16px;">';
    echo '<a href="../dossiers/list.php" style="background:#2980b9;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin-right:10px;">📋 Retour à la liste</a>';
    echo '<a href="export.php" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">🔄 Nouvel export</a>';
    echo '</div>';
    echo '<p style="font-size:0.9em;color:#999;">Vérifiez les filtres appliqués ou contactez l\'administrateur.</p>';
    echo '</div>';
    exit;
}

switch (strtolower($format)) {
    case 'csv':
        // Nettoyer les buffers de sortie pour éviter les problèmes
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Ajouter BOM UTF-8 pour Excel
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // En-têtes de colonnes
        fputcsv($output, [
            'Référence', 
            'Titre', 
            'Statut', 
            'Priorité', 
            'Service', 
            'Responsable',
            'Créé le', 
            'Échéance'
        ], ';'); // Utiliser point-virgule comme séparateur pour Excel français
        
        // Données des dossiers
        foreach ($dossiers as $dossier) {
            fputcsv($output, [
                $dossier['reference'] ?? '',
                $dossier['titre'] ?? '',
                $dossier['status'] ?? '',
                $dossier['priority'] ?? '',
                $dossier['service'] ?? '',
                $dossier['responsable_name'] ?? '',
                isset($dossier['created_at']) ? date('d/m/Y H:i', strtotime($dossier['created_at'])) : '',
                isset($dossier['deadline']) && $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : ''
            ], ';');
        }
        
        fclose($output);
        exit;
        
    case 'excel':
        // Vérifier si PHPExcel ou PhpSpreadsheet est disponible
        $phpexcelPath = __DIR__.'/../../libs/PHPExcel.php';
        $phpspreadsheetPath = __DIR__.'/../../libs/PhpSpreadsheet/autoload.php';
        
        if (file_exists($phpspreadsheetPath)) {
            // Utiliser PhpSpreadsheet (version moderne)
            try {
                require_once $phpspreadsheetPath;
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Dossiers MINSANTE');
                
                // En-têtes avec style
                $headers = ['Référence', 'Titre', 'Statut', 'Priorité', 'Service', 'Responsable', 'Créé le', 'Échéance'];
                $sheet->fromArray($headers, null, 'A1');
                
                // Style des en-têtes
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '2980B9']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ];
                $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
                
                // Données
                $row = 2;
                foreach ($dossiers as $dossier) {
                    $sheet->setCellValue('A' . $row, $dossier['reference'] ?? '');
                    $sheet->setCellValue('B' . $row, $dossier['titre'] ?? '');
                    $sheet->setCellValue('C' . $row, $dossier['status'] ?? '');
                    $sheet->setCellValue('D' . $row, $dossier['priority'] ?? '');
                    $sheet->setCellValue('E' . $row, $dossier['service'] ?? '');
                    $sheet->setCellValue('F' . $row, $dossier['responsable_name'] ?? '');
                    $sheet->setCellValue('G' . $row, isset($dossier['created_at']) ? date('d/m/Y H:i', strtotime($dossier['created_at'])) : '');
                    $sheet->setCellValue('H' . $row, isset($dossier['deadline']) && $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : '');
                    $row++;
                }
                
                // Auto-ajuster les colonnes
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                // Nettoyer les buffers
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
                
            } catch (Exception $e) {
                // Fallback vers CSV si Excel échoue
                header('Location: ?format=csv');
                exit;
            }
            
        } elseif (file_exists($phpexcelPath)) {
            // Utiliser PHPExcel (version ancienne)
            try {
                require_once $phpexcelPath;
                
                $excel = new PHPExcel();
                $sheet = $excel->getActiveSheet();
                $sheet->setTitle('Dossiers MINSANTE');
                
                // En-têtes
                $headers = ['Référence', 'Titre', 'Statut', 'Priorité', 'Service', 'Responsable', 'Créé le', 'Échéance'];
                foreach ($headers as $index => $header) {
                    $col = chr(65 + $index); // A, B, C, etc.
                    $sheet->setCellValue($col . '1', $header);
                    $sheet->getStyle($col . '1')->getFont()->setBold(true);
                }
                
                // Données
                $row = 2;
                foreach ($dossiers as $dossier) {
                    $sheet->setCellValue('A' . $row, $dossier['reference'] ?? '');
                    $sheet->setCellValue('B' . $row, $dossier['titre'] ?? '');
                    $sheet->setCellValue('C' . $row, $dossier['status'] ?? '');
                    $sheet->setCellValue('D' . $row, $dossier['priority'] ?? '');
                    $sheet->setCellValue('E' . $row, $dossier['service'] ?? '');
                    $sheet->setCellValue('F' . $row, $dossier['responsable_name'] ?? '');
                    $sheet->setCellValue('G' . $row, isset($dossier['created_at']) ? date('d/m/Y H:i', strtotime($dossier['created_at'])) : '');
                    $sheet->setCellValue('H' . $row, isset($dossier['deadline']) && $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : '');
                    $row++;
                }
                
                // Nettoyer les buffers
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');
                
                $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
                $writer->save('php://output');
                exit;
                
            } catch (Exception $e) {
                // Fallback vers Excel XML si PhpSpreadsheet échoue
                generateExcelXML($dossiers, $filename);
                exit;
            }
            
        } elseif (file_exists($phpexcelPath)) {
            // Utiliser PhpSpreadsheet (version moderne)
            try {
                require_once $phpspreadsheetPath;
                
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Dossiers MINSANTE');
                
                // En-têtes avec style
                $headers = ['Référence', 'Titre', 'Statut', 'Priorité', 'Service', 'Responsable', 'Créé le', 'Échéance'];
                $sheet->fromArray($headers, null, 'A1');
                
                // Style des en-têtes
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '2980B9']],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
                ];
                $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
                
                // Données
                $row = 2;
                foreach ($dossiers as $dossier) {
                    $sheet->setCellValue('A' . $row, $dossier['reference'] ?? '');
                    $sheet->setCellValue('B' . $row, $dossier['titre'] ?? '');
                    $sheet->setCellValue('C' . $row, $dossier['status'] ?? '');
                    $sheet->setCellValue('D' . $row, $dossier['priority'] ?? '');
                    $sheet->setCellValue('E' . $row, $dossier['service'] ?? '');
                    $sheet->setCellValue('F' . $row, $dossier['responsable_name'] ?? '');
                    $sheet->setCellValue('G' . $row, isset($dossier['created_at']) ? date('d/m/Y H:i', strtotime($dossier['created_at'])) : '');
                    $sheet->setCellValue('H' . $row, isset($dossier['deadline']) && $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : '');
                    $row++;
                }
                
                // Auto-ajuster les colonnes
                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                // Nettoyer les buffers
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
                
            } catch (Exception $e) {
                // Fallback vers Excel XML si PhpSpreadsheet échoue
                generateExcelXML($dossiers, $filename);
                exit;
            }
            
        } elseif (file_exists($phpexcelPath)) {
            // Utiliser PHPExcel (version ancienne)
            try {
                require_once $phpexcelPath;
                
                $excel = new PHPExcel();
                $sheet = $excel->getActiveSheet();
                $sheet->setTitle('Dossiers MINSANTE');
                
                // En-têtes
                $headers = ['Référence', 'Titre', 'Statut', 'Priorité', 'Service', 'Responsable', 'Créé le', 'Échéance'];
                foreach ($headers as $index => $header) {
                    $col = chr(65 + $index); // A, B, C, etc.
                    $sheet->setCellValue($col . '1', $header);
                    $sheet->getStyle($col . '1')->getFont()->setBold(true);
                }
                
                // Données
                $row = 2;
                foreach ($dossiers as $dossier) {
                    $sheet->setCellValue('A' . $row, $dossier['reference'] ?? '');
                    $sheet->setCellValue('B' . $row, $dossier['titre'] ?? '');
                    $sheet->setCellValue('C' . $row, $dossier['status'] ?? '');
                    $sheet->setCellValue('D' . $row, $dossier['priority'] ?? '');
                    $sheet->setCellValue('E' . $row, $dossier['service'] ?? '');
                    $sheet->setCellValue('F' . $row, $dossier['responsable_name'] ?? '');
                    $sheet->setCellValue('G' . $row, isset($dossier['created_at']) ? date('d/m/Y H:i', strtotime($dossier['created_at'])) : '');
                    $sheet->setCellValue('H' . $row, isset($dossier['deadline']) && $dossier['deadline'] ? date('d/m/Y', strtotime($dossier['deadline'])) : '');
                    $row++;
                }
                
                // Nettoyer les buffers
                if (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Expires: 0');
                
                $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
                $writer->save('php://output');
                exit;
                
            } catch (Exception $e) {
                // Fallback vers Excel XML
                generateExcelXML($dossiers, $filename);
                exit;
            }
            
        } else {
            // Aucune librairie Excel - utiliser Excel XML natif
            generateExcelXML($dossiers, $filename);
            exit;
        }
        break;
    
    case 'pdf':
        // Vérifier si mPDF est disponible
        $mpdfPath = __DIR__ . '/../../libs/mpdf/autoload.php';
        if (!file_exists($mpdfPath)) {
            echo '<div style="max-width:600px;margin:48px auto;padding:32px 24px;background:#fff;border-radius:14px;box-shadow:0 2px 16px #e74c3c22;">';
            echo '<h3 style="color:#c0392b;margin-bottom:16px;">❌ mPDF non installé</h3>';
            echo '<p style="color:#666;margin-bottom:20px;">Le module d\'export PDF (mPDF) n\'est pas installé correctement.</p>';
            echo '<div style="margin-bottom:16px;">';
            echo '<a href="../../libs/mpdf/install_mpdf.php" style="background:#2980b9;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin-right:10px;">🚀 Installer mPDF</a>';
            echo '<a href="../../libs/mpdf/test_mpdf.php" style="background:#f39c12;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">🧪 Tester mPDF</a>';
            echo '</div>';
            echo '<p style="font-size:0.9em;color:#999;">Ou utilisez l\'export <a href="?format=csv">CSV</a> ou <a href="?format=excel">Excel</a></p>';
            echo '</div>';
            exit;
        }
        
        try {
            require_once $mpdfPath;
            
            // Essayer différentes méthodes d'instanciation
            $mpdf = null;
            
            if (class_exists('Mpdf\\Mpdf')) {
                $mpdf = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'orientation' => 'L', // Paysage pour plus de colonnes
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 20,
                    'margin_bottom' => 20
                ]);
            } elseif (class_exists('mPDF')) {
                // Version ancienne de mPDF
                $mpdf = new mPDF('utf-8', 'A4-L', 0, '', 15, 15, 20, 20);
            } else {
                throw new Exception("Aucune classe mPDF trouvée");
            }
            
            $html = generatePdfContent($dossiers, $filters);
            $mpdf->WriteHTML($html);
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $mpdf->Output($filename, 'D');
            
        } catch (Exception $e) {
            echo '<div style="max-width:600px;margin:48px auto;padding:32px 24px;background:#fff;border-radius:14px;box-shadow:0 2px 16px #e74c3c22;">';
            echo '<h3 style="color:#c0392b;margin-bottom:16px;">❌ Erreur de génération PDF</h3>';
            echo '<p style="color:#666;margin-bottom:20px;">Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<div style="margin-bottom:16px;">';
            echo '<a href="../../libs/mpdf/test_mpdf.php" style="background:#f39c12;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;margin-right:10px;">🧪 Tester mPDF</a>';
            echo '<a href="?format=csv" style="background:#27ae60;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">📊 Export CSV</a>';
            echo '</div>';
            echo '<details style="margin-top:20px;"><summary style="cursor:pointer;color:#2980b9;">Détails techniques</summary>';
            echo '<pre style="background:#f8f9fa;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</details>';
            echo '</div>';
            exit;
        }
        break;
        
    default:
        die('Format non supporté');
}

/**
 * Génère le contenu HTML pour l'export PDF
 */
function generatePdfContent($dossiers, $filters) {
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
    
    // Construire le résumé des filtres appliqués
    $filtresAppliques = [];
    if (!empty($filters['status'])) $filtresAppliques[] = "Statut: " . $filters['status'];
    if (!empty($filters['priority'])) $filtresAppliques[] = "Priorité: " . $filters['priority'];
    if (!empty($filters['service'])) $filtresAppliques[] = "Service: " . $filters['service'];
    if (!empty($filters['responsable_id'])) $filtresAppliques[] = "Responsable: ID " . $filters['responsable_id'];
    if (!empty($filters['date_debut'])) $filtresAppliques[] = "Date début: " . $filters['date_debut'];
    if (!empty($filters['date_fin'])) $filtresAppliques[] = "Date fin: " . $filters['date_fin'];
    
    $filtresText = !empty($filtresAppliques) ? implode(' | ', $filtresAppliques) : 'Aucun filtre appliqué';
    
    $html = '
    <style>
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif; 
            font-size: 10px; 
            color: #333; 
            margin: 0; 
            padding: 0;
        }
        .header { 
            text-align: center; 
            margin-bottom: 20px; 
            border-bottom: 2px solid #2980b9; 
            padding-bottom: 15px; 
        }
        .header h1 { 
            color: #2980b9; 
            margin: 0; 
            font-size: 18px; 
            font-weight: bold;
        }
        .header p { 
            color: #666; 
            margin: 5px 0; 
            font-size: 10px;
        }
        .filters { 
            background: #f8f9fa; 
            padding: 8px; 
            border-radius: 4px; 
            margin-bottom: 15px; 
            font-size: 9px;
            color: #555;
        }
        .summary {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 10px;
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 9px;
        }
        .table th { 
            background: #2980b9; 
            color: white; 
            padding: 8px 4px; 
            border: 1px solid #ddd;
            font-weight: bold;
            text-align: center;
        }
        .table td { 
            padding: 6px 4px; 
            border: 1px solid #ddd; 
            vertical-align: top;
        }
        .table tr:nth-child(even) { 
            background: #f8f9fa; 
        }
        .status-badge, .priority-badge { 
            padding: 2px 6px; 
            border-radius: 10px; 
            color: white; 
            font-weight: bold; 
            font-size: 8px;
            display: inline-block;
            text-align: center;
            min-width: 50px;
        }
        .footer {
            margin-top: 20px; 
            text-align: center; 
            color: #666; 
            font-size: 8px; 
            border-top: 1px solid #ddd; 
            padding-top: 10px;
        }
        .page-break { 
            page-break-before: always; 
        }
    </style>
    
    <div class="header">
        <h1>📋 Export des Dossiers - MINSANTE</h1>
        <p>Généré le ' . date('d/m/Y à H:i:s') . '</p>
    </div>
    
    <div class="filters">
        <strong>Filtres appliqués :</strong> ' . htmlspecialchars($filtresText) . '
    </div>
    
    <div class="summary">
        <strong>Résumé :</strong> ' . count($dossiers) . ' dossier(s) exporté(s)
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th style="width: 12%;">Référence</th>
                <th style="width: 25%;">Titre</th>
                <th style="width: 10%;">Statut</th>
                <th style="width: 10%;">Priorité</th>
                <th style="width: 15%;">Service</th>
                <th style="width: 15%;">Responsable</th>
                <th style="width: 13%;">Créé le</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($dossiers as $index => $dossier) {
        // Saut de page tous les 25 dossiers pour éviter les coupures
        if ($index > 0 && $index % 25 === 0) {
            $html .= '</tbody></table><div class="page-break"></div><table class="table"><thead><tr>
                <th style="width: 12%;">Référence</th>
                <th style="width: 25%;">Titre</th>
                <th style="width: 10%;">Statut</th>
                <th style="width: 10%;">Priorité</th>
                <th style="width: 15%;">Service</th>
                <th style="width: 15%;">Responsable</th>
                <th style="width: 13%;">Créé le</th>
            </tr></thead><tbody>';
        }
        
        $statusColor = $statusColors[$dossier['status']] ?? '#95a5a6';
        $priorityColor = $priorityColors[$dossier['priority']] ?? '#95a5a6';
        
        $html .= '<tr>
            <td style="font-weight: bold; color: #2980b9;">' . htmlspecialchars($dossier['reference']) . '</td>
            <td>' . htmlspecialchars(substr($dossier['titre'], 0, 40) . (strlen($dossier['titre']) > 40 ? '...' : '')) . '</td>
            <td style="text-align: center;">
                <span class="status-badge" style="background: ' . $statusColor . ';">' . strtoupper($dossier['status']) . '</span>
            </td>
            <td style="text-align: center;">
                <span class="priority-badge" style="background: ' . $priorityColor . ';">' . strtoupper($dossier['priority']) . '</span>
            </td>
            <td>' . htmlspecialchars($dossier['service']) . '</td>
            <td>' . htmlspecialchars($dossier['responsable_name'] ?? 'N/A') . '</td>
            <td style="text-align: center;">' . date('d/m/Y', strtotime($dossier['created_at'])) . '</td>
        </tr>';
        
        // Si le dossier a une description, l'ajouter sur une ligne séparée
        if (!empty($dossier['description'])) {
            $html .= '<tr style="background: #f0f8ff;">
                <td colspan="7" style="font-size: 8px; color: #666; padding: 4px;">
                    <strong>Description :</strong> ' . htmlspecialchars(substr($dossier['description'], 0, 150) . (strlen($dossier['description']) > 150 ? '...' : '')) . '
                </td>
            </tr>';
        }
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="footer">
        <p>Document généré automatiquement par le Système de Gestion des Dossiers - MINSANTE</p>
        <p>' . BASE_URL . ' | ' . date('d/m/Y à H:i:s') . '</p>
        <p>Total : ' . count($dossiers) . ' dossier(s)</p>
    </div>';
    
    return $html;
}

/**
 * Génère un fichier Excel au format XML (compatible Excel 2003+)
 * Cette fonction ne nécessite aucune librairie externe
 */
function generateExcelXML($dossiers, $filename) {
    // Nettoyer les buffers de sortie
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers pour Excel XML
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Début du XML Excel
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    // Styles
    echo '<Styles>' . "\n";
    echo '<Style ss:ID="Header">' . "\n";
    echo '<Font ss:Bold="1" ss:Size="12" ss:Color="#FFFFFF"/>' . "\n";
    echo '<Interior ss:Color="#2980B9" ss:Pattern="Solid"/>' . "\n";
    echo '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    echo '<Style ss:ID="Data">' . "\n";
    echo '<Borders>' . "\n";
    echo '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>' . "\n";
    echo '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>' . "\n";
    echo '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>' . "\n";
    echo '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/>' . "\n";
    echo '</Borders>' . "\n";
    echo '</Style>' . "\n";
    echo '</Styles>' . "\n";
    
    // Worksheet
    echo '<Worksheet ss:Name="Dossiers MINSANTE">' . "\n";
    echo '<Table>' . "\n";
    
    // Définir les largeurs de colonnes
    echo '<Column ss:Width="120"/>' . "\n"; // Référence
    echo '<Column ss:Width="200"/>' . "\n"; // Titre
    echo '<Column ss:Width="100"/>' . "\n"; // Statut
    echo '<Column ss:Width="100"/>' . "\n"; // Priorité
    echo '<Column ss:Width="150"/>' . "\n"; // Service
    echo '<Column ss:Width="150"/>' . "\n"; // Responsable
    echo '<Column ss:Width="120"/>' . "\n"; // Créé le
    echo '<Column ss:Width="120"/>' . "\n"; // Échéance
    
    // En-têtes
    echo '<Row>' . "\n";
    $headers = ['Référence', 'Titre', 'Statut', 'Priorité', 'Service', 'Responsable', 'Créé le', 'Échéance'];
    foreach ($headers as $header) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">' . htmlspecialchars($header, ENT_XML1) . '</Data></Cell>' . "\n";
    }
    echo '</Row>' . "\n";
    
    // Données
    foreach ($dossiers as $dossier) {
        echo '<Row>' . "\n";
        
        // Référence
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['reference'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Titre
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['titre'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Statut
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['status'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Priorité
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['priority'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Service
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['service'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Responsable
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($dossier['responsable_name'] ?? '', ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Date de création
        $createdDate = '';
        if (isset($dossier['created_at']) && $dossier['created_at']) {
            $createdDate = date('d/m/Y H:i', strtotime($dossier['created_at']));
        }
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($createdDate, ENT_XML1) . '</Data></Cell>' . "\n";
        
        // Échéance
        $deadline = '';
        if (isset($dossier['deadline']) && $dossier['deadline']) {
            $deadline = date('d/m/Y', strtotime($dossier['deadline']));
        }
        echo '<Cell ss:StyleID="Data"><Data ss:Type="String">' . htmlspecialchars($deadline, ENT_XML1) . '</Data></Cell>' . "\n";
        
        echo '</Row>' . "\n";
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
}