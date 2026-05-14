<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);

$question_id = required_param('question_id', PARAM_INT);
$question = \local_codejudge\question_manager::get_question($question_id);
if (!$question) {
    print_error('invalidquestion', 'local_codejudge');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/teacher/add_testcase.php', ['question_id' => $question_id]));
$PAGE->set_title(get_string('addtestcase', 'local_codejudge'));
$PAGE->set_heading(get_string('addtestcase', 'local_codejudge'));
$PAGE->set_pagelayout('standard');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $input_data      = optional_param('input_data', '', PARAM_RAW);
    $expected_output = required_param('expected_output', PARAM_RAW);
    $type            = required_param('type', PARAM_ALPHA);

    $allowed_types = ['main', 'custom'];
    if (!in_array($type, $allowed_types)) {
        $type = 'main';
    }

    if (empty(trim($expected_output))) {
        $error = 'Expected output is required.';
    } else {
        $data = (object)[
            'question_id'     => $question_id,
            'input_data'      => $input_data,
            'expected_output' => $expected_output,
            'type'            => $type,
        ];
        \local_codejudge\question_manager::add_test_case($data);
        $success = 'Test case added successfully!';
    }
}

$test_cases = \local_codejudge\question_manager::get_test_cases($question_id);

echo $OUTPUT->header();
?>
<div class="container" style="max-width:700px;">
    <p class="text-muted">For question: <strong><?php echo format_string($question->title); ?></strong></p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post">
        <?php echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
        <div class="form-group">
            <label for="input_data">Input Data (leave empty if none)</label>
            <textarea id="input_data" name="input_data" class="form-control" rows="4"
                      placeholder="e.g. 5 6 7 8 2"></textarea>
        </div>
        <div class="form-group">
            <label for="expected_output">Expected Output <span class="text-danger">*</span></label>
            <textarea id="expected_output" name="expected_output" class="form-control" rows="4"
                      placeholder="e.g. 8" required></textarea>
        </div>
        <div class="form-group">
            <label for="type">Type</label>
            <select id="type" name="type" class="form-control">
                <option value="main">Main</option>
                <option value="custom">Custom</option>
            </select>
        </div>
        <button type="submit" class="btn btn-info">Add Test Case</button>
        <a href="<?php echo (new moodle_url('/local/codejudge/teacher/dashboard.php'))->out(); ?>"
           class="btn btn-secondary">Done</a>
    </form>

    <?php if (!empty($test_cases)): ?>
        <hr>
        <h5>Existing Test Cases (<?php echo count($test_cases); ?>)</h5>
        <table class="table table-sm table-bordered">
            <thead><tr><th>#</th><th>Type</th><th>Input</th><th>Expected Output</th></tr></thead>
            <tbody>
            <?php $i = 1; foreach ($test_cases as $tc): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($tc->type); ?></td>
                    <td><pre class="mb-0" style="max-height:60px;overflow:auto"><?php echo htmlspecialchars(($tc->input_data === '' || $tc->input_data === null) ? '(none)' : $tc->input_data); ?></pre></td>
                    <td><pre class="mb-0" style="max-height:60px;overflow:auto"><?php echo htmlspecialchars($tc->expected_output); ?></pre></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
echo $OUTPUT->footer();
