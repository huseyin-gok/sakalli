<?php
/** @var string $title */
/** @var array<string, string> $categories */
/** @var array<string, mixed>|null $tpl */
/** @var string $csrf */
/** @var string|null $error */
/** @var array<string, mixed>|null $old */
/** @var list<array<string, mixed>> $email_presets */
require dirname(__DIR__) . '/layouts/main.php';

$old = $old ?? [];
$email_presets = $email_presets ?? [];
$lv = $tpl['latest_version'] ?? null;
$name = $tpl['name'] ?? ($old['name'] ?? '');
$cat = $tpl['category'] ?? ($old['category'] ?? 'other');
$desc = $tpl['description'] ?? ($old['description'] ?? '');
$subject = $lv['subject'] ?? ($old['subject'] ?? '');
$html = $lv['body_html'] ?? ($old['body_html'] ?? '');
$plain = $lv['body_plain'] ?? ($old['body_plain'] ?? '');
$isActive = isset($tpl['is_active']) ? (bool) $tpl['is_active'] : true;
$isEdit = $tpl !== null;
$action = $isEdit ? url('/templates/edit/' . (int) $tpl['id']) : url('/templates/create');
$presetsJson = json_encode($email_presets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
?>
<h1 class="h4 mb-3"><?= e($title) ?></h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($isEdit): ?>
    <?php if (!empty($_GET['saved'])): ?>
        <div class="alert alert-success py-2">Şablon kaydedildi.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['test_sent'])): ?>
        <div class="alert alert-success py-2">Test e-postası gönderildi.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['test_err'])): ?>
        <?php
        $terr = (string) $_GET['test_err'];
        $tmsg = match ($terr) {
            'smtp' => 'SMTP gönderimi başarısız. Ayarlar → SMTP veya .env (SMTP_HOST, SMTP_USER, SMTP_PASSWORD) değerlerini kontrol edin.',
            'invalid_email' => 'Geçersiz alıcı e-posta adresi.',
            'no_version' => 'Şablon sürümü bulunamadı.',
            default => 'Gönderim hatası.',
        };
        ?>
        <div class="alert alert-danger py-2"><?= e($tmsg) ?></div>
    <?php endif; ?>
<?php endif; ?>
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
        <strong class="small">Hazır şablon</strong>
        <select id="email-preset-select" class="form-select form-select-sm" style="max-width: 280px;">
            <option value="">— Örnek seçin (internet / eğitim örnekleri) —</option>
            <?php foreach ($email_presets as $p): ?>
                <option value="<?= e((string) $p['id']) ?>"><?= e((string) ($p['name'] ?? $p['id'])) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="email-preset-apply">Yükle</button>
        <span class="small text-muted ms-auto">Outlook uyumu için tablolar korunur; gönderimde değişkenler otomatik dolar.</span>
    </div>
    <div class="card-body py-2 border-top">
        <p id="preset-from-hint" class="small text-warning mb-0 d-none"></p>
        <p class="small text-muted mb-0">Agresif taklit şablonlarında <strong>suggested_from_name</strong> kampanya «görünen gönderen» alanına kopyalanmalıdır.</p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-sm btn-outline-dark" id="btn-preview-draft">Taslak önizle (yeni sekme)</button>
    <?php if ($isEdit): ?>
        <a class="btn btn-sm btn-outline-dark" href="<?= url('/templates/' . (int) $tpl['id'] . '/preview') ?>" target="_blank" rel="noopener">Kayıtlı önizle</a>
    <?php endif; ?>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="small text-muted">Değişkenler (gönderimde değiştirilir): <code>{{ad_soyad}}</code> <code>{{ad}}</code> <code>{{soyad}}</code> <code>{{benzersiz_link}}</code> <code>{{eposta}}</code> <code>{{departman}}</code> <code>{{kurum_adi}}</code> <code>{{kampanya_adi}}</code></p>
        <form method="post" action="<?= e($action) ?>" id="template-form">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Ad</label>
                    <input type="text" name="name" id="tpl_name" class="form-control" required value="<?= e((string) $name) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kategori</label>
                    <select name="category" id="tpl_category" class="form-select">
                        <?php foreach ($categories as $k => $label): ?>
                            <option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Açıklama</label>
                    <input type="text" name="description" class="form-control" value="<?= e((string) $desc) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">E-posta konusu</label>
                    <input type="text" name="subject" id="tpl_subject" class="form-control" required value="<?= e((string) $subject) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">HTML gövde <span class="text-muted fw-normal">(TinyMCE — ücretsiz CDN)</span></label>
                    <textarea name="body_html" id="body_html" class="form-control" rows="14"><?= e((string) $html) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Düz metin (boş bırakılırsa HTML’den türetilir)</label>
                    <textarea name="body_plain" id="tpl_plain" class="form-control font-monospace" rows="5"><?= e((string) $plain) ?></textarea>
                </div>
                <?php if ($isEdit): ?>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="act" <?= $isActive ? 'checked' : '' ?>>
                            <label class="form-check-label" for="act">Aktif</label>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Kaydet</button>
                <a class="btn btn-outline-secondary" href="<?= url('/templates') ?>">İptal</a>
            </div>
        </form>
    </div>
</div>
<?php if ($isEdit): ?>
<div class="card shadow-sm mt-4">
    <div class="card-header py-2"><strong>Test e-postası</strong></div>
    <div class="card-body">
        <p class="small text-muted mb-3">İçerik, önizlemedeki gibi <strong>örnek değişkenlerle</strong> doldurulur ve SMTP üzerinden gönderilir. Konu satırına <code>[Sakallı test]</code> öneki eklenir.</p>
        <form method="post" action="<?= url('/templates/' . (int) $tpl['id'] . '/test-email') ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="col-md-7">
                <label class="form-label">Alıcı e-posta</label>
                <input type="email" name="test_email" class="form-control" required placeholder="ornek@kurum.com">
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-success">Test gönder</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<script type="application/json" id="email-presets-json"><?= $presetsJson ?></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    var draftPreviewUrl = <?= json_encode(url('/templates/preview-draft'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var csrfVal = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var presets = [];
    try {
        var el = document.getElementById('email-presets-json');
        if (el && el.textContent) presets = JSON.parse(el.textContent);
    } catch (e) { presets = []; }

    tinymce.init({
        selector: '#body_html',
        height: 480,
        menubar: 'edit insert view format table tools',
        plugins: 'link lists table code fullscreen autoresize image',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link table | forecolor removeformat | code fullscreen',
        branding: false,
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        valid_children: '+body[style]',
        verify_html: false,
        convert_urls: false,
        content_style: 'body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; max-width: 640px; margin: 12px auto; padding: 8px; } table { max-width: 100%; }',
        language: 'tr_TR',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@23.10.5/langs6/tr_TR.js',
        setup: function (editor) {
            editor.on('change input', function () { editor.save(); });
        }
    });

    document.getElementById('email-preset-apply').addEventListener('click', function () {
        var sel = document.getElementById('email-preset-select');
        var id = sel.value;
        if (!id) return;
        var p = presets.find(function (x) { return x.id === id; });
        if (!p) return;
        if (!confirm('Seçilen örnek, mevcut konu ve HTML alanlarının üzerine yazılacak. Devam edilsin mi?')) return;
        var subj = document.getElementById('tpl_subject');
        var nm = document.getElementById('tpl_name');
        var cat = document.getElementById('tpl_category');
        var plain = document.getElementById('tpl_plain');
        if (p.subject) subj.value = p.subject;
        if (p.suggested_name) nm.value = p.suggested_name;
        if (p.category && cat.querySelector('option[value="' + p.category + '"]')) cat.value = p.category;
        if (p.plain) plain.value = p.plain;
        var ed = tinymce.get('body_html');
        if (ed) ed.setContent(p.html || '');
        var hint = document.getElementById('preset-from-hint');
        if (hint) {
            if (p.suggested_from_name) {
                hint.textContent = 'Kampanya oluştururken görünen gönderen adı önerisi: «' + p.suggested_from_name + '» (Ayarlar veya kampanya detayından SMTP_FROM_NAME).';
                hint.classList.remove('d-none');
            } else {
                hint.classList.add('d-none');
            }
        }
    });

    document.getElementById('email-preset-select').addEventListener('change', function () {
        var id = this.value;
        var hint = document.getElementById('preset-from-hint');
        if (!hint) return;
        if (!id) { hint.classList.add('d-none'); return; }
        var p = presets.find(function (x) { return x.id === id; });
        if (p && p.suggested_from_name) {
            hint.textContent = 'Önerilen görünen gönderen: «' + p.suggested_from_name + '»';
            hint.classList.remove('d-none');
        } else {
            hint.classList.add('d-none');
        }
    });

    document.getElementById('template-form').addEventListener('submit', function (e) {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        var ta = document.getElementById('body_html');
        if (!ta || ta.value.trim() === '') {
            e.preventDefault();
            alert('HTML gövde boş olamaz. Editörde içerik olduğundan emin olun.');
            return;
        }
    });

    document.getElementById('btn-preview-draft').addEventListener('click', function () {
        if (typeof tinymce !== 'undefined') tinymce.triggerSave();
        var f = document.createElement('form');
        f.method = 'POST';
        f.action = draftPreviewUrl;
        f.target = '_blank';
        var c = document.createElement('input');
        c.type = 'hidden';
        c.name = '_csrf';
        c.value = csrfVal;
        f.appendChild(c);
        var b = document.createElement('input');
        b.type = 'hidden';
        b.name = 'body_html';
        b.value = document.getElementById('body_html').value;
        f.appendChild(b);
        document.body.appendChild(f);
        f.submit();
        document.body.removeChild(f);
    });
})();
</script>
<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
