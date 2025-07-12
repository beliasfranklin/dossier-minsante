.notification-menu {
    position: relative;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #e74c3c;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(231,76,60,0.18);
    font-weight: bold;
    border: 2px solid #fff;
}

.notification-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    width: 350px;
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-radius: 5px;
    z-index: 1000;
    display: none;
    animation: fadeInNotif 0.25s cubic-bezier(.4,0,.2,1);
}

.notification-menu:hover .notification-dropdown {
    display: block;
}

.notification-header {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #f5f5f5;
    display: block;
    color: #333;
}

.notification-item.unread {
    background: #f8f9fa;
    font-weight: 500;
}

.notification-item:hover {
    background: #f0f0f0;
}

.notification-item strong {
    color: #2980b9;
    font-size: 1em;
}

.notification-item p {
    margin: 2px 0 4px 0;
    font-size: 0.97em;
}

.notification-header h4 {
    margin: 0;
    font-size: 1.1em;
    color: #2980b9;
}

.notification-header a {
    color: #3498db;
    font-size: 0.95em;
    text-decoration: none;
    font-weight: 500;
}

.notification-header a:hover {
    text-decoration: underline;
}

/* Version page complète */
.notification-full-list .notification-item {
    margin-bottom: 10px;
    border-radius: 5px;
    border: 1px solid #eee;
    position: relative;
    padding-right: 100px;
}

.notification-full-list .notification-item.unread {
    border-left: 3px solid #3498db;
}

.mark-read-btn, .view-related-btn {
    position: absolute;
    right: 10px;
    font-size: 0.8em;
}

.mark-read-btn {
    top: 10px;
    color: #3498db;
}

.view-related-btn {
    bottom: 10px;
    color: #2ecc71;
}

/* Responsive pour notifications */
@media (max-width: 500px) {
    .notification-dropdown {
        width: 98vw;
        left: 0;
        right: 0;
        min-width: unset;
        border-radius: 0 0 8px 8px;
    }
    .notification-list {
        max-height: 250px;
    }
}

/* Amélioration accessibilité et animation */
@keyframes fadeInNotif {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: none; }
}