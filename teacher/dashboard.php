<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/teacher/dashboard.php'));
$PAGE->set_title(get_string('dashboard', 'local_codejudge'));
$PAGE->set_heading(get_string('dashboard', 'local_codejudge'));
$PAGE->set_pagelayout('standard');

$filter_qid = optional_param('question_id', 0, PARAM_INT);

$stats       = \local_codejudge\report_manager::get_question_stats();
$submissions = \local_codejudge\submission_manager::get_all_submissions($filter_qid ?: null);
$questions   = \local_codejudge\question_manager::get_all_questions();

echo $OUTPUT->header();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="<?php echo (new moodle_url('/local/codejudge/teacher/add_question.php'))->out(); ?>"
           class="btn btn-primary">
            + <?php echo get_string('addquestion', 'local_codejudge'); ?>
        </a>
    </div>

    <!-- Question Stats -->
    <h4>Questions Overview</h4>
    <table class="table table-bordered table-hover mb-4">
        <thead class="thead-dark">
            <tr>
                <th>Title</th><th>Max Marks</th><th>Submissions</th>
                <th>Students</th><th>Avg Score</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stats as $s): ?>
            <tr>
                <td><?php echo format_string($s->title); ?></td>
                <td><?php echo (int)$s->marks; ?></td>
                <td><?php echo (int)$s->total_submissions; ?></td>
                <td><?php echo (int)$s->unique_students; ?></td>
                <td><?php echo $s->avg_marks !== null ? round($s->avg_marks, 2) : '-'; ?></td>
                <td>
                    <a href="<?php echo (new moodle_url('/local/codejudge/teacher/edit_question.php', ['id' => $s->id]))->out(); ?>"
                       class="btn btn-sm btn-warning">Edit</a>
                    <a href="<?php echo (new moodle_url('/local/codejudge/teacher/add_testcase.php', ['question_id' => $s->id]))->out(); ?>"
                       class="btn btn-sm btn-info">+ Test Case</a>
                    <a href="<?php echo (new moodle_url('/local/codejudge/teacher/delete_question.php', ['id' => $s->id, 'sesskey' => sesskey()]))->out(); ?>"
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this question and all test cases?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Submissions -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h4>Student Submissions</h4>
        <form class="form-inline" method="get">
            <label class="mr-2">Filter by question:</label>
            <select name="question_id" class="form-control mr-2" onchange="this.form.submit()">
                <option value="0">All</option>
                <?php foreach ($questions as $q): ?>
                    <option value="<?php echo $q->id; ?>" <?php echo ($filter_qid == $q->id) ? 'selected' : ''; ?>>
                        <?php echo format_string($q->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <table class="table table-bordered table-sm">
        <thead class="thead-light">
            <tr>
                <th>Student</th><th>Question</th><th>Language</th>
                <th>Score</th><th>Status</th><th>Date</th><th>Detail</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($submissions as $s): ?>
            <tr>
                <td><?php echo format_string($s->firstname . ' ' . $s->lastname); ?></td>
                <td><?php echo format_string($s->question_title); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($s->language)); ?></td>
                <td><?php echo $s->total_marks; ?></td>
                <td>
                    <span class="badge badge-<?php echo ($s->status === 'completed') ? 'success' : 'secondary'; ?>">
                        <?php echo htmlspecialchars($s->status); ?>
                    </span>
                </td>
                <td><?php echo userdate($s->timecreated, '%d %b %Y %H:%M'); ?></td>
                <td>
                    <a href="<?php echo (new moodle_url('/local/codejudge/student/result.php', ['id' => $s->id]))->out(); ?>"
                       class="btn btn-xs btn-primary">View</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
echo $OUTPUT->footer();
