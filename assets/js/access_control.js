// Script pour signaler l'absence de droits d'accès
function showAccessDenied(message = "Vous n'avez pas les droits d'accès à cette fonctionnalité.") {
    if (!document.getElementById('access-denied-alert')) {
        const alert = document.createElement('div');
        alert.id = 'access-denied-alert';
        alert.style.position = 'fixed';
        alert.style.top = '20px';
        alert.style.right = '20px';
        alert.style.background = '#e74c3c';
        alert.style.color = '#fff';
        alert.style.padding = '16px 24px';
        alert.style.borderRadius = '8px';
        alert.style.boxShadow = '0 4px 16px rgba(231,76,60,0.15)';
        alert.style.zIndex = 9999;
        alert.style.fontWeight = 'bold';
        alert.innerText = message;
        document.body.appendChild(alert);
        setTimeout(() => { alert.remove(); }, 4000);
    }
}
// Exemple d'utilisation : showAccessDenied();
