<?php
/**
 * Générateur de PDF Avancé avec Templates
 * Utilise mPDF pour créer des rapports PDF personnalisés
 */

require_once '../../libs/mpdf/vendor/autoload.php';

class AdvancedPdfGenerator {
    private $mpdf;
    private $defaultFont = 'DejaVuSans';
    private $templates = [];
    
    public function __construct() {
        $this->initializeMpdf();
        $this->loadTemplates();
    }
    
    /**
     * Initialise mPDF avec la configuration par défaut
     */
    private function initializeMpdf() {
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 10,
            'default_font' => $this->defaultFont,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'tempDir' => '../../tmp/'
        ]);
    }
    
    /**
     * Charge les templates disponibles
     */
    private function loadTemplates() {
        $this->templates = [
            'dossier_simple' => [
                'name' => 'Dossier Simple',
                'description' => 'Rapport basique d\'un dossier',
                'template' => 'dossier_simple.html'
            ],
            'dossier_complet' => [
                'name' => 'Dossier Complet',
                'description' => 'Rapport détaillé avec historique',
                'template' => 'dossier_complet.html'
            ],
            'rapport_analytics' => [
                'name' => 'Rapport Analytics',
                'description' => 'Rapport de statistiques et KPI',
                'template' => 'rapport_analytics.html'
            ],
            'liste_dossiers' => [
                'name' => 'Liste de Dossiers',
                'description' => 'Liste tabulaire de dossiers',
                'template' => 'liste_dossiers.html'
            ],
            'certificat' => [
                'name' => 'Certificat',
                'description' => 'Certificat officiel de validation',
                'template' => 'certificat.html'
            ]
        ];
    }
    
    /**
     * Génère un PDF pour un dossier spécifique
     */
    public function generateDossierPdf($dossierId, $template = 'dossier_complet', $options = []) {
        global $conn;
        
        // Récupérer les données du dossier
        $stmt = $conn->prepare("
            SELECT d.*, u.name as responsable_name, u.email as responsable_email,
                   creator.name as created_by_name
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            LEFT JOIN users creator ON d.created_by = creator.id
            WHERE d.id = ?
        ");
        $stmt->execute([$dossierId]);
        $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dossier) {
            throw new Exception('Dossier non trouvé');
        }
        
        // Récupérer l'historique si nécessaire
        $historique = [];
        if ($template === 'dossier_complet') {
            $historique = $this->getDossierHistory($dossierId);
        }
        
        // Récupérer les documents associés
        $documents = $this->getDossierDocuments($dossierId);
        
        // Préparer les données pour le template
        $data = [
            'dossier' => $dossier,
            'historique' => $historique,
            'documents' => $documents,
            'options' => $options,
            'generated_at' => date('d/m/Y H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? 'Système'
        ];
        
        return $this->renderTemplate($template, $data, $options);
    }
    
    /**
     * Génère un rapport d'analytics
     */
    public function generateAnalyticsReport($analyticsData, $options = []) {
        $data = [
            'kpis' => $analyticsData['kpis'] ?? [],
            'charts_data' => $analyticsData['charts'] ?? [],
            'performance' => $analyticsData['performance'] ?? [],
            'period' => $options['period'] ?? '30 derniers jours',
            'generated_at' => date('d/m/Y H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? 'Système'
        ];
        
        return $this->renderTemplate('rapport_analytics', $data, $options);
    }
    
    /**
     * Génère un certificat de validation
     */
    public function generateCertificate($dossierId, $options = []) {
        global $conn;
        
        $stmt = $conn->prepare("
            SELECT d.*, u.name as responsable_name, 
                   validator.name as validated_by_name
            FROM dossiers d
            LEFT JOIN users u ON d.responsable_id = u.id
            LEFT JOIN users validator ON d.validated_by = validator.id
            WHERE d.id = ? AND d.status = 'valide'
        ");
        $stmt->execute([$dossierId]);
        $dossier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dossier) {
            throw new Exception('Dossier non trouvé ou non validé');
        }
        
        $data = [
            'dossier' => $dossier,
            'certificate_number' => 'CERT-' . str_pad($dossierId, 6, '0', STR_PAD_LEFT) . '-' . date('Y'),
            'generated_at' => date('d/m/Y H:i:s'),
            'qr_code' => $this->generateQrCode($dossier['reference'])
        ];
        
        return $this->renderTemplate('certificat', $data, $options);
    }
    
    /**
     * Génère une liste de dossiers
     */
    public function generateDossiersList($dossiers, $filters = [], $options = []) {
        $data = [
            'dossiers' => $dossiers,
            'filters' => $filters,
            'total_count' => count($dossiers),
            'generated_at' => date('d/m/Y H:i:s'),
            'generated_by' => $_SESSION['user_name'] ?? 'Système'
        ];
        
        return $this->renderTemplate('liste_dossiers', $data, $options);
    }
    
    /**
     * Rend un template avec les données fournies
     */
    private function renderTemplate($templateName, $data, $options = []) {
        if (!isset($this->templates[$templateName])) {
            throw new Exception('Template non trouvé: ' . $templateName);
        }
        
        // Charger le template HTML
        $templatePath = __DIR__ . '/templates/' . $this->templates[$templateName]['template'];
        if (!file_exists($templatePath)) {
            throw new Exception('Fichier template non trouvé: ' . $templatePath);
        }
        
        $htmlContent = file_get_contents($templatePath);
        
        // Remplacer les variables dans le template
        $htmlContent = $this->processTemplate($htmlContent, $data);
        
        // Configuration spécifique au template
        $this->configureForTemplate($templateName, $options);
        
        // Générer le PDF
        $this->mpdf->WriteHTML($htmlContent);
        
        // Options de sortie
        $filename = $this->generateFilename($templateName, $data, $options);
        
        if ($options['output'] === 'download') {
            return $this->mpdf->Output($filename, 'D');
        } elseif ($options['output'] === 'save') {
            $savePath = '../../cache/exports/' . $filename;
            $this->mpdf->Output($savePath, 'F');
            return $savePath;
        } else {
            return $this->mpdf->Output($filename, 'I');
        }
    }
    
    /**
     * Traite le template en remplaçant les variables
     */
    private function processTemplate($html, $data) {
        // Remplacements simples
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $html = str_replace('{{' . $key . '}}', htmlspecialchars($value), $html);
            }
        }
        
        // Traitement des boucles et conditions
        $html = $this->processLoops($html, $data);
        $html = $this->processConditions($html, $data);
        $html = $this->processIncludes($html, $data);
        
        return $html;
    }
    
    /**
     * Traite les boucles dans le template
     */
    private function processLoops($html, $data) {
        // Rechercher les boucles {{#each array}}...{{/each}}
        preg_match_all('/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $arrayName = $match[1];
            $loopContent = $match[2];
            $replacement = '';
            
            if (isset($data[$arrayName]) && is_array($data[$arrayName])) {
                foreach ($data[$arrayName] as $item) {
                    $itemContent = $loopContent;
                    
                    // Remplacer les variables de l'item
                    if (is_array($item)) {
                        foreach ($item as $itemKey => $itemValue) {
                            if (is_scalar($itemValue)) {
                                $itemContent = str_replace('{{' . $itemKey . '}}', htmlspecialchars($itemValue), $itemContent);
                            }
                        }
                    }
                    
                    $replacement .= $itemContent;
                }
            }
            
            $html = str_replace($match[0], $replacement, $html);
        }
        
        return $html;
    }
    
    /**
     * Traite les conditions dans le template
     */
    private function processConditions($html, $data) {
        // Rechercher les conditions {{#if condition}}...{{/if}}
        preg_match_all('/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $condition = $match[1];
            $content = $match[2];
            $replacement = '';
            
            // Évaluer la condition
            if (isset($data[$condition]) && $data[$condition]) {
                $replacement = $content;
            }
            
            $html = str_replace($match[0], $replacement, $html);
        }
        
        return $html;
    }
    
    /**
     * Traite les inclusions dans le template
     */
    private function processIncludes($html, $data) {
        // Rechercher les inclusions {{include template_name}}
        preg_match_all('/\{\{include\s+(\w+)\}\}/', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $includeName = $match[1];
            $includePath = __DIR__ . '/templates/includes/' . $includeName . '.html';
            
            if (file_exists($includePath)) {
                $includeContent = file_get_contents($includePath);
                $includeContent = $this->processTemplate($includeContent, $data);
                $html = str_replace($match[0], $includeContent, $html);
            } else {
                $html = str_replace($match[0], '<!-- Include not found: ' . $includeName . ' -->', $html);
            }
        }
        
        return $html;
    }
    
    /**
     * Configure mPDF selon le template
     */
    private function configureForTemplate($templateName, $options) {
        switch ($templateName) {
            case 'certificat':
                $this->mpdf->SetDisplayMode('fullpage');
                $this->mpdf->showWatermarkText = true;
                $this->mpdf->watermarkTextAlpha = 0.1;
                break;
                
            case 'rapport_analytics':
                $this->mpdf->SetHeader('Rapport Analytics MINSANTE|' . date('d/m/Y') . '|Page {PAGENO}');
                $this->mpdf->SetFooter('Document confidentiel - MINSANTE');
                break;
                
            case 'liste_dossiers':
                $this->mpdf->SetHeader('Liste des dossiers|' . date('d/m/Y') . '|Page {PAGENO}');
                break;
        }
        
        // Styles CSS spécifiques
        $this->addCustomStyles($templateName);
    }
    
    /**
     * Ajoute des styles CSS personnalisés
     */
    private function addCustomStyles($templateName) {
        $css = $this->getBaseStyles();
        
        switch ($templateName) {
            case 'certificat':
                $css .= $this->getCertificateStyles();
                break;
                
            case 'rapport_analytics':
                $css .= $this->getAnalyticsStyles();
                break;
        }
        
        $this->mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
    }
    
    /**
     * Styles CSS de base
     */
    private function getBaseStyles() {
        return '
        <style>
        body {
            font-family: DejaVuSans, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 18pt;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        h2 {
            color: #34495e;
            font-size: 14pt;
            margin-top: 20px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        
        h3 {
            color: #34495e;
            font-size: 12pt;
            margin-top: 15px;
            margin-bottom: 8px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        table th {
            background-color: #3498db;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        
        table td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .info-box {
            background-color: #ecf0f1;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
        }
        
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
            color: white;
        }
        
        .status-en_cours { background-color: #3498db; }
        .status-valide { background-color: #27ae60; }
        .status-rejete { background-color: #e74c3c; }
        .status-archive { background-color: #95a5a6; }
        
        .priority-urgent { color: #e74c3c; font-weight: bold; }
        .priority-high { color: #f39c12; font-weight: bold; }
        .priority-medium { color: #3498db; }
        .priority-low { color: #95a5a6; }
        
        .footer-info {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
        
        .signature-box {
            border: 1px solid #ddd;
            width: 200px;
            height: 80px;
            margin: 20px 0;
            text-align: center;
            padding: 10px;
        }
        </style>';
    }
    
    /**
     * Styles spécifiques aux certificats
     */
    private function getCertificateStyles() {
        return '
        <style>
        .certificate-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .certificate-title {
            font-size: 24pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
            text-transform: uppercase;
        }
        
        .certificate-content {
            text-align: center;
            font-size: 12pt;
            line-height: 2;
            margin: 40px 0;
        }
        
        .certificate-signature {
            margin-top: 60px;
            display: table;
            width: 100%;
        }
        
        .signature-left, .signature-right {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        
        .qr-code {
            position: absolute;
            bottom: 20px;
            right: 20px;
        }
        </style>';
    }
    
    /**
     * Styles spécifiques aux rapports analytics
     */
    private function getAnalyticsStyles() {
        return '
        <style>
        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .kpi-item {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            background-color: #f8f9fa;
        }
        
        .kpi-value {
            font-size: 18pt;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }
        
        .kpi-label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
        }
        
        .chart-placeholder {
            border: 2px dashed #ddd;
            height: 200px;
            display: table-cell;
            text-align: center;
            vertical-align: middle;
            color: #999;
            font-style: italic;
        }
        </style>';
    }
    
    /**
     * Récupère l'historique d'un dossier
     */
    private function getDossierHistory($dossierId) {
        global $conn;
        
        // Si une table d'audit existe
        $stmt = $conn->prepare("
            SELECT 'status_change' as type, 
                   CONCAT('Statut changé en ', status) as action,
                   updated_at as date,
                   'Système' as user_name
            FROM dossiers 
            WHERE id = ?
            ORDER BY updated_at DESC
            LIMIT 10
        ");
        $stmt->execute([$dossierId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère les documents associés à un dossier
     */
    private function getDossierDocuments($dossierId) {
        // Simulation - adapter selon votre structure
        return [
            [
                'name' => 'Document principal.pdf',
                'size' => '2.3 MB',
                'uploaded_at' => '2024-01-15 10:30:00'
            ]
        ];
    }
    
    /**
     * Génère un QR code pour le certificat
     */
    private function generateQrCode($reference) {
        // Simulation - implémenter avec une vraie bibliothèque QR Code
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }
    
    /**
     * Génère le nom de fichier pour le PDF
     */
    private function generateFilename($templateName, $data, $options) {
        $prefix = match($templateName) {
            'dossier_simple', 'dossier_complet' => 'dossier_' . ($data['dossier']['reference'] ?? 'unknown'),
            'certificat' => 'certificat_' . ($data['dossier']['reference'] ?? 'unknown'),
            'rapport_analytics' => 'rapport_analytics_' . date('Y-m-d'),
            'liste_dossiers' => 'liste_dossiers_' . date('Y-m-d'),
            default => 'document'
        };
        
        return $prefix . '.pdf';
    }
    
    /**
     * Obtient la liste des templates disponibles
     */
    public function getAvailableTemplates() {
        return $this->templates;
    }
    
    /**
     * Crée les templates HTML par défaut
     */
    public static function createDefaultTemplates() {
        $templatesDir = __DIR__ . '/templates/';
        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }
        
        $includesDir = $templatesDir . 'includes/';
        if (!is_dir($includesDir)) {
            mkdir($includesDir, 0755, true);
        }
        
        // Template dossier complet
        $dossierComplet = '
        <h1>Dossier {{dossier.reference}}</h1>
        
        <div class="info-box">
            <h2>Informations Générales</h2>
            <table>
                <tr><td><strong>Référence:</strong></td><td>{{dossier.reference}}</td></tr>
                <tr><td><strong>Titre:</strong></td><td>{{dossier.titre}}</td></tr>
                <tr><td><strong>Statut:</strong></td><td><span class="status-badge status-{{dossier.status}}">{{dossier.status}}</span></td></tr>
                <tr><td><strong>Priorité:</strong></td><td><span class="priority-{{dossier.priority}}">{{dossier.priority}}</span></td></tr>
                <tr><td><strong>Responsable:</strong></td><td>{{dossier.responsable_name}}</td></tr>
                <tr><td><strong>Créé le:</strong></td><td>{{dossier.created_at}}</td></tr>
                <tr><td><strong>Mis à jour le:</strong></td><td>{{dossier.updated_at}}</td></tr>
                {{#if dossier.deadline}}<tr><td><strong>Échéance:</strong></td><td>{{dossier.deadline}}</td></tr>{{/if}}
            </table>
        </div>
        
        {{#if dossier.description}}
        <h2>Description</h2>
        <div class="info-box">
            {{dossier.description}}
        </div>
        {{/if}}
        
        {{#if historique}}
        <h2>Historique</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Action</th>
                    <th>Utilisateur</th>
                </tr>
            </thead>
            <tbody>
                {{#each historique}}
                <tr>
                    <td>{{date}}</td>
                    <td>{{action}}</td>
                    <td>{{user_name}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        {{/if}}
        
        <div class="footer-info">
            Document généré le {{generated_at}} par {{generated_by}}<br>
            Système de Gestion des Dossiers - MINSANTE
        </div>';
        
        file_put_contents($templatesDir . 'dossier_complet.html', $dossierComplet);
        
        // Template certificat
        $certificat = '
        <div class="certificate-header">
            <h1 class="certificate-title">Certificat de Validation</h1>
            <p><strong>MINISTÈRE DE LA SANTÉ</strong></p>
            <p>Numéro: {{certificate_number}}</p>
        </div>
        
        <div class="certificate-content">
            <p>Le présent certificat atteste que le dossier</p>
            <p><strong>{{dossier.reference}} - {{dossier.titre}}</strong></p>
            <p>a été validé conformément aux procédures en vigueur</p>
            <p>le {{dossier.updated_at}}</p>
            <p>par {{dossier.validated_by_name}}</p>
        </div>
        
        <div class="certificate-signature">
            <div class="signature-left">
                <div class="signature-box">
                    <p>Responsable du dossier</p>
                    <br>
                    <p>{{dossier.responsable_name}}</p>
                </div>
            </div>
            <div class="signature-right">
                <div class="signature-box">
                    <p>Validé par</p>
                    <br>
                    <p>{{dossier.validated_by_name}}</p>
                </div>
            </div>
        </div>
        
        {{#if qr_code}}
        <div class="qr-code">
            <img src="{{qr_code}}" width="80" height="80" alt="QR Code">
        </div>
        {{/if}}';
        
        file_put_contents($templatesDir . 'certificat.html', $certificat);
        
        // Template liste dossiers
        $listeDossiers = '
        <h1>Liste des Dossiers</h1>
        
        <p><strong>Nombre total:</strong> {{total_count}} dossier(s)</p>
        
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Titre</th>
                    <th>Statut</th>
                    <th>Priorité</th>
                    <th>Responsable</th>
                    <th>Créé le</th>
                    <th>Échéance</th>
                </tr>
            </thead>
            <tbody>
                {{#each dossiers}}
                <tr>
                    <td>{{reference}}</td>
                    <td>{{titre}}</td>
                    <td><span class="status-badge status-{{status}}">{{status}}</span></td>
                    <td><span class="priority-{{priority}}">{{priority}}</span></td>
                    <td>{{responsable_name}}</td>
                    <td>{{created_at}}</td>
                    <td>{{deadline}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
        
        <div class="footer-info">
            Document généré le {{generated_at}} par {{generated_by}}<br>
            Système de Gestion des Dossiers - MINSANTE
        </div>';
        
        file_put_contents($templatesDir . 'liste_dossiers.html', $listeDossiers);
        
        echo "Templates PDF créés avec succès dans: $templatesDir\n";
    }
}
