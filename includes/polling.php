<?php

/* start initialization section */
chdir(dirname(__FILE__));
chdir('../../../');

include_once('./lib/functions.php');
include_once('./lib/rrd.php');

$rrdexport_debug = false;
if(read_config_option('rrdexport_log_debug')!='') $rrdexport_debug=true;

function rrdexport_log($log) {
    global $rrdexport_debug;
    if($rrdexport_debug) cacti_log($log);
}

ini_set("max_execution_time", "0");
ini_set("memory_limit", "512M");

function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function rrdexport_poller_bottom () {
    global $config;
    /* record the start time */
    $start = microtime_float();
    $timestamp = time();

    cacti_log('RRD EXPORT RUN: ' . date('Y-m-d H:i:s', $timestamp), false, 'SYSTEM');
    $rrdexport_log_path = read_config_option('rrdexport_log_path');
    if($rrdexport_log_path=='') $rrdexport_log_path=$config['base_path'] . '/log/';
    if(substr($rrdexport_log_path,-1)!='/') $rrdexport_log_path .= '/';

    if(!file_exists($rrdexport_log_path)) {
        if(mkdir($rrdexport_log_path,0770,true)) {
            cacti_log("[rrdexport] [WARNING] created new path: $rrdexport_log_path");
        } else {  
            cacti_log("[rrdexport] [ERROR] failed to create log path: $rrdexport_log_path");
            return;
        }
    }

    $total_jobs = db_fetch_assoc("SELECT
                    plugin_rrdexport_datasource.local_data_id AS local_data_id,
                    plugin_rrdexport_schedules.sc_interval AS sc_interval,
                    plugin_rrdexport_schedules.ltime AS ltime,
                    plugin_rrdexport_schedules.cf_type AS cf_type,
                    `host`.description,
                    `host`.hostname,
                    data_template_data.name_cache,
                    data_template.`name`,
                    poller_item.rrd_path,
                    poller_item.rrd_step
                    FROM
                    plugin_rrdexport_schedules
                    LEFT JOIN plugin_rrdexport_datasource ON plugin_rrdexport_schedules.id = plugin_rrdexport_datasource.`schedule`
                    LEFT JOIN data_template_data ON data_template_data.local_data_id = plugin_rrdexport_datasource.local_data_id
                    INNER JOIN data_local ON data_local.id = plugin_rrdexport_datasource.local_data_id
                    INNER JOIN `host` ON data_local.host_id = `host`.id
                    INNER JOIN data_template ON data_template_data.data_template_id = data_template.id
                    INNER JOIN poller_item ON poller_item.local_data_id = plugin_rrdexport_datasource.local_data_id
                    WHERE
                        plugin_rrdexport_schedules.enabled = 'on'
                    AND
	                    rrd_path IS NOT NULL
                    AND (
                        (
                            ltime + sc_interval <= UNIX_TIMESTAMP(NOW())
                        )
                        OR (
                            (ltime IS NULL)
                            AND (
                                stime + sc_interval <= UNIX_TIMESTAMP(NOW())
                            )
                        )
                    )
                    GROUP BY
	                    plugin_rrdexport_datasource.local_data_id, plugin_rrdexport_schedules.cf_type, plugin_rrdexport_schedules.sc_interval");

    // Update Last Execute time
    db_execute("UPDATE plugin_rrdexport_schedules SET ltime=$timestamp
                    WHERE
                    plugin_rrdexport_schedules.enabled = 'on' AND
                    (
                      (ltime+sc_interval <= UNIX_TIMESTAMP(NOW())) OR
                      (
                        (ltime IS NULL) AND (stime+sc_interval <= UNIX_TIMESTAMP(NOW()))
                      )
                    )");


    if (sizeof($total_jobs) > 0) {
        foreach ($total_jobs as $jobs) {
            // Set log file name
            $rrdexport_log_filename = date('Y-m-d_His', $timestamp) . "_" . $jobs['local_data_id'];
            if($jobs['name_cache']) $rrdexport_log_filename .= "_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $jobs['name_cache']);
            $rrdexport_log_filename .= "_" . $jobs['cf_type'] . "_" . $jobs['sc_interval'] . ".log";
            $rrdexport_log = $rrdexport_log_path.$rrdexport_log_filename;
            if(!touch($rrdexport_log)) {
                cacti_log("[rrdexport] [ERROR] failed to write to log file: $rrdexport_log");
                break;
            }

            $prefix_output_field = 'local_data_id="' . $jobs['local_data_id'] . '" ';
            $prefix_output_field .= 'hostname="' . $jobs['hostname'] . '" ';
            $prefix_output_field .= 'host_description="' . $jobs['description'] . '" ';
            $prefix_output_field .= 'job_name="' . $jobs['name'] . '" ';
            $prefix_output_field .= 'job_fullname="' . $jobs['name_cache'] . '" ';

            $fetch_result = rrdtool_function_fetch_kv($jobs['local_data_id'], $timestamp-$jobs['sc_interval'], $timestamp
                ,$jobs['cf_type'], false, $jobs['rrd_path']);

            $count_data_source_type = count($fetch_result['data_source_names']);
            for($values_row=0; $values_row < count($fetch_result['values']);$values_row++){
                $count_data_field = 0;
                /* set timestamp */
                $output = date('[Y-m-d H:i:s] ', $fetch_result['values'][$values_row][0]) . $prefix_output_field;
                for($values_column=1; $values_column < $count_data_source_type; $values_column++ ){
                    if(isset($fetch_result['values'][$values_row][$values_column])) {
                        $count_data_field++;
                        $output .= $fetch_result['data_source_names'][$values_column] . '="' . $fetch_result['values'][$values_row][$values_column] . '"  ';
                    }
                }
                $output .= "\n";

                /* Skip blank line */
                if($count_data_field > 0)
                    file_put_contents($rrdexport_log,$output,FILE_APPEND);
            }
        }
    }


	/* record the end time */
	$end = microtime_float();

	/* log statistics */
    $rrdexport_stats = sprintf("Time:%01.4f TotalJobs:%s", $end - $start, sizeof($total_jobs));
    cacti_log('RRD EXPORT STATS: ' . $rrdexport_stats, false, 'SYSTEM');
    return;
}


function rrdtool_function_fetch_kv($local_data_id, $start_time, $end_time, $rrd_cf = "AVERAGE", $cal_exponent=false, $rrdtool_file = null, $resolution = 0, $show_unknown = false){
    /* validate local data id */
    if (empty($local_data_id) && is_null($rrdtool_file)) {
        return array();
    }

    /* initialize fetch array */
    $fetch_array = array();
    $regex = '';

    /* check if we have been passed a file instead of lodal data source to look up */
    if (is_null($rrdtool_file)) {
        $data_source_path = get_data_source_path($local_data_id, true);
    } else {
        $data_source_path = $rrdtool_file;
    }

    /* build and run the rrdtool fetch command with all of our data */
    $cmd_line = "fetch $data_source_path $rrd_cf -s $start_time -e $end_time";
    if ($resolution > 0) {
        $cmd_line .= " -r $resolution";
    }

    $output = rrdtool_execute($cmd_line, false, RRDTOOL_OUTPUT_STDOUT);

    /* grab the first line of the output which contains a list of data sources in this rrd output */
    $line_one_eol = strpos($output, "\n");
    $line_one = substr($output, 0, $line_one_eol);
    $output = substr($output, $line_one_eol);
    $output = preg_split('/[\r\n]{1,2}/', $output, null, PREG_SPLIT_NO_EMPTY);

    /* find the data sources in the rrdtool output */
    if (preg_match_all('/\S+/', $line_one, $data_source_names)) {
        /* version 1.0.49 changed the output slightly, remove the timestamp label if present */
        if (preg_match('/^timestamp/', $line_one)) {
            array_shift($data_source_names[0]);
        }
        $fetch_array["data_source_names"] = $data_source_names[0];
        array_unshift($fetch_array["data_source_names"], "timestamp");

        /* build a regular expression to match each data source value in the rrdtool output line */
        $regex = '/([0-9]+):\s+';
        for ($i = 1; $i < count($fetch_array["data_source_names"]); $i++) {
            $regex .= '([\-]?[0-9]{1}[.,][0-9]+e[\+-][0-9]{2,3}|-?[Nn][Aa][Nn])';

            if ($i < count($fetch_array["data_source_names"]) - 1) {
                $regex .= '\s+';
            }
        }
        $regex .= '/';
    }

    $fetch_array["values"] = array();
    for ($output_line = 0; $output_line < count($output); $output_line++) {
        $matches = array();
        $fetch_array["values"][$output_line] = array();

        if (preg_match($regex, $output[$output_line], $matches)) {
            /* only process the output line if we have the correct number of matches */
            if (count($matches) - 1 == count($fetch_array["data_source_names"])) {
                $fetch_array["values"][$output_line][0] = $matches[1];

                for ($i = 2; $i <= count($fetch_array["data_source_names"]); $i++) {
                    if ((strtolower($matches[$i]) == "nan") || (strtolower($matches[$i]) == "-nan")) {
                        if ($show_unknown) {
                            $fetch_array["values"][$output_line][$i - 1] = "U";
                        }
                    } else {
                        if($cal_exponent) {
                            list($mantisa, $exponent) = explode('e', $matches[$i]);
                            $mantisa = str_replace(",", ".", $mantisa);
                            $value = ($mantisa * (pow(10, (float)$exponent)));
                            $fetch_array["values"][$output_line][$i - 1] = ($value * 1);
                        }else {
                            $fetch_array["values"][$output_line][$i - 1] = $matches[$i];
                        }
                    }
                }
            }
        }
    }
    return $fetch_array;
}