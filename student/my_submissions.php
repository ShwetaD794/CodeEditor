<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/student/my_submissions.php'));
$PAGE->set_title('My Submissions');
$PAGE->set_heading('My Submissions');
$PAGE->set_pagelayout('standard');

global $USER, $DB;

$sql = "SELECT s.*, q.title AS question_title
        FROM {codejudge_submissions} s
        JOIN {codejudge_questions} q ON q.id = s.question_id
        WHERE s.user_id = :uid
        ORDER BY s.timecreated DESC";

$submissions = $DB->get_records_sql($sql, ['uid' => (int)$USER->id]);

echo $OUTPUT->header();
?>
<div class="container-fluid mt-3">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa fa-list"></i> My Submissions</h2>
        <a href="<?php echo (new moodle_url('/local/codejudge/index.php'))->out(); ?>"
           class="btn btn-primary">
            &larr; Back to Questions
        </a>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="alert alert-info">
            <strong>No submissions yet.</strong>
            You have not submitted any code yet.
            <a href="<?php echo (new moodle_url('/local/codejudge/index.php'))->out(); ?>">
                Go attempt a question.
            </a>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header bg-dark text-white">
                <strong>Total Submissions: <?php echo count($submissions); ?></strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Language</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th>Date &amp; Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach ($submissions as $s): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo format_string($s->question_title); ?></strong></td>
                            <td>
                                <span class="badge badge-dark">
                                    <?php echo htmlspecialchars(strtoupper($s->language)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($s->status === 'completed'): ?>
                                    <span class="badge badge-<?php echo $s->total_marks > 0 ? 'success' : 'danger'; ?> p-2">
                                        <?php echo $s->total_marks; ?> marks
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $s->status === 'completed' ? 'success' : ($s->status === 'error' ? 'danger' : 'warning'); ?>">
                                    <?php echo htmlspecialchars($s->status); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y, h:i A', $s->timecreated); ?></td>
                            <td>
                                <a href="<?php echo (new moodle_url('/local/codejudge/student/result.php',
                                    ['id' => $s->id]))->out(); ?>"
                                   class="btn btn-sm btn-info">
                                    View Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
<?php
echo $OUTPUT->footer();