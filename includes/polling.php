<?php

/* start initialization section */
# chdir('../../../');
include_once($config["base_path"] . 'include/auth.php');
include_once($config["base_path"] . 'lib/rrd.php');

ini_set("max_execution_time", "0");
ini_set("memory_limit", "512M");

function microtime_float(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

# rrdtool_function_fetch
function rrdexport_poller_bottom () {
    global $config;
    /* record the start time */
    $start = microtime_float();
    $timestamp = time();

    cacti_log('RRD EXPORT RUN: ' . $timestamp, false, 'SYSTEM');
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

    $total_jobs = db_fetch_assoc("
                    SELECT
                    plugin_rrdexport_datasource.local_data_id as local_data_id,
                    sc_interval,
                    ltime,
                    name_cache
                    FROM
                    plugin_rrdexport_schedules
                    INNER JOIN plugin_rrdexport_datasource
                    ON plugin_rrdexport_schedules.id = plugin_rrdexport_datasource.`schedule`
                    INNER JOIN data_template_data
                    ON data_template_data.local_data_id = plugin_rrdexport_datasource.local_data_id
                    WHERE
                    plugin_rrdexport_schedules.enabled = 'on' AND
                    (
                      (ltime+sc_interval <= UNIX_TIMESTAMP(NOW())) OR
                      (
                        (ltime IS NULL) AND (stime+sc_interval <= UNIX_TIMESTAMP(NOW()))
                      )
                    )
                    GROUP BY
                    plugin_rrdexport_datasource.local_data_id");

    # Update Last Execute time
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
            $rrdexport_log_filename = $jobs['local_data_id'] . "_" . date('m-d-Y_His', $timestamp);
            if($jobs['name_cache']) $rrdexport_log_filename .= "_" . preg_replace('/[^A-Za-z0-9_\-]/', '_', $jobs['name_cache']);
            $rrdexport_log_filename .= ".log";
            $rrdexport_log = $rrdexport_log_path.$rrdexport_log_filename;
            if(!touch($rrdexport_log)) {
                cacti_log("[rrdexport] [ERROR] failed to write to log file: $rrdexport_log");
                break;
            }
            $fetch_result = rrdtool_function_fetch_kv($jobs['local_data_id'], $timestamp-$jobs['sc_interval'], $timestamp);


            file_put_contents($rrdexport_log,print_r($fetch_result,true),FILE_APPEND);
        }
    }


	/* record the end time */
	$end = microtime_float();

	/* log statistics */
    $rrdexport_stats = sprintf("Time:%01.4f TotalJobs:%s", $end - $start, sizeof($total_jobs));
    cacti_log('RRD EXPORT STATS: ' . $rrdexport_stats, false, 'SYSTEM');
    return;
}


function rrdtool_function_fetch_kv($local_data_id, $start_time, $end_time, $rrd_cf = "AVERAGE", $resolution = 0, $show_unknown = false, $rrdtool_file = null) {
    /* validate local data id */
    if (empty($local_data_id) && is_null($rrdtool_file)) {
        return array();
    }

    /* initialize fetch array */
    $fetch_array = array();

    /* check if we have been passed a file instead of lodal data source to look up */
    if (is_null($rrdtool_file)) {
        $data_source_path = get_data_source_path($local_data_id, true);
    }else{
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

    /* split the output into an array */
    $output = preg_split('/[\r\n]{1,2}/', $output, null, PREG_SPLIT_NO_EMPTY);

    /* find the data sources in the rrdtool output */
    if (preg_match_all('/\S+/', $line_one, $data_source_names)) {
        /* version 1.0.49 changed the output slightly, remove the timestamp label if present */
        if (preg_match('/^timestamp/', $line_one)) {
            array_shift($data_source_names[0]);
        }
        array_unshift($fetch_array["data_source_names"], "timestamp", $data_source_names[0]);

        /* build a regular expression to match each data source value in the rrdtool output line */
        $regex = '/([0-9]+):\s+';
        for ($i=0; $i < count($fetch_array["data_source_names"]); $i++) {
            $regex .= '([\-]?[0-9]{1}[.,][0-9]+e[\+-][0-9]{2,3}|-?[Nn][Aa][Nn])';

            if ($i < count($fetch_array["data_source_names"]) - 1) {
                $regex .= '\s+';
            }
        }
        $regex .= '/';
    }

    /* loop through each line of the output */
    $fetch_array["values"] = array();
    for ($output_line = 0; $output_line < count($output); $output_line++) {
        $matches = array();
        $fetch_array["values"][$output_line] = array();
        /* match the output line */
        if (preg_match($regex, $output[$output_line], $matches)) {
            /* only process the output line if we have the correct number of matches */
            if (count($matches) - 1 == count($fetch_array["data_source_names"])) {
                /* get all values from the line and set them to the appropriate data source */
                for ($i=1; $i <= count($fetch_array["data_source_names"]); $i++) {
                    if (! isset($fetch_array["values"][$output_line][$i - 1])) {
                        $fetch_array["values"][$output_line][$i - 1] = array();
                    }
                    if ((strtolower($matches[$i]) == "nan") || (strtolower($matches[$i]) == "-nan")) {
                        if ($show_unknown) {
                            $fetch_array["values"][$output_line][$i - 1] = "U";
                        }
                    } else {
                        list($mantisa, $exponent) = explode('e', $matches[$i]);
                        if($exponent) {
                            $mantisa = str_replace(",", ".", $mantisa);
                            $value = ($mantisa * (pow(10, (float)$exponent)));
                        }else{
                            $value = $mantisa;
                        }
                        $fetch_array["values"][$output_line][$i - 1] = ($value * 1);

                    }
                }
            }
        }
    }

    return $fetch_array;
}