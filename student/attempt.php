<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:submit', $context);

$id = required_param('id', PARAM_INT);

$question = \local_codejudge\question_manager::get_question($id);
if (!$question) {
    print_error('invalidquestion', 'local_codejudge');
}

$languages = \local_codejudge\question_manager::get_languages();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/student/attempt.php', ['id' => $id]));
$PAGE->set_title(format_string($question->title));
$PAGE->set_heading(format_string($question->title));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

// Prepare boilerplate map for JS
$boilerplates = [];
foreach ($languages as $lang) {
    $boilerplates[$lang->name] = $lang->boilerplate_code ?? '';
}
$boilerplates_json = json_encode($boilerplates);

$back_url      = (new moodle_url('/local/codejudge/index.php'))->out(false);
$submit_url    = (new moodle_url('/local/codejudge/submit.php'))->out(false);
$history_url   = (new moodle_url('/local/codejudge/student/my_submissions.php'))->out(false);
?>

<style>
/* ── Page layout ────────────────────────────────── */
.cj-wrap {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    padding: 20px 10px;
    max-width: 100%; /* increase from 1400px */
    margin: 0 auto;
}

/* ── Left panel ─────────────────────────────────── */
.cj-left {
    width: 360px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 14px;
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 80px);
    overflow-y: auto;
}

.cj-question-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.09);
    overflow: hidden;
}

.cj-question-header {
    background: linear-gradient(135deg, #1a73e8, #0d47a1);
    color: #fff;
    padding: 16px 20px;
}

.cj-question-header h4 {
    margin: 0 0 4px 0;
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.3;
}

.cj-question-header .cj-marks-badge {
    display: inline-block;
    background: rgba(255,255,255,0.25);
    border-radius: 20px;
    padding: 2px 12px;
    font-size: 0.82rem;
    font-weight: 600;
}

.cj-question-body {
    padding: 18px 20px;
    font-size: 0.95rem;
    color: #333;
    line-height: 1.7;
}

.cj-question-body p { margin-bottom: 10px; }
.cj-question-body p:last-child { margin-bottom: 0; }

.cj-divider {
    border: none;
    border-top: 1px solid #e8ecf0;
    margin: 12px 0;
}

.cj-info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    color: #555;
    margin-bottom: 6px;
}

.cj-info-row:last-child { margin-bottom: 0; }

.cj-info-label {
    font-weight: 600;
    color: #1a73e8;
    min-width: 70px;
}

.cj-nav-btns {
    display: flex;
    gap: 8px;
}

.cj-nav-btns a {
    padding: 8px 14px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none !important;
    border: 1px solid #d0d7de;
    background: #f6f8fa;
    color: #333;
    transition: all 0.15s ease;
}

.cj-nav-btns a:hover {
    background: #eaeef2;
}

.cj-nav-btns a:hover { transform: translateX(2px); }

.cj-btn-back   { background: #f0f4ff; color: #1a73e8; border: 1.5px solid #c5d6f8; }
.cj-btn-hist   { background: #f0faf4; color: #1a7a4a; border: 1.5px solid #b2dfc5; }

/* ── Right panel ────────────────────────────────── */
.cj-right {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cj-editor-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.09);
    overflow: hidden;
}

.cj-editor-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 18px;
    background: #f7f9fc;
    border-bottom: 1px solid #e4e8ee;
}

.cj-editor-toolbar h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
}

.cj-lang-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cj-lang-wrap label {
    margin: 0;
    font-size: 0.88rem;
    font-weight: 600;
    color: #555;
}

.cj-lang-select {
    padding: 6px 12px;
    border: 1.5px solid #c5d6f8;
    border-radius: 6px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #1a73e8;
    background: #fff;
    cursor: pointer;
    outline: none;
}

.cj-lang-select:focus { border-color: #1a73e8; }

#ace-editor {
    height: 420px;
    font-size: 14px;
    line-height: 1.6;
}

.cj-editor-footer {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: #f7f9fc;
    border-top: 1px solid #e4e8ee;
}

.cj-submit-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 28px;
    background: linear-gradient(135deg, #2ecc71, #1a9b4f);
    color: #fff !important;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    text-decoration: none;
}

.cj-submit-btn:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.cj-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.cj-status-text {
    font-size: 0.88rem;
    color: #888;
    font-style: italic;
}

/* ── Results section ────────────────────────────── */
.cj-results-card {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.09);
    overflow: hidden;
}

.cj-results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #f7f9fc;
    border-bottom: 1px solid #e4e8ee;
}

.cj-results-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
}

.cj-results-body {
    padding: 16px 18px;
}

.cj-score-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-weight: 700;
    font-size: 1.05rem;
}

.cj-score-success { background: #e8f8ee; color: #1a7a4a; border: 1.5px solid #a8dfc0; }
.cj-score-partial  { background: #fff8e1; color: #8a6000; border: 1.5px solid #ffe082; }
.cj-score-zero     { background: #fdecea; color: #b71c1c; border: 1.5px solid #f5c6cb; }

.cj-tc-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.cj-tc {
    border-radius: 8px;
    overflow: hidden;
    border: 1.5px solid #e0e0e0;
}

.cj-tc-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    font-weight: 700;
    font-size: 0.92rem;
}

.cj-tc-pass .cj-tc-header { background: #e8f8ee; color: #1a7a4a; border-bottom: 1.5px solid #a8dfc0; }
.cj-tc-fail .cj-tc-header { background: #fdecea; color: #b71c1c; border-bottom: 1.5px solid #f5c6cb; }

.cj-verdict-badge {
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.cj-tc-pass .cj-verdict-badge { background: #1a7a4a; color: #fff; }
.cj-tc-fail .cj-verdict-badge { background: #b71c1c; color: #fff; }

.cj-tc-body {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0;
    background: #fafbfc;
}

.cj-tc-col {
    padding: 12px 14px;
    border-right: 1px solid #eee;
    font-size: 0.85rem;
}

.cj-tc-col:last-child { border-right: none; }

.cj-tc-col-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #888;
    margin-bottom: 6px;
}

.cj-tc-col pre {
    margin: 0;
    font-family: 'Courier New', monospace;
    font-size: 0.88rem;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
    padding: 6px 10px;
    white-space: pre-wrap;
    word-break: break-word;
    color: #1a1a2e;
    max-height: 100px;
    overflow-y: auto;
    min-height: 32px;
}

.cj-tc-none { color: #aaa; font-style: italic; font-size: 0.82rem; }

/* ── Responsive ─────────────────────────────────── */
@media (max-width: 900px) {
    .cj-wrap { flex-direction: column; }
    .cj-left { width: 100%; position: static; max-height: none; }
    .cj-tc-body { grid-template-columns: 1fr; }
    .cj-tc-col { border-right: none; border-bottom: 1px solid #eee; }
    .cj-tc-col:last-child { border-bottom: none; }
}
</style>

<div class="cj-wrap">

    <!-- ── LEFT PANEL ── -->
    <div class="cj-left">

        <!-- Question card -->
        <div class="cj-question-card">
            <div class="cj-question-header">
                <h4><?php echo format_string($question->title); ?></h4>
                <span class="cj-marks-badge">&#9733; <?php echo (int)$question->marks; ?> Marks</span>
            </div>
            <div class="cj-question-body">
                <?php echo format_text($question->description, FORMAT_PLAIN, ['trusted' => false, 'noclean' => false]); ?>
                <hr class="cj-divider">
                <div class="cj-info-row">
                    <span class="cj-info-label">Max Marks</span>
                    <span><?php echo (int)$question->marks; ?></span>
                </div>
                <div class="cj-info-row">
                    <span class="cj-info-label">Languages</span>
                    <span>
                        <?php foreach ($languages as $lang): ?>
                            <span style="display:inline-block;background:#e8f0fe;color:#1a73e8;
                                         border-radius:4px;padding:1px 8px;font-size:0.78rem;
                                         font-weight:600;margin:1px;">
                                <?php echo htmlspecialchars(strtoupper($lang->name)); ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Navigation buttons -->
        <div class="cj-nav-btns">
            <a href="<?php echo $back_url; ?>" class="cj-btn-back">
                &#8592; Back to Questions
            </a>
            <a href="<?php echo $history_url; ?>" class="cj-btn-hist">
                &#128196; My Submissions
            </a>
        </div>

    </div>

    <!-- ── RIGHT PANEL ── -->
    <div class="cj-right">

        <!-- Editor card -->
        <div class="cj-editor-card">
            <div class="cj-editor-toolbar">
                <h5>&#128187; Code Editor</h5>
                <div class="cj-lang-wrap">
                    <label for="language-select">Language:</label>
                    <select id="language-select" class="cj-lang-select">
                        <?php foreach ($languages as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang->name); ?>">
                                <?php echo htmlspecialchars(strtoupper($lang->name)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="ace-editor"><?php echo htmlspecialchars($boilerplates['python'] ?? ''); ?></div>

            <div class="cj-editor-footer">
                <button id="submit-btn" class="cj-submit-btn" onclick="submitCode()">
                    &#9654; Submit Code
                </button>
                <span id="submit-status" class="cj-status-text"></span>
            </div>
        </div>

        <!-- Results card (hidden until submission) -->
        <div id="results-section" class="cj-results-card" style="display:none;">
            <div class="cj-results-header">
                <h5>&#9989; Results</h5>
                <span id="results-summary" style="font-size:0.88rem;color:#555;"></span>
            </div>
            <div class="cj-results-body" id="results-body"></div>
        </div>

    </div>
</div>

<!-- Ace Editor from CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/ace.js"></script>
<script>
var editor;
var boilerplates = <?php echo $boilerplates_json; ?>;

document.addEventListener('DOMContentLoaded', function () {

    // Fix Ace + Moodle RequireJS conflict
    if (typeof ace !== 'undefined') {
        ace.config.set('basePath',   'https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/');
        ace.config.set('workerPath', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/');
        ace.config.set('modePath',   'https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/');
        ace.config.set('themePath',  'https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/');
    }

    editor = ace.edit('ace-editor');
    editor.setTheme('ace/theme/monokai');
    editor.session.setMode('ace/mode/python');
    editor.setValue(boilerplates['python'] || '', -1);
    editor.setOptions({
        fontSize:       '14px',
        showPrintMargin: false,
        wrap:            true
    });

    document.getElementById('language-select').addEventListener('change', function () {
        var lang = this.value;
        var modeMap = {
            'python': 'ace/mode/python',
            'c':      'ace/mode/c_cpp',
            'cpp':    'ace/mode/c_cpp',
            'java':   'ace/mode/java'
        };
        editor.session.setMode(modeMap[lang] || 'ace/mode/text');
        editor.setValue(boilerplates[lang] || '', -1);
    });
});

function submitCode() {
    var btn    = document.getElementById('submit-btn');
    var status = document.getElementById('submit-status');

    btn.disabled      = true;
    status.textContent = '⏳ Running your code...';

    var code     = editor.getValue();
    var language = document.getElementById('language-select').value;

    var formData = new FormData();
    formData.append('question_id', '<?php echo (int)$question->id; ?>');
    formData.append('language',    language);
    formData.append('code',        code);
    formData.append('sesskey',     M.cfg.sesskey);

    fetch('<?php echo $submit_url; ?>', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled      = false;
            status.textContent = '';
            displayResults(data);
        })
        .catch(function (err) {
            btn.disabled      = false;
            status.textContent = '⚠ Error: ' + err.message;
        });
}

function displayResults(data) {
    var section = document.getElementById('results-section');
    var body    = document.getElementById('results-body');
    var summary = document.getElementById('results-summary');

    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (data.error) {
        body.innerHTML = '<div style="padding:14px;background:#fdecea;border-radius:8px;color:#b71c1c;">'
                       + '<strong>⚠ Error:</strong> ' + escHtml(data.error) + '</div>';
        summary.textContent = '';
        return;
    }

    var results   = data.results || [];
    var passed    = results.filter(function (r) { return r.result === 'PASS'; }).length;
    var total     = results.length;
    var scoreHtml = '';

    if (data.total_marks !== undefined) {
        var cls = data.total_marks >= data.max_marks ? 'cj-score-success'
                : (data.total_marks > 0 ? 'cj-score-partial' : 'cj-score-zero');
        var icon = data.total_marks >= data.max_marks ? '🎉' : (data.total_marks > 0 ? '📊' : '❌');
        scoreHtml = '<div class="cj-score-bar ' + cls + '">'
                  + icon + ' Score: <strong>' + data.total_marks + ' / ' + data.max_marks + ' marks</strong>'
                  + '&nbsp;&nbsp;|&nbsp;&nbsp;' + passed + ' / ' + total + ' test cases passed'
                  + '</div>';
        summary.textContent = passed + '/' + total + ' passed';
    }

    var tcHtml = '<div class="cj-tc-list">';
    results.forEach(function (r, i) {
        var pass = r.result === 'PASS';
        var cls  = pass ? 'cj-tc-pass' : 'cj-tc-fail';
        var icon = pass ? '✔' : '✘';

        var inputVal    = r.input    ? escHtml(r.input)           : '<span class="cj-tc-none">(no input)</span>';
        var expectedVal = r.expected_output ? escHtml(r.expected_output) : '<span class="cj-tc-none">(empty)</span>';
        var actualVal   = r.student_output  ? escHtml(r.student_output)  : '<span class="cj-tc-none">(no output)</span>';

        tcHtml += '<div class="cj-tc ' + cls + '">';
        tcHtml +=   '<div class="cj-tc-header">'
                  +   icon + ' Test Case ' + (i + 1)
                  +   '&nbsp;&nbsp;<span class="cj-verdict-badge">' + r.result + '</span>'
                  + '</div>';
        tcHtml +=   '<div class="cj-tc-body">';
        tcHtml +=     '<div class="cj-tc-col"><div class="cj-tc-col-label">Input</div><pre>' + inputVal + '</pre></div>';
        tcHtml +=     '<div class="cj-tc-col"><div class="cj-tc-col-label">Expected Output</div><pre>' + expectedVal + '</pre></div>';
        tcHtml +=     '<div class="cj-tc-col"><div class="cj-tc-col-label">Your Output</div><pre>' + actualVal + '</pre></div>';
        tcHtml +=   '</div>';
        tcHtml += '</div>';
    });
    tcHtml += '</div>';

    body.innerHTML = scoreHtml + tcHtml;
}

function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}
</script>

<?php echo $OUTPUT->footer(); ?>