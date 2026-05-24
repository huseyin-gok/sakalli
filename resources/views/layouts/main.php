<?php

/** @var string $title */
$navReport = rbac_has_any_role(['super_admin', 'security_manager', 'report_viewer']);
$navSecurity = rbac_has_any_role(['super_admin', 'security_manager']);
$logoUrl = branding_logo_url();
$panelName = current_user_display_name();
$panelEmail = current_user_email();
$panelRoles = current_user_role_labels();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = app_base_path();
if ($base !== '' && str_starts_with($currentPath, $base)) {
    $currentPath = substr($currentPath, strlen($base)) ?: '/';
}
$currentPath = '/' . ltrim($currentPath, '/');
$navActive = static function (string $prefix) use ($currentPath): string {
    $p = $prefix === '/' ? '/' : rtrim($prefix, '/');
    if ($p === '/') {
        return $currentPath === '/' || $currentPath === '/dashboard' ? ' active fw-semibold' : '';
    }

    return str_starts_with($currentPath, $p) ? ' active fw-semibold' : '';
};
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Sakallı') ?> — Sakallı</title>
    <script>
        (function() {
            try {
                var t = localStorage.getItem('sakalli_theme');
                if (t === 'light' || t === 'dark') {
                    document.documentElement.setAttribute('data-bs-theme', t);
                }
            } catch (e) {}
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        [data-bs-theme="dark"] {
            --app-bg: #0a0a0a;
            --app-sidebar: #030303;
            --app-border: #252525;
            --bs-body-bg: #0a0a0a;
            --bs-body-color: #e8e8e8;
        }

        [data-bs-theme="light"] {
            --app-bg: #f4f4f4;
            --app-sidebar: #ffffff;
            --app-border: #dee2e6;
            --bs-body-bg: #f4f4f4;
            --bs-body-color: #212529;
        }

        [data-bs-theme="light"] .app-sidebar.text-bg-dark {
            color: inherit !important;
        }

        .app-sidebar .text-on-sidebar {
            color: rgba(255, 255, 255, .92);
        }

        .app-sidebar .text-on-sidebar-muted {
            color: rgba(255, 255, 255, .55);
        }

        [data-bs-theme="light"] .app-sidebar .text-on-sidebar {
            color: rgba(33, 37, 41, .92);
        }

        [data-bs-theme="light"] .app-sidebar .text-on-sidebar-muted {
            color: rgba(33, 37, 41, .55);
        }

        .app-mobile-header {
            color: rgba(255, 255, 255, .95);
        }

        .app-mobile-header .app-mobile-header-muted {
            color: rgba(255, 255, 255, .55);
        }

        [data-bs-theme="light"] .app-mobile-header {
            color: var(--bs-body-color) !important;
            background: var(--app-sidebar) !important;
        }

        [data-bs-theme="light"] .app-mobile-header .app-mobile-header-muted {
            color: rgba(33, 37, 41, .55) !important;
        }

        [data-bs-theme="light"] .app-mobile-header .btn-outline-light {
            --bs-btn-color: #212529;
            --bs-btn-border-color: #adb5bd;
            --bs-btn-hover-color: #212529;
            --bs-btn-hover-bg: #e9ecef;
            --bs-btn-hover-border-color: #adb5bd;
        }

        [data-bs-theme="light"] .border-white-10 {
            border-color: rgba(0, 0, 0, .08) !important;
        }

        [data-bs-theme="light"] .app-sidebar .role-badge {
            background-color: #e9ecef !important;
            color: #212529 !important;
            border-color: #ced4da !important;
        }

        .app-body {
            background: var(--app-bg);
            min-height: 100vh;
        }

        .app-sidebar .nav-link {
            color: rgba(255, 255, 255, .78);
            border-radius: .375rem;
        }

        .app-sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, .08);
        }

        .app-sidebar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .14);
        }

        [data-bs-theme="light"] .app-sidebar .nav-link {
            color: rgba(33, 37, 41, .78);
        }

        [data-bs-theme="light"] .app-sidebar .nav-link:hover {
            color: #000;
            background: rgba(0, 0, 0, .06);
        }

        [data-bs-theme="light"] .app-sidebar .nav-link.active {
            color: #000;
            background: rgba(0, 0, 0, .1);
        }

        .app-sidebar .nav-settings-toggle {
            color: rgba(255, 255, 255, .55);
            line-height: 1;
            transition: transform .2s ease;
        }

        .app-sidebar .nav-settings-toggle[aria-expanded="true"] {
            transform: rotate(180deg);
        }

        .app-sidebar .nav-settings-toggle:hover {
            color: #fff;
        }

        [data-bs-theme="light"] .app-sidebar .nav-settings-toggle {
            color: rgba(33, 37, 41, .55);
        }

        [data-bs-theme="light"] .app-sidebar .nav-settings-toggle:hover {
            color: #000;
        }

        [data-bs-theme="dark"] .app-sidebar .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-bs-theme="light"] .app-sidebar .btn-outline-light {
            --bs-btn-color: #212529;
            --bs-btn-border-color: #adb5bd;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #212529;
            --bs-btn-hover-border-color: #212529;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: #212529;
            --bs-btn-active-border-color: #212529;
        }

        .app-sidebar.text-bg-dark {
            background-color: var(--app-sidebar) !important;
        }

        @media (min-width: 992px) {
            .offcanvas-lg.app-sidebar.text-bg-dark {
                background-color: var(--app-sidebar) !important;
                transform: none !important;
                visibility: visible !important;
                position: sticky;
                top: 0;
                align-self: flex-start;
                height: 100vh;
                max-height: 100vh;
                display: flex !important;
                flex-direction: column;
                border-right: 1px solid var(--app-border) !important;
            }
        }

        .app-main-inner .card {
            border-color: var(--app-border);
        }

        .border-white-10 {
            border-color: rgba(255, 255, 255, .1) !important;
        }

        /* Mavi/yeşil Bootstrap vurguları yerine siyah temaya uyumlu nötr gri */
        [data-bs-theme="dark"] .btn-primary {
            --bs-btn-color: #f2f2f2;
            --bs-btn-bg: #3a3a3a;
            --bs-btn-border-color: #505050;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #4a4a4a;
            --bs-btn-hover-border-color: #606060;
            --bs-btn-focus-shadow-rgb: 120, 120, 120;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: #525252;
            --bs-btn-active-border-color: #6a6a6a;
            --bs-btn-disabled-color: #888;
            --bs-btn-disabled-bg: #2c2c2c;
            --bs-btn-disabled-border-color: #3a3a3a;
        }

        [data-bs-theme="dark"] .btn-success {
            --bs-btn-color: #f0f0f0;
            --bs-btn-bg: #454545;
            --bs-btn-border-color: #656565;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #555;
            --bs-btn-hover-border-color: #767676;
            --bs-btn-focus-shadow-rgb: 120, 120, 120;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: #5c5c5c;
            --bs-btn-active-border-color: #808080;
            --bs-btn-disabled-color: #888;
            --bs-btn-disabled-bg: #2c2c2c;
            --bs-btn-disabled-border-color: #3a3a3a;
        }

        [data-bs-theme="dark"] .btn-outline-primary,
        [data-bs-theme="dark"] .btn-outline-success {
            --bs-btn-color: #d0d0d0;
            --bs-btn-border-color: #6a6a6a;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: rgba(255, 255, 255, .08);
            --bs-btn-hover-border-color: #888;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: rgba(255, 255, 255, .12);
            --bs-btn-active-border-color: #999;
            --bs-btn-disabled-color: #666;
            --bs-btn-disabled-border-color: #444;
        }

        [data-bs-theme="dark"] .alert-success,
        [data-bs-theme="dark"] .alert-info {
            --bs-alert-bg: rgba(255, 255, 255, .06);
            --bs-alert-border-color: var(--app-border);
            --bs-alert-color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] .badge.bg-primary {
            background-color: #424242 !important;
        }

        [data-bs-theme="dark"] .badge.bg-success {
            background-color: #3d3d3d !important;
        }

        [data-bs-theme="dark"] .card-header.bg-primary.bg-opacity-10,
        [data-bs-theme="dark"] .card-header.bg-success.bg-opacity-10 {
            background-color: rgba(255, 255, 255, .06) !important;
            color: inherit;
            border-bottom-color: var(--app-border) !important;
        }

        [data-bs-theme="dark"] .border-primary,
        [data-bs-theme="dark"] .border-success {
            border-color: var(--app-border) !important;
        }

        [data-bs-theme="light"] .btn-primary {
            --bs-btn-color: #fff;
            --bs-btn-bg: #495057;
            --bs-btn-border-color: #495057;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #343a40;
            --bs-btn-hover-border-color: #343a40;
            --bs-btn-focus-shadow-rgb: 73, 80, 87;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: #2b3035;
            --bs-btn-active-border-color: #2b3035;
        }

        [data-bs-theme="light"] .btn-success {
            --bs-btn-color: #fff;
            --bs-btn-bg: #5c636a;
            --bs-btn-border-color: #5c636a;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #495057;
            --bs-btn-hover-border-color: #495057;
            --bs-btn-focus-shadow-rgb: 92, 99, 106;
            --bs-btn-active-color: #fff;
            --bs-btn-active-bg: #41464b;
            --bs-btn-active-border-color: #41464b;
        }

        [data-bs-theme="light"] .btn-outline-primary,
        [data-bs-theme="light"] .btn-outline-success {
            --bs-btn-color: #495057;
            --bs-btn-border-color: #adb5bd;
            --bs-btn-hover-color: #fff;
            --bs-btn-hover-bg: #495057;
            --bs-btn-hover-border-color: #495057;
        }

        [data-bs-theme="light"] .alert-success,
        [data-bs-theme="light"] .alert-info {
            --bs-alert-bg: rgba(0, 0, 0, .04);
            --bs-alert-border-color: var(--app-border);
            --bs-alert-color: var(--bs-body-color);
        }

        [data-bs-theme="light"] .badge.bg-primary {
            background-color: #6c757d !important;
        }

        [data-bs-theme="light"] .badge.bg-success {
            background-color: #868e96 !important;
        }

        [data-bs-theme="light"] .card-header.bg-primary.bg-opacity-10,
        [data-bs-theme="light"] .card-header.bg-success.bg-opacity-10 {
            background-color: rgba(0, 0, 0, .04) !important;
            color: inherit;
            border-bottom-color: var(--app-border) !important;
        }

        [data-bs-theme="light"] .border-primary,
        [data-bs-theme="light"] .border-success {
            border-color: var(--app-border) !important;
        }
    </style>
</head>

<body class="app-body text-body">
    <div class="d-flex min-vh-100 w-100">
        <aside class="offcanvas-lg offcanvas-start app-sidebar text-bg-dark flex-shrink-0 border-end border-secondary border-opacity-25"
            tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel" style="width: 260px; max-width: 85vw;">
            <div class="offcanvas-header d-lg-none border-bottom border-white-10">
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($logoUrl) ?>" alt="" width="44" height="44" class="rounded object-fit-contain p-0" style="max-height:44px;width:auto;">
                    <span id="appSidebarLabel" class="offcanvas-title fs-6 fw-semibold text-on-sidebar">Sakallı</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Kapat"></button>
            </div>
            <div class="offcanvas-body d-flex flex-column p-0 py-lg-4 h-100 overflow-y-auto">
                <div class="px-3 pb-3 mb-2 border-bottom border-white-10 d-none d-lg-block text-center">
                    <a class="text-on-sidebar text-decoration-none d-inline-flex flex-column align-items-center gap-2" href="<?= url('/dashboard') ?>">
                        <img src="<?= e($logoUrl) ?>" alt="Sakallı" width="96" height="96" class="rounded-3 object-fit-contain d-block mx-auto" style="max-height:96px;width:auto;">
                        <span class="fs-6 fw-semibold">Sakallı</span>
                    </a>
                </div>
                <nav class="nav flex-column px-2 gap-1 flex-grow-1">
                    <a class="nav-link py-2 px-3<?= $navActive('/dashboard') ?>" href="<?= url('/dashboard') ?>">Dashboard</a>
                    <?php if ($navSecurity): ?>
                        <a class="nav-link py-2 px-3<?= $navActive('/templates') ?>" href="<?= url('/templates') ?>">Şablonlar</a>
                        <a class="nav-link py-2 px-3<?= $navActive('/campaigns') ?>" href="<?= url('/campaigns') ?>">Kampanyalar</a>
                        <a class="nav-link py-2 px-3<?= $navActive('/users') ?>" href="<?= url('/users') ?>">Kullanıcılar</a>
                    <?php endif; ?>
                    <?php if ($navReport): ?>
                        <a class="nav-link py-2 px-3<?= $navActive('/events') ?>" href="<?= url('/events') ?>">Olaylar</a>
                        <a class="nav-link py-2 px-3<?= $navActive('/reports') ?>" href="<?= url('/reports') ?>">Raporlar</a>
                    <?php endif; ?>
                    <?php if ($navSecurity): ?>
                        <?php
                        $settingsMenuOpen = str_starts_with($currentPath, '/settings');
                        $settingsGeneralActive = $currentPath === '/settings' || $currentPath === '/settings/';
                        ?>
                        <div class="nav-settings-group">
                            <div class="d-flex align-items-stretch">
                                <a class="nav-link flex-grow-1 py-2 px-3<?= $settingsGeneralActive ? ' active fw-semibold' : '' ?>" href="<?= url('/settings') ?>">Ayarlar</a>
                                <button type="button"
                                    class="btn btn-link nav-settings-toggle py-2 px-2 border-0 text-decoration-none align-self-center"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#settingsSubmenu"
                                    aria-expanded="<?= $settingsMenuOpen ? 'true' : 'false' ?>"
                                    aria-controls="settingsSubmenu"
                                    aria-label="LDAP ve SMTP alt menüsünü aç/kapat">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z" />
                                    </svg>
                                </button>
                            </div>
                            <div class="collapse<?= $settingsMenuOpen ? ' show' : '' ?>" id="settingsSubmenu">
                                <a class="nav-link py-2 px-3 ps-4 small<?= str_starts_with($currentPath, '/settings/ldap') ? ' active fw-semibold' : '' ?>" href="<?= url('/settings/ldap') ?>">LDAP</a>
                                <a class="nav-link py-2 px-3 ps-4 small<?= str_starts_with($currentPath, '/settings/smtp') ? ' active fw-semibold' : '' ?>" href="<?= url('/settings/smtp') ?>">SMTP</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </nav>
                <div class="px-3 pt-3 mt-auto border-top border-white-10">
                    <?php if ($panelName !== '' || $panelEmail !== ''): ?>
                        <div class="mb-3 small">
                            <?php if ($panelName !== ''): ?>
                                <div class="text-on-sidebar fw-semibold text-break"><?= e($panelName) ?></div>
                            <?php endif; ?>
                            <?php if ($panelEmail !== '' && strcasecmp($panelEmail, $panelName) !== 0): ?>
                                <div class="text-on-sidebar-muted text-break mt-1"><?= e($panelEmail) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3 small">
                        <div class="text-on-sidebar-muted text-uppercase mb-1" style="font-size: .65rem; letter-spacing: .06em;">Yetkiler</div>
                        <?php if ($panelRoles !== []): ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($panelRoles as $roleName): ?>
                                    <span class="role-badge badge rounded-pill border border-secondary bg-dark text-light fw-normal"><?= e($roleName) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-on-sidebar-muted">Rol atanmamış — varsayılan tam erişim</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <div class="text-on-sidebar-muted text-uppercase mb-1" style="font-size: .65rem; letter-spacing: .06em;">Tema</div>
                        <div class="btn-group w-100" role="group" aria-label="Arayüz teması">
                            <button type="button" class="btn btn-sm btn-outline-light theme-choice flex-fill" data-app-theme="dark" id="themeBtnDark">Siyah</button>
                            <button type="button" class="btn btn-sm btn-outline-light theme-choice flex-fill" data-app-theme="light" id="themeBtnLight">Beyaz</button>
                        </div>
                    </div>
                    <a class="btn btn-outline-light btn-sm w-100" href="<?= url('/logout') ?>">Çıkış</a>
                </div>
            </div>
        </aside>

        <div class="flex-grow-1 min-w-0 d-flex flex-column min-vh-100 app-main-inner">
            <header class="app-mobile-header d-lg-none sticky-top px-2 py-2 d-flex align-items-center gap-2 shadow-sm border-bottom border-white-10"
                style="background: var(--app-sidebar);">
                <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Menü">☰</button>
                <img src="<?= e($logoUrl) ?>" alt="" width="60 " height="60" class="rounded object-fit-contain p-0" style="max-height:40px;width:auto;">
                <span class="small fw-semibold">Sakallı</span>
                <?php if ($panelName !== ''): ?>
                    <span class="small app-mobile-header-muted ms-auto text-truncate" style="max-width: 42vw;"><?= e($panelName) ?></span>
                <?php endif; ?>
            </header>
            <main class="container-fluid px-3 px-md-4 py-4 flex-grow-1 pb-5">
                <?php /* içerik */ ?>