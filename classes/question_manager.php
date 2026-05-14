<?php
namespace local_codejudge;

defined('MOODLE_INTERNAL') || die();

class question_manager {

    /**
     * Get all questions.
     */
    public static function get_all_questions(): array {
        global $DB;
        return $DB->get_records('codejudge_questions', null, 'timecreated DESC');
    }

    /**
     * Get a single question by ID.
     */
    public static function get_question(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('codejudge_questions', ['id' => $id]) ?: null;
    }

    /**
     * Create a new question.
     */
    public static function create_question(\stdClass $data): int {
        global $DB, $USER;
        $data->createdby   = $USER->id;
        $data->timecreated = time();
        $data->timemodified = time();
        return $DB->insert_record('codejudge_questions', $data);
    }

    /**
     * Update an existing question.
     */
    public static function update_question(\stdClass $data): bool {
        global $DB;
        $data->timemodified = time();
        return $DB->update_record('codejudge_questions', $data);
    }

    /**
     * Delete a question and all its test cases.
     */
    public static function delete_question(int $id): void {
        global $DB;
        $DB->delete_records('codejudge_test_cases', ['question_id' => $id]);
        $DB->delete_records('codejudge_questions', ['id' => $id]);
    }

    /**
     * Get test cases for a question.
     */
    public static function get_test_cases(int $question_id): array {
        global $DB;
        return $DB->get_records('codejudge_test_cases', ['question_id' => $question_id]);
    }

    /**
     * Add a test case.
     */
    public static function add_test_case(\stdClass $data): int {
        global $DB;
        $data->timecreated = time();
        return $DB->insert_record('codejudge_test_cases', $data);
    }

    /**
     * Delete a test case.
     */
    public static function delete_test_case(int $id): void {
        global $DB;
        $DB->delete_records('codejudge_test_cases', ['id' => $id]);
    }

    /**
     * Get all active languages.
     */
    public static function get_languages(): array {
        global $DB;
        return $DB->get_records('codejudge_languages', ['is_active' => 1]);
    }

    /**
     * Get a language record by name.
     */
    public static function get_language_by_name(string $name): ?\stdClass {
        global $DB;
        return $DB->get_record('codejudge_languages', ['name' => $name, 'is_active' => 1]) ?: null;
    }
}
