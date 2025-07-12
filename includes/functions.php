<?php
/**
 * Fichier de fonctions utilitaires pour l'application de gestion des dossiers
 */

/**
 * Génère un mot de passe sécurisé avec pepper
 * @param string $password Mot de passe en clair
 * @return string Mot de passe hashé
 */
function generateSecurePassword($password) {
    return password_hash($password . PEPPER, PASSWORD_BCRYPT);
}

/**
 * Valide un mot de passe
 * @param string $password Mot de passe à vérifier
 * @param string $hash Hash stocké en base
 * @return bool True si valide, false sinon
 */
function validatePassword($password, $hash) {
    return password_verify($password . PEPPER, $hash);
}

/**
 * Journalise une action dans l'historique
 * @param int $userId ID de l'utilisateur
 * @param string $actionType Type d'action
 * @param int|null $dossierId ID du dossier concerné (optionnel)
 * @param string|null $details Détails supplémentaires (optionnel)
 * @return bool Succès de l'opération
 */
function logAction($userId, $actionType, $dossierId = null, $details = null) {
    $sql = "INSERT INTO historiques (user_id, dossier_id, action_type, action_details) 
            VALUES (?, ?, ?, ?)";
    return executeQuery($sql, [$userId, $dossierId, $actionType, $details]);
}

/**
 * Récupère l'historique d'un dossier
 * @param int $dossierId ID du dossier
 * @return array Liste des actions
 */
function getDossierHistory($dossierId) {
    $sql = "SELECT h.*, u.name as user_name 
            FROM historiques h
            JOIN users u ON h.user_id = u.id
            WHERE h.dossier_id = ?
            ORDER BY h.created_at DESC";
    return fetchAll($sql, [$dossierId]);
}

/**
 * Change le statut d'un dossier et journalise l'action
 * @param int $dossierId ID du dossier
 * @param string $newStatus Nouveau statut
 * @param int $userId ID de l'utilisateur effectuant le changement
 * @param string|null $comment Commentaire (optionnel)
 * @return bool Succès de l'opération
 */
function changeDossierStatus($dossierId, $newStatus, $userId, $comment = null) {
    // Vérifier que le statut est valide
    $allowedStatuses = ['en_cours', 'valide', 'rejete', 'archive'];
    if (!in_array($newStatus, $allowedStatuses)) {
        return false;
    }

    // Mettre à jour le statut
    $sql = "UPDATE dossiers SET status = ? WHERE id = ?";
    $success = executeQuery($sql, [$newStatus, $dossierId]);

    if ($success) {
        // Journaliser le changement
        $details = "Changement de statut vers " . $newStatus;
        if ($comment) {
            $details .= ". Commentaire: " . $comment;
        }
        logAction($userId, 'status_change', $dossierId, $details);

        // Ajouter un commentaire si fourni
        if ($comment) {
            addDossierComment($dossierId, $userId, $comment);
        }

        // Notifier les parties prenantes
        notifyStatusChange($dossierId, $newStatus);
    }

    return $success;
}

/**
 * Ajoute un commentaire à un dossier
 * @param int $dossierId ID du dossier
 * @param int $userId ID de l'utilisateur
 * @param string $comment Commentaire
 * @return bool Succès de l'opération
 */
function addDossierComment($dossierId, $userId, $comment) {
    $sql = "INSERT INTO commentaires (dossier_id, user_id, contenu) 
            VALUES (?, ?, ?)";
    $success = executeQuery($sql, [$dossierId, $userId, $comment]);

    if ($success) {
        logAction($userId, 'comment_added', $dossierId, substr($comment, 0, 50) . "...");
    }

    return $success;
}

/**
 * Récupère les commentaires d'un dossier
 * @param int $dossierId ID du dossier
 * @return array Liste des commentaires
 */
function getDossierComments($dossierId) {
    $sql = "SELECT c.*, u.name as user_name 
            FROM commentaires c
            JOIN users u ON c.user_id = u.id
            WHERE c.dossier_id = ?
            ORDER BY c.created_at DESC";
    return fetchAll($sql, [$dossierId]);
}

/**
 * Notifie les utilisateurs concernés par un changement de statut
 * @param int $dossierId ID du dossier
 * @param string $newStatus Nouveau statut
 */
function notifyStatusChange($dossierId, $newStatus) {
    // Récupérer les informations du dossier
    $dossier = fetchOne("SELECT d.*, u.email as responsable_email 
                         FROM dossiers d
                         JOIN users u ON d.responsable_id = u.id
                         WHERE d.id = ?", [$dossierId]);

    if (!$dossier) return;

    // Préparer le message
    $subject = "Mise à jour du dossier " . $dossier['reference'];
    $message = "Le statut de votre dossier " . $dossier['reference'] . " a été changé à " . $newStatus;

    // Envoyer l'email (simplifié - à implémenter avec une librairie d'email)
    // mail($dossier['responsable_email'], $subject, $message);

    // TODO: Ajouter la notification WhatsApp si configurée
}

/**
 * Génère un numéro de référence unique pour un nouveau dossier
 * @param string $type Type de dossier
 * @return string Numéro de référence
 */
function generateDossierReference($type) {
    $prefix = strtoupper(substr($type, 0, 3)) . date('Ym');
    
    // Trouver le dernier numéro pour ce préfixe
    $lastRef = fetchOne("SELECT reference FROM dossiers 
                         WHERE reference LIKE ? 
                         ORDER BY id DESC LIMIT 1", ["$prefix%"]);

    if ($lastRef) {
        $lastNum = intval(substr($lastRef['reference'], -4));
        $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNum = '0001';
    }

    return $prefix . $newNum;
}

/**
 * Vérifie si un utilisateur a accès à un dossier
 * @param int $userId ID de l'utilisateur
 * @param int $dossierId ID du dossier
 * @return bool True si l'accès est autorisé
 */
function canAccessDossier($userId, $dossierId) {
    // Les admins ont accès à tout
    $user = fetchOne("SELECT role FROM users WHERE id = ?", [$userId]);
    if ($user && $user['role'] == ROLE_ADMIN) {
        return true;
    }

    // Vérifier si l'utilisateur est créateur ou responsable du dossier
    $dossier = fetchOne("SELECT created_by, responsable_id FROM dossiers WHERE id = ?", [$dossierId]);
    
    if ($dossier && ($dossier['created_by'] == $userId || $dossier['responsable_id'] == $userId)) {
        return true;
    }

    // Vérifier les permissions par service/département si nécessaire
    // (À implémenter selon les règles métiers spécifiques)

    return false;
}

/**
 * Récupère les pièces jointes d'un dossier
 * @param int $dossierId ID du dossier
 * @return array Liste des pièces jointes
 */
function getDossierAttachments($dossierId) {
    $sql = "SELECT p.*, u.name as uploaded_by_name 
            FROM pieces_jointes p
            JOIN users u ON p.uploaded_by = u.id
            WHERE p.dossier_id = ?
            ORDER BY p.uploaded_at DESC";
    return fetchAll($sql, [$dossierId]);
}

/**
 * Formatte une date pour l'affichage
 * @param string $date Date au format MySQL
 * @return string Date formatée
 */
function formatDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y H:i', strtotime($date));
}

/**
 * Sanitize les données avant affichage
 * @param string $data Donnée à nettoyer
 * @return string Donnée nettoyée
 */
function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Récupère les workflows disponibles pour un type de dossier
 * @param string $type Type de dossier
 * @return array Étapes du workflow
 */
function getWorkflowSteps($type) {
    $sql = "SELECT * FROM workflows 
            WHERE type_dossier = ? 
            ORDER BY ordre ASC";
    return fetchAll($sql, [$type]);
}

/**
 * Vérifie si une transition de statut est autorisée
 * @param string $currentStatus Statut actuel
 * @param string $newStatus Nouveau statut proposé
 * @param string $type Type de dossier
 * @return bool True si la transition est autorisée
 */
function isStatusTransitionAllowed($currentStatus, $newStatus, $type) {
    // Permettre l'archivage et le rejet depuis n'importe quel statut
    if (strtolower($newStatus) === 'archive' || strtolower($newStatus) === 'rejete') {
        return true;
    }

    // Permettre la réouverture d'un dossier rejeté
    if (strtolower($currentStatus) === 'rejete' && strtolower($newStatus) === 'en_cours') {
        return true;
    }

    // Récupérer les étapes du workflow
    $steps = getWorkflowSteps($type);
    
    // Si pas de workflow défini, autoriser les transitions
    if (empty($steps)) {
        return true;
    }

    $statusOrder = array_column($steps, 'etape');

    // Transition normale : vers l'étape suivante uniquement
    $currentPos = array_search(strtolower($currentStatus), array_map('strtolower', $statusOrder));
    $newPos = array_search(strtolower($newStatus), array_map('strtolower', $statusOrder));

    return ($currentPos !== false && $newPos !== false && $newPos === $currentPos + 1);
}

/**
 * Récupère les dossiers selon des critères de recherche
 * @param array $filters Critères de recherche
 * @return array Dossiers correspondants
 */
function searchDossiers($filters = []) {
    $baseQuery = "SELECT d.*, u1.name as created_by_name, u2.name as responsable_name 
                 FROM dossiers d
                 LEFT JOIN users u1 ON d.created_by = u1.id
                 LEFT JOIN users u2 ON d.responsable_id = u2.id";
    
    $where = [];
    $params = [];
    
    // Construction dynamique des clauses WHERE
    if (!empty($filters['status'])) {
        $where[] = "d.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['type'])) {
        $where[] = "d.type = ?";
        $params[] = $filters['type'];
    }
    
    // [...] Ajoutez d'autres filtres de la même manière
    
    // Assemblage final
    $sql = $baseQuery;
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    // Tri
    $orderBy = 'd.created_at DESC';
    if (!empty($filters['sort'])) {
        $allowedSorts = ['reference', 'titre', 'created_at', 'status'];
        if (in_array($filters['sort'], $allowedSorts)) {
            $direction = (!empty($filters['order']) && strtoupper($filters['order']) === 'ASC') ? 'ASC' : 'DESC';
            $orderBy = 'd.' . $filters['sort'] . ' ' . $direction;
        }
    }
    $sql .= " ORDER BY " . $orderBy;
    
    // Pagination - Conversion en entiers
    if (!empty($filters['limit'])) {
        $limit = (int)$filters['limit'];
        $sql .= " LIMIT $limit";
        // $params[] = $limit; // On ne passe plus en paramètre
        if (!empty($filters['offset'])) {
            $offset = (int)$filters['offset'];
            $sql .= " OFFSET $offset";
            // $params[] = $offset; // On ne passe plus en paramètre
        }
    }
    
    return fetchAll($sql, $params);
}

/**
 * Vérifie si un email existe déjà en base
 */
function emailExists($email) {
    $user = fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    return $user !== false;
}

/**
 * Nettoie les données d'entrée
 */
function cleanInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Formate une action pour l'affichage
 */
function formatAction($type, $details) {
    $actions = [
        'dossier_created' => 'a créé le dossier',
        'dossier_updated' => 'a modifié le dossier',
        'status_change' => 'a changé le statut: ' . $details,
        'comment_added' => 'a ajouté un commentaire',
        'chargement du fichier' => $details,
        'suppression du fichier' => $details
    ];
    // Si détails non vide, l'afficher pour les actions inconnues
    if (isset($actions[$type])) {
        return $actions[$type];
    } elseif (!empty($details)) {
        return $details;
    } else {
        return $type;
    }
}

/**
 * Obtient le nom d'un rôle
 */
function getRoleName($roleId) {
    $roles = [
        ROLE_ADMIN => 'Administrateur',
        ROLE_GESTIONNAIRE => 'Gestionnaire',
        ROLE_CONSULTANT => 'Consultant'
    ];
    return $roles[$roleId] ?? 'Inconnu';
}

// Dans functions.php
function logDebug($message, $data = null) {
    if (DEBUG_MODE) {
        $logEntry = date('[Y-m-d H:i:s]') . " - $message";
        if ($data) {
            $logEntry .= "\n" . print_r($data, true);
        }
        file_put_contents(__DIR__ . '/../logs/debug.log', $logEntry . "\n", FILE_APPEND);
    }
}

/**
 * Gère l'upload des fichiers joints
 */
function handleFileUploads($dossierId, $files) {
    $uploadDir = __DIR__ . "/../../uploads/dossiers/$dossierId/";
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $filename = basename($files['name'][$i]);
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
            executeQuery(
                "INSERT INTO pieces_jointes 
                 (dossier_id, nom_fichier, chemin, uploaded_by) 
                 VALUES (?, ?, ?, ?)",
                [$dossierId, $filename, $targetPath, $_SESSION['user_id']]
            );
        }
    }
}
/**
 * Formate la taille des fichiers
 */
function formatFileSize($bytes) {
    $units = ['o', 'Ko', 'Mo', 'Go'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Suppression de la redéfinition en double de la fonction hasPermission (doublon)

// Fonction utilitaire pour les balises <option>
if (!function_exists('selected')) {
    function selected($value, $selectedValue) {
        return ($value == $selectedValue) ? 'selected' : '';
    }
}

/**
 * Affiche le temps écoulé depuis une date donnée
 * @param string $datetime Date/heure au format MySQL
 * @param bool $full Affichage complet ou abrégé
 * @return string Temps écoulé in français
 */
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'w' => 'sem',
        'd' => 'j',
        'h' => 'h',
        'i' => 'min',
        's' => 's',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'il y a ' . implode(', ', $string) : 'à l’instant';
}