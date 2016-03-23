<?php

function rrdexport_config_settings () {
    global $tabs, $settings, $item_rows, $config;
    if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php') return;

    $rrdexport_log_path = $config['base_path'] . '/log/';
    $tabs['rrdexport'] = 'RRD Export Schedules';
    $settings['rrdexport'] = array(
        'rrdexport_output_header' => array(
            'friendly_name' => 'RRD Export Output Options',
            'method' => 'spacer',
        ),
        'rrdexport_log_path' => array(
            'friendly_name' => 'Export output path',
            'description' => 'This is the path location to output the command "rrdtool fetch ..."',
            'method' => 'textbox',
            'default' => $rrdexport_log_path,
            'max_length' => 255
        ),
        'rrdexport_log_debug' => array(
            'friendly_name' => 'Enable Debug Mode',
            'description' => 'debug logs outputted into cacti.log',
            'method' => 'checkbox',
            'default' => false
        ),
    );
}