// Actualisation périodique des notifications
function updateNotificationBadge() {
    fetch('api/notifications/unread_count.php')
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.notification-badge');
            if (data.count > 0) {
                if (!badge) {
                    const bell = document.getElementById('notificationBell');
                    bell.innerHTML += `<span class="notification-badge">${data.count}</span>`;
                } else {
                    badge.textContent = data.count;
                }
            } else if (badge) {
                badge.remove();
            }
        });
}

// Actualiser toutes les 30 secondes
setInterval(updateNotificationBadge, 30000);

// Marquer comme lu au clic
document.addEventListener('click', function(e) {
    if (e.target.closest('.notification-item')) {
        const notifId = e.target.closest('.notification-item').dataset.id;
        if (notifId) {
            fetch('api/notifications/mark_as_read.php?id=' + notifId);
        }
    }
});