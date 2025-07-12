// Fonctions utilitaires
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des messages flash
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    }
    
    // Confirmation avant suppression
    const deleteButtons = document.querySelectorAll('.btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                e.preventDefault();
            }
        });
    });
    
    // Gestion des onglets
    const tabs = document.querySelectorAll('.tab-button');
    if (tabs.length > 0) {
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                document.getElementById(tabId).style.display = 'block';
                this.classList.add('active');
            });
        });
        
        // Activer le premier onglet par défaut
        tabs[0].click();
    }
});

// Fonction pour charger plus de dossiers (pagination infinie)
function loadMoreDossiers(page) {
    fetch(`api/dossiers.php?page=${page}`)
        .then(response => response.json())
        .then(data => {
            if (data.length > 0) {
                const container = document.getElementById('dossiers-container');
                data.forEach(dossier => {
                    const html = `
                        <div class="dossier-card">
                            <h3>${dossier.titre}</h3>
                            <p>${dossier.reference}</p>
                            <span class="status-badge status-${dossier.status}">
                                ${dossier.status.replace('_', ' ')}
                            </span>
                        </div>
                    `;
                    container.insertAdjacentHTML('beforeend', html);
                });
            } else {
                document.getElementById('load-more').style.display = 'none';
            }
        });
}

// Initialisation de la pagination
var currentPage = window.currentPage || 1;
let loadMoreBtn = document.getElementById('load-more');
if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function() {
        currentPage++;
        loadMoreDossiers(currentPage);
    });
}

// Gestion des pièces jointes
let fileUpload = document.getElementById('file-upload');
if (fileUpload) {
    fileUpload.addEventListener('change', function(e) {
        const files = e.target.files;
        const fileList = document.getElementById('file-list');
        if (fileList) {
            fileList.innerHTML = '';
            for (let i = 0; i < files.length; i++) {
                const li = document.createElement('li');
                li.textContent = `${files[i].name} (${formatFileSize(files[i].size)})`;
                fileList.appendChild(li);
            }
        }
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}