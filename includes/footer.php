</main>
        <footer class="app-footer" style="background:linear-gradient(135deg,#f8fafc 0%,#eaf6fb 100%);padding:24px 0;margin-top:48px;box-shadow:0 -2px 16px rgba(41,128,185,0.08);">
            <div class="container" style="max-width:1200px;margin:auto;padding:0 24px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img src="<?= BASE_URL ?>assets/img/admin-users.svg" alt="Logo" style="height:32px;width:auto;">
                        <div>
                            <p style="margin:0;color:#2980b9;font-size:1.1em;font-weight:600;">Ministère de la Santé Publique</p>
                            <p style="margin:4px 0 0 0;color:#636e72;font-size:0.92em;">Version <?= APP_VERSION ?> &copy; <?= date('Y') ?></p>
                        </div>
                    </div>
                    <div style="display:flex;gap:24px;align-items:center;">
                        <a href="#" style="color:#2980b9;text-decoration:none;font-size:0.98em;transition:color 0.2s;">Aide</a>
                        <a href="#" style="color:#2980b9;text-decoration:none;font-size:0.98em;transition:color 0.2s;">Contact</a>
                        <a href="#" style="color:#2980b9;text-decoration:none;font-size:0.98em;transition:color 0.2s;">Mentions légales</a>
                    </div>
                </div>
            </div>
        </footer>
        <script src="<?= BASE_URL ?>assets/js/script.js?v=<?= time() ?>"></script>
        <style>
        .app-footer a:hover { color:#27ae60 !important; }
        @media (max-width: 768px) {
            .app-footer .container > div { justify-content:center;text-align:center; }
            .app-footer .container > div > div:last-child { margin-top:16px; }
        }
        </style>
    </body>
</html>