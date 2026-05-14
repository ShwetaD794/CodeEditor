<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:managequestions', $context);
require_sesskey();

$id          = required_param('id', PARAM_INT);
$question_id = required_param('question_id', PARAM_INT);

\local_codejudge\question_manager::delete_test_case($id);

redirect(
    new moodle_url('/local/codejudge/teacher/edit_question.php', ['id' => $question_id]),
    'Test case deleted.'
);
