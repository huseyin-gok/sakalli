<?php

declare(strict_types=1);

/** @var bool $sent */
/** @var bool $already */
$sent = !empty($sent);
$already = !empty($already);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teşekkürler — Sakallı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <?php if ($already): ?>
                        <h1 class="h5">Zaten gönderildi</h1>
                        <p class="text-muted small mb-0">Bu simülasyon için geri bildiriminiz daha önce kaydedilmişti.</p>
                    <?php elseif ($sent): ?>
                        <h1 class="h5">Teşekkürler</h1>
                        <p class="text-muted small mb-0">Geri bildiriminiz kaydedildi.</p>
                    <?php else: ?>
                        <h1 class="h5">Tamam</h1>
                        <p class="text-muted small mb-0">İşlem tamamlandı.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
