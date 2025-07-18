<?php
require_once __DIR__ . '/../../includes/config.php';
requireAuth();

// Récupérer les workflows définis
$workflows = fetchAll("SELECT * FROM workflows ORDER BY type_dossier, ordre");

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_step'])) {
        $type = cleanInput($_POST['type_dossier']);
        $etape = cleanInput($_POST['etape']);
        $roleRequis = (int)$_POST['role_requis'];
        $ordre = (int)$_POST['ordre'];
        
        executeQuery(
            "INSERT INTO workflows (type_dossier, etape, role_requis, ordre) 
             VALUES (?, ?, ?, ?)",
            [$type, $etape, $roleRequis, $ordre]
        );
        $_SESSION['flash']['success'] = "Étape ajoutée avec succès";
        header("Refresh:0");
        exit();
    }
    
    if (isset($_POST['update_step'])) {
        // Même logique que pour l'ajout
    }
    
    if (isset($_POST['delete_step'])) {
        $id = (int)$_POST['step_id'];
        executeQuery("DELETE FROM workflows WHERE id = ?", [$id]);
        $_SESSION['flash']['success'] = "Étape supprimée";
        header("Refresh:0");
        exit();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Workflows - <?= t('app_name') ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />
    <style>
        /* Variables CSS pour une cohérence visuelle */
        :root {
            --primary-color: #667eea;
            --primary-light: #764ba2;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-gray: #f8fafc;
            --border-color: #e2e8f0;
            --text-color: #1f2937;
            --text-muted: #6b7280;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-hover: 0 8px 32px rgba(0,0,0,0.12);
            --shadow-active: 0 12px 40px rgba(0,0,0,0.16);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
            --glassmorphism: rgba(255, 255, 255, 0.25);
            --glassmorphism-border: rgba(255, 255, 255, 0.18);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Overlay pour effet glassmorphism */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
        }

        /* Header de page moderne */
        .content-header {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            color: white;
            padding: 3rem 2.5rem;
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            gap: 2rem;
            box-shadow: var(--shadow-hover);
            position: relative;
            overflow: hidden;
        }

        .content-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            opacity: 0.5;
            z-index: -1;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            transition: var(--transition);
        }

        .header-icon:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 12px 48px rgba(0,0,0,0.15);
        }

        .header-icon i {
            font-size: 2rem;
            color: white;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .header-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .header-text p {
            font-size: 1.1rem;
            margin: 0;
            color: rgba(255,255,255,0.9);
            font-weight: 500;
        }

        /* Cartes avec glassmorphism */
        .workflow-card {
            background: var(--glassmorphism);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glassmorphism-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-hover);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: var(--transition);
        }

        .workflow-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-active);
        }

        .card-header {
            background: rgba(255,255,255,0.1);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            color: rgba(255,255,255,0.9);
            font-size: 1.1rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Formulaire moderne */
        .workflow-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: white;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: var(--radius);
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 1rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .form-input::placeholder {
            color: rgba(255,255,255,0.7);
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: rgba(255,255,255,0.5);
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 3px rgba(255,255,255,0.1);
        }

        .form-select-wrapper {
            position: relative;
        }

        .select-arrow {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.7);
            pointer-events: none;
            transition: var(--transition);
        }

        .form-select-wrapper:hover .select-arrow {
            color: rgba(255,255,255,0.9);
        }

        .form-select option {
            background: #1f2937;
            color: white;
            padding: 0.5rem;
        }

        /* Boutons modernisés */
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: var(--transition);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(16,185,129,0.8), rgba(5,150,105,0.8));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgba(5,150,105,0.9), rgba(4,120,87,0.9));
        }

        .btn-danger {
            background: linear-gradient(135deg, rgba(239,68,68,0.8), rgba(220,38,38,0.8));
            color: white;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, rgba(220,38,38,0.9), rgba(185,28,28,0.9));
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Tableau moderne */
        .table-container {
            background: rgba(255,255,255,0.95);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }

        .modern-table th {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-color);
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modern-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            color: var(--text-color);
        }

        .table-row-hover {
            transition: var(--transition);
        }

        .table-row-hover:hover {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            transform: translateY(-1px);
        }

        /* Badges modernisés */
        .type-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .type-etude {
            background: linear-gradient(135deg, rgba(59,130,246,0.8), rgba(37,99,235,0.8));
            color: white;
        }

        .type-projet {
            background: linear-gradient(135deg, rgba(16,185,129,0.8), rgba(5,150,105,0.8));
            color: white;
        }

        .type-administratif {
            background: linear-gradient(135deg, rgba(245,158,11,0.8), rgba(217,119,6,0.8));
            color: white;
        }

        .role-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            background: linear-gradient(135deg, rgba(124,58,237,0.8), rgba(109,40,217,0.8));
            color: white;
            display: inline-block;
        }

        .order-badge {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Animations */
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

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        .animate-fade-in:nth-child(1) { animation-delay: 0.1s; }
        .animate-fade-in:nth-child(2) { animation-delay: 0.2s; }
        .animate-fade-in:nth-child(3) { animation-delay: 0.3s; }

        /* États d'alerte */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            border: 1px solid transparent;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.1));
            border-color: rgba(16,185,129,0.2);
            color: #059669;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239,68,68,0.1), rgba(220,38,38,0.1));
            border-color: rgba(239,68,68,0.2);
            color: #dc2626;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .content-header {
                flex-direction: column;
                text-align: center;
                padding: 2rem 1.5rem;
            }

            .header-text h1 {
                font-size: 2rem;
            }

            .workflow-form {
                flex-direction: column;
                gap: 1rem;
            }

            .form-group {
                min-width: 100%;
            }

            .card-body {
                padding: 1.5rem;
            }

            .modern-table th,
            .modern-table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .header-icon {
                width: 60px;
                height: 60px;
            }

            .header-icon i {
                font-size: 1.5rem;
            }

            .header-text h1 {
                font-size: 1.5rem;
            }

            .btn {
                padding: 0.75rem 1rem;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <?php if (isset($_SESSION['flash']['success'])): ?>
        <div class="alert alert-success animate-fade-in">
            <i class="fas fa-check-circle"></i>
            <?= $_SESSION['flash']['success']; unset($_SESSION['flash']['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash']['error'])): ?>
        <div class="alert alert-danger animate-fade-in">
            <i class="fas fa-exclamation-circle"></i>
            <?= $_SESSION['flash']['error']; unset($_SESSION['flash']['error']); ?>
        </div>
    <?php endif; ?>

    <div class="content-header animate-fade-in">
        <div class="header-icon">
            <i class="fas fa-project-diagram"></i>
        </div>
        <div class="header-text">
            <h1>Gestion des Workflows</h1>
            <p>Configuration des processus et étapes de validation pour optimiser vos flux de travail</p>
        </div>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="workflow-card animate-fade-in">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Ajouter une nouvelle étape</h3>
        </div>
        <div class="card-body">
            <form method="post" class="workflow-form">
                <div class="form-group">
                    <label class="form-label">Type de dossier</label>
                    <div class="form-select-wrapper">
                        <select name="type_dossier" class="form-select" required>
                            <option value="">Sélectionner un type</option>
                            <option value="Etude">📊 Étude</option>
                            <option value="Projet">🚀 Projet</option>
                            <option value="Administratif">📋 Administratif</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nom de l'étape</label>
                    <input type="text" name="etape" class="form-input" placeholder="Ex: Validation technique, Révision..." required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Rôle requis</label>
                    <div class="form-select-wrapper">
                        <select name="role_requis" class="form-select" required>
                            <option value="">Sélectionner un rôle</option>
                            <option value="<?= ROLE_ADMIN ?>">👑 Administrateur</option>
                            <option value="<?= ROLE_GESTIONNAIRE ?>">👤 Gestionnaire</option>
                            <option value="<?= ROLE_CONSULTANT ?>">🧑‍💼 Consultant</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ordre d'exécution</label>
                    <input type="number" name="ordre" class="form-input" min="1" max="99" placeholder="1" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="add_step" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter l'étape
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des workflows -->
    <div class="workflow-card animate-fade-in">
        <div class="card-header">
            <h3><i class="fas fa-list-ul"></i> Workflows configurés (<?= count($workflows) ?> étapes)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($workflows)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <h3>Aucun workflow configuré</h3>
                    <p>Commencez par ajouter votre première étape de workflow en utilisant le formulaire ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-tag"></i> Type</th>
                                <th><i class="fas fa-tasks"></i> Étape</th>
                                <th><i class="fas fa-user-shield"></i> Rôle requis</th>
                                <th><i class="fas fa-sort-numeric-up"></i> Ordre</th>
                                <th><i class="fas fa-cogs"></i> Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workflows as $index => $wf): ?>
                            <tr class="table-row-hover" style="animation-delay: <?= $index * 0.1 ?>s;">
                                <td>
                                    <span class="type-badge type-<?= strtolower($wf['type_dossier']) ?>">
                                        <?php
                                        $icons = [
                                            'Etude' => '📊',
                                            'Projet' => '🚀',
                                            'Administratif' => '📋'
                                        ];
                                        echo $icons[$wf['type_dossier']] ?? '📄';
                                        ?>
                                        <?= htmlspecialchars($wf['type_dossier']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($wf['etape']) ?></strong>
                                </td>
                                <td>
                                    <span class="role-badge">
                                        <?php
                                        $roleIcons = [
                                            ROLE_ADMIN => '👑',
                                            ROLE_GESTIONNAIRE => '👤',
                                            ROLE_CONSULTANT => '🧑‍💼'
                                        ];
                                        echo $roleIcons[$wf['role_requis']] ?? '👤';
                                        ?>
                                        <?= getRoleName($wf['role_requis']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="order-badge"><?= $wf['ordre'] ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="editWorkflow(<?= $wf['id'] ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirmDelete('<?= htmlspecialchars($wf['etape']) ?>')">
                                            <input type="hidden" name="step_id" value="<?= $wf['id'] ?>">
                                            <button type="submit" name="delete_step" class="btn btn-sm btn-danger" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Aperçu des flux de travail -->
    <div class="workflow-card animate-fade-in">
        <div class="card-header">
            <h3><i class="fas fa-eye"></i> Aperçu des flux par type</h3>
        </div>
        <div class="card-body">
            <div class="workflow-preview">
                <?php
                $workflowsByType = [];
                foreach ($workflows as $wf) {
                    $workflowsByType[$wf['type_dossier']][] = $wf;
                }
                ?>
                <?php foreach ($workflowsByType as $type => $steps): ?>
                    <div class="workflow-flow">
                        <h4 class="flow-title">
                            <span class="type-badge type-<?= strtolower($type) ?>">
                                <?php
                                $icons = ['Etude' => '📊', 'Projet' => '🚀', 'Administratif' => '📋'];
                                echo $icons[$type] ?? '📄';
                                ?>
                                <?= htmlspecialchars($type) ?>
                            </span>
                            <span class="flow-count"><?= count($steps) ?> étapes</span>
                        </h4>
                        <div class="flow-steps">
                            <?php foreach ($steps as $index => $step): ?>
                                <div class="flow-step">
                                    <div class="step-number"><?= $step['ordre'] ?></div>
                                    <div class="step-content">
                                        <div class="step-name"><?= htmlspecialchars($step['etape']) ?></div>
                                        <div class="step-role"><?= getRoleName($step['role_requis']) ?></div>
                                    </div>
                                </div>
                                <?php if ($index < count($steps) - 1): ?>
                                    <div class="flow-arrow">
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Animation d'apparition au chargement
document.addEventListener('DOMContentLoaded', function() {
    const elements = document.querySelectorAll('.animate-fade-in');
    elements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 200);
    });
});

// Confirmation de suppression personnalisée
function confirmDelete(stepName) {
    return confirm(`Êtes-vous sûr de vouloir supprimer l'étape "${stepName}" ?\n\nCette action est irréversible.`);
}

// Fonction pour éditer un workflow (à implémenter)
function editWorkflow(id) {
    // TODO: Implémenter l'édition
    alert('Fonctionnalité d\'édition à implémenter');
}

// Animations au scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.workflow-card').forEach(card => {
    observer.observe(card);
});
</script>

<style>
/* Styles additionnels pour les nouvelles fonctionnalités */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}

.empty-state h3 {
    color: var(--text-color);
    margin-bottom: 0.5rem;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.d-inline {
    display: inline-block;
}

.btn-info {
    background: linear-gradient(135deg, rgba(59,130,246,0.8), rgba(37,99,235,0.8));
    color: white;
}

.btn-info:hover {
    background: linear-gradient(135deg, rgba(37,99,235,0.9), rgba(29,78,216,0.9));
}

/* Aperçu des flux */
.workflow-preview {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.workflow-flow {
    background: rgba(255,255,255,0.05);
    border-radius: var(--radius);
    padding: 1.5rem;
    border: 1px solid rgba(255,255,255,0.1);
}

.flow-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    color: white;
}

.flow-count {
    background: rgba(255,255,255,0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.flow-steps {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.flow-step {
    background: rgba(255,255,255,0.1);
    border-radius: var(--radius);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 200px;
    transition: var(--transition);
}

.flow-step:hover {
    background: rgba(255,255,255,0.15);
    transform: translateY(-2px);
}

.step-number {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.step-content {
    flex: 1;
}

.step-name {
    font-weight: 600;
    color: white;
    margin-bottom: 0.25rem;
}

.step-role {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.7);
}

.flow-arrow {
    color: rgba(255,255,255,0.5);
    font-size: 1.2rem;
    margin: 0 0.5rem;
}

@media (max-width: 768px) {
    .flow-steps {
        flex-direction: column;
        align-items: stretch;
    }
    
    .flow-arrow {
        transform: rotate(90deg);
        margin: 0.5rem 0;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>