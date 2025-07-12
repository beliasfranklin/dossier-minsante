// Exemple d'appel AJAX pour notifier par email ou WhatsApp
function notifyUser(type, to, subject, message) {
    let url = '';
    let data = {};
    if (type === 'email') {
        url = '/api/notifications/send_email.php';
        data = { to, subject, body: message };
    } else if (type === 'whatsapp') {
        url = '/api/notifications/send_whatsapp.php';
        data = { to, message };
    }
    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resp => {
        // Afficher une notification de succès ou d'erreur
        if (resp.success) {
            alert('Notification envoyée !');
        } else {
            alert('Erreur lors de l\'envoi de la notification.');
        }
    });
}
