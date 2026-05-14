<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_codejudge_install() {
    global $DB;

    $now = time();

    $languages = [
        [
            'name'           => 'python',
            'compile_cmd'    => '',
            'run_cmd'        => 'python3 solution.py',
            'is_active'      => 1,
            'boilerplate_code' => "# Python boilerplate\nimport sys\n\ndef main():\n    # Your code here\n    pass\n\nif __name__ == '__main__':\n    main()\n",
            'timecreated'    => $now,
        ],
        [
            'name'           => 'c',
            'compile_cmd'    => 'gcc -o solution solution.c -lm',
            'run_cmd'        => './solution',
            'is_active'      => 1,
            'boilerplate_code' => "#include <stdio.h>\n\nint main() {\n    // Your code here\n    return 0;\n}\n",
            'timecreated'    => $now,
        ],
        [
            'name'           => 'cpp',
            'compile_cmd'    => 'g++ -o solution solution.cpp',
            'run_cmd'        => './solution',
            'is_active'      => 1,
            'boilerplate_code' => "#include <iostream>\nusing namespace std;\n\nint main() {\n    // Your code here\n    return 0;\n}\n",
            'timecreated'    => $now,
        ],
        [
            'name'           => 'java',
            'compile_cmd'    => 'javac Solution.java',
            'run_cmd'        => 'java Solution',
            'is_active'      => 1,
            'boilerplate_code' => "public class Solution {\n    public static void main(String[] args) {\n        // Your code here\n    }\n}\n",
            'timecreated'    => $now,
        ],
    ];

    foreach ($languages as $lang) {
        $DB->insert_record('codejudge_languages', (object)$lang);
    }

    return true;
}
