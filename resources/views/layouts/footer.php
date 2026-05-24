        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var storageKey = 'sakalli_theme';
    function currentTheme() {
        var v = document.documentElement.getAttribute('data-bs-theme');
        return v === 'light' || v === 'dark' ? v : 'dark';
    }
    function apply(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            theme = 'dark';
        }
        document.documentElement.setAttribute('data-bs-theme', theme);
        try {
            localStorage.setItem(storageKey, theme);
        } catch (e) {}
        document.querySelectorAll('.theme-choice').forEach(function (btn) {
            var on = btn.getAttribute('data-app-theme') === theme;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }
    function init() {
        var saved = null;
        try {
            saved = localStorage.getItem(storageKey);
        } catch (e) {}
        if (saved === 'light' || saved === 'dark') {
            apply(saved);
        } else {
            apply(currentTheme());
        }
        document.querySelectorAll('.theme-choice').forEach(function (btn) {
            btn.addEventListener('click', function () {
                apply(btn.getAttribute('data-app-theme') || 'dark');
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
</body>
</html>
