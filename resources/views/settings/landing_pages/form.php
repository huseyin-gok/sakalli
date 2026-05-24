<?php
/** @var string $title */
/** @var array<string, mixed>|null $page */
/** @var string $csrf */
/** @var string|null $error */
/** @var string $landing_presets_json */
$landing_presets_json = $landing_presets_json ?? '[]';
require dirname(__DIR__, 2) . '/layouts/main.php';

$isEdit = $page !== null && isset($page['id']);
$name = (string) ($page['name'] ?? '');
$slug = (string) ($page['slug'] ?? '');
$pageTitle = (string) ($page['page_title'] ?? '');
$html = (string) ($page['content_html'] ?? '');
$showFb = !empty($page['show_feedback_form']);
$credCap = !empty($page['credential_capture']);
?>
<div class="mb-3">
    <a class="small" href="<?= url('/settings/landing-pages') ?>">← Bilgilendirme şablonları</a>
</div>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= $isEdit ? e(url('/settings/landing-pages/edit/' . (int) $page['id'])) : e(url('/settings/landing-pages/create')) ?>" id="landing-page-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="mb-3">
                <label class="form-label">Görünen ad</label>
                <input type="text" name="name" id="lp_name" class="form-control" value="<?= e($name) ?>" required maxlength="191">
            </div>
            <div class="mb-3">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" id="lp_slug" class="form-control font-monospace" value="<?= e($slug) ?>" required maxlength="128">
                <div class="form-text">Benzersiz kısa kod (ör. <code>phishing-egitim-2026</code>). Hazır şablon yüklerken boşsa öneri doldurulur.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Sayfa başlığı (<code>&lt;title&gt;</code>)</label>
                <input type="text" name="page_title" id="lp_page_title" class="form-control" value="<?= e($pageTitle) ?>" maxlength="255"
                       placeholder="Boş bırakılırsa varsayılan başlık kullanılır">
            </div>
            <?php require dirname(__DIR__, 2) . '/partials/tinymce_landing_toolbar.php'; ?>
            <div class="mb-3">
                <label class="form-label">İçerik (HTML) <span class="text-muted fw-normal">(TinyMCE)</span></label>
                <textarea name="content_html" id="content_html" class="form-control" rows="14"><?= e($html) ?></textarea>
                <div class="form-text">Takip linki sayfasında, formun üstünde gösterilir. <strong>Kimlik yakalama</strong> modunda isteğe bağlıdır (hazır şablon önerilir).</div>
            </div>
            <?php
            $textarea_id = 'content_html';
            $form_id = 'landing-page-form';
            $title_input_id = 'lp_page_title';
            $name_input_id = 'lp_name';
            $slug_input_id = 'lp_slug';
            $credential_input_id = 'lp_credential_capture';
            require dirname(__DIR__, 2) . '/partials/tinymce_landing_scripts.php';
            ?>
            <div class="mb-3 form-check">
                <input type="checkbox" name="credential_capture" value="1" class="form-check-input" id="lp_credential_capture" <?= $credCap ? 'checked' : '' ?>>
                <label class="form-check-label" for="lp_credential_capture"><strong>Kimlik yakalama simülasyonu</strong> — kullanıcı adı ve parola formu (bilgilendirme yerine)</label>
                <div class="form-text text-danger small">Yalnızca yetkili simülasyon kapsamında kullanın. Girilen parolalar veritabanında düz metin saklanır; erişimi sınırlayın.</div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="show_feedback_form" value="1" class="form-check-input" id="sff" <?= $showFb ? 'checked' : '' ?>>
                <label class="form-check-label" for="sff">Bilgilendirme sayfasında geri bildirim formu göster (kimlik modu kapalıyken)</label>
            </div>
            <script>
            (function () {
                var cc = document.getElementById('lp_credential_capture');
                var sff = document.getElementById('sff');
                if (!cc || !sff) return;
                cc.addEventListener('change', function () { if (cc.checked) sff.checked = false; });
                sff.addEventListener('change', function () { if (sff.checked) cc.checked = false; });
            })();
            </script>
            <button type="submit" class="btn btn-primary">Kaydet</button>
            <a class="btn btn-outline-secondary" href="<?= url('/settings/landing-pages') ?>">İptal</a>
        </form>
    </div>
</div>
<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
