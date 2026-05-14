<?php
/**
 * teacher/download_report.php
 * Generates and streams a PDF class report for the teacher.
 */
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:viewreports', $context);

global $DB, $SITE;

// ── Gather all questions ───────────────────────────────────────────────────
$questions_raw = $DB->get_records('codejudge_questions', null, 'timecreated DESC');

$questions = [];
foreach ($questions_raw as $q) {
    $subs = $DB->get_records('codejudge_submissions', ['question_id' => $q->id, 'status' => 'completed']);
    $total    = count($subs);
    $sum      = 0;
    $pass_cnt = 0;
    foreach ($subs as $s) {
        $sum += floatval($s->total_marks);
        if (floatval($s->total_marks) >= floatval($q->marks)) {
            $pass_cnt++;
        }
    }
    $questions[] = [
        'id'                => (int)$q->id,
        'title'             => $q->title,
        'marks'             => (int)$q->marks,
        'total_submissions' => $total,
        'pass_count'        => $pass_cnt,
        'avg_score'         => $total > 0 ? round($sum / $total, 2) : 0,
    ];
}

// ── Gather all students with submissions ───────────────────────────────────
$users_sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
              FROM {user} u
              JOIN {codejudge_submissions} s ON s.user_id = u.id
              ORDER BY u.lastname, u.firstname";
$users = $DB->get_records_sql($users_sql);

$students = [];
foreach ($users as $u) {
    $subs_raw = $DB->get_records_sql(
        "SELECT s.*, q.title AS question_title, q.marks AS max_marks
         FROM {codejudge_submissions} s
         JOIN {codejudge_questions} q ON q.id = s.question_id
         WHERE s.user_id = :uid
         ORDER BY s.timecreated DESC",
        ['uid' => $u->id]
    );

    $submissions = [];
    foreach ($subs_raw as $s) {
        $reports_raw = $DB->get_records('codejudge_reports', ['submission_id' => $s->id]);
        $reports = [];
        foreach ($reports_raw as $r) {
            $reports[] = [
                'input'    => $r->input,
                'expected' => $r->expected_output,
                'actual'   => $r->student_output,
                'result'   => $r->result,
            ];
        }
        $submissions[] = [
            'question'    => $s->question_title,
            'language'    => $s->language,
            'marks'       => floatval($s->total_marks),
            'max_marks'   => floatval($s->max_marks),
            'status'      => $s->status,
            'timecreated' => (int)$s->timecreated,
            'reports'     => $reports,
        ];
    }

    $students[] = [
        'id'          => (int)$u->id,
        'name'        => fullname($u),
        'submissions' => $submissions,
    ];
}

// ── Build payload ──────────────────────────────────────────────────────────
$payload = json_encode([
    'site_name'    => $SITE->fullname ?? 'Moodle',
    'generated_at' => date('d F Y, h:i A'),
    'questions'    => $questions,
    'students'     => $students,
]);

// ── Call judge service report endpoint ────────────────────────────────────
$judge_base = rtrim(
    str_replace('/run', '', get_config('local_codejudge', 'judge_url') ?: 'http://127.0.0.1:5000/run'),
    '/'
);
$report_url = $judge_base . '/report/teacher';

$ch = curl_init($report_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($response === false || !empty($curl_err) || $http_code !== 200) {
    // Show error in Moodle
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/codejudge/teacher/download_report.php'));
    $PAGE->set_title('Report Error');
    echo $OUTPUT->header();
    echo $OUTPUT->notification("Failed to generate report. Judge service error: HTTP $http_code $curl_err", 'error');
    echo $OUTPUT->footer();
    exit;
}

// ── Stream PDF to browser ──────────────────────────────────────────────────
$filename = 'class_report_' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($response));
header('Cache-Control: no-cache, no-store');
echo $response;
exit;