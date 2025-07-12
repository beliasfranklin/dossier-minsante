<?php
require_once 'includes/config.php';
$token = $_GET['token'] ?? '';
$user = fetchOne("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token]);
if (!$user) {
    exit('Lien invalide ou expiré.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 6) {
        $msg = t('password_min_length_error');
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        executeQuery("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?", [$hash, $user['id']]);
        $msg = t('password_reset_success');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= t('reset_password_title') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    body { background: linear-gradient(135deg,#3498db,#2980b9); min-height: 100vh; }
    .reset-container {
        max-width: 400px;
        margin: 60px auto;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 6px 32px rgba(41,128,185,0.13);
        padding: 36px 32px 28px 32px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .reset-container h2 {
        color: #2980b9;
        font-size: 1.6em;
        margin-bottom: 8px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-align: center;
    }
    .reset-container p {
        color: #636e72;
        font-size: 1em;
        margin-bottom: 18px;
        text-align: center;
    }
    .reset-container form {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .reset-container label {
        font-weight: 600;
        color: #2980b9;
        margin-bottom: 6px;
    }
    .reset-container input[type="password"] {
        padding: 12px 16px;
        border: 2px solid #e0e6ed;
        border-radius: 10px;
        font-size: 1em;
        transition: border 0.2s;
    }
    .reset-container input[type="password"]:focus {
        border-color: #2980b9;
        outline: none;
    }
    .reset-container button {
        background: linear-gradient(135deg,#3498db,#2980b9);
        color: #fff;
        border: none;
        padding: 12px 0;
        border-radius: 10px;
        font-weight: 700;
        font-size: 1.1em;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(41,128,185,0.10);
        transition: background 0.2s, transform 0.2s;
    }
    .reset-container button:hover {
        background: linear-gradient(135deg,#2980b9,#3498db);
        transform: translateY(-2px);
    }
    .reset-container .msg {
        margin-bottom: 16px;
        padding: 10px 16px;
        border-radius: 8px;
        background: #eaf6fb;
        color: #2980b9;
        font-weight: 600;
        text-align: center;
    }
    .reset-container .back-link {
        margin-top: 18px;
        color: #2980b9;
        text-decoration: none;
        font-size: 0.98em;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: color 0.2s;
    }
    .reset-container .back-link:hover {
        color: #1a5276;
    }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2><i class="fas fa-key"></i> <?= t('reset_password_title') ?></h2>
        <p><?= t('reset_password_desc') ?></p>
        <?php if (!empty($msg)) echo '<div class="msg">' . htmlspecialchars($msg) . '</div>'; ?>
        <form method="post">
            <label for="password"><i class="fas fa-lock"></i> <?= t('new_password') ?></label>
            <input type="password" name="password" id="password" required placeholder="<?= t('password_min_chars') ?>">
            <button type="submit"><i class="fas fa-check"></i> <?= t('reset_password_button') ?></button>
        </form>
        <a href="login.php" class="back-link"><i class="fas fa-arrow-left"></i> <?= t('back_to_login') ?></a>
    </div>
</body>
</html>
