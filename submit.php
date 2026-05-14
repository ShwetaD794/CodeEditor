<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/codejudge:submit', $context);

header('Content-Type: application/json');

$question_id = required_param('question_id', PARAM_INT);
$language    = required_param('language', PARAM_ALPHANUMEXT);
$code        = required_param('code', PARAM_RAW);

// Basic validation
if (empty(trim($code))) {
    echo json_encode(['error' => 'Code cannot be empty.']);
    die;
}

$allowed_languages = ['python', 'c', 'cpp', 'java'];
if (!in_array($language, $allowed_languages)) {
    echo json_encode(['error' => 'Unsupported language.']);
    die;
}

$question = \local_codejudge\question_manager::get_question($question_id);
if (!$question) {
    echo json_encode(['error' => 'Question not found.']);
    die;
}

try {
    $result = \local_codejudge\submission_manager::submit(
        (int)$USER->id,
        $question_id,
        $language,
        $code
    );
    echo json_encode($result);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
