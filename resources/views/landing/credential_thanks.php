<?php

declare(strict_types=1);

use App\Services\EmailTemplateFooterService;

$logoUrl = branding_logo_url();
$motto = EmailTemplateFooterService::footerMottoLine();
$contactLine = EmailTemplateFooterService::footerContactLine();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Güvenlik farkındalık bildirimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php if (!empty($previewMode)): ?>
<div class="py-2 px-3 text-center small" style="background:#ffc107;color:#333;border-bottom:1px solid #e0a800;">
    <strong>Önizleme</strong> — Form gönderimi simüle edildi; kayıt oluşturulmadı.
</div>
<?php endif; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-block rounded-3 p-3 mb-3" style="background:#2a2a2a;">
                        <img src="<?= e($logoUrl) ?>" alt="Sakallı" width="100" height="100" class="rounded object-fit-contain d-block mx-auto" style="max-height:100px;width:auto;">
                    </div>
                    <p class="mb-2"><strong>Oltaya düştünüz.</strong></p>
                    <p class="small text-muted mb-2">
                        <em><?= e($motto) ?></em>
                    </p>
                    <p class="small text-muted mb-0">
                        <?= e($contactLine) ?>
                    </p>
                    <hr class="my-3">
                    <p class="small text-muted mb-0">
                        (Bu sayfa yalnızca kurum içi güvenlik farkındalık simülasyonu kapsamındadır.)
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
