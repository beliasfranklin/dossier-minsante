<?php
// Intégration avec le système RH
function syncRHData() {
    $apiUrl = "https://rh.minsante.cm/api/employees";
    $apiKey = defined('RH_API_KEY') ? RH_API_KEY : '';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['employees'])) {
        foreach ($data['employees'] as $emp) {
            // Mise à jour des utilisateurs
            executeQuery(
                "INSERT INTO users (external_id, name, email, department) 
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE 
                 name = VALUES(name), department = VALUES(department)",
                [$emp['id'], $emp['name'], $emp['email'], $emp['department']]
            );
        }
        return true;
    }
    
    return false;
}

// Intégration avec le système financier
function syncFinancialData($dossierId) {
    $dossier = fetchOne("SELECT * FROM dossiers WHERE id = ?", [$dossierId]);
    if (!$dossier) return false;
    
    $data = [
        'reference' => $dossier['reference'],
        'amount' => $dossier['budget'] ?? 0,
        'department' => $dossier['service']
    ];
    
    $ch = curl_init("https://finance.minsante.cm/api/projects");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . FINANCE_API_KEY
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}