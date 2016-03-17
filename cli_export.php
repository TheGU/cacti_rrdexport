<?php

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
    die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);


if (strpos($dir, 'plugins') !== false) {
    chdir('../../');
}
include('./include/global.php');
include_once('./plugins/rrdexport/includes/polling.php');

/* set the defaults */
$force     = false;
$debug     = false;
$sc_interval = 86400;
$timestamp = time();

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

foreach($parms as $parameter) {
    @list($arg, $value) = @explode('=', $parameter);

    switch ($arg) {
        case "--auto":
        case "-a":
            print "Force execute export schedule\n";
            rrdexport_poller_bottom();
            exit;
        case "--id":
        case "-id":
            $id = $value;
            break;
        case "--interval":
        case "-i":
            $sc_interval = $value;
            break;
        case "--debug":
        case "-d":
            $debug = true;
            break;
        case "--force":
        case "-f":
            $force = true;
            break;
        case "-h":
        case "-v":
        case "--version":
        case "--help":
            display_help();
            exit(-1);
        default:
            print "ERROR: Invalid Parameter " . $parameter . "\n\n";
            display_help();
            exit(-1);
    }
}


echo "Interval : ".$sc_interval ."\n";
$id = intval($id);
if ($id < 1) {
    echo "ID Invalid\n";
    return;
}
print microtime_float() . "\n";
print "Export RRD for Datasource ID #$id\n";

$fetch_result = rrdtool_function_fetch_kv($id, $timestamp-$sc_interval, $timestamp,'AVERAGE', true);
print "Finish Export Average\n";

$count_data_source_type = count($fetch_result['data_source_names']);
for($values_row=0; $values_row < count($fetch_result['values']);$values_row++){
    $count_data_field = 0;
    /* set timestamp */
    $output = date('[m-d-Y H:i:s] ', $fetch_result['values'][$values_row][0]);
    for($values_column=1; $values_column < $count_data_source_type; $values_column++ ){
        if(isset($fetch_result['values'][$values_row][$values_column])) {
            $count_data_field++;
            $output .= $fetch_result['data_source_names'][$values_column] . '="' . $fetch_result['values'][$values_row][$values_column] . '"  ';
        }
    }
    $output .= "\n";

    /* Skip blank line */
    if($count_data_field > 0)
        print $output;
}


/*	display_help - displays the usage of the function */
function display_help () {
    print "RRD Export Command Line Interface\n";
    print "usage: cli_export.php --id=N [--interval=N] \n\n";
    print "--auto, -a              - Manual run scheduler\n";
    print "--id=N, -id=N           - Specific data source id to export\n";
    print "--interval=N, -i=N      - Second to now to export. Data will export from NOW to NOW-interval\n";
    print "--help, -h -V --version - Display this help message\n";
    print "--version, -V  \n\n";
}
