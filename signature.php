<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib.php');

$userid = optional_param('userid', $USER->id, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);

$user    = \core_user::get_user($userid, '*', MUST_EXIST);
$context = \core\context\user::instance($userid);

require_login();
if ($userid != $USER->id) {
    require_capability('moodle/user:editprofile', $context);
}

$signaturefonts = certificatebeautiful_signature_fonts();
$defaultfont    = certificatebeautiful_signature_default_font();

if ($delete) {
    require_sesskey();
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_certificatebeautiful', 'signature');
    $DB->delete_records('certificatebeautiful_usersignature', ['userid' => $userid]);
    redirect(
        new \moodle_url('/mod/certificatebeautiful/signature.php', ['userid' => $userid]),
        get_string('signaturedeleted', 'certificatebeautiful'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $fontslug = required_param('selectedfont', PARAM_ALPHA);
    $sigtext  = required_param('signaturetext', PARAM_TEXT);
    $imagedata = required_param('imagedata', PARAM_RAW);

    if (!array_key_exists($fontslug, $signaturefonts)) {
        throw new \moodle_exception('invalidfont', 'certificatebeautiful');
    }
    $sigtext = preg_replace('/[^A-Za-zÀ-ÖØ-öø-ÿ\s\-\.]/u', '', $sigtext);
    $sigtext = trim(mb_substr($sigtext, 0, 60));
    if (mb_strlen($sigtext) < 2) {
        throw new \moodle_exception('invalidtext', 'certificatebeautiful');
    }
    if (!preg_match('/^data:image\/png;base64,(.+)$/s', $imagedata, $m)) {
        throw new \moodle_exception('invalidtext', 'certificatebeautiful');
    }
    $pngdata = base64_decode($m[1]);
    if (!$pngdata || strlen($pngdata) < 100) {
        throw new \moodle_exception('invalidtext', 'certificatebeautiful');
    }
    $img = @imagecreatefromstring($pngdata);
    if (!$img) {
        throw new \moodle_exception('invalidtext', 'certificatebeautiful');
    }
    imagedestroy($img);

    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_certificatebeautiful', 'signature');
    $fs->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'mod_certificatebeautiful',
        'filearea'  => 'signature',
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => 'signature.png',
    ], $pngdata);

    certificatebeautiful_signature_save_meta($userid, $fontslug, $sigtext);

    redirect(
        new \moodle_url('/mod/certificatebeautiful/signature.php', ['userid' => $userid]),
        get_string('signaturesaved', 'certificatebeautiful'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/mod/certificatebeautiful/signature.php', ['userid' => $userid]));
$PAGE->set_title(get_string('mysignature', 'certificatebeautiful'));
$PAGE->set_heading(fullname($user));
$PAGE->set_pagelayout('standard');

$meta          = certificatebeautiful_get_signature_meta($userid);
$currenturl   = certificatebeautiful_get_signature_url($userid);
$defaulttext  = $meta['text'] ?: fullname($user);
$selectedfont = $meta['font'] ?: $defaultfont;
if (!array_key_exists($selectedfont, $signaturefonts)) {
    $selectedfont = $defaultfont;
}

$fontfaces = '';
foreach ($signaturefonts as $slug => $info) {
    $url = (new \moodle_url('/mod/certificatebeautiful/font.php', ['font' => $slug]))->out(false);
    $fontfaces .= sprintf(
        "@font-face { font-family: %s; src: url('%s'); font-display: swap; }\n",
        trim(explode(',', $info['family'])[0]),
        $url
    );
}

$currentsrc = $currenturl
    ? $currenturl->out(false, ['rev' => $meta['timemodified']])
    : '';

echo $OUTPUT->header();
?>
<style>
<?= $fontfaces ?>
.sig-wrap           { max-width: 780px; margin: 0 auto; padding: 0 16px 48px; }
.sig-section-label  { font-size: .92rem; font-weight: 600; color: #475569; margin: 24px 0 8px; }
.sig-name-input     {
    display: block; width: 100%; box-sizing: border-box;
    padding: 10px 14px; font-size: 1.1rem;
    border: 2px solid #cbd5e1; border-radius: 8px;
    outline: none; transition: border-color .2s;
}
.sig-name-input:focus { border-color: #3b82f6; }
.sig-hint           { font-size: .78rem; color: #94a3b8; margin: 4px 0 0; }
.sig-grid           { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 4px; }
@media (max-width: 520px) { .sig-grid { grid-template-columns: 1fr; } }
.sig-card           {
    border: 2px solid #e2e8f0; border-radius: 12px;
    padding: 14px 10px 10px; cursor: pointer;
    background: #fff; transition: border-color .18s, box-shadow .18s;
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    user-select: none;
}
.sig-card:hover     { border-color: #93c5fd; box-shadow: 0 2px 8px rgba(59,130,246,.14); }
.sig-card.selected  { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,.16); }
.sig-card input     { display: none; }
.sig-font-label     { font-size: .68rem; font-weight: 700; color: #64748b; letter-spacing: .06em; text-transform: uppercase; }
.sig-canvas         { width: 100%; height: 68px; display: block; }
.sig-current        {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;
    padding: 16px; margin-bottom: 4px; text-align: center; min-height: 56px;
}
.sig-current img    { max-height: 64px; }
.sig-actions        { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; align-items: center; }
.sig-btn-save       {
    background: #2563eb; color: #fff; border: none; border-radius: 8px;
    padding: 11px 28px; font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background .18s;
}
.sig-btn-save:hover { background: #1d4ed8; }
.sig-btn-delete     {
    background: #fff; color: #dc2626; border: 2px solid #fca5a5;
    border-radius: 8px; padding: 9px 20px; font-size: .9rem; font-weight: 600;
    cursor: pointer; transition: background .18s; text-decoration: none;
    display: inline-flex; align-items: center;
}
.sig-btn-delete:hover { background: #fef2f2; color: #dc2626; text-decoration: none; }
</style>

<div class="sig-wrap">
    <?= $OUTPUT->heading(get_string('mysignature', 'certificatebeautiful'), 2) ?>

    <?php if ($currenturl): ?>
    <p class="sig-section-label"><?= get_string('currentsignature', 'certificatebeautiful') ?></p>
    <div class="sig-current">
        <img src="<?= s($currentsrc) ?>" alt="<?= s(get_string('currentsignature', 'certificatebeautiful')) ?>">
    </div>
    <?php endif ?>

    <form id="sig-form" method="post" action="">
        <input type="hidden" name="sesskey"      value="<?= sesskey() ?>">
        <input type="hidden" name="userid"       value="<?= (int)$userid ?>">
        <input type="hidden" name="imagedata"    id="sig-imagedata"    value="">
        <input type="hidden" name="selectedfont" id="sig-selectedfont" value="<?= s($selectedfont) ?>">

        <p class="sig-section-label"><?= get_string('signaturetext', 'certificatebeautiful') ?></p>
        <input type="text" id="sig-text-input" class="sig-name-input"
               name="signaturetext"
               value="<?= s($defaulttext) ?>"
               maxlength="60"
               placeholder="<?= s(fullname($user)) ?>">
        <p class="sig-hint"><?= get_string('signaturetext_help', 'certificatebeautiful') ?></p>

        <p class="sig-section-label" style="margin-top:20px;">
            <?= get_string('choosestyle', 'certificatebeautiful') ?>
        </p>

        <div class="sig-grid">
        <?php foreach ($signaturefonts as $slug => $info): ?>
            <label class="sig-card <?= ($slug === $selectedfont) ? 'selected' : '' ?>"
                   id="card-<?= $slug ?>" data-font="<?= $slug ?>">
                <span class="sig-font-label"><?= s($info['label']) ?></span>
                <canvas class="sig-canvas" id="canvas-<?= $slug ?>" width="340" height="68"></canvas>
                <input type="radio" name="fontstyle" value="<?= $slug ?>"
                       <?= ($slug === $selectedfont) ? 'checked' : '' ?>>
            </label>
        <?php endforeach ?>
        </div>

        <div class="sig-actions">
            <button type="submit" class="sig-btn-save">
                <?= get_string('savesignature', 'certificatebeautiful') ?>
            </button>
            <?php if ($currenturl): ?>
            <a href="<?= (new \moodle_url('/mod/certificatebeautiful/signature.php', [
                'userid'  => $userid,
                'delete'  => 1,
                'sesskey' => sesskey(),
            ]))->out() ?>"
               class="sig-btn-delete"
               onclick="return confirm('<?= s(get_string('confirmdelete', 'certificatebeautiful')) ?>')">
                <?= get_string('deletesignature', 'certificatebeautiful') ?>
            </a>
            <?php endif ?>
        </div>
    </form>
</div>

<script>
(function () {
    'use strict';

    const FONTS = <?= json_encode($signaturefonts) ?>;
    const W = 340, H = 68;

    let currentFont = <?= json_encode($selectedfont) ?>;
    let currentText = <?= json_encode($defaulttext) ?>;

    function clean(t) {
        return (t || '').replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s\-\.]/g, '').substring(0, 60).trim() || ' ';
    }

    function drawCanvas(slug, text) {
        const info   = FONTS[slug];
        const canvas = document.getElementById('canvas-' + slug);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, W, H);

        const safe = clean(text);
        let fs = info.size;
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle     = info.color;
        ctx.textBaseline  = 'middle';
        ctx.textAlign     = 'center';
        ctx.fillText(safe, W / 2, H / 2);
    }

    function drawAll() {
        Object.keys(FONTS).forEach(s => drawCanvas(s, currentText));
    }

    function buildPng() {
        const info = FONTS[currentFont];
        const hd   = document.createElement('canvas');
        hd.width   = W * 2;
        hd.height  = H * 2;
        const ctx  = hd.getContext('2d');
        ctx.scale(2, 2);

        const safe = clean(currentText);
        let fs = info.size;
        ctx.font = fs + 'px ' + info.family;
        while (ctx.measureText(safe).width > W - 24 && fs > 22) {
            fs -= 2;
            ctx.font = fs + 'px ' + info.family;
        }
        ctx.fillStyle    = info.color;
        ctx.textBaseline = 'middle';
        ctx.textAlign    = 'center';
        ctx.fillText(safe, W / 2, H / 2);
        return hd.toDataURL('image/png');
    }

    document.querySelectorAll('.sig-card').forEach(card => {
        card.addEventListener('click', function () {
            currentFont = this.dataset.font;
            document.querySelectorAll('.sig-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('sig-selectedfont').value = currentFont;
        });
    });

    let timer;
    document.getElementById('sig-text-input').addEventListener('input', function () {
        currentText = this.value;
        clearTimeout(timer);
        timer = setTimeout(drawAll, 100);
    });

    document.getElementById('sig-form').addEventListener('submit', function (e) {
        const png = buildPng();
        document.getElementById('sig-imagedata').value = png;
        document.getElementById('sig-selectedfont').value = currentFont;
    });

    if (document.fonts && document.fonts.load) {
        Promise.all(
            Object.values(FONTS).map(f => document.fonts.load('40px ' + f.family))
        ).then(drawAll).catch(drawAll);
    } else {
        setTimeout(drawAll, 800);
    }
}());
</script>
<?php
echo $OUTPUT->footer();
