<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/codejudge/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_codejudge'));
$PAGE->set_heading(get_string('pluginname', 'local_codejudge'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$questions = \local_codejudge\question_manager::get_all_questions();

echo '<div class="mt-3">';
if (has_capability('local/codejudge:managequestions', $context)) {
    // Teacher button
    echo '<a href="' . (new moodle_url('/local/codejudge/teacher/dashboard.php'))->out() . '"
             class="btn btn-primary">
             <i class="fa fa-tachometer"></i> Teacher Dashboard
          </a>';
} else {
    // Student button
    echo '<a href="' . (new moodle_url('/local/codejudge/student/my_submissions.php'))->out() . '"
             class="btn btn-info">
             <i class="fa fa-list"></i> My Submissions
          </a>';
}

echo '<div class="mt-3">';

if (empty($questions)) {
    echo $OUTPUT->notification(get_string('noquestions', 'local_codejudge') ?: 'No questions available yet.', 'info');
} else {
    echo '<div class="container-fluid">';
    echo '<h2>' . get_string('questions', 'local_codejudge') . '</h2>';
    echo '<div class="list-group">';
    foreach ($questions as $q) {
        $url = new moodle_url('/local/codejudge/student/attempt.php', ['id' => $q->id]);
        echo '<a href="' . $url . '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">';
        echo '<div>';
        echo '<h5 class="mb-1">' . format_string($q->title) . '</h5>';
        echo '<small class="text-muted">' . format_text($q->description, FORMAT_PLAIN, ['trusted' => false, 'noclean' => false]) . '</small>';
        echo '</div>';
        echo '<span class="badge badge-primary badge-pill">' . (int)$q->marks . ' marks</span>';
        echo '</a>';
    }
    echo '</div></div>';
}

echo $OUTPUT->footer();
