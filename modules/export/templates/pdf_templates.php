<?php
// Template pour l'export PDF d'un dossier complet

$template_dossier_complet = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 10px;
        }
        
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin: 10px 0;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .dossier-info {
            background: #f8f9fa;
            border-left: 4px solid #2c5aa0;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin: 15px 0;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 30%;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }
        
        .info-value {
            display: table-cell;
            padding: 5px 0;
            vertical-align: top;
        }
        
        .section {
            margin: 25px 0;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .table th {
            background: #2c5aa0;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }
        
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-nouveau { background: #e3f2fd; color: #1976d2; }
        .status-en-cours { background: #fff3e0; color: #f57c00; }
        .status-validation { background: #f3e5f5; color: #7b1fa2; }
        .status-termine { background: #e8f5e8; color: #388e3c; }
        .status-rejete { background: #ffebee; color: #d32f2f; }
        
        .priority {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .priority-haute { background: #ffcdd2; color: #c62828; }
        .priority-moyenne { background: #fff3e0; color: #ef6c00; }
        .priority-basse { background: #e8f5e8; color: #2e7d32; }
        
        .documents-list {
            list-style: none;
            padding: 0;
        }
        
        .documents-list li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .documents-list li:before {
            content: "📄 ";
            margin-right: 5px;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-muted { color: #666; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{LOGO_PATH}}" alt="Logo MINSANTE" class="logo">
        <div class="title">MINISTÈRE DE LA SANTÉ PUBLIQUE</div>
        <div class="subtitle">République du Cameroun - Paix, Travail, Patrie</div>
        <div class="subtitle"><strong>DOSSIER MÉDICAL COMPLET</strong></div>
    </div>
    
    <div class="dossier-info">
        <h2 style="margin: 0 0 15px 0; color: #2c5aa0;">Informations du Dossier</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Numéro de Dossier:</div>
                <div class="info-value"><strong>{{NUMERO_DOSSIER}}</strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Nom du Patient:</div>
                <div class="info-value">{{NOM_PATIENT}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Prénom du Patient:</div>
                <div class="info-value">{{PRENOM_PATIENT}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de Naissance:</div>
                <div class="info-value">{{DATE_NAISSANCE}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Sexe:</div>
                <div class="info-value">{{SEXE}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Adresse:</div>
                <div class="info-value">{{ADRESSE}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Téléphone:</div>
                <div class="info-value">{{TELEPHONE}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{EMAIL}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Statut:</div>
                <div class="info-value"><span class="status status-{{STATUT_CLASS}}">{{STATUT}}</span></div>
            </div>
            <div class="info-row">
                <div class="info-label">Priorité:</div>
                <div class="info-value"><span class="priority priority-{{PRIORITE_CLASS}}">{{PRIORITE}}</span></div>
            </div>
            <div class="info-row">
                <div class="info-label">Date de Création:</div>
                <div class="info-value">{{DATE_CREATION}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Dernière Modification:</div>
                <div class="info-value">{{DATE_MODIFICATION}}</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Description du Dossier</div>
        <p>{{DESCRIPTION}}</p>
    </div>
    
    {{#if ANTECEDENTS}}
    <div class="section">
        <div class="section-title">Antécédents Médicaux</div>
        <p>{{ANTECEDENTS}}</p>
    </div>
    {{/if}}
    
    {{#if ALLERGIES}}
    <div class="section">
        <div class="section-title">Allergies Connues</div>
        <p>{{ALLERGIES}}</p>
    </div>
    {{/if}}
    
    {{#if TRAITEMENTS}}
    <div class="section">
        <div class="section-title">Traitements en Cours</div>
        <p>{{TRAITEMENTS}}</p>
    </div>
    {{/if}}
    
    {{#if NOTES}}
    <div class="section">
        <div class="section-title">Notes Médicales</div>
        <p>{{NOTES}}</p>
    </div>
    {{/if}}
    
    {{#if DOCUMENTS}}
    <div class="section">
        <div class="section-title">Documents Associés</div>
        <ul class="documents-list">
            {{#each DOCUMENTS}}
            <li>{{nom_fichier}} - {{type_document}} ({{taille}})</li>
            {{/each}}
        </ul>
    </div>
    {{/if}}
    
    {{#if CONSULTATIONS}}
    <div class="section page-break">
        <div class="section-title">Historique des Consultations</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Médecin</th>
                    <th>Type</th>
                    <th>Diagnostic</th>
                    <th>Prescription</th>
                </tr>
            </thead>
            <tbody>
                {{#each CONSULTATIONS}}
                <tr>
                    <td>{{date_consultation}}</td>
                    <td>{{medecin}}</td>
                    <td>{{type_consultation}}</td>
                    <td>{{diagnostic}}</td>
                    <td>{{prescription}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    {{/if}}
    
    {{#if EXAMENS}}
    <div class="section">
        <div class="section-title">Examens et Analyses</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type d\'Examen</th>
                    <th>Résultat</th>
                    <th>Observations</th>
                </tr>
            </thead>
            <tbody>
                {{#each EXAMENS}}
                <tr>
                    <td>{{date_examen}}</td>
                    <td>{{type_examen}}</td>
                    <td>{{resultat}}</td>
                    <td>{{observations}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    {{/if}}
    
    {{#if HISTORIQUE}}
    <div class="section page-break">
        <div class="section-title">Historique des Modifications</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                {{#each HISTORIQUE}}
                <tr>
                    <td>{{date_action}}</td>
                    <td>{{utilisateur}}</td>
                    <td>{{action}}</td>
                    <td>{{details}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    {{/if}}
    
    <div class="section">
        <div class="section-title">Informations de Génération</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Généré le:</div>
                <div class="info-value">{{DATE_GENERATION}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Généré par:</div>
                <div class="info-value">{{UTILISATEUR_GENERATION}}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Version du Système:</div>
                <div class="info-value">MINSANTE v2.0</div>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>Document généré automatiquement par le Système MINSANTE - Page {PAGENO} sur {nb}</p>
        <p>Ce document contient des informations médicales confidentielles - Usage strictement professionnel</p>
    </div>
</body>
</html>';

// Template pour certificat médical
$template_certificat = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            padding: 40px;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 30px;
            margin-bottom: 40px;
        }
        
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #2c5aa0;
            margin: 15px 0;
        }
        
        .subtitle {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .certificat-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            text-transform: uppercase;
            margin: 30px 0;
            text-decoration: underline;
        }
        
        .content {
            text-align: justify;
            margin: 30px 0;
            font-size: 16px;
            line-height: 2;
        }
        
        .patient-info {
            background: #f8f9fa;
            border: 2px solid #2c5aa0;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        
        .signature-section {
            margin-top: 60px;
            display: table;
            width: 100%;
        }
        
        .signature-left, .signature-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 20px;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            width: 200px;
            margin: 40px 0 10px 0;
        }
        
        .date-lieu {
            text-align: right;
            margin: 30px 0;
            font-weight: bold;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .important {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{LOGO_PATH}}" alt="Logo MINSANTE" class="logo">
        <div class="title">MINISTÈRE DE LA SANTÉ PUBLIQUE</div>
        <div class="subtitle">République du Cameroun - Paix, Travail, Patrie</div>
        <div class="subtitle">{{ETABLISSEMENT}}</div>
        <div class="subtitle">{{ADRESSE_ETABLISSEMENT}}</div>
    </div>
    
    <div class="certificat-title">Certificat Médical</div>
    
    <div class="date-lieu">
        {{LIEU}}, le {{DATE_CERTIFICAT}}
    </div>
    
    <div class="content">
        Je soussigné(e), <strong>{{MEDECIN_NOM}}</strong>, {{MEDECIN_TITRE}}, 
        exerçant au {{ETABLISSEMENT}}, certifie avoir examiné ce jour :
    </div>
    
    <div class="patient-info">
        <strong>Nom et Prénom :</strong> {{NOM_PATIENT}} {{PRENOM_PATIENT}}<br>
        <strong>Né(e) le :</strong> {{DATE_NAISSANCE}} à {{LIEU_NAISSANCE}}<br>
        <strong>Sexe :</strong> {{SEXE}}<br>
        <strong>Adresse :</strong> {{ADRESSE}}<br>
        <strong>Profession :</strong> {{PROFESSION}}<br>
        {{#if CNI}}<strong>CNI N° :</strong> {{CNI}}<br>{{/if}}
    </div>
    
    <div class="content">
        {{#if DIAGNOSTIC}}
        <strong>Diagnostic :</strong><br>
        {{DIAGNOSTIC}}
        <br><br>
        {{/if}}
        
        {{#if OBSERVATIONS}}
        <strong>Observations :</strong><br>
        {{OBSERVATIONS}}
        <br><br>
        {{/if}}
        
        {{#if DUREE_ARRET}}
        <div class="important">
            Je certifie que l\'état de santé de {{NOM_PATIENT}} {{PRENOM_PATIENT}} 
            nécessite un arrêt de travail de <strong>{{DUREE_ARRET}}</strong> 
            à compter du {{DATE_DEBUT_ARRET}}{{#if DATE_FIN_ARRET}} jusqu\'au {{DATE_FIN_ARRET}}{{/if}}.
        </div>
        {{/if}}
        
        {{#if APTITUDE}}
        <div class="important">
            Je certifie que {{NOM_PATIENT}} {{PRENOM_PATIENT}} est <strong>{{APTITUDE}}</strong> 
            pour {{CONTEXTE_APTITUDE}}.
        </div>
        {{/if}}
        
        {{#if RESTRICTIONS}}
        <strong>Restrictions ou recommandations :</strong><br>
        {{RESTRICTIONS}}
        <br><br>
        {{/if}}
    </div>
    
    <div class="content">
        Ce certificat est délivré à l\'intéressé(e) pour servir et valoir ce que de droit.
    </div>
    
    <div class="signature-section">
        <div class="signature-left">
            <strong>Le Patient :</strong><br>
            (Signature)<br>
            <div class="signature-line"></div>
            {{NOM_PATIENT}} {{PRENOM_PATIENT}}
        </div>
        <div class="signature-right">
            <strong>Le Médecin :</strong><br>
            (Signature et Cachet)<br>
            <div class="signature-line"></div>
            {{MEDECIN_NOM}}<br>
            {{MEDECIN_TITRE}}<br>
            Ordre N° {{NUMERO_ORDRE}}
        </div>
    </div>
    
    <div class="footer">
        <p>Certificat N° {{NUMERO_CERTIFICAT}} - Généré le {{DATE_GENERATION}}</p>
        <p>Document officiel - Toute falsification est passible de poursuites</p>
    </div>
</body>
</html>';

// Template pour liste de dossiers
$template_liste_dossiers = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.3;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2c5aa0;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
            margin: 10px 0;
        }
        
        .subtitle {
            font-size: 12px;
            color: #666;
        }
        
        .filters-info {
            background: #f8f9fa;
            border-left: 4px solid #2c5aa0;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 10px;
        }
        
        .stats-summary {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            background: #e9ecef;
            padding: 10px;
        }
        
        .stat-item {
            display: table-cell;
            text-align: center;
            width: 25%;
            padding: 5px;
        }
        
        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
        }
        
        .stat-label {
            font-size: 9px;
            color: #666;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 9px;
        }
        
        .table th {
            background: #2c5aa0;
            color: white;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }
        
        .table td {
            padding: 4px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .status {
            padding: 2px 4px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-nouveau { background: #e3f2fd; color: #1976d2; }
        .status-en-cours { background: #fff3e0; color: #f57c00; }
        .status-validation { background: #f3e5f5; color: #7b1fa2; }
        .status-termine { background: #e8f5e8; color: #388e3c; }
        .status-rejete { background: #ffebee; color: #d32f2f; }
        
        .priority {
            padding: 1px 3px;
            border-radius: 2px;
            font-size: 7px;
            font-weight: bold;
        }
        
        .priority-haute { background: #ffcdd2; color: #c62828; }
        .priority-moyenne { background: #fff3e0; color: #ef6c00; }
        .priority-basse { background: #e8f5e8; color: #2e7d32; }
        
        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .small { font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LISTE DES DOSSIERS MÉDICAUX</div>
        <div class="subtitle">Ministère de la Santé Publique - République du Cameroun</div>
        <div class="subtitle">Généré le {{DATE_GENERATION}} par {{UTILISATEUR}}</div>
    </div>
    
    {{#if FILTRES}}
    <div class="filters-info">
        <strong>Filtres appliqués :</strong> {{FILTRES}}
    </div>
    {{/if}}
    
    <div class="stats-summary">
        <div class="stat-item">
            <div class="stat-value">{{TOTAL_DOSSIERS}}</div>
            <div class="stat-label">Total Dossiers</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{NOUVEAUX}}</div>
            <div class="stat-label">Nouveaux</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{EN_COURS}}</div>
            <div class="stat-label">En Cours</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{TERMINES}}</div>
            <div class="stat-label">Terminés</div>
        </div>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th style="width: 8%;">N° Dossier</th>
                <th style="width: 15%;">Patient</th>
                <th style="width: 8%;">Date Naissance</th>
                <th style="width: 10%;">Téléphone</th>
                <th style="width: 20%;">Description</th>
                <th style="width: 8%;">Statut</th>
                <th style="width: 6%;">Priorité</th>
                <th style="width: 8%;">Créé le</th>
                <th style="width: 8%;">Modifié le</th>
                <th style="width: 9%;">Médecin</th>
            </tr>
        </thead>
        <tbody>
            {{#each DOSSIERS}}
            <tr>
                <td><strong>{{numero_dossier}}</strong></td>
                <td>{{nom}} {{prenom}}</td>
                <td class="small">{{date_naissance}}</td>
                <td class="small">{{telephone}}</td>
                <td class="small">{{description_courte}}</td>
                <td><span class="status status-{{statut_class}}">{{statut}}</span></td>
                <td><span class="priority priority-{{priorite_class}}">{{priorite}}</span></td>
                <td class="small">{{date_creation}}</td>
                <td class="small">{{date_modification}}</td>
                <td class="small">{{medecin_nom}}</td>
            </tr>
            {{/each}}
        </tbody>
    </table>
    
    {{#if PAGINATION}}
    <div class="text-center" style="margin-top: 20px;">
        <small>Page {{PAGE_ACTUELLE}} sur {{TOTAL_PAGES}} - Affichage de {{DEBUT}} à {{FIN}} sur {{TOTAL_DOSSIERS}} dossiers</small>
    </div>
    {{/if}}
    
    <div class="footer">
        <p>Document généré automatiquement - MINSANTE v2.0 - Page {PAGENO} sur {nb}</p>
    </div>
</body>
</html>';

// Template pour rapport statistique
$template_rapport_stats = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #2c5aa0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #2c5aa0;
            margin: 10px 0;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .period-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        
        .stats-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }
        
        .stats-row {
            display: table-row;
        }
        
        .stat-card {
            display: table-cell;
            width: 25%;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            text-align: center;
            vertical-align: top;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }
        
        .section {
            margin: 30px 0;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c5aa0;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        
        .chart-placeholder {
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 15px 0;
            color: #666;
            font-style: italic;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .table th {
            background: #2c5aa0;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        
        .table td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
        }
        
        .table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .trend-up {
            color: #28a745;
            font-weight: bold;
        }
        
        .trend-down {
            color: #dc3545;
            font-weight: bold;
        }
        
        .trend-stable {
            color: #ffc107;
            font-weight: bold;
        }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
            padding: 10px;
            border-top: 1px solid #ddd;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">RAPPORT STATISTIQUE</div>
        <div class="subtitle">Système de Gestion des Dossiers Médicaux</div>
        <div class="subtitle">Ministère de la Santé Publique - République du Cameroun</div>
    </div>
    
    <div class="period-info">
        Période d\'analyse : du {{DATE_DEBUT}} au {{DATE_FIN}}
    </div>
    
    <div class="stats-grid">
        <div class="stats-row">
            <div class="stat-card">
                <span class="stat-value">{{TOTAL_DOSSIERS}}</span>
                <div class="stat-label">Total Dossiers</div>
            </div>
            <div class="stat-card">
                <span class="stat-value">{{NOUVEAUX_DOSSIERS}}</span>
                <div class="stat-label">Nouveaux Dossiers</div>
            </div>
            <div class="stat-card">
                <span class="stat-value">{{DOSSIERS_TERMINES}}</span>
                <div class="stat-label">Dossiers Terminés</div>
            </div>
            <div class="stat-card">
                <span class="stat-value">{{TAUX_COMPLETION}}%</span>
                <div class="stat-label">Taux de Completion</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <div class="section-title">Répartition par Statut</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Statut</th>
                    <th>Nombre</th>
                    <th>Pourcentage</th>
                    <th>Évolution</th>
                </tr>
            </thead>
            <tbody>
                {{#each REPARTITION_STATUT}}
                <tr>
                    <td>{{statut}}</td>
                    <td>{{nombre}}</td>
                    <td>{{pourcentage}}%</td>
                    <td class="trend-{{evolution_class}}">{{evolution}}</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Évolution Mensuelle</div>
        <div class="chart-placeholder">
            [Graphique d\'évolution mensuelle des dossiers]
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Mois</th>
                    <th>Nouveaux</th>
                    <th>Terminés</th>
                    <th>En cours</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                {{#each EVOLUTION_MENSUELLE}}
                <tr>
                    <td>{{mois}}</td>
                    <td>{{nouveaux}}</td>
                    <td>{{termines}}</td>
                    <td>{{en_cours}}</td>
                    <td><strong>{{total}}</strong></td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    
    <div class="section page-break">
        <div class="section-title">Performance par Médecin</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Médecin</th>
                    <th>Dossiers Traités</th>
                    <th>Dossiers Terminés</th>
                    <th>Temps Moyen</th>
                    <th>Taux de Succès</th>
                </tr>
            </thead>
            <tbody>
                {{#each PERFORMANCE_MEDECINS}}
                <tr>
                    <td>{{nom_medecin}}</td>
                    <td>{{dossiers_traites}}</td>
                    <td>{{dossiers_termines}}</td>
                    <td>{{temps_moyen}} jours</td>
                    <td>{{taux_succes}}%</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Répartition par Priorité</div>
        <div class="chart-placeholder">
            [Graphique en secteurs - Répartition par priorité]
        </div>
        <table class="table">
            <thead>
                <tr>
                    <th>Priorité</th>
                    <th>Nombre</th>
                    <th>Pourcentage</th>
                    <th>Temps Moyen de Traitement</th>
                </tr>
            </thead>
            <tbody>
                {{#each REPARTITION_PRIORITE}}
                <tr>
                    <td>{{priorite}}</td>
                    <td>{{nombre}}</td>
                    <td>{{pourcentage}}%</td>
                    <td>{{temps_moyen}} jours</td>
                </tr>
                {{/each}}
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Indicateurs Clés de Performance</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Indicateur</th>
                    <th>Valeur Actuelle</th>
                    <th>Objectif</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Temps moyen de traitement</td>
                    <td>{{TEMPS_MOYEN_TRAITEMENT}} jours</td>
                    <td>≤ 7 jours</td>
                    <td class="trend-{{TEMPS_STATUT}}">{{TEMPS_EVALUATION}}</td>
                </tr>
                <tr>
                    <td>Taux de satisfaction patients</td>
                    <td>{{TAUX_SATISFACTION}}%</td>
                    <td>≥ 90%</td>
                    <td class="trend-{{SATISFACTION_STATUT}}">{{SATISFACTION_EVALUATION}}</td>
                </tr>
                <tr>
                    <td>Dossiers en retard</td>
                    <td>{{DOSSIERS_RETARD}}</td>
                    <td>≤ 5%</td>
                    <td class="trend-{{RETARD_STATUT}}">{{RETARD_EVALUATION}}</td>
                </tr>
                <tr>
                    <td>Disponibilité système</td>
                    <td>{{DISPONIBILITE}}%</td>
                    <td>≥ 99%</td>
                    <td class="trend-{{DISPO_STATUT}}">{{DISPO_EVALUATION}}</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Recommandations</div>
        <ul>
            {{#each RECOMMANDATIONS}}
            <li>{{.}}</li>
            {{/each}}
        </ul>
    </div>
    
    <div class="footer">
        <p>Rapport généré le {{DATE_GENERATION}} par {{UTILISATEUR}} - MINSANTE v2.0 - Page {PAGENO} sur {nb}</p>
    </div>
</body>
</html>';

// Sauvegarder les templates
return [
    'dossier_complet' => $template_dossier_complet,
    'certificat' => $template_certificat,
    'liste_dossiers' => $template_liste_dossiers,
    'rapport_stats' => $template_rapport_stats
];
?>
