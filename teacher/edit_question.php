<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);

$id = required_param('id', PARAM_INT);
$question = \local_codejudge\question_manager::get_question($id);
if (!$question) {
    print_error('invalidquestion', 'local_codejudge');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/teacher/edit_question.php', ['id' => $id]));
$PAGE->set_title(get_string('editquestion', 'local_codejudge'));
$PAGE->set_heading(get_string('editquestion', 'local_codejudge'));
$PAGE->set_pagelayout('standard');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $title       = required_param('title', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $marks       = required_param('marks', PARAM_INT);

    if (empty(trim($title))) {
        $error = 'Title is required.';
    } else {
        $data = (object)[
            'id'          => $id,
            'title'       => $title,
            'description' => $description,
            'marks'       => $marks,
        ];
        \local_codejudge\question_manager::update_question($data);
        redirect(
            new moodle_url('/local/codejudge/teacher/dashboard.php'),
            'Question updated.'
        );
    }
}

echo $OUTPUT->header();
?>
<div class="container" style="max-width:700px;">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]); ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" class="form-control"
                   value="<?php echo htmlspecialchars($question->title); ?>" required>
        </div>
        <div class="form-group">
            <label for="description">Problem Description</label>
            <textarea id="description" name="description" class="form-control" rows="8"><?php
                echo htmlspecialchars($question->description);
            ?></textarea>
        </div>
        <div class="form-group">
            <label for="marks">Marks</label>
            <input type="number" id="marks" name="marks" class="form-control"
                   value="<?php echo (int)$question->marks; ?>" min="1" required>
        </div>
        <button type="submit" class="btn btn-warning">Update Question</button>
        <a href="<?php echo (new moodle_url('/local/codejudge/teacher/dashboard.php'))->out(); ?>"
           class="btn btn-secondary">Cancel</a>
    </form>

    <hr>
    <h5>Test Cases</h5>
    <?php
    $test_cases = \local_codejudge\question_manager::get_test_cases($id);
    if (empty($test_cases)): ?>
        <p class="text-muted">No test cases yet.</p>
    <?php else: ?>
        <table class="table table-sm table-bordered">
            <thead><tr><th>#</th><th>Type</th><th>Input</th><th>Expected Output</th><th></th></tr></thead>
            <tbody>
            <?php $i = 1; foreach ($test_cases as $tc): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($tc->type); ?></td>
                    <td><pre class="mb-0"><?php echo htmlspecialchars(($tc->input_data === '' || $tc->input_data === null) ? '(none)' : $tc->input_data); ?></pre></td>
                    <td><pre class="mb-0"><?php echo htmlspecialchars($tc->expected_output); ?></pre></td>
                    <td>
                        <a href="<?php echo (new moodle_url('/local/codejudge/teacher/delete_testcase.php',
                            ['id' => $tc->id, 'question_id' => $id, 'sesskey' => sesskey()]))->out(); ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this test case?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="<?php echo (new moodle_url('/local/codejudge/teacher/add_testcase.php', ['question_id' => $id]))->out(); ?>"
       class="btn btn-info">+ Add Test Case</a>
</div>
<?php
echo $OUTPUT->footer();
