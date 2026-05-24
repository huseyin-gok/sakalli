<?php

declare(strict_types=1);

/** @var string $token */
/** @var string $pageTitle */
/** @var string $bodyHtml */
/** @var string $credentialsPostUrl */
$token = $token ?? '';
$pageTitle = $pageTitle ?? 'Oturum açın';
$bodyHtml = $bodyHtml ?? '';
$credentialsPostUrl = $credentialsPostUrl ?? url('/track/credentials');
$previewMode = !empty($previewMode);
$previewThanksUrl = $previewThanksUrl ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php if ($previewMode): ?>
<div class="py-2 px-3 text-center small" style="background:#ffc107;color:#333;border-bottom:1px solid #e0a800;">
    <strong>Önizleme</strong> — Form gönderilmez; kimlik bilgisi kaydedilmez.
    <?php if ($previewThanksUrl !== ''): ?>
        <a href="<?= e($previewThanksUrl) ?>" class="ms-2">Form sonrası sayfayı gör →</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow border-0">
                <div class="card-body p-4">
                    <?php if (trim(strip_tags($bodyHtml)) !== ''): ?>
                        <div class="mb-4 landing-credential-html"><?= $bodyHtml ?></div>
                    <?php endif; ?>
                    <form method="post" action="<?= e($credentialsPostUrl) ?>" autocomplete="off"<?= $previewMode ? ' onsubmit="return false;"' : '' ?>>
                        <input type="hidden" name="tracking_token" value="<?= e($token) ?>">
                        <?php if (!$previewMode): ?>
                        <div class="visually-hidden" aria-hidden="true">
                            <label>Şirket <input type="text" name="company" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted" for="sim_username">E-posta veya kullanıcı adı</label>
                            <input type="text" name="sim_username" id="sim_username" class="form-control" maxlength="255"
                                   autocapitalize="none" autocomplete="username"<?= $previewMode ? ' disabled' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted" for="sim_password">Parola</label>
                            <input type="password" name="sim_password" id="sim_password" class="form-control" maxlength="512"
                                   autocomplete="current-password"<?= $previewMode ? ' disabled' : '' ?>>
                        </div>
                        <button type="submit" class="btn btn-dark w-100"<?= $previewMode ? ' disabled' : '' ?>>Oturum aç</button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">Referans: <?= e($token) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
