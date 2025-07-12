<?php
/**
 * Module de signature électronique pour les workflows
 * Gestion des signatures numériques, certificats et authentification forte
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/workflow_config.php';
requireAuth();

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'sign_document':
            $instanceId = (int)$_POST['instance_id'];
            $signatureData = $_POST['signature_data'] ?? '';
            $pin = $_POST['pin'] ?? '';
            $result = signWorkflowDocument($instanceId, $userId, $signatureData, $pin);
            echo json_encode($result);
            exit;
            
        case 'verify_signature':
            $signatureId = (int)$_POST['signature_id'];
            $result = verifySignature($signatureId);
            echo json_encode($result);
            exit;
            
        case 'generate_certificate':
            $result = generateUserCertificate($userId);
            echo json_encode($result);
            exit;
            
        case 'revoke_signature':
            $signatureId = (int)$_POST['signature_id'];
            $reason = cleanInput($_POST['reason'] ?? '');
            $result = revokeSignature($signatureId, $userId, $reason);
            echo json_encode($result);
            exit;
    }
}

/**
 * Signer un document de workflow
 */
function signWorkflowDocument($instanceId, $userId, $signatureData, $pin) {
    global $pdo;
    
    try {
        // Vérifier les permissions
        if (!canUserSignInstance($instanceId, $userId)) {
            return ['success' => false, 'message' => 'Permissions insuffisantes pour signer'];
        }
        
        // Vérifier le PIN utilisateur
        if (!verifyUserPin($userId, $pin)) {
            return ['success' => false, 'message' => 'PIN incorrect'];
        }
        
        // Récupérer l'instance
        $stmt = $pdo->prepare("
            SELECT wi.*, d.reference, d.titre 
            FROM workflow_instances wi
            JOIN dossiers d ON wi.dossier_id = d.id
            WHERE wi.id = ?
        ");
        $stmt->execute([$instanceId]);
        $instance = $stmt->fetch();
        
        if (!$instance) {
            return ['success' => false, 'message' => 'Instance introuvable'];
        }
        
        // Générer le hash de signature
        $documentData = generateDocumentHash($instance);
        $signatureHash = generateSignatureHash($userId, $documentData, $signatureData);
        
        // Enregistrer la signature
        $stmt = $pdo->prepare("
            INSERT INTO workflow_signatures 
            (workflow_instance_id, user_id, signature_hash, signature_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $instanceId,
            $userId,
            $signatureHash,
            $signatureData,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        if ($success) {
            // Approuver l'étape du workflow
            approveWorkflowStepWithSignature($instanceId, $userId);
            
            // Logger l'action
            logSignatureAction($instanceId, $userId, 'sign', 'Document signé électroniquement');
            
            return [
                'success' => true, 
                'message' => 'Document signé avec succès',
                'signature_id' => $pdo->lastInsertId()
            ];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la signature'];
        }
        
    } catch (Exception $e) {
        logError("Erreur signature: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système lors de la signature'];
    }
}

/**
 * Vérifier une signature
 */
function verifySignature($signatureId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT ws.*, wi.dossier_id, u.nom, u.email
            FROM workflow_signatures ws
            JOIN workflow_instances wi ON ws.workflow_instance_id = wi.id
            JOIN users u ON ws.user_id = u.id
            WHERE ws.id = ?
        ");
        $stmt->execute([$signatureId]);
        $signature = $stmt->fetch();
        
        if (!$signature) {
            return ['success' => false, 'message' => 'Signature introuvable'];
        }
        
        // Vérifier l'intégrité
        $isValid = verifySignatureIntegrity($signature);
        
        // Vérifier le certificat
        $certificateValid = verifyUserCertificate($signature['user_id']);
        
        // Vérifier la non-répudiation
        $timestampValid = verifyTimestamp($signature['signed_at']);
        
        $result = [
            'success' => true,
            'signature_id' => $signatureId,
            'signer' => $signature['nom'],
            'signer_email' => $signature['email'],
            'signed_at' => $signature['signed_at'],
            'is_valid' => $isValid,
            'certificate_valid' => $certificateValid,
            'timestamp_valid' => $timestampValid,
            'integrity_score' => calculateIntegrityScore($signature)
        ];
        
        // Logger la vérification
        logSignatureAction($signature['workflow_instance_id'], $_SESSION['user_id'], 'verify', 
                          'Vérification de signature #' . $signatureId);
        
        return $result;
        
    } catch (Exception $e) {
        logError("Erreur vérification signature: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la vérification'];
    }
}

/**
 * Générer un certificat utilisateur
 */
function generateUserCertificate($userId) {
    global $pdo;
    
    try {
        // Vérifier si l'utilisateur a déjà un certificat valide
        $stmt = $pdo->prepare("
            SELECT * FROM user_certificates 
            WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Certificat valide déjà existant'];
        }
        
        // Récupérer les informations utilisateur
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur introuvable'];
        }
        
        // Générer le certificat
        $certificateData = generateX509Certificate($user);
        $privateKey = $certificateData['private_key'];
        $publicKey = $certificateData['public_key'];
        $certificate = $certificateData['certificate'];
        
        // Enregistrer le certificat
        $stmt = $pdo->prepare("
            INSERT INTO user_certificates 
            (user_id, certificate_data, public_key, private_key_hash, serial_number, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 YEAR))
        ");
        
        $success = $stmt->execute([
            $userId,
            $certificate,
            $publicKey,
            hash('sha256', $privateKey),
            generateSerialNumber(),
        ]);
        
        if ($success) {
            return [
                'success' => true,
                'message' => 'Certificat généré avec succès',
                'certificate_id' => $pdo->lastInsertId(),
                'expires_at' => date('Y-m-d', strtotime('+2 years'))
            ];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la génération'];
        }
        
    } catch (Exception $e) {
        logError("Erreur génération certificat: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système'];
    }
}

/**
 * Révoquer une signature
 */
function revokeSignature($signatureId, $userId, $reason) {
    global $pdo;
    
    try {
        // Vérifier les permissions (admin ou propriétaire)
        if (!isAdmin() && !isSignatureOwner($signatureId, $userId)) {
            return ['success' => false, 'message' => 'Permissions insuffisantes'];
        }
        
        // Marquer la signature comme révoquée
        $stmt = $pdo->prepare("
            UPDATE workflow_signatures 
            SET is_revoked = 1, revoked_by = ?, revoked_at = NOW(), revocation_reason = ?
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$userId, $reason, $signatureId]);
        
        if ($success) {
            // Logger la révocation
            logSignatureAction($signatureId, $userId, 'revoke', $reason);
            
            return ['success' => true, 'message' => 'Signature révoquée'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la révocation'];
        }
        
    } catch (Exception $e) {
        logError("Erreur révocation signature: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur système'];
    }
}

/**
 * Vérifier si un utilisateur peut signer une instance
 */
function canUserSignInstance($instanceId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT wi.*, u.role 
        FROM workflow_instances wi
        JOIN users u ON u.id = ?
        WHERE wi.id = ? AND wi.status = 'active'
        AND (wi.role_requis = u.role OR wi.delegated_to = ?)
    ");
    $stmt->execute([$userId, $instanceId, $userId]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Vérifier le PIN utilisateur
 */
function verifyUserPin($userId, $pin) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $pinHash = $stmt->fetchColumn();
    
    return $pinHash && password_verify($pin, $pinHash);
}

/**
 * Générer le hash du document
 */
function generateDocumentHash($instance) {
    $data = json_encode([
        'dossier_id' => $instance['dossier_id'],
        'workflow_step_id' => $instance['workflow_step_id'],
        'reference' => $instance['reference'],
        'titre' => $instance['titre'],
        'timestamp' => time()
    ]);
    
    return hash('sha256', $data);
}

/**
 * Générer le hash de signature
 */
function generateSignatureHash($userId, $documentHash, $signatureData) {
    $data = $userId . '|' . $documentHash . '|' . $signatureData . '|' . time();
    return hash('sha256', $data);
}

/**
 * Approuver une étape avec signature
 */
function approveWorkflowStepWithSignature($instanceId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE workflow_instances 
        SET status = 'approved', 
            approved_by = ?, 
            approved_at = NOW(),
            comments = 'Approuvé avec signature électronique'
        WHERE id = ?
    ");
    
    return $stmt->execute([$userId, $instanceId]);
}

/**
 * Vérifier l'intégrité d'une signature
 */
function verifySignatureIntegrity($signature) {
    // Recalculer le hash et comparer
    $originalData = reconstructSignatureData($signature);
    $calculatedHash = hash('sha256', $originalData);
    
    return hash_equals($signature['signature_hash'], $calculatedHash);
}

/**
 * Vérifier le certificat utilisateur
 */
function verifyUserCertificate($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_certificates 
        WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
        AND is_revoked = 0
    ");
    $stmt->execute([$userId]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Vérifier le timestamp
 */
function verifyTimestamp($timestamp) {
    $signedTime = strtotime($timestamp);
    $currentTime = time();
    
    // Vérifier que la signature n'est pas trop ancienne (30 jours max)
    return ($currentTime - $signedTime) <= (30 * 24 * 3600);
}

/**
 * Calculer le score d'intégrité
 */
function calculateIntegrityScore($signature) {
    $score = 100;
    
    // Déduire des points selon différents critères
    if (!verifySignatureIntegrity($signature)) $score -= 50;
    if (!verifyUserCertificate($signature['user_id'])) $score -= 30;
    if (!verifyTimestamp($signature['signed_at'])) $score -= 20;
    
    return max(0, $score);
}

/**
 * Générer un certificat X.509
 */
function generateX509Certificate($user) {
    // Configuration du certificat
    $config = [
        "digest_alg" => "sha256",
        "private_key_bits" => 2048,
        "private_key_type" => OPENSSL_KEYTYPE_RSA,
    ];
    
    // Créer une paire de clés
    $privateKey = openssl_pkey_new($config);
    $publicKey = openssl_pkey_get_details($privateKey)['key'];
    
    // Informations du certificat
    $dn = [
        "countryName" => "CM",
        "stateOrProvinceName" => "Centre",
        "localityName" => "Yaounde",
        "organizationName" => "MINSANTE",
        "organizationalUnitName" => "Gestion Dossiers",
        "commonName" => $user['nom'],
        "emailAddress" => $user['email']
    ];
    
    // Créer le certificat
    $csr = openssl_csr_new($dn, $privateKey, $config);
    $cert = openssl_csr_sign($csr, null, $privateKey, 730, $config); // 2 ans
    
    // Exporter
    openssl_x509_export($cert, $certOut);
    openssl_pkey_export($privateKey, $privateKeyOut);
    
    return [
        'certificate' => $certOut,
        'public_key' => $publicKey,
        'private_key' => $privateKeyOut
    ];
}

/**
 * Générer un numéro de série
 */
function generateSerialNumber() {
    return strtoupper(bin2hex(random_bytes(8)));
}

/**
 * Vérifier si l'utilisateur est propriétaire de la signature
 */
function isSignatureOwner($signatureId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT user_id FROM workflow_signatures WHERE id = ?");
    $stmt->execute([$signatureId]);
    $ownerId = $stmt->fetchColumn();
    
    return $ownerId == $userId;
}

/**
 * Reconstruire les données de signature
 */
function reconstructSignatureData($signature) {
    return $signature['user_id'] . '|' . $signature['document_hash'] . '|' . 
           $signature['signature_data'] . '|' . strtotime($signature['signed_at']);
}

/**
 * Logger une action de signature
 */
function logSignatureAction($instanceId, $userId, $action, $details) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO signature_audit_log 
        (workflow_instance_id, user_id, action, details, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $instanceId,
        $userId,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

// Interface utilisateur
$currentUser = getCurrentUser();
$instanceId = (int)($_GET['instance_id'] ?? 0);

if ($instanceId) {
    $stmt = $pdo->prepare("
        SELECT wi.*, d.reference, d.titre, w.nom as step_name
        FROM workflow_instances wi
        JOIN dossiers d ON wi.dossier_id = d.id
        JOIN workflows w ON wi.workflow_step_id = w.id
        WHERE wi.id = ?
    ");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signature Électronique - MINSANTE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .signature-pad {
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .certificate-info {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 10px 0;
        }
        .signature-validity {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .valid { color: #28a745; }
        .invalid { color: #dc3545; }
        .pending { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-signature"></i> Signature Électronique</h2>
                    <a href="../workflow/automatic.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au Workflow
                    </a>
                </div>

                <?php if ($instance): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-file-contract"></i> Document à Signer</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Dossier:</strong> <?= htmlspecialchars($instance['reference']) ?></p>
                                <p><strong>Titre:</strong> <?= htmlspecialchars($instance['titre']) ?></p>
                                <p><strong>Étape:</strong> <?= htmlspecialchars($instance['step_name']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Statut:</strong> 
                                    <span class="badge bg-<?= $instance['status'] === 'active' ? 'warning' : 'info' ?>">
                                        <?= ucfirst($instance['status']) ?>
                                    </span>
                                </p>
                                <p><strong>Date de début:</strong> <?= $instance['started_at'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (canUserSignInstance($instanceId, $_SESSION['user_id'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-pen-fancy"></i> Zone de Signature</h5>
                    </div>
                    <div class="card-body">
                        <form id="signatureForm">
                            <input type="hidden" name="action" value="sign_document">
                            <input type="hidden" name="instance_id" value="<?= $instanceId ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Signature Manuscrite</label>
                                <canvas id="signaturePad" class="signature-pad" width="600" height="200"></canvas>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSignature()">
                                        <i class="fas fa-eraser"></i> Effacer
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="pin" class="form-label">PIN de Sécurité</label>
                                <input type="password" class="form-control" id="pin" name="pin" required 
                                       placeholder="Entrez votre PIN à 4 chiffres" maxlength="4" pattern="[0-9]{4}">
                                <div class="form-text">Votre PIN personnel pour authentifier la signature</div>
                            </div>

                            <div class="certificate-info">
                                <h6><i class="fas fa-certificate"></i> Informations du Certificat</h6>
                                <p><strong>Signataire:</strong> <?= htmlspecialchars($currentUser['nom']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($currentUser['email']) ?></p>
                                <p><strong>Rôle:</strong> <?= getRoleName($currentUser['role']) ?></p>
                                <div class="signature-validity">
                                    <i class="fas fa-shield-alt valid"></i>
                                    <span>Certificat valide et actif</span>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-signature"></i> Signer le Document
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Vous n'avez pas les permissions nécessaires pour signer cette étape.
                </div>
                <?php endif; ?>

                <!-- Historique des signatures -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Historique des Signatures</h5>
                    </div>
                    <div class="card-body">
                        <div id="signaturesHistory">
                            <!-- Sera rempli par JavaScript -->
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Instance de workflow introuvable.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Canvas pour la signature
        const canvas = document.getElementById('signaturePad');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;

        // Configuration du canvas
        ctx.strokeStyle = '#2c3e50';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        // Événements de signature
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Support tactile
        canvas.addEventListener('touchstart', handleTouch);
        canvas.addEventListener('touchmove', handleTouch);
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            const rect = canvas.getBoundingClientRect();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function draw(e) {
            if (!isDrawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        function handleTouch(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                            e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }

        function clearSignature() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }

        // Soumission du formulaire
        document.getElementById('signatureForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Vérifier que la signature n'est pas vide
            const imageData = canvas.toDataURL();
            if (isCanvasEmpty()) {
                alert('Veuillez dessiner votre signature');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('signature_data', imageData);
            
            try {
                const response = await fetch('signature.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Document signé avec succès !');
                    window.location.href = '../workflow/automatic.php?dossier_id=' + <?= $instance['dossier_id'] ?? 0 ?>;
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            }
        });

        function isCanvasEmpty() {
            const blank = document.createElement('canvas');
            blank.width = canvas.width;
            blank.height = canvas.height;
            return canvas.toDataURL() === blank.toDataURL();
        }

        // Charger l'historique des signatures
        async function loadSignaturesHistory() {
            try {
                const response = await fetch(`signature.php?action=get_history&instance_id=<?= $instanceId ?>`);
                const signatures = await response.json();
                
                const container = document.getElementById('signaturesHistory');
                if (signatures.length === 0) {
                    container.innerHTML = '<p class="text-muted">Aucune signature pour cette étape</p>';
                    return;
                }
                
                let html = '<div class="table-responsive"><table class="table table-sm">';
                html += '<thead><tr><th>Signataire</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
                
                signatures.forEach(sig => {
                    html += `
                        <tr>
                            <td>${sig.signer_name}</td>
                            <td>${sig.signed_at}</td>
                            <td>
                                <span class="badge bg-${sig.is_valid ? 'success' : 'danger'}">
                                    ${sig.is_valid ? 'Valide' : 'Invalide'}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="verifySignature(${sig.id})">
                                    <i class="fas fa-check-circle"></i> Vérifier
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                container.innerHTML = html;
                
            } catch (error) {
                console.error('Erreur chargement historique:', error);
            }
        }

        async function verifySignature(signatureId) {
            try {
                const formData = new FormData();
                formData.append('action', 'verify_signature');
                formData.append('signature_id', signatureId);
                
                const response = await fetch('signature.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Signature vérifiée:\nValidité: ${result.is_valid ? 'Valide' : 'Invalide'}\nScore d'intégrité: ${result.integrity_score}%`);
                } else {
                    alert('Erreur lors de la vérification: ' + result.message);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }

        // Charger l'historique au chargement de la page
        if (<?= $instanceId ?>) {
            loadSignaturesHistory();
        }
    </script>
</body>
</html>
