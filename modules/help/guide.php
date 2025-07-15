<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';

requireAuth();

// Fonction de fallback pour la traduction si elle n'existe pas
if (!function_exists('t')) {
    function t($key, $default = null) {
        $translations = [
            'app_name' => 'MINSANTE - Gestion des Dossiers'
        ];
        return $translations[$key] ?? $default ?? $key;
    }
}

$pageTitle = "Guide Utilisateur - " . t('app_name');

$sections = [
    [
        'id' => 'getting-started',
        'title' => 'Premiers pas',
        'icon' => 'fas fa-play-circle',
        'color' => '#3498db',
        'description' => 'Découvrez les bases de l\'application',
        'content' => [
            [
                'title' => 'Connexion et navigation',
                'steps' => [
                    'Connectez-vous avec vos identifiants',
                    'Explorez le menu principal en haut de la page',
                    'Utilisez la barre de recherche pour trouver rapidement des dossiers',
                    'Consultez le tableau de bord pour voir vos tâches en cours'
                ]
            ],
            [
                'title' => 'Interface utilisateur',
                'steps' => [
                    'La barre de navigation contient tous les menus principaux',
                    'Le tableau de bord affiche les statistiques importantes',
                    'Les notifications apparaissent en haut à droite',
                    'Votre profil est accessible via le menu Profil'
                ]
            ]
        ]
    ],
    [
        'id' => 'dossiers',
        'title' => 'Gestion des dossiers',
        'icon' => 'fas fa-folder-open',
        'color' => '#e67e22',
        'description' => 'Créer, modifier et gérer vos dossiers',
        'content' => [
            [
                'title' => 'Créer un nouveau dossier',
                'steps' => [
                    'Allez dans "Dossiers" > "Nouveau dossier"',
                    'Remplissez les informations obligatoires (nom, catégorie)',
                    'Ajoutez une description détaillée',
                    'Définissez la priorité et l\'échéance si nécessaire',
                    'Cliquez sur "Enregistrer" pour créer le dossier'
                ]
            ],
            [
                'title' => 'Modifier un dossier existant',
                'steps' => [
                    'Trouvez le dossier dans la liste ou via la recherche',
                    'Cliquez sur "Modifier" ou l\'icône crayon',
                    'Effectuez vos modifications',
                    'Sauvegardez les changements',
                    'Un historique des modifications est conservé'
                ]
            ],
            [
                'title' => 'Statuts des dossiers',
                'steps' => [
                    '<strong>Nouveau :</strong> Dossier venant d\'être créé',
                    '<strong>En cours :</strong> Dossier en traitement actif',
                    '<strong>En attente :</strong> Dossier en attente de validation',
                    '<strong>Validé :</strong> Dossier approuvé et finalisé',
                    '<strong>Archivé :</strong> Dossier terminé et archivé'
                ]
            ]
        ]
    ],
    [
        'id' => 'search',
        'title' => 'Recherche et filtres',
        'icon' => 'fas fa-search',
        'color' => '#9b59b6',
        'description' => 'Trouvez rapidement l\'information recherchée',
        'content' => [
            [
                'title' => 'Recherche globale',
                'steps' => [
                    'Utilisez la barre de recherche en haut de la page',
                    'Tapez au moins 3 caractères pour des suggestions',
                    'Sélectionnez dans les suggestions ou appuyez sur Entrée',
                    'Les résultats sont organisés par catégorie'
                ]
            ],
            [
                'title' => 'Filtres avancés',
                'steps' => [
                    'Dans la liste des dossiers, utilisez les filtres',
                    'Filtrez par statut, catégorie, date de création',
                    'Combinez plusieurs filtres pour affiner la recherche',
                    'Sauvegardez vos filtres favoris'
                ]
            ]
        ]
    ],
    [
        'id' => 'reports',
        'title' => 'Rapports et exports',
        'icon' => 'fas fa-chart-bar',
        'color' => '#e74c3c',
        'description' => 'Générer et exporter vos données',
        'content' => [
            [
                'title' => 'Générer un rapport',
                'steps' => [
                    'Allez dans "Rapports" et choisissez le type de rapport',
                    'Sélectionnez la période d\'analyse',
                    'Configurez les filtres si nécessaire',
                    'Cliquez sur "Générer le rapport"',
                    'Le rapport s\'affiche avec des graphiques interactifs'
                ]
            ],
            [
                'title' => 'Exporter des données',
                'steps' => [
                    'Dans n\'importe quelle liste, cliquez sur "Exporter"',
                    'Choisissez le format : PDF pour impression, Excel pour analyse',
                    'Sélectionnez les colonnes à inclure',
                    'Le fichier est téléchargé automatiquement'
                ]
            ]
        ]
    ],
    [
        'id' => 'security',
        'title' => 'Sécurité et permissions',
        'icon' => 'fas fa-shield-alt',
        'color' => '#27ae60',
        'description' => 'Protégez votre compte et vos données',
        'content' => [
            [
                'title' => 'Sécurité du compte',
                'steps' => [
                    'Utilisez un mot de passe fort (8+ caractères, majuscules, chiffres)',
                    'Changez votre mot de passe régulièrement',
                    'Ne partagez jamais vos identifiants',
                    'Déconnectez-vous après utilisation sur un poste partagé'
                ]
            ],
            [
                'title' => 'Permissions et rôles',
                'steps' => [
                    '<strong>Consultant :</strong> Consultation des dossiers autorisés',
                    '<strong>Gestionnaire :</strong> Création et modification des dossiers',
                    '<strong>Administrateur :</strong> Gestion complète du système',
                    'Contactez un administrateur pour changer de rôle'
                ]
            ]
        ]
    ],
    [
        'id' => 'troubleshooting',
        'title' => 'Résolution de problèmes',
        'icon' => 'fas fa-wrench',
        'color' => '#f39c12',
        'description' => 'Solutions aux problèmes courants',
        'content' => [
            [
                'title' => 'Problèmes de connexion',
                'steps' => [
                    'Vérifiez que vos identifiants sont corrects',
                    'Essayez de réinitialiser votre mot de passe',
                    'Videz le cache de votre navigateur',
                    'Contactez l\'administrateur si le problème persiste'
                ]
            ],
            [
                'title' => 'Problèmes d\'affichage',
                'steps' => [
                    'Actualisez la page (F5 ou Ctrl+R)',
                    'Vérifiez que JavaScript est activé',
                    'Essayez avec un autre navigateur',
                    'Désactivez temporairement les extensions de navigateur'
                ]
            ],
            [
                'title' => 'Problèmes de performance',
                'steps' => [
                    'Fermez les onglets inutiles',
                    'Videz le cache du navigateur',
                    'Vérifiez votre connexion internet',
                    'Contactez le support si les lenteurs persistent'
                ]
            ]
        ]
    ]
];
?>

<div class="page-header" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-book-open"></i>
            Guide Utilisateur
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            Documentation complète pour utiliser efficacement l'application
        </p>
    </div>
</div>

<div class="container">
    <div style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem; align-items: start;">
        
        <!-- Menu de navigation -->
        <div class="guide-nav" style="position: sticky; top: 2rem;">
            <div style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden;">
                <div style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1.5rem; text-align: center;">
                    <h3 style="margin: 0; font-size: 1.1rem;">
                        <i class="fas fa-list"></i> Sommaire
                    </h3>
                </div>
                
                <nav style="padding: 1rem 0;">
                    <?php foreach ($sections as $section): ?>
                        <a href="#<?= $section['id'] ?>" 
                           onclick="scrollToSection('<?= $section['id'] ?>')"
                           style="display: flex; align-items: center; gap: 12px; padding: 12px 1.5rem; color: #2c3e50; text-decoration: none; border-left: 3px solid transparent; transition: all 0.2s;"
                           onmouseover="this.style.background='#f8fafc'; this.style.borderLeftColor='<?= $section['color'] ?>'"
                           onmouseout="this.style.background='transparent'; this.style.borderLeftColor='transparent'">
                            <i class="<?= $section['icon'] ?>" style="color: <?= $section['color'] ?>; width: 20px;"></i>
                            <span style="font-weight: 500; font-size: 0.9rem;"><?= $section['title'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="guide-content">
            <?php foreach ($sections as $section): ?>
                <section id="<?= $section['id'] ?>" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 2rem; overflow: hidden;">
                    
                    <!-- En-tête de section -->
                    <div style="background: linear-gradient(135deg, <?= $section['color'] ?>15, <?= $section['color'] ?>08); padding: 2rem; border-bottom: 1px solid #e1e8ed;">
                        <h2 style="margin: 0 0 8px 0; color: #2c3e50; display: flex; align-items: center; gap: 12px; font-size: 1.8rem;">
                            <i class="<?= $section['icon'] ?>" style="color: <?= $section['color'] ?>; font-size: 2rem;"></i>
                            <?= $section['title'] ?>
                        </h2>
                        <p style="margin: 0; color: #7f8c8d; font-size: 1.1rem;">
                            <?= $section['description'] ?>
                        </p>
                    </div>

                    <!-- Contenu de section -->
                    <div style="padding: 2rem;">
                        <?php foreach ($section['content'] as $content): ?>
                            <div style="margin-bottom: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 12px; border-left: 4px solid <?= $section['color'] ?>;">
                                <h3 style="margin: 0 0 1rem 0; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-chevron-right" style="color: <?= $section['color'] ?>; font-size: 0.8rem;"></i>
                                    <?= $content['title'] ?>
                                </h3>
                                
                                <ol style="margin: 0; padding-left: 1.5rem; color: #5a6c7d; line-height: 1.8;">
                                    <?php foreach ($content['steps'] as $step): ?>
                                        <li style="margin-bottom: 0.5rem; padding-left: 8px;">
                                            <?= $step ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <!-- Section d'aide rapide -->
            <div style="background: linear-gradient(135deg, #1e3c72, #2a5298); color: white; border-radius: 16px; padding: 3rem; text-align: center; margin-top: 3rem;">
                <h2 style="margin: 0 0 1rem 0;">
                    <i class="fas fa-rocket" style="margin-right: 12px;"></i>
                    Conseils pour une utilisation optimale
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">
                    <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px);">
                        <i class="fas fa-keyboard" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #74b9ff;"></i>
                        <h4 style="margin: 0 0 8px 0;">Raccourcis clavier</h4>
                        <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">
                            Ctrl+F : Recherche rapide<br>
                            Ctrl+N : Nouveau dossier<br>
                            Ctrl+S : Sauvegarder
                        </p>
                    </div>

                    <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px);">
                        <i class="fas fa-mobile-alt" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #00b894;"></i>
                        <h4 style="margin: 0 0 8px 0;">Version mobile</h4>
                        <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">
                            L'application s'adapte<br>
                            automatiquement à votre<br>
                            téléphone ou tablette
                        </p>
                    </div>

                    <div style="background: rgba(255,255,255,0.1); padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px);">
                        <i class="fas fa-sync-alt" style="font-size: 2rem; margin-bottom: 1rem; display: block; color: #fdcb6e;"></i>
                        <h4 style="margin: 0 0 8px 0;">Synchronisation</h4>
                        <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">
                            Vos données sont<br>
                            automatiquement sauvegardées<br>
                            en temps réel
                        </p>
                    </div>
                </div>

                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.2);">
                    <p style="margin: 0 0 1rem 0; opacity: 0.9;">
                        Des questions ? Notre équipe support est là pour vous aider
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="<?= BASE_URL ?>modules/help/faq.php" 
                           style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 12px 24px; border-radius: 25px; font-weight: 600; transition: all 0.2s; border: 1px solid rgba(255,255,255,0.3);"
                           onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            <i class="fas fa-question-circle"></i> FAQ
                        </a>
                        <a href="mailto:support@minsante.gov" 
                           style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 12px 24px; border-radius: 25px; font-weight: 600; transition: all 0.2s; border: 1px solid rgba(255,255,255,0.3);"
                           onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function scrollToSection(sectionId) {
    document.getElementById(sectionId).scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

// Mise en surbrillance de la section active dans le menu
function updateActiveSection() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.guide-nav a');
    
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop - 100;
        const sectionHeight = section.offsetHeight;
        if (window.pageYOffset >= sectionTop && window.pageYOffset < sectionTop + sectionHeight) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.style.background = 'transparent';
        link.style.borderLeftColor = 'transparent';
        link.style.fontWeight = '500';
        
        if (link.getAttribute('href') === '#' + current) {
            link.style.background = '#f8fafc';
            link.style.borderLeftColor = link.querySelector('i').style.color;
            link.style.fontWeight = '600';
        }
    });
}

// Écouter le scroll
window.addEventListener('scroll', updateActiveSection);
window.addEventListener('load', updateActiveSection);

// Recherche dans le guide
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchTerm = prompt('Rechercher dans le guide :');
        if (searchTerm) {
            window.find(searchTerm);
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
