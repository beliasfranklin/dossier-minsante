<?php
require_once '../../includes/header.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireAuth();

$pageTitle = "Recherche Globale - " . t('app_name');
$query = trim($_GET['q'] ?? '');
$results = [];
$totalResults = 0;

if (strlen($query) >= 2) {
    try {
        // Recherche dans les dossiers
        $stmt = $pdo->prepare("
            SELECT id, titre, numero_dossier, status, description, created_at
            FROM dossiers 
            WHERE (titre LIKE ? OR numero_dossier LIKE ? OR description LIKE ?) 
            AND deleted_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $dossiers = $stmt->fetchAll();

        if ($dossiers) {
            $results['Dossiers'] = $dossiers;
            $totalResults += count($dossiers);
        }

        // Recherche dans les utilisateurs (si gestionnaire+)
        if (hasPermission(ROLE_GESTIONNAIRE)) {
            $stmt = $pdo->prepare("
                SELECT id, nom, prenom, email, role 
                FROM users 
                WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?) 
                AND deleted_at IS NULL
                ORDER BY nom, prenom 
                LIMIT 10
            ");
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $users = $stmt->fetchAll();

            if ($users) {
                $results['Utilisateurs'] = $users;
                $totalResults += count($users);
            }
        }

        // Recherche dans les catégories
        if (function_exists('fetchAll')) {
            $categories = fetchAll("
                SELECT id, nom, description 
                FROM categories 
                WHERE nom LIKE ? OR description LIKE ?
                LIMIT 5
            ", [$searchTerm, $searchTerm]);

            if ($categories) {
                $results['Catégories'] = $categories;
                $totalResults += count($categories);
            }
        }

    } catch (PDOException $e) {
        error_log("Erreur recherche globale: " . $e->getMessage());
    }
}
?>

<div class="page-header" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 2rem 0; margin-bottom: 2rem; border-radius: 12px;">
    <div class="container">
        <h1 style="margin: 0; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-search"></i>
            Recherche Globale
        </h1>
        <p style="margin: 8px 0 0 0; opacity: 0.9;">
            <?= $query ? "Résultats pour : \"" . htmlspecialchars($query) . "\"" : "Recherchez dans tous les modules" ?>
        </p>
    </div>
</div>

<div class="container">
    <!-- Formulaire de recherche -->
    <div class="search-form-container" style="background: white; padding: 2rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <form method="GET" class="advanced-search-form">
            <div style="display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: end;">
                <div>
                    <label for="search-input" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                        <i class="fas fa-search"></i> Terme de recherche
                    </label>
                    <input type="text" 
                           id="search-input"
                           name="q" 
                           value="<?= htmlspecialchars($query) ?>"
                           placeholder="Rechercher dossiers, utilisateurs, catégories..."
                           style="width: 100%; padding: 12px 16px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;"
                           onfocus="this.style.borderColor='#3498db'"
                           onblur="this.style.borderColor='#e1e8ed'">
                </div>
                <button type="submit" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </div>
        </form>
    </div>

    <?php if ($query && strlen($query) >= 2): ?>
        <!-- Résultats -->
        <div class="results-summary" style="margin-bottom: 2rem;">
            <p style="color: #7f8c8d; font-size: 1.1rem;">
                <i class="fas fa-info-circle"></i>
                <?= $totalResults ?> résultat<?= $totalResults > 1 ? 's' : '' ?> trouvé<?= $totalResults > 1 ? 's' : '' ?>
            </p>
        </div>

        <?php if ($totalResults > 0): ?>
            <?php foreach ($results as $category => $items): ?>
                <div class="results-section" style="background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); margin-bottom: 2rem; overflow: hidden;">
                    <div class="section-header" style="background: linear-gradient(135deg, #f8fafc, #e3f2fd); padding: 1.5rem; border-bottom: 1px solid #e1e8ed;">
                        <h3 style="margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
                            <?php if ($category === 'Dossiers'): ?>
                                <i class="fas fa-folder" style="color: #27ae60;"></i>
                            <?php elseif ($category === 'Utilisateurs'): ?>
                                <i class="fas fa-users" style="color: #3498db;"></i>
                            <?php else: ?>
                                <i class="fas fa-tags" style="color: #f39c12;"></i>
                            <?php endif; ?>
                            <?= $category ?> (<?= count($items) ?>)
                        </h3>
                    </div>
                    <div class="section-content" style="padding: 0;">
                        <?php foreach ($items as $item): ?>
                            <div class="result-item" style="padding: 1.5rem; border-bottom: 1px solid #f0f4f8; transition: background 0.2s; cursor: pointer;" 
                                 onmouseover="this.style.background='#f8fafc'" 
                                 onmouseout="this.style.background='white'"
                                 onclick="<?php
                                    if ($category === 'Dossiers') {
                                        echo "window.location.href='" . BASE_URL . "modules/dossiers/view.php?id=" . $item['id'] . "'";
                                    } elseif ($category === 'Utilisateurs') {
                                        echo "window.location.href='" . BASE_URL . "modules/users/profile.php?id=" . $item['id'] . "'";
                                    } else {
                                        echo "window.location.href='" . BASE_URL . "modules/dossiers/list.php?category=" . $item['id'] . "'";
                                    }
                                 ?>">
                                
                                <?php if ($category === 'Dossiers'): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 8px 0; color: #2c3e50;">
                                                <i class="fas fa-folder" style="color: #27ae60; margin-right: 8px;"></i>
                                                <?= htmlspecialchars($item['titre']) ?>
                                            </h4>
                                            <p style="margin: 0 0 8px 0; color: #7f8c8d;">
                                                <strong>Dossier #<?= htmlspecialchars($item['numero_dossier']) ?></strong>
                                            </p>
                                            <?php if ($item['description']): ?>
                                                <p style="margin: 0; color: #5a6c7d; line-height: 1.5;">
                                                    <?= htmlspecialchars(substr($item['description'], 0, 120)) ?>
                                                    <?= strlen($item['description']) > 120 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div style="text-align: right; margin-left: 1rem;">
                                            <span class="status-badge" style="background: <?= getStatusColor($item['status']) ?>; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 500;">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                            <p style="margin: 8px 0 0 0; color: #95a5a6; font-size: 0.9em;">
                                                <?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                
                                <?php elseif ($category === 'Utilisateurs'): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3498db, #2980b9); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.2rem;">
                                            <?= strtoupper(substr($item['prenom'], 0, 1) . substr($item['nom'], 0, 1)) ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 4px 0; color: #2c3e50;">
                                                <?= htmlspecialchars($item['prenom'] . ' ' . $item['nom']) ?>
                                            </h4>
                                            <p style="margin: 0 0 4px 0; color: #7f8c8d;">
                                                <i class="fas fa-envelope" style="margin-right: 6px;"></i>
                                                <?= htmlspecialchars($item['email']) ?>
                                            </p>
                                            <span style="background: #e8f4fd; color: #2980b9; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 500;">
                                                <?= getRoleName($item['role']) ?>
                                            </span>
                                        </div>
                                    </div>
                                
                                <?php else: ?>
                                    <div>
                                        <h4 style="margin: 0 0 8px 0; color: #2c3e50;">
                                            <i class="fas fa-tag" style="color: #f39c12; margin-right: 8px;"></i>
                                            <?= htmlspecialchars($item['nom']) ?>
                                        </h4>
                                        <?php if ($item['description']): ?>
                                            <p style="margin: 0; color: #7f8c8d;">
                                                <?= htmlspecialchars($item['description']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        
        <?php else: ?>
            <div class="no-results" style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
                <i class="fas fa-search" style="font-size: 4rem; color: #bdc3c7; margin-bottom: 1rem;"></i>
                <h3 style="color: #7f8c8d; margin-bottom: 1rem;">Aucun résultat trouvé</h3>
                <p style="color: #95a5a6; margin-bottom: 2rem;">
                    Essayez avec des termes différents ou vérifiez l'orthographe.
                </p>
                <a href="<?= BASE_URL ?>modules/search/global.php" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: transform 0.2s;"
                   onmouseover="this.style.transform='translateY(-2px)'"
                   onmouseout="this.style.transform='translateY(0)'">
                    <i class="fas fa-refresh"></i> Nouvelle recherche
                </a>
            </div>
        <?php endif; ?>
    
    <?php elseif ($query && strlen($query) < 2): ?>
        <div class="search-hint" style="text-align: center; padding: 2rem; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 12px; color: #856404;">
            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
            Veuillez saisir au moins 2 caractères pour effectuer une recherche.
        </div>
    <?php endif; ?>
</div>

<?php
function getStatusColor($status) {
    switch ($status) {
        case 'nouveau': return '#3498db';
        case 'en_cours': return '#f39c12';
        case 'en_attente': return '#e67e22';
        case 'valide': return '#27ae60';
        case 'archive': return '#95a5a6';
        default: return '#7f8c8d';
    }
}

require_once '../../includes/footer.php';
?>
