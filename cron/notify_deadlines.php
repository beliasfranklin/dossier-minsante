<?php
// cron/notify_deadlines.php
// Script à exécuter quotidiennement pour notifier les responsables des dossiers proches de l'échéance

require_once __DIR__ . '/../includes/config.php';

$dueSoon = fetchAll("SELECT d.id, d.reference, u.id as user_id 
                    FROM dossiers d
                    JOIN users u ON d.responsable_id = u.id
                    WHERE d.status = 'en_cours'
                    AND d.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)");

foreach ($dueSoon as $dossier) {
    createNotification(
        $dossier['user_id'],
        "Échéance proche",
        "Le dossier ".$dossier['reference']." arrive à échéance bientôt",
        'dossiers',
        $dossier['id']
    );
}

echo "Notifications d'échéance envoyées : ".count($dueSoon)."\n";
