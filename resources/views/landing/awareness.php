<?php

declare(strict_types=1);

use App\Services\EmailTemplateFooterService;

/** @var string $token */
/** @var string $pageTitle */
/** @var string $bodyHtml */
/** @var bool $showFeedbackForm */
/** @var string $feedbackPostUrl */
if (!isset($pageTitle, $bodyHtml)) {
    $pageTitle = 'Güvenlik Farkındalığı — Simülasyon';
    $bodyHtml = '';
}
$token = $token ?? '';
$showFeedbackForm = !empty($showFeedbackForm);
$feedbackPostUrl = $feedbackPostUrl ?? url('/track/feedback');
$logoUrl = branding_logo_url();
$motto = EmailTemplateFooterService::footerMottoLine();
$contactLine = EmailTemplateFooterService::footerContactLine();
$previewMode = !empty($previewMode);
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
    <strong>Önizleme</strong> — Geri bildirim gönderilmez; takip kaydı oluşturulmaz.
</div>
<?php endif; ?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="d-inline-block rounded-3 p-3 mb-3" style="background:#2a2a2a;">
                            <img src="<?= e($logoUrl) ?>" alt="Sakallı" width="100" height="100"
                                class="rounded object-fit-contain d-block mx-auto" style="max-height:100px;width:auto;">
                        </div>
                        <p class="lead mb-0 px-md-3" style="font-size:1.05rem;line-height:1.5;">
                            <em><?= e($motto) ?></em>
                        </p>
                    </div>
                    <?= $bodyHtml ?>
                    <div class="alert alert-secondary border mt-4" role="note">
                        <p class="small fw-semibold mb-2">İletişim</p>
                        <p class="small mb-0"><?= e($contactLine) ?></p>
                    </div>
                    <?php if ($showFeedbackForm && !$previewMode): ?>
                        <hr>
                        <form method="post" action="<?= e($feedbackPostUrl) ?>" class="mt-2">
                            <input type="hidden" name="tracking_token" value="<?= e($token) ?>">
                            <p class="fw-semibold small mb-2">Geri bildirim</p>
                            <div class="visually-hidden" aria-hidden="true">
                                <label>Web sitesi <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-0">Mesajınız (isteğe bağlı)</label>
                                <textarea name="comment" class="form-control form-control-sm" rows="3" maxlength="4000" placeholder="Görüş veya soru"></textarea>
                            </div>
                            <div class="mb-2">
                                <span class="form-label small d-block">Simülasyonu anladınız mı?</span>
                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                    <input type="radio" class="btn-check" name="understood" value="yes" id="fb_y">
                                    <label class="btn btn-outline-secondary" for="fb_y">Evet</label>
                                    <input type="radio" class="btn-check" name="understood" value="partial" id="fb_p">
                                    <label class="btn btn-outline-secondary" for="fb_p">Kısmen</label>
                                    <input type="radio" class="btn-check" name="understood" value="no" id="fb_n">
                                    <label class="btn btn-outline-secondary" for="fb_n">Hayır</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-dark btn-sm">Gönder</button>
                        </form>
                    <?php elseif ($showFeedbackForm && $previewMode): ?>
                        <hr>
                        <p class="small text-muted mb-0">Geri bildirim formu gerçek gönderimde burada görünür (önizlemede kapalı).</p>
                    <?php endif; ?>
                    <hr>
                    <p class="text-muted small mb-0">
                        Referans token: <?= e($token) ?> — Bu sayfa eğitim amaçlıdır; kişisel veri toplamaz.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
