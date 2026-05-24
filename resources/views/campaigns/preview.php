<?php

declare(strict_types=1);

/** @var string $title */
/** @var int $campaign_id */
/** @var array<string, mixed> $preview */
/** @var array<string, string> $status_labels */
/** @var array<string, string> $interaction_labels */
$campaign = $preview['campaign'] ?? [];
$email = $preview['email'] ?? [];
$flow = $preview['flow'] ?? [];
$cid = $campaign_id;
$interactionMode = trim((string) ($campaign['interaction_mode'] ?? ''));
$interactionLabel = $interaction_labels[$interactionMode] ?? $interaction_labels[''];
$flowLabel = (string) ($flow['flow_label'] ?? '—');
$status = (string) ($campaign['status'] ?? '');
require dirname(__DIR__) . '/layouts/main.php';
?>
<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a class="small" href="<?= url('/campaigns/' . $cid) ?>">← Kampanya detayı</a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/campaigns/' . $cid . '/preview/email') ?>" target="_blank" rel="noopener">E-postayı yeni sekmede aç</a>
</div>

<div class="alert alert-warning py-2 small mb-3">
    <strong>Önizleme modu</strong> — E-posta gönderilmez, tıklama/geri bildirim/kimlik kaydı oluşturulmaz.
    Örnek alıcı ve değişkenler kullanılır; gerçek hedeflerde kişiselleştirilir.
</div>

<h1 class="h4 mb-3"><?= e($title) ?></h1>

<div class="card shadow-sm mb-4">
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-sm-3">Durum</dt>
            <dd class="col-sm-9"><?= e($status_labels[$status] ?? $status) ?></dd>
            <dt class="col-sm-3">Şablon</dt>
            <dd class="col-sm-9"><?= e((string) ($campaign['template_name'] ?? '—')) ?></dd>
            <dt class="col-sm-3">Görünen gönderen</dt>
            <dd class="col-sm-9"><?= e((string) ($email['from_name'] ?? '')) ?> &lt;<?= e((string) ($email['from_address'] ?? '')) ?>&gt;</dd>
            <dt class="col-sm-3">Tıklama akışı</dt>
            <dd class="col-sm-9"><?= e($flowLabel) ?></dd>
            <dt class="col-sm-3">Akış modu (kayıtlı)</dt>
            <dd class="col-sm-9"><?= e($interactionLabel) ?></dd>
            <?php if (!empty($campaign['landing_page_name'])): ?>
                <dt class="col-sm-3">Landing şablonu</dt>
                <dd class="col-sm-9"><?= e((string) $campaign['landing_page_name']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-email" data-bs-toggle="tab" data-bs-target="#pane-email" type="button" role="tab">1. E-posta</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-flow" data-bs-toggle="tab" data-bs-target="#pane-flow" type="button" role="tab">2. Linke tıklanınca</button>
    </li>
    <?php if (!empty($flow['has_thanks_step'])): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-thanks" data-bs-toggle="tab" data-bs-target="#pane-thanks" type="button" role="tab">3. Form sonrası</button>
        </li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pane-email" role="tabpanel">
        <p class="small text-muted mb-2">
            Konu: <strong><?= e((string) ($email['subject'] ?? '')) ?></strong>
            — Örnek alıcı: <?= e((string) ($email['to_example'] ?? '')) ?>
        </p>
        <iframe
            src="<?= e(url('/campaigns/' . $cid . '/preview/email')) ?>"
            title="E-posta önizleme"
            class="w-100 border rounded bg-white"
            style="min-height:520px;"
            loading="lazy"></iframe>
    </div>
    <div class="tab-pane fade" id="pane-flow" role="tabpanel">
        <p class="small text-muted mb-2">
            Kullanıcı e-postadaki bağlantıya tıkladığında açılacak sayfa
            (<em><?= e($flowLabel) ?></em>).
        </p>
        <iframe
            src="<?= e(url('/campaigns/' . $cid . '/preview/flow')) ?>"
            title="Tıklama akışı önizleme"
            class="w-100 border rounded bg-white"
            style="min-height:640px;"
            loading="lazy"></iframe>
    </div>
    <?php if (!empty($flow['has_thanks_step'])): ?>
        <div class="tab-pane fade" id="pane-thanks" role="tabpanel">
            <p class="small text-muted mb-2">Sahte oturum formu gönderildikten sonra gösterilen bilgilendirme.</p>
            <iframe
                src="<?= e(url('/campaigns/' . $cid . '/preview/thanks')) ?>"
                title="Teşekkür önizleme"
                class="w-100 border rounded bg-white"
                style="min-height:420px;"
                loading="lazy"></iframe>
        </div>
    <?php endif; ?>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
