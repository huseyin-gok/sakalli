<?php
/** @var string $csrf */
/** @var string|null $error */
$loginLogo = branding_logo_url();
?>
<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — Sakallı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0a0a0a !important; }
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
        }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 text-body">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow border-secondary">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <img src="<?= e($loginLogo) ?>" alt="Sakallı" width="104" height="104" class="rounded-3 object-fit-contain d-block mx-auto" style="max-height:104px;width:auto;">
                    </div>
                    <h1 class="h5 mb-3 text-center">Sakallı — Güvenlik Farkındalığı</h1>
                    <p class="text-secondary small text-center">Kurum içi eğitim ve simülasyon yönetimi</p>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($ldap_debug ?? '')): ?>
                        <div class="alert alert-warning small mb-0">
                            <strong>LDAP debug</strong> (yalnızca APP_DEBUG=true):<br>
                            <?= e($ldap_debug) ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= url('/login') ?>" autocomplete="off">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <div class="mb-3">
                            <label class="form-label">Kullanıcı adı</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Parola</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Giriş</button>
                    </form>
                </div>
            </div>
            <p class="text-center text-secondary small mt-3">Bu sistem yalnızca yetkili kurumsal farkındalık testleri içindir.</p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
