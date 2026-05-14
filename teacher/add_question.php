<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/teacher/add_question.php'));
$PAGE->set_title(get_string('addquestion', 'local_codejudge'));
$PAGE->set_heading(get_string('addquestion', 'local_codejudge'));
$PAGE->set_pagelayout('standard');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $title       = required_param('title', PARAM_TEXT);
    $description = required_param('description', PARAM_RAW);
    $marks       = required_param('marks', PARAM_INT);

    if (empty(trim($title))) {
        $error = 'Title is required.';
    } elseif ($marks < 1) {
        $error = 'Marks must be at least 1.';
    } else {
        $data = (object)[
            'title'       => $title,
            'description' => $description,
            'marks'       => $marks,
        ];
        $id = \local_codejudge\question_manager::create_question($data);
        redirect(
            new moodle_url('/local/codejudge/teacher/add_testcase.php', ['question_id' => $id]),
            'Question created. Now add test cases.'
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
            <label for="title">Title <span class="text-danger">*</span></label>
            <input type="text" id="title" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="description">Problem Description <span class="text-danger">*</span></label>
            <textarea id="description" name="description" class="form-control" rows="8" required></textarea>
        </div>
        <div class="form-group">
            <label for="marks">Marks <span class="text-danger">*</span></label>
            <input type="number" id="marks" name="marks" class="form-control" value="10" min="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Create Question</button>
        <a href="<?php echo (new moodle_url('/local/codejudge/teacher/dashboard.php'))->out(); ?>"
           class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php
echo $OUTPUT->footer();
