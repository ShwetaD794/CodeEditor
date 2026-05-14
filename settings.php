<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_codejudge', get_string('pluginname', 'local_codejudge'));

    $settings->add(new admin_setting_configtext(
        'local_codejudge/judge_url',
        get_string('judgeurl', 'local_codejudge'),
        get_string('judgeurl_desc', 'local_codejudge'),
        'http://localhost:5000/run',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_codejudge/timeout',
        get_string('timeout', 'local_codejudge'),
        get_string('timeout_desc', 'local_codejudge'),
        '5',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
