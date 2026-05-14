<?php
namespace local_codejudge;

defined('MOODLE_INTERNAL') || die();

class report_manager {

    /**
     * Get aggregate statistics per question for the teacher dashboard.
     */
    public static function get_question_stats(): array {
        global $DB;

        $sql = "SELECT q.id, q.title, q.marks,
                       COUNT(DISTINCT s.id) AS total_submissions,
                       COUNT(DISTINCT s.user_id) AS unique_students,
                       AVG(s.total_marks) AS avg_marks
                FROM {codejudge_questions} q
                LEFT JOIN {codejudge_submissions} s ON s.question_id = q.id
                GROUP BY q.id, q.title, q.marks
                ORDER BY q.timecreated DESC";

        return $DB->get_records_sql($sql);
    }

    /**
     * Get a submission with its question data.
     */
    public static function get_submission_with_question(int $submission_id): ?\stdClass {
        global $DB;

        $sql = "SELECT s.*, q.title AS question_title, q.marks AS max_marks,
                       u.firstname, u.lastname
                FROM {codejudge_submissions} s
                JOIN {codejudge_questions} q ON q.id = s.question_id
                JOIN {user} u ON u.id = s.user_id
                WHERE s.id = :sid";

        return $DB->get_record_sql($sql, ['sid' => $submission_id]) ?: null;
    }
}
