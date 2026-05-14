<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);
require_sesskey();

$id = required_param('id', PARAM_INT);

$question = \local_codejudge\question_manager::get_question($id);
if ($question) {
    \local_codejudge\question_manager::delete_question($id);
}

redirect(
    new moodle_url('/local/codejudge/teacher/dashboard.php'),
    'Question deleted.'
);
