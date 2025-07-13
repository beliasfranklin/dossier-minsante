<?php
require_once __DIR__ . '/../../includes/integrations.php';
// ...autres includes et initialisations...

// Synchronisation RH si demandé
if (isset($_POST['sync_rh'])) {
    $result = syncRHData();
    if ($result) {
        echo '<div class="alert alert-success">Synchronisation RH réussie !</div>';
    } else {
        echo '<div class="alert alert-danger">Échec de la synchronisation RH.</div>';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des utilisateurs</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" />
</head>
<body>
    <div class="container">
        <h1>Liste des utilisateurs</h1>
        <form method="post" style="margin-bottom:18px;">
            <button type="submit" name="sync_rh" class="btn btn-info"><i class="fa-solid fa-arrows-rotate"></i> Synchroniser RH</button>
        </form>
        <!-- Ici, affichez la liste des utilisateurs -->
        <?php
        // Exemple d'affichage (à adapter selon votre structure)
        $users = fetchAll("SELECT * FROM users ORDER BY name ASC");
        if ($users) {
            echo '<table class="table"><thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Département</th></tr></thead><tbody>';
            foreach ($users as $user) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($user['external_id'] ?? $user['id']) . '</td>';
                echo '<td>' . htmlspecialchars($user['name']) . '</td>';
                echo '<td>' . htmlspecialchars($user['email']) . '</td>';
                echo '<td>' . htmlspecialchars($user['department']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div class="alert alert-info">Aucun utilisateur trouvé.</div>';
        }
        ?>
    </div>
</body>
</html>
