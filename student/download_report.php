<?php
/**
 * student/download_report.php
 * Generates and streams a personal PDF report for the logged-in student.
 */
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();
$context = context_system::instance();
require_capability('local/codejudge:viewown', $context);

global $DB, $USER, $SITE;

// ── Fetch student's own submissions ───────────────────────────────────────
$subs_raw = $DB->get_records_sql(
    "SELECT s.*, q.title AS question_title, q.marks AS max_marks
     FROM {codejudge_submissions} s
     JOIN {codejudge_questions} q ON q.id = s.question_id
     WHERE s.user_id = :uid
     ORDER BY s.timecreated DESC",
    ['uid' => $USER->id]
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

// ── Calculate class average and rank for context ───────────────────────────
$all_students_sql = "SELECT user_id, SUM(total_marks) AS earned,
                            COUNT(*) AS sub_count
                     FROM {codejudge_submissions}
                     WHERE status = 'completed'
                     GROUP BY user_id";
$all_scores   = $DB->get_records_sql($all_students_sql);

// Get max marks totals per user
$all_avail_sql = "SELECT s.user_id,
                         SUM(q.marks) AS avail
                  FROM {codejudge_submissions} s
                  JOIN {codejudge_questions} q ON q.id = s.question_id
                  WHERE s.status = 'completed'
                  GROUP BY s.user_id";
$all_avail = $DB->get_records_sql($all_avail_sql);

$ranked = [];
foreach ($all_scores as $uid => $row) {
    $avail = isset($all_avail[$uid]) ? floatval($all_avail[$uid]->avail) : 1;
    $earned = floatval($row->earned);
    $ranked[$uid] = $avail > 0 ? ($earned / $avail * 100) : 0;
}
arsort($ranked);

$rank          = array_search($USER->id, array_keys($ranked));
$rank          = $rank !== false ? $rank + 1 : count($ranked) + 1;
$total_students= count($ranked);
$class_avg     = count($ranked) > 0 ? round(array_sum($ranked) / count($ranked), 1) : 0;

// ── Build payload ──────────────────────────────────────────────────────────
$payload = json_encode([
    'student_name'   => fullname($USER),
    'site_name'      => $SITE->fullname ?? 'Moodle',
    'generated_at'   => date('d F Y, h:i A'),
    'rank'           => $rank,
    'total_students' => $total_students,
    'class_avg_pct'  => $class_avg,
    'submissions'    => $submissions,
]);

// ── Call judge service ─────────────────────────────────────────────────────
$judge_base = rtrim(
    str_replace('/run', '', get_config('local_codejudge', 'judge_url') ?: 'http://127.0.0.1:5000/run'),
    '/'
);
$report_url = $judge_base . '/report/student';

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
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/codejudge/student/download_report.php'));
    $PAGE->set_title('Report Error');
    echo $OUTPUT->header();
    echo $OUTPUT->notification("Failed to generate report. Judge service error: HTTP $http_code $curl_err", 'error');
    echo $OUTPUT->footer();
    exit;
}

// ── Stream PDF ─────────────────────────────────────────────────────────────
$filename = 'my_report_' . date('Y-m-d') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($response));
header('Cache-Control: no-cache, no-store');
echo $response;
exit;