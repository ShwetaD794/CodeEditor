<?php
namespace local_codejudge;

defined('MOODLE_INTERNAL') || die();

class submission_manager {

    /**
     * Store a new submission and trigger judging.
     *
     * @return array ['submission_id' => int, 'results' => array]
     */
    public static function submit(int $user_id, int $question_id, string $language, string $code): array {
        global $DB;

        // Store submission with pending status.
        $submission = (object)[
            'user_id'     => $user_id,
            'question_id' => $question_id,
            'language'    => $language,
            'code'        => $code,
            'total_marks' => 0,
            'status'      => 'pending',
            'timecreated' => time(),
        ];
        $submission_id = $DB->insert_record('codejudge_submissions', $submission);

        // Fetch test cases.
        $test_cases_records = question_manager::get_test_cases($question_id);
        $test_cases = [];
        foreach ($test_cases_records as $tc) {
            $test_cases[] = [
                'id'              => $tc->id,
                'input'           => $tc->input_data,
                'expected_output' => $tc->expected_output,
            ];
        }

        if (empty($test_cases)) {
            $DB->set_field('codejudge_submissions', 'status', 'error', ['id' => $submission_id]);
            return ['submission_id' => $submission_id, 'results' => [], 'error' => 'No test cases defined.'];
        }

        // Call judge service.
        $judge_response = local_codejudge_call_judge($code, $language, $test_cases);

        if (isset($judge_response['error'])) {
            $DB->set_field('codejudge_submissions', 'status', 'error', ['id' => $submission_id]);
            return ['submission_id' => $submission_id, 'results' => [], 'error' => $judge_response['error']];
        }

        // Process and store results.
        $results       = $judge_response['results'] ?? [];
        $question      = question_manager::get_question($question_id);
        $total_tc      = count($results);
        $passed_tc     = 0;

        foreach ($results as $idx => $r) {
            $tc_id = $test_cases[$idx]['id'] ?? 0;
            $passed = (strtoupper($r['result'] ?? '') === 'PASS');
            if ($passed) {
                $passed_tc++;
            }

            $report = (object)[
                'submission_id'   => $submission_id,
                'test_case_id'    => $tc_id,
                'input'           => $r['input'] ?? '',
                'student_output'  => $r['student_output'] ?? '',
                'expected_output' => $r['expected_output'] ?? '',
                'result'          => $passed ? 'PASS' : 'FAIL',
                'timecreated'     => time(),
            ];
            $DB->insert_record('codejudge_reports', $report);
        }

        $total_marks = ($total_tc > 0 && $question)
            ? round(($passed_tc / $total_tc) * $question->marks, 2)
            : 0;

        $DB->update_record('codejudge_submissions', (object)[
            'id'          => $submission_id,
            'total_marks' => $total_marks,
            'status'      => 'completed',
        ]);

        return [
            'submission_id' => $submission_id,
            'results'       => $results,
            'total_marks'   => $total_marks,
            'max_marks'     => $question ? $question->marks : 0,
        ];
    }

    /**
     * Get all submissions (teacher view).
     */
    public static function get_all_submissions(?int $question_id = null): array {
        global $DB;
        $params = [];
        $where  = '';
        if ($question_id) {
            $where  = 'WHERE s.question_id = :qid';
            $params = ['qid' => $question_id];
        }

        $sql = "SELECT s.*, q.title AS question_title,
                       u.firstname, u.lastname
                FROM {codejudge_submissions} s
                JOIN {codejudge_questions} q ON q.id = s.question_id
                JOIN {user} u ON u.id = s.user_id
                $where
                ORDER BY s.timecreated DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get submissions for a specific user.
     */
    public static function get_user_submissions(int $user_id): array {
        global $DB;

        $sql = "SELECT s.*, q.title AS question_title
                FROM {codejudge_submissions} s
                JOIN {codejudge_questions} q ON q.id = s.question_id
                WHERE s.user_id = :uid
                ORDER BY s.timecreated DESC";

        return $DB->get_records_sql($sql, ['uid' => $user_id]);
    }

    /**
     * Get detailed reports for a submission.
     */
    public static function get_reports(int $submission_id): array {
        global $DB;
        return $DB->get_records('codejudge_reports', ['submission_id' => $submission_id]);
    }
}
