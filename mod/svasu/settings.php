<?php
defined('MOODLE_INTERNAL') || die();
global $CFG, $DB;

if ($hassiteconfig) {
    $ADMIN->add('mod_svasu', new admin_category('mod_svasu_settings', new lang_string('pluginname', 'mod_svasu')));
    $settingspage = new admin_settingpage('mod_svasu', new lang_string('manage', 'mod_svasu'));
    if ($ADMIN->fulltree) {         
        $settings->add(new admin_setting_configtext(
            'mod_svasu/url',
            'Url',
            'Enter url of svasu.cloud',
            null,
            PARAM_TEXT,
            68
        ));
        $settings->add(new admin_setting_configtext(
            'mod_svasu/token',
            'Token',
            'Enter token of svasu.cloud',
            null,
            PARAM_TEXT,
            68
        ));
    }
    $ADMIN->add('mod_svasu', $settings);
}
