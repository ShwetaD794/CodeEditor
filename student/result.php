<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();

$submission_id = required_param('id', PARAM_INT);

$submission = \local_codejudge\report_manager::get_submission_with_question($submission_id);
if (!$submission) {
    print_error('invalidsubmission', 'local_codejudge');
}

// Ensure the user can see this submission.
$is_teacher = has_capability('local/codejudge:viewreports', $context);
$is_owner   = ($submission->user_id == $USER->id);

if (!$is_teacher && !$is_owner) {
    print_error('accessdenied', 'error');
}

$reports = \local_codejudge\submission_manager::get_reports($submission_id);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/student/result.php', ['id' => $submission_id]));
$PAGE->set_title('Submission Result');
$PAGE->set_heading('Submission Result');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>
<div class="container-fluid">
    <h2><?php echo format_string($submission->question_title); ?></h2>
    <div class="alert alert-<?php echo ($submission->total_marks >= $submission->max_marks) ? 'success' : 'info'; ?>">
        <strong>Score: <?php echo $submission->total_marks; ?> / <?php echo $submission->max_marks; ?> marks</strong>
        &nbsp;|&nbsp; Language: <?php echo htmlspecialchars(strtoupper($submission->language)); ?>
        &nbsp;|&nbsp; Submitted: <?php echo userdate($submission->timecreated); ?>
        <?php if ($is_teacher): ?>
            &nbsp;|&nbsp; Student: <?php echo fullname($submission); ?>
        <?php endif; ?>
    </div>

    <h4>Code</h4>
    <pre class="bg-dark text-white p-3 rounded"><?php echo htmlspecialchars($submission->code); ?></pre>

    <h4>Test Case Results</h4>
    <?php foreach ($reports as $r): ?>
        <div class="card mb-3 border-<?php echo ($r->result === 'PASS') ? 'success' : 'danger'; ?>">
            <div class="card-header <?php echo ($r->result === 'PASS') ? 'bg-success' : 'bg-danger'; ?> text-white">
                <strong><?php echo htmlspecialchars($r->result); ?></strong>
            </div>
            <div class="card-body">
                <p><strong>Input:</strong><br><pre><?php echo htmlspecialchars($r->input ?: '(none)'); ?></pre></p>
                <p><strong>Expected Output:</strong><br><pre><?php echo htmlspecialchars($r->expected_output); ?></pre></p>
                <p><strong>Your Output:</strong><br><pre><?php echo htmlspecialchars($r->student_output); ?></pre></p>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="<?php echo (new moodle_url('/local/codejudge/index.php'))->out(); ?>" class="btn btn-secondary">
        &larr; Back to Questions
    </a>
</div>
<?php
echo $OUTPUT->footer();
