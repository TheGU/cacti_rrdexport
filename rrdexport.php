<?php
chdir('../../');
include_once('./include/auth.php');

$tabs = array(
    "general" => "General",
    "data_local" => "Data Sources"
);

$tabs = api_plugin_hook_function('rrdexport_tabs', $tabs);

switch ($_REQUEST['action']) {
    case 'save':
        form_save();
        break;
    case 'actions':
        form_actions();
        break;
    case 'edit':
        include_once('./include/top_header.php');
        schedule_edit();
        include_once('./include/bottom_footer.php');
        break;
    default:
        include_once('./include/top_header.php');
        schedule_list();
        include_once('./include/bottom_footer.php');
        break;
}

function form_save() {}

function form_actions() {}

function schedule_edit() {}

function schedule_list() {}