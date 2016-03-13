<?php
function plugin_rrdexport_version () {
    return array(
        'name' 		=> 'rrdexport',
        'version' 	=> '0.1',
        'longname'	=> 'RRD Auto Export',
        'author'	=> 'Pattapong Jantarach',
        'homepage'	=> 'http://pattapongj.com',
        'email'		=> 'pattapongj@qmail.org',
        'url'		=> 'https://github.com/TheGU/cacti_rrdexport'
    );
}

function plugin_rrdexport_install () {
    api_plugin_register_hook('rrdexport', 'config_arrays', 'rrdexport_config_arrays', 'setup.php');
    api_plugin_register_hook('rrdexport', 'config_settings', 'rrdexport_config_settings', 'includes/settings.php');
    api_plugin_register_hook('rrdexport', 'draw_navigation_text', 'rrdexport_draw_navigation_text', 'setup.php');
    api_plugin_register_realm('rrdexport', 'rrdexport.php', 'RRD Export Schedules', 1);
    rrdexport_setup_database ();
}

function plugin_rrdexport_uninstall () {
}

function plugin_rrdexport_check_config () {
    return true;
}

function plugin_rrdexport_upgrade () {
    return false;
}

function rrdexport_version () {
    return plugin_rrdexport_version();
}

function rrdexport_config_arrays () {
    global $menu;
    $menu["Management"]['plugins/rrdexport/rrdexport.php'] = "RRD Export Schedules";
}

function rrdexport_draw_navigation_text ($nav) {
    $nav["rrdexport.php:"] = array("title" => "Export Schedules", "mapping" => "index.php:", "url" => "rrdexport.php", "level" => "1");
    $nav["rrdexport.php:edit"] = array("title" => "Export Schedule (Edit)", "mapping" => "index.php:", "url" => "rrdexport.php", "level" => "1");
    $nav["rrdexport.php:actions"] = array("title" => "Export Schedules", "mapping" => "index.php:", "url" => "rrdexport.php", "level" => "1");
    return $nav;
}

function rrdexport_setup_database () {
    $data = array();
    $data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
    $data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on');
    $data['columns'][] = array('name' => 'name', 'type' => 'varchar(128)', 'NULL' => true);
    $data['columns'][] = array('name' => 'stime', 'type' => 'int(22)', 'NULL' => false);
    $data['columns'][] = array('name' => 'ltime', 'type' => 'int(22)', 'NULL' => false);
    $data['columns'][] = array('name' => 'minterval', 'type' => 'int(11)', 'NULL' => false);
    $data['primary'] = 'id';
    $data['keys'][] = array('name' => 'mtype', 'columns' => 'mtype');
    $data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
    $data['type'] = 'MyISAM';
    $data['comment'] = 'Export Schedules';
    api_plugin_db_table_create ('rrdexport', 'plugin_rrdexport_schedules', $data);

    $data = array();
    $data['columns'][] = array('name' => 'type', 'type' => 'int(6)', 'NULL' => false);
    $data['columns'][] = array('name' => 'local_data_id', 'type' => 'int(12)', 'NULL' => false);
    $data['columns'][] = array('name' => 'rrd_path', 'type' => 'varchar(255)', 'NULL' => false);
    $data['columns'][] = array('name' => 'schedule', 'type' => 'int(12)', 'NULL' => false);
    $data['primary'] = 'type`,`schedule`,`local_data_id';
    $data['keys'][] = array('name' => 'type', 'columns' => 'type');
    $data['keys'][] = array('name' => 'schedule', 'columns' => 'schedule');
    $data['type'] = 'MyISAM';
    $data['comment'] = 'Export Schedules Datasource';
    api_plugin_db_table_create ('rrdexport', 'plugin_rrdexport_datasource', $data);
}
