<?php
require_once __DIR__.'/../../includes/config.php';
require_once __DIR__.'/../../includes/filter_functions.php';
requireAuth();

// Interface utilisateur moderne pour l'export
if (!isset($_GET['format'])): ?>
<?php include __DIR__.'/../../includes/header.php'; ?>

<style>
:root {
    --export-primary: #667eea;
    --export-secondary: #764ba2;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --danger-color: #ef4444;
    --info-color: #3b82f6;
    --light-bg: #f8fafc;
    --white: #ffffff;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
}

.export-section {
    background: linear-gradient(135deg, var(--export-primary) 0%, var(--export-secondary) 100%);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    margin-bottom: 32px;
    padding: 32px;
    position: relative;
    overflow: hidden;
    animation: slideInDown 0.8s ease-out;
}

.export-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    pointer-events: none;
}

.export-section h1 {
    margin: 0 0 8px 0;
    font-size: 2.2rem;
    font-weight: 800;
    color: var(--white);
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 16px;
}

.export-section .subtitle {
    margin: 0;
    font-size: 1.1rem;
    color: rgba(255, 255, 255, 0.9);
    position: relative;
    z-index: 1;
    font-weight: 500;
}

.export-header-icon {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: var(--white);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.export-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 24px 32px;
    margin-bottom: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.stat-item {
    text-align: center;
    flex: 1;
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--export-primary), var(--export-secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.2rem;
    margin: 0 auto 12px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--export-primary);
}

.export-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.export-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.format-card {
    background: var(--white);
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out;
}

.format-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.format-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, var(--export-primary), var(--export-secondary));
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.format-card:hover::before {
    transform: scaleX(1);
}

.format-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 20px;
}

.format-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--white);
    position: relative;
    overflow: hidden;
}

.csv-card .format-icon {
    background: linear-gradient(135deg, #10b981, #059669);
}

.excel-card .format-icon {
    background: linear-gradient(135deg, #059669, #047857);
}

.pdf-card .format-icon {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.format-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.format-description {
    color: var(--text-secondary);
    font-size: 1rem;
    margin: 0 0 20px 0;
    line-height: 1.6;
}

.format-features {
    list-style: none;
    padding: 0;
    margin: 0 0 24px 0;
}

.format-features li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.format-features li i {
    color: var(--success-color);
    width: 16px;
}

.export-btn {
    width: 100%;
    padding: 16px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    overflow: hidden;
}

.csv-card .export-btn {
    background: linear-gradient(135deg, #10b981, #059669);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.excel-card .export-btn {
    background: linear-gradient(135deg, #059669, #047857);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(5, 150, 105, 0.4);
}

.pdf-card .export-btn {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: var(--white);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
}

.info-panel {
    background: var(--white);
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border-color);
    margin-bottom: 32px;
}

.info-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: var(--light-bg);
    border-radius: 12px;
    border: 1px solid var(--border-color);
}

.info-item-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--export-primary), var(--export-secondary));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.1rem;
    flex-shrink: 0;
}

.info-item-content h4 {
    margin: 0 0 4px 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.info-item-content p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--export-primary);
    text-decoration: none;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 50px;
    background: rgba(102, 126, 234, 0.1);
    transition: all 0.3s ease;
    margin-bottom: 20px;
}

.back-link:hover {
    background: var(--export-primary);
    color: var(--white);
    transform: translateX(-4px);
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .export-section {
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .export-section h1 {
        font-size: 1.8rem;
        flex-direction: column;
        text-align: center;
        gap: 12px;
    }
    
    .export-stats {
        flex-direction: column;
        gap: 20px;
        padding: 20px;
    }
    
    .export-options-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .format-card {
        padding: 24px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<div class="export-container">
    <a href="../dossiers/list.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        Retour à la liste des dossiers
    </a>

    <div class="export-section">
        <h1>
            <div class="export-header-icon">
                <i class="fas fa-download"></i>
            </div>
            Export des Dossiers
        </h1>
        <p class="subtitle">Téléchargez vos données dans le format de votre choix avec les filtres appliqués</p>
    </div>

    <div class="export-stats">
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="stat-label">Formats</div>
            <div class="stat-value">3 Disponibles</div>
        </div>
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-filter"></i>
            </div>
            <div class="stat-label">Filtres</div>
            <div class="stat-value">Pris en compte</div>
        </div>
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-label">Sécurité</div>
            <div class="stat-value">Garantie</div>
        </div>
        <div class="stat-item">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-label">Export</div>
            <div class="stat-value">Temps réel</div>
        </div>
    </div>

    <div class="export-options-grid">
        <div class="format-card csv-card">
            <div class="format-header">
                <div class="format-icon">
                    <i class="fas fa-file-csv"></i>
                </div>
                <div>
                    <h3 class="format-title">Export CSV</h3>
                    <p class="format-description">Format universel compatible avec tous les tableurs</p>
                </div>
            </div>
            
            <ul class="format-features">
                <li><i class="fas fa-check"></i> Compatible Excel, LibreOffice, Google Sheets</li>
                <li><i class="fas fa-check"></i> Encodage UTF-8 avec BOM</li>
                <li><i class="fas fa-check"></i> Séparateur point-virgule</li>
                <li><i class="fas fa-check"></i> Téléchargement instantané</li>
            </ul>
            
            <a href="?format=csv" class="export-btn">
                <i class="fas fa-download"></i>
                Télécharger CSV
            </a>
        </div>

        <div class="format-card excel-card">
            <div class="format-header">
                <div class="format-icon">
                    <i class="fas fa-file-excel"></i>
                </div>
                <div>
                    <h3 class="format-title">Export Excel</h3>
                    <p class="format-description">Fichier Excel natif avec mise en forme avancée</p>
                </div>
            </div>
            
            <ul class="format-features">
                <li><i class="fas fa-check"></i> Format XLSX natif</li>
                <li><i class="fas fa-check"></i> En-têtes stylisés</li>
                <li><i class="fas fa-check"></i> Colonnes auto-ajustées</li>
                <li><i class="fas fa-check"></i> Prêt pour l'analyse</li>
            </ul>
            
            <a href="?format=excel" class="export-btn">
                <i class="fas fa-download"></i>
                Télécharger Excel
            </a>
        </div>

        <div class="format-card pdf-card">
            <div class="format-header">
                <div class="format-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div>
                    <h3 class="format-title">Export PDF</h3>
                    <p class="format-description">Document PDF formaté prêt pour l'impression</p>
                </div>
            </div>
            
            <ul class="format-features">
                <li><i class="fas fa-check"></i> Format paysage optimisé</li>
                <li><i class="fas fa-check"></i> En-têtes et pieds de page</li>
                <li><i class="fas fa-check"></i> Badges colorés pour statuts</li>
                <li><i class="fas fa-check"></i> Pagination automatique</li>
            </ul>
            
            <a href="?format=pdf" class="export-btn">
                <i class="fas fa-download"></i>
                Télécharger PDF
            </a>
        </div>
    </div>

    <div class="info-panel">
        <h3 class="info-title">
            <i class="fas fa-info-circle"></i>
            Informations importantes
        </h3>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-item-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div class="info-item-content">
                    <h4>Filtres appliqués</h4>
                    <p>L'export prendra en compte tous les filtres que vous avez appliqués sur la liste des dossiers (statut, priorité, dates, etc.)</p>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-item-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="info-item-content">
                    <h4>Données complètes</h4>
                    <p>Toutes les données visibles dans la liste sont exportées : référence, titre, statut, priorité, service, responsable, dates</p>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-item-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="info-item-content">
                    <h4>Sécurité des données</h4>
                    <p>Seules les données que vous êtes autorisé à voir sont exportées. Respect total des permissions utilisateur</p>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-item-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="info-item-content">
                    <h4>Export en temps réel</h4>
                    <p>Les données exportées reflètent l'état actuel de la base de données au moment du téléchargement</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animation d'apparition progressive des cartes
    const cards = document.querySelectorAll('.format-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });
    
    // Animation des statistiques
    setTimeout(() => {
        const statValues = document.querySelectorAll('.stat-value');
        statValues.forEach((stat, index) => {
            stat.style.opacity = '0';
            setTimeout(() => {
                stat.style.transition = 'opacity 0.6s ease';
                stat.style.opacity = '1';
            }, index * 100);
        });
    }, 500);
    
    // Effet de ripple sur les boutons d'export
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple-animation 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    console.log('📊 Page d\'export initialisée avec succès');
});

// CSS pour l'animation de ripple
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
</script>

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