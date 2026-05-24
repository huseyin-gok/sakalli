<?php
/**
 * TinyMCE başlatma + preset verisi (textarea’dan sonra yükleyin).
 *
 * @var string $landing_presets_json
 * @var string $textarea_id
 * @var string $form_id
 * @var string $title_input_id
 * @var string $name_input_id
 * @var string $slug_input_id
 * @var string $credential_input_id
 */
$name_input_id = $name_input_id ?? '';
$slug_input_id = $slug_input_id ?? '';
$credential_input_id = $credential_input_id ?? '';
?>
<script type="application/json" id="landing-presets-data"><?= $landing_presets_json ?></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    var textareaId = <?= json_encode($textarea_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var formId = <?= json_encode($form_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var titleInputId = <?= json_encode($title_input_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var nameInputId = <?= json_encode($name_input_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var slugInputId = <?= json_encode($slug_input_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;
    var credentialInputId = <?= json_encode($credential_input_id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>;

    var presets = [];
    try {
        var raw = document.getElementById('landing-presets-data');
        if (raw && raw.textContent) presets = JSON.parse(raw.textContent);
    } catch (e) { presets = []; }

    var sel = document.getElementById('landing-preset-select');
    if (sel && presets.length) {
        presets.forEach(function (p) {
            var o = document.createElement('option');
            o.value = p.id;
            o.textContent = p.name || p.id;
            sel.appendChild(o);
        });
    }

    tinymce.init({
        selector: '#' + textareaId,
        height: 420,
        menubar: 'edit insert view format tools',
        plugins: 'link lists code fullscreen autoresize',
        toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link | forecolor removeformat | code fullscreen',
        branding: false,
        valid_elements: '*[*]',
        extended_valid_elements: '*[*]',
        verify_html: false,
        convert_urls: false,
        content_style: 'body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 15px; max-width: 720px; margin: 12px auto; padding: 8px; line-height: 1.5; }',
        language: 'tr_TR',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@23.10.5/langs6/tr_TR.js',
        setup: function (editor) {
            editor.on('change input', function () { editor.save(); });
        }
    });

    document.getElementById('landing-preset-apply').addEventListener('click', function () {
        if (!sel) return;
        var id = sel.value;
        if (!id) return;
        var p = presets.find(function (x) { return x.id === id; });
        if (!p) return;
        if (!confirm('Seçilen hazır şablon, mevcut içeriğin üzerine yazılacak. Devam edilsin mi?')) return;
        if (titleInputId) {
            var ti = document.getElementById(titleInputId);
            if (ti && p.page_title) ti.value = p.page_title;
        }
        if (nameInputId) {
            var ni = document.getElementById(nameInputId);
            if (ni && p.suggested_name && !ni.value.trim()) ni.value = p.suggested_name;
        }
        if (slugInputId) {
            var si = document.getElementById(slugInputId);
            if (si && p.suggested_slug && !si.value.trim()) si.value = p.suggested_slug;
        }
        var ed = tinymce.get(textareaId);
        if (ed) ed.setContent(p.html || '');
        if (credentialInputId) {
            var credEl = document.getElementById(credentialInputId);
            if (credEl && typeof p.credential_capture !== 'undefined') {
                credEl.checked = !!p.credential_capture;
                if (credEl.checked) {
                    var sff = document.getElementById('sff');
                    if (sff) sff.checked = false;
                }
            }
        }
    });

    var frm = document.getElementById(formId);
    if (frm) {
        frm.addEventListener('submit', function () {
            if (typeof tinymce !== 'undefined') tinymce.triggerSave();
        });
    }
})();
</script>
