<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';

requireAuth();

$pageTitle = "FAQ - Questions Fréquentes - " . t('app_name');

$faqs = [
    [
        'category' => 'Général',
        'icon' => 'fas fa-info-circle',
        'color' => '#3498db',
        'questions' => [
            [
                'question' => 'Comment créer un nouveau dossier ?',
                'answer' => 'Pour créer un nouveau dossier, allez dans le menu "Dossiers" > "Nouveau dossier" ou cliquez sur le bouton "+" dans la liste des dossiers. Remplissez les informations obligatoires et sauvegardez.'
            ],
            [
                'question' => 'Comment rechercher un dossier spécifique ?',
                'answer' => 'Utilisez la barre de recherche en haut de la page ou allez dans "Dossiers" > "Liste des dossiers" et utilisez les filtres disponibles (statut, catégorie, date, etc.).'
            ],
            [
                'question' => 'Que signifient les différents statuts des dossiers ?',
                'answer' => '<ul><li><strong>Nouveau :</strong> Dossier venant d\'être créé</li><li><strong>En cours :</strong> Dossier en traitement</li><li><strong>En attente :</strong> Dossier en attente de validation</li><li><strong>Validé :</strong> Dossier approuvé</li><li><strong>Archivé :</strong> Dossier terminé et archivé</li></ul>'
            ]
        ]
    ],
    [
        'category' => 'Notifications',
        'icon' => 'fas fa-bell',
        'color' => '#f39c12',
        'questions' => [
            [
                'question' => 'Comment activer les notifications par email ?',
                'answer' => 'Allez dans votre "Profil" > "Préférences" et cochez "Notifications par email". Vous pouvez aussi configurer les types de notifications dans les paramètres.'
            ],
            [
                'question' => 'Pourquoi je ne reçois pas de notifications ?',
                'answer' => 'Vérifiez que :<br>• Les notifications sont activées dans vos préférences<br>• Votre adresse email est correcte<br>• Les emails ne sont pas dans vos spams<br>• Les notifications navigateur sont autorisées'
            ],
            [
                'question' => 'Comment configurer les échéances ?',
                'answer' => 'Si vous êtes gestionnaire ou administrateur, allez dans "Gestion" > "Configuration Échéances" pour définir les délais de rappel et les types de notifications.'
            ]
        ]
    ],
    [
        'category' => 'Sécurité',
        'icon' => 'fas fa-shield-alt',
        'color' => '#e74c3c',
        'questions' => [
            [
                'question' => 'Comment changer mon mot de passe ?',
                'answer' => 'Allez dans "Profil" > "Sécurité" et utilisez le formulaire "Changer le mot de passe". Vous devrez saisir votre mot de passe actuel et le nouveau mot de passe deux fois.'
            ],
            [
                'question' => 'Quels sont les critères d\'un mot de passe sécurisé ?',
                'answer' => 'Un mot de passe sécurisé doit contenir :<br>• Au moins 8 caractères<br>• Des lettres majuscules et minuscules<br>• Des chiffres<br>• Des caractères spéciaux (recommandé)'
            ],
            [
                'question' => 'Que faire si j\'ai oublié mon mot de passe ?',
                'answer' => 'Cliquez sur "Mot de passe oublié ?" sur la page de connexion. Un email avec un lien de réinitialisation vous sera envoyé.'
            ]
        ]
    ],
    [
        'category' => 'Rapports',
        'icon' => 'fas fa-chart-line',
        'color' => '#9b59b6',
        'questions' => [
            [
                'question' => 'Comment générer un rapport ?',
                'answer' => 'Allez dans "Rapports" et choisissez le type de rapport souhaité. Vous pouvez filtrer par période, statut, catégorie, etc. Les rapports peuvent être exportés en PDF ou Excel.'
            ],
            [
                'question' => 'Comment exporter des données ?',
                'answer' => 'Dans la section "Rapports" > "Export", choisissez le format (PDF, Excel) et les données à exporter. Vous pouvez aussi exporter directement depuis les listes de dossiers.'
            ],
            [
                'question' => 'Les statistiques ne s\'affichent pas correctement',
                'answer' => 'Vérifiez que vous avez les permissions nécessaires pour voir les statistiques. Essayez de rafraîchir la page ou de changer la période d\'analyse.'
            ]
        ]
    ],
    [
        'category' => 'Permissions',
        'icon' => 'fas fa-users-cog',
        'color' => '#27ae60',
        'questions' => [
            [
                'question' => 'Quels sont les différents rôles utilisateur ?',
                'answer' => '<ul><li><strong>Consultant :</strong> Peut consulter les dossiers</li><li><strong>Gestionnaire :</strong> Peut créer, modifier et gérer les dossiers</li><li><strong>Administrateur :</strong> Accès complet au système</li></ul>'
            ],
            [
                'question' => 'Comment demander des permissions supplémentaires ?',
                'answer' => 'Contactez un administrateur ou utilisez le système de demande d\'accès intégré. Une notification sera envoyée aux administrateurs pour traiter votre demande.'
            ],
            [
                'question' => 'Pourquoi je ne peux pas accéder à certaines fonctionnalités ?',
                'answer' => 'Certaines fonctionnalités sont limitées selon votre rôle. Vérifiez vos permissions dans votre profil ou contactez un administrateur pour plus d\'informations.'
            ]
        ]
    ]
];
?>

<div class="page-header" style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-question-circle"></i>
            Questions Fréquentes (FAQ)
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            Trouvez rapidement des réponses à vos questions
        </p>
    </div>
</div>

<div class="container">
    <!-- Barre de recherche FAQ -->
    <div class="search-faq" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="flex: 1;">
                <input type="text" id="faqSearch" placeholder="Rechercher dans la FAQ..." 
                       style="width: 100%; padding: 16px 20px; border: 2px solid #e1e8ed; border-radius: 50px; font-size: 1.1rem; background: #f8fafc;"
                       onfocus="this.style.borderColor='#f39c12'; this.style.background='white'"
                       onblur="this.style.borderColor='#e1e8ed'; this.style.background='#f8fafc'">
            </div>
            <button onclick="searchFAQ()" 
                    style="background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border: none; padding: 16px 24px; border-radius: 50px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                <i class="fas fa-search"></i> Rechercher
            </button>
        </div>
    </div>

    <!-- Navigation par catégories -->
    <div class="category-nav" style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; justify-content: center;">
        <?php foreach ($faqs as $index => $category): ?>
            <button onclick="scrollToCategory('category-<?= $index ?>')" 
                    style="background: linear-gradient(135deg, <?= $category['color'] ?>, <?= adjustBrightness($category['color'], -20) ?>); color: white; border: none; padding: 12px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.2)'"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <i class="<?= $category['icon'] ?>"></i>
                <?= $category['category'] ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- FAQ par catégories -->
    <?php foreach ($faqs as $index => $category): ?>
        <div id="category-<?= $index ?>" class="faq-category" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 2rem; overflow: hidden;">
            <div class="category-header" style="background: linear-gradient(135deg, <?= $category['color'] ?>15, <?= $category['color'] ?>08); padding: 2rem; border-bottom: 1px solid #e1e8ed;">
                <h2 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                    <i class="<?= $category['icon'] ?>" style="color: <?= $category['color'] ?>; font-size: 1.5rem;"></i>
                    <?= $category['category'] ?>
                </h2>
                <p style="margin: 8px 0 0 0; color: #7f8c8d;">
                    <?= count($category['questions']) ?> question<?= count($category['questions']) > 1 ? 's' : '' ?>
                </p>
            </div>
            
            <div class="questions-container">
                <?php foreach ($category['questions'] as $qIndex => $qa): ?>
                    <div class="faq-item" style="border-bottom: 1px solid #f0f4f8;">
                        <div class="question" 
                             onclick="toggleAnswer('answer-<?= $index ?>-<?= $qIndex ?>')"
                             style="padding: 1.5rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: white; transition: all 0.2s;"
                             onmouseover="this.style.background='#f8fafc'"
                             onmouseout="this.style.background='white'">
                            <h4 style="margin: 0; color: #2c3e50; font-weight: 600;">
                                <i class="fas fa-question-circle" style="color: <?= $category['color'] ?>; margin-right: 12px;"></i>
                                <?= htmlspecialchars($qa['question']) ?>
                            </h4>
                            <i class="fas fa-chevron-down toggle-icon" id="icon-<?= $index ?>-<?= $qIndex ?>" 
                               style="color: <?= $category['color'] ?>; transition: transform 0.3s ease;"></i>
                        </div>
                        <div class="answer" id="answer-<?= $index ?>-<?= $qIndex ?>" 
                             style="max-height: 0; overflow: hidden; transition: all 0.3s ease; background: #f8fafc;">
                            <div style="padding: 0 1.5rem 1.5rem 1.5rem; color: #5a6c7d; line-height: 1.6;">
                                <div style="background: white; padding: 1.5rem; border-radius: 12px; border-left: 4px solid <?= $category['color'] ?>;">
                                    <i class="fas fa-lightbulb" style="color: <?= $category['color'] ?>; margin-right: 8px;"></i>
                                    <?= $qa['answer'] ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Section d'aide supplémentaire -->
    <div class="help-section" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white; border-radius: 16px; padding: 3rem; text-align: center; margin-top: 3rem;">
        <h2 style="margin: 0 0 1rem 0;">
            <i class="fas fa-life-ring" style="margin-right: 12px;"></i>
            Besoin d'aide supplémentaire ?
        </h2>
        <p style="margin: 0 0 2rem 0; opacity: 0.9; font-size: 1.1rem;">
            Si vous ne trouvez pas la réponse à votre question, n'hésitez pas à nous contacter
        </p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            <a href="<?= BASE_URL ?>modules/support/ticket.php" 
               style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-4px)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
                <i class="fas fa-ticket-alt" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <h4 style="margin: 0 0 8px 0;">Ouvrir un ticket</h4>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Support technique personnalisé</p>
            </a>

            <a href="<?= BASE_URL ?>modules/help/guide.php" 
               style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-4px)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
                <i class="fas fa-book" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <h4 style="margin: 0 0 8px 0;">Guide utilisateur</h4>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">Documentation complète</p>
            </a>

            <a href="mailto:support@minsante.gov" 
               style="background: rgba(255,255,255,0.2); color: white; text-decoration: none; padding: 1.5rem; border-radius: 12px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); transition: all 0.2s;"
               onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-4px)'"
               onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(0)'">
                <i class="fas fa-envelope" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                <h4 style="margin: 0 0 8px 0;">Email support</h4>
                <p style="margin: 0; opacity: 0.9; font-size: 0.9rem;">support@minsante.gov</p>
            </a>
        </div>
    </div>
</div>

<script>
function toggleAnswer(answerId) {
    const answer = document.getElementById(answerId);
    const icon = document.getElementById('icon-' + answerId.split('-')[1] + '-' + answerId.split('-')[2]);
    
    if (answer.style.maxHeight === '0px' || answer.style.maxHeight === '') {
        answer.style.maxHeight = answer.scrollHeight + 'px';
        icon.style.transform = 'rotate(180deg)';
    } else {
        answer.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
    }
}

function scrollToCategory(categoryId) {
    document.getElementById(categoryId).scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });
}

function searchFAQ() {
    const searchTerm = document.getElementById('faqSearch').value.toLowerCase();
    const faqItems = document.querySelectorAll('.faq-item');
    let foundResults = false;
    
    faqItems.forEach(item => {
        const question = item.querySelector('.question h4').textContent.toLowerCase();
        const answer = item.querySelector('.answer').textContent.toLowerCase();
        
        if (question.includes(searchTerm) || answer.includes(searchTerm)) {
            item.style.display = 'block';
            item.style.background = searchTerm ? '#fff8e1' : 'white';
            foundResults = true;
        } else {
            item.style.display = searchTerm ? 'none' : 'block';
            item.style.background = 'white';
        }
    });
    
    // Masquer/afficher les catégories vides
    document.querySelectorAll('.faq-category').forEach(category => {
        const visibleItems = category.querySelectorAll('.faq-item[style*="display: block"], .faq-item:not([style*="display: none"])');
        category.style.display = visibleItems.length > 0 ? 'block' : 'none';
    });
    
    if (!foundResults && searchTerm) {
        alert('Aucun résultat trouvé pour "' + searchTerm + '"');
    }
}

// Recherche en temps réel
document.getElementById('faqSearch').addEventListener('input', function() {
    if (this.value.length > 2 || this.value.length === 0) {
        searchFAQ();
    }
});

// Raccourci clavier pour la recherche
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('faqSearch').focus();
    }
});
</script>

<?php
function adjustBrightness($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

require_once '../../includes/footer.php';
?>
