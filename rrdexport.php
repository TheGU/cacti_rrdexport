<?php
# TODO: This line for Dev only, Should remove before release
# include_once('./functions.php');

chdir('../../');
include_once('./include/auth.php');
# include_once($config["base_path"] . '/plugins/rrdexport/functions.php');

$tabs = array(
    "general" => "General",
    "data_local" => "Data Sources"
);

$tabs = api_plugin_hook_function('rrdexport_tabs', $tabs);

$sc_intervals = array(86400 => 'Every Day', 604800 => 'Every Week');

$schedule_actions = array(
    1 => 'Delete',
);

$assoc_actions = array(
    1 => "Associate",
    2 => "Disassociate"
);

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

function form_save() {
    if (isset($_POST["save_component"])) {
        input_validate_input_number(get_request_var_post('id'));
        input_validate_input_number(get_request_var_post('sc_interval'));
        if (isset($_POST['name'])) {
            $_POST['name'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('name')));
        }
        if (isset($_POST['stime'])) {
            $_POST['stime'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('stime')));
        }

        if(isset($_POST['id']) && ($_POST['id']!=0 || $_POST['id']!='')) {
            $save['id'] = $_POST['id'];
        }
        $save['name']  = $_POST['name'];
        $save['stime'] = strtotime($_POST['stime']);
        $save['sc_interval'] = $_POST['sc_interval'];

        if (isset($_POST['enabled']))
            $save['enabled'] = 'on';
        else
            $save['enabled'] = 'off';

        if (!is_error_message()) {
            $id = sql_save($save, 'plugin_rrdexport_schedules');
            if ($id) {
                raise_message(1);
            } else {
                raise_message(2);
            }
        }
        header('Location: rrdexport.php?tab=general&action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
        exit;
    }
}

function form_actions() {}

function schedule_edit() {
    global $colors, $config, $tabs, $sc_intervals;

    input_validate_input_number(get_request_var("id"));

    /* set the default tab */
    load_current_session_value("tab", "sess_rrdexport_tab", "general");
    $current_tab = $_REQUEST["tab"];

    if (sizeof($tabs) && isset($_REQUEST['id'])) {
        /* draw the tabs */
        print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

        foreach (array_keys($tabs) as $tab_short_name) {
            print "<td style='padding:3px 10px 2px 5px;background-color:" . (($tab_short_name == $current_tab) ? "silver;" : "#DFDFDF;") .
                "white-space:nowrap;'" .
                " width='1%' " .
                " align='center' class='tab'>
				<span class='textHeader'><a href='" . htmlspecialchars($config['url_path'] .
                    "plugins/rrdexport/rrdexport.php?action=edit&id=" . get_request_var_request('id') .
                    "&tab=" . $tab_short_name) .
                "'>$tabs[$tab_short_name]</a></span>
				</td>\n
				<td width='1'></td>\n";
        }

        print "<td></td>\n</tr></table>\n";
    }

    if (isset($_REQUEST['id'])) {
        $id = get_request_var_request('id');
        $rrdexport_item_data = db_fetch_row('SELECT * FROM plugin_rrdexport_schedules WHERE id = ' . $id);
    } else {
        $id = 0;
        $rrdexport_item_data = array('id' => $id, 'name' => 'New RRD Export Schedule', 'enabled' => 'on', 'stime' => time(), 'sc_interval' => 86400);
    }

    $header_label = get_header_label();

    if ($_REQUEST["tab"] == "general") {
        html_start_box("<strong>General Settings</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

        $form_array = array(
            'general_header' => array(
                'friendly_name' => 'RRD Export Schedule',
                'method' => 'spacer',
            ),
            'name' => array(
                'friendly_name' => 'Schedule Name',
                'method' => 'textbox',
                'max_length' => 100,
                'default' => $rrdexport_item_data['name'],
                'description' => 'Name that represent these set of export',
                'value' => isset($rrdexport_item_data['name']) ? $rrdexport_item_data['name'] : ''
            ),
            'enabled' => array(
                'friendly_name' => 'Enabled',
                'method' => 'checkbox',
                'default' => 'on',
                'description' => 'Whether or not this schedule will be run.',
                'value' => isset($rrdexport_item_data['enabled']) ? $rrdexport_item_data['enabled'] : ''
            ),
            'sc_interval' => array(
                'friendly_name' => 'Interval',
                'method' => 'drop_array',
                'array' => $sc_intervals,
                'default' => 86400,
                'description' => 'This is the interval in which the start time will repeat.',
                'value' => isset($rrdexport_item_data['sc_interval']) ? $rrdexport_item_data['sc_interval'] : '0'
            ),
            'stime' => array(
                'friendly_name' => 'Start Time',
                'method' => 'textbox',
                'max_length' => 100,
                'description' => 'The start date / time for this schedule. Most date / time formats accepted.',
                'default' => date("F j, Y, G:i", time()),
                'value' => isset($rrdexport_item_data['stime']) ?  date("l, F j, Y, G:i", $rrdexport_item_data['stime']) : ''
            ),
            "save_component" => array(
                "method" => "hidden",
                "value" => "1"
            ),
            "save" => array(
                "method" => "hidden",
                "value" => "edit"
            ),
            "id" => array(
                "method" => "hidden",
                "value" => $id
            )
        );

        draw_edit_form(
            array(
                "config" => array(),
                "fields" => $form_array
            )
        );

        html_end_box();

        form_save_button('rrdexport.php', 'return');

        ?>
        <script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar.js"></script>
        <script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/lang/calendar-en.js"></script>
        <script type="text/javascript" src="<?php echo $config['url_path']; ?>include/jscalendar/calendar-setup.js"></script>
        <script type='text/javascript'>
            // Initialize the calendar
            calendar=null;

            // This function displays the calendar associated to the input field 'id'
            function showCalendar(id) {
                var el = document.getElementById(id);
                if (calendar != null) {
                    // we already have some calendar created
                    calendar.hide();  // so we hide it first.
                } else {
                    // first-time call, create the calendar.
                    var cal = new Calendar(true, null, selected, closeHandler);
                    cal.weekNumbers = false;  // Do not display the week number
                    cal.showsTime = true;     // Display the time
                    cal.time24 = true;        // Hours have a 24 hours format
                    cal.showsOtherMonths = false;    // Just the current month is displayed
                    calendar = cal;                  // remember it in the global var
                    cal.setRange(1900, 2070);        // min/max year allowed.
                    cal.create();
                }

                calendar.setDateFormat('%A, %B %d, %Y, %H:%S');    // set the specified date format
                calendar.parseDate(el.value);                // try to parse the text in field
                calendar.sel = el;                           // inform it what input field we use

                // Display the calendar below the input field
                calendar.showAtElement(el, "Br");        // show the calendar

                return false;
            }

            // This function update the date in the input field when selected
            function selected(cal, date) {
                cal.sel.value = date;      // just update the date in the input field.
            }

            // This function gets called when the end-user clicks on the 'Close' button.
            // It just hides the calendar without destroying it.
            function closeHandler(cal) {
                cal.hide();                        // hide the calendar
                calendar = null;
            }

            stime.onclick=new Function("return showCalendar('stime')");
        </script>
        <?php
    }elseif ($_REQUEST["tab"] == "data_local") {
        datasource_list($header_label);
    }else{
        api_plugin_hook_function('rrdexport_show_tab', $header_label);
    }
}

function datasource_list($header_label) {
    global $colors, $assoc_actions, $item_rows;

    /* ================= input validation ================= */
    input_validate_input_number(get_request_var_request("ds_rows"));
    input_validate_input_number(get_request_var_request("host_id"));
    input_validate_input_number(get_request_var_request("template_id"));
    input_validate_input_number(get_request_var_request("method_id"));
    input_validate_input_number(get_request_var_request("page"));
    /* ==================================================== */

    /* clean up search string */
    if (isset($_REQUEST["filter"])) {
        $_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
        # $_REQUEST["filter"] = sanitize_search_string(get_request_var_request("filter"));
    }

    /* clean up sort_column string */
    if (isset($_REQUEST["sort_column"])) {
        $_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
    }

    /* clean up sort_direction string */
    if (isset($_REQUEST["sort_direction"])) {
        $_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
    }

    /* clean up associated string */
    if (isset($_REQUEST["associated"])) {
        $_REQUEST["associated"] = sanitize_search_string(get_request_var("associated"));
        #$_REQUEST["associated"] = sanitize_search_string(get_request_var_request("associated"));
    }

    /* if the user pushed the 'clear' button */
    if (isset($_REQUEST["clear_x"])) {
        kill_session_var("sess_rrdexport_current_page");
        kill_session_var("sess_rrdexport_filter");
        kill_session_var("sess_rrdexport_sort_column");
        kill_session_var("sess_rrdexport_sort_direction");
        kill_session_var("sess_rrdexport_rows");
        kill_session_var("sess_rrdexport_host_id");
        kill_session_var("sess_rrdexport_template_id");
        kill_session_var("sess_rrdexport_method_id");
        kill_session_var("sess_rrdexport_associated");

        unset($_REQUEST["page"]);
        unset($_REQUEST["filter"]);
        unset($_REQUEST["sort_column"]);
        unset($_REQUEST["sort_direction"]);
        unset($_REQUEST["ds_rows"]);
        unset($_REQUEST["host_id"]);
        unset($_REQUEST["template_id"]);
        unset($_REQUEST["method_id"]);
        unset($_REQUEST["associated"]);
    }


    /* remember these search fields in session vars so we don't have to keep passing them around */
    load_current_session_value("page", "sess_rrdexport_current_page", "1");
    load_current_session_value("filter", "sess_rrdexport_filter", "");
    load_current_session_value("sort_column", "sess_rrdexport_sort_column", "name_cache");
    load_current_session_value("sort_direction", "sess_rrdexport_sort_direction", "ASC");
    load_current_session_value("ds_rows", "sess_rrdexport_rows", read_config_option("num_rows_data_source"));
    load_current_session_value("host_id", "sess_rrdexport_host_id", "-1");
    load_current_session_value("template_id", "sess_rrdexport_template_id", "-1");
    load_current_session_value("method_id", "sess_rrdexport_method_id", "-1");
    load_current_session_value("associated", "sess_rrdexport_associated", "true");

    $host = db_fetch_row("select hostname from host where id=" . get_request_var_request("host_id"));

    /* if the number of rows is -1, set it to the default */
    if (get_request_var_request("ds_rows") == -1) {
        $_REQUEST["ds_rows"] = read_config_option("num_rows_data_source");
    }

    ?>
    <script type="text/javascript">
        <!--

        function applyDSFilterChange(objForm) {
            strURL = '?tab=data_local&action=edit&id=<?php print get_request_var_request('id');?>'
            strURL = strURL + '&host_id=' + objForm.host_id.value;
            strURL = strURL + '&filter=' + objForm.filter.value;
            strURL = strURL + '&ds_rows=' + objForm.ds_rows.value;
            strURL = strURL + '&template_id=' + objForm.template_id.value;
            strURL = strURL + '&method_id=' + objForm.method_id.value;
            strURL = strURL + '&associated=' + objForm.associated.checked;
            document.location = strURL;
        }

        function clearViewDeviceFilterChange(objForm) {
            strURL = '?tab=data_local&action=edit&id=<?php print get_request_var_request('id');?>&clear_x=true'
            document.location = strURL;
        }

        -->
    </script>
    <?php

    html_start_box("<strong>Associated Data Sources</strong> " . htmlspecialchars($header_label), "100%", $colors["header"], "3", "center", "");

    ?>
    <tr bgcolor="#<?php print $colors["panel"];?>">
        <td>
            <form name="form_data_sources" method="post" action="rrdexport.php?action=edit&tab=data_local">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="50">
                            Host:&nbsp;
                        </td>
                        <td>
                            <select name="host_id" onChange="applyDSFilterChange(document.form_data_sources)">
                                <option value="-1"<?php if (get_request_var_request("host_id") == "-1") {?> selected<?php }?>>Any</option>
                                <option value="0"<?php if (get_request_var_request("host_id") == "0") {?> selected<?php }?>>None</option>
                                <?php
                                $hosts = db_fetch_assoc("select id,CONCAT_WS('',description,' (',hostname,')') as name from host order by description,hostname");

                                if (sizeof($hosts) > 0) {
                                    foreach ($hosts as $host) {
                                        print "<option value='" . $host["id"] . "'"; if (get_request_var_request("host_id") == $host["id"]) { print " selected"; } print ">" . title_trim(htmlspecialchars($host["name"]), 40) . "</option>\n";
                                    }
                                }
                                ?>

                            </select>
                        </td>
                        <td width="50">
                            &nbsp;Template:&nbsp;
                        </td>
                        <td width="1">
                            <select name="template_id" onChange="applyDSFilterChange(document.form_data_sources)">
                                <option value="-1"<?php if (get_request_var_request("template_id") == "-1") {?> selected<?php }?>>Any</option>
                                <option value="0"<?php if (get_request_var_request("template_id") == "0") {?> selected<?php }?>>None</option>
                                <?php

                                $templates = db_fetch_assoc("SELECT DISTINCT data_template.id, data_template.name
								FROM data_template
								INNER JOIN data_template_data
								ON data_template.id=data_template_data.data_template_id
								WHERE data_template_data.local_data_id>0
								ORDER BY data_template.name");

                                if (sizeof($templates) > 0) {
                                    foreach ($templates as $template) {
                                        print "<option value='" . $template["id"] . "'"; if (get_request_var_request("template_id") == $template["id"]) { print " selected"; } print ">" . title_trim(htmlspecialchars($template["name"]), 40) . "</option>\n";
                                    }
                                }
                                ?>

                            </select>
                        </td>
                        <td nowrap style='white-space: nowrap;'>
                            &nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
                            <input type="submit" name="clear_x" value="Clear" title="Clear Filters">
                        </td>
                    </tr>
                    <tr>
                        <td width="50">
                            Method:&nbsp;
                        </td>
                        <td width="1">
                            <select name="method_id" onChange="applyDSFilterChange(document.form_data_sources)">
                                <option value="-1"<?php if (get_request_var_request("method_id") == "-1") {?> selected<?php }?>>Any</option>
                                <option value="0"<?php if (get_request_var_request("method_id") == "0") {?> selected<?php }?>>None</option>
                                <?php

                                $methods = db_fetch_assoc("SELECT DISTINCT data_input.id, data_input.name
								FROM data_input
								INNER JOIN data_template_data
								ON data_input.id=data_template_data.data_input_id
								WHERE data_template_data.local_data_id>0
								ORDER BY data_input.name");

                                if (sizeof($methods) > 0) {
                                    foreach ($methods as $method) {
                                        print "<option value='" . $method["id"] . "'"; if (get_request_var_request("method_id") == $method["id"]) { print " selected"; } print ">" . title_trim(htmlspecialchars($method["name"]), 40) . "</option>\n";
                                    }
                                }
                                ?>
                            </select>
                        </td>
                        <td nowrap style='white-space: nowrap;' width="50">
                            &nbsp;Rows per Page:&nbsp;
                        </td>
                        <td width="1">
                            <select name="ds_rows" onChange="applyDSFilterChange(document.form_data_sources)">
                                <option value="-1"<?php if (get_request_var_request("ds_rows") == "-1") {?> selected<?php }?>>Default</option>
                                <?php
                                if (sizeof($item_rows) > 0) {
                                    foreach ($item_rows as $key => $value) {
                                        print "<option value='" . $key . "'"; if (get_request_var_request("ds_rows") == $key) { print " selected"; } print ">" . htmlspecialchars($value) . "</option>\n";
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <table cellpadding="1" cellspacing="0">
                    <tr>
                        <td width="50">
                            Search:&nbsp;
                        </td>
                        <td width="1">
                            <input type="text" name="filter" size="40" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
                        </td>
                        <td>
                            <input type='checkbox' name='associated' id='associated' onChange='applyDSFilterChange(document.form_data_sources)' <?php print ($_REQUEST['associated'] == 'true' || $_REQUEST['associated'] == 'on' ? 'checked':'');?>>
                        </td>
                        <td width="50">
                            Associated?
                        </td>
                    </tr>
                </table>
                <input type='hidden' name='page' value='1'>
                <input type='hidden' name='id' value='<?php print get_request_var_request('id');?>'>
            </form>
        </td>
    </tr>
    <?php

    html_end_box();

    /* form the 'where' clause for our main sql query */
    if (strlen(get_request_var_request("filter"))) {
        $sql_where1 = "AND (data_template_data.name_cache like '%%" . get_request_var_request("filter") . "%%'" .
            " OR data_template_data.local_data_id like '%%" . get_request_var_request("filter") . "%%'" .
            " OR data_template.name like '%%" . get_request_var_request("filter") . "%%'" .
            " OR data_input.name like '%%" . get_request_var_request("filter") . "%%')";

        $sql_where2 = "AND (data_template_data.name_cache like '%%" . get_request_var_request("filter") . "%%'" .
            " OR data_template.name like '%%" . get_request_var_request("filter") . "%%')";
    }else{
        $sql_where1 = "";
        $sql_where2 = "";
    }

    if (get_request_var_request("host_id") == "-1") {
        /* Show all items */
    }elseif (get_request_var_request("host_id") == "0") {
        $sql_where1 .= " AND data_local.host_id=0";
        $sql_where2 .= " AND data_local.host_id=0";
    }elseif (!empty($_REQUEST["host_id"])) {
        $sql_where1 .= " AND data_local.host_id=" . get_request_var_request("host_id");
        $sql_where2 .= " AND data_local.host_id=" . get_request_var_request("host_id");
    }

    if (get_request_var_request("template_id") == "-1") {
        /* Show all items */
    }elseif (get_request_var_request("template_id") == "0") {
        $sql_where1 .= " AND data_template_data.data_template_id=0";
        $sql_where2 .= " AND data_template_data.data_template_id=0";
    }elseif (!empty($_REQUEST["host_id"])) {
        $sql_where1 .= " AND data_template_data.data_template_id=" . get_request_var_request("template_id");
        $sql_where2 .= " AND data_template_data.data_template_id=" . get_request_var_request("template_id");
    }

    if (get_request_var_request("method_id") == "-1") {
        /* Show all items */
    }elseif (get_request_var_request("method_id") == "0") {
        $sql_where1 .= " AND data_template_data.data_input_id=0";
        $sql_where2 .= " AND data_template_data.data_input_id=0";
    }elseif (!empty($_REQUEST["method_id"])) {
        $sql_where1 .= " AND data_template_data.data_input_id=" . get_request_var_request("method_id");
        $sql_where2 .= " AND data_template_data.data_input_id=" . get_request_var_request("method_id");
    }

    if (get_request_var_request("associated") == "false") {
        /* Show all items */
    } else {
        $sql_where1 .= " AND plugin_rrdexport_datasource.schedule=" . get_request_var_request('id');
    }

    $total_rows = sizeof(db_fetch_assoc("SELECT
		data_local.id
		FROM (data_local,data_template_data)
		LEFT JOIN data_input
		ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template
		ON (data_local.data_template_id=data_template.id)
		LEFT JOIN plugin_rrdexport_datasource
		ON (data_local.id=plugin_rrdexport_datasource.local_data_id)
		WHERE data_local.id=data_template_data.local_data_id
		$sql_where1"));

    $poller_intervals = array_rekey(db_fetch_assoc("SELECT data_template_data.local_data_id AS id,
		Min(data_template_data.rrd_step*rra.steps) AS poller_interval
		FROM data_template
		INNER JOIN (data_local
		INNER JOIN ((data_template_data_rra
		INNER JOIN data_template_data ON data_template_data_rra.data_template_data_id=data_template_data.id)
		INNER JOIN rra ON data_template_data_rra.rra_id = rra.id) ON data_local.id = data_template_data.local_data_id) ON data_template.id = data_template_data.data_template_id
		$sql_where2
		GROUP BY data_template_data.local_data_id"), "id", "poller_interval");

    $data_sources = db_fetch_assoc("SELECT
		data_template_data.local_data_id,
		data_template_data.name_cache,
		data_template_data.active,
		data_input.name as data_input_name,
		data_template.name as data_template_name,
		plugin_rrdexport_datasource.schedule as associated,
		data_local.host_id
		FROM (data_local,data_template_data)
		LEFT JOIN data_input
		ON (data_input.id=data_template_data.data_input_id)
		LEFT JOIN data_template
		ON (data_local.data_template_id=data_template.id)
		LEFT JOIN plugin_rrdexport_datasource
		ON (data_local.id=plugin_rrdexport_datasource.local_data_id)
		WHERE data_local.id=data_template_data.local_data_id
		$sql_where1
		ORDER BY ". get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
        " LIMIT " . (get_request_var_request("ds_rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("ds_rows"));

    print "<form name='chk' method='post' action='rrdexport.php'>\n";

    html_start_box("", "100%", $colors["header"], "3", "center", "");

    /* generate page list */
    $url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("ds_rows"), $total_rows, "rrdexport.php?tab=data_local&action=edit&id=". get_request_var_request('id') ."&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id"));

    $nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='7'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("rrdexport.php?tab=data_local&action=edit&id=". get_request_var_request('id') ."&associated=" . get_request_var_request("associated") ."&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . ((get_request_var_request("ds_rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < get_request_var_request("ds_rows")) || ($total_rows < (get_request_var_request("ds_rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("ds_rows")*get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * get_request_var_request("ds_rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("rrdexport.php?tab=data_local&action=edit&id=". get_request_var_request('id') ."&associated=" . get_request_var_request("associated") ."&filter=" . get_request_var_request("filter") . "&host_id=" . get_request_var_request("host_id") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("ds_rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

    print $nav;

    $display_text = array(
        "associated" => array("Scheduled", "ASC"),
        "name_cache" => array("Name", "ASC"),
        "local_data_id" => array("ID","ASC"),
        "data_input_name" => array("Data Input Method", "ASC"),
        "nosort" => array("Poller Interval", "ASC"),
        "active" => array("Active", "ASC"),
        "data_template_name" => array("Template Name", "ASC"));

    html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

    $i = 0;
    if (sizeof($data_sources) > 0) {
        foreach ($data_sources as $data_source) {
            $data_source["data_template_name"] = htmlspecialchars($data_source["data_template_name"]);
            $data_name_cache = title_trim(htmlspecialchars($data_source["name_cache"]), read_config_option("max_title_data_source"));

            if (trim(get_request_var_request("filter") != "")) {
                $data_source['data_input_name'] = (preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", htmlspecialchars($data_source['data_input_name'])));
                $data_source['data_template_name'] = preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", $data_source['data_template_name']);
                $data_name_cache = preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", ($data_name_cache));
            }

            /* keep copy of data source for comparison */
            $data_source_orig = $data_source;
            $data_source = api_plugin_hook_function('data_sources_table', $data_source);
            /* we're escaping strings here, so no need to escape them on form_selectable_cell */
            if ($data_source_orig["data_template_name"] != $data_source["data_template_name"]) {
                /* was changed by plugin, plugin has to take care for html-escaping */
                $data_template_name = ((empty($data_source["data_template_name"])) ? "<em>None</em>" : $data_source["data_template_name"]);
            } else {
                /* we take care of html-escaping */
                $data_template_name = ((empty($data_source["data_template_name"])) ? "<em>None</em>" : htmlspecialchars($data_source["data_template_name"]));
            }
            if ($data_source_orig["data_input_name"] != $data_source["data_input_name"]) {
                /* was changed by plugin, plugin has to take care for html-escaping */
                $data_input_name = ((empty($data_source["data_input_name"])) ? "<em>None</em>" : $data_source["data_input_name"]);
            } else {
                /* we take care of html-escaping, see above */
                $data_input_name = ((empty($data_source["data_input_name"])) ? "<em>External</em>" : $data_source["data_input_name"]);
            }
            $poller_interval    = ((isset($poller_intervals[$data_source["local_data_id"]])) ? $poller_intervals[$data_source["local_data_id"]] : 0);

            if ($data_source['associated'] != '') {
                $schedule_names = '<span style="color:green;font-weight:bold;">Current List</span>';
            } else {
                $schedule_names = '';
            }
            if (sizeof($lists = db_fetch_assoc("SELECT name FROM plugin_rrdexport_schedules INNER JOIN plugin_rrdexport_datasource ON plugin_rrdexport_schedules.id=plugin_rrdexport_datasource.schedule WHERE local_data_id=" . $data_source["local_data_id"] . " AND plugin_rrdexport_schedules.id != " . get_request_var_request('id')))) {
                foreach($lists as $sc) {
                    $schedule_names .= (strlen($schedule_names) ? ", ":"") . "<span style='color:purple;font-weight:bold;'>" . $sc['name'] . "</span>";
                }
            }
            if ($schedule_names == '') {
                $schedule_names = '<span style="color:red;font-weight:bold;">No Schedules</span>';
            }

            form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $data_source["local_data_id"]); $i++;
            form_selectable_cell($schedule_names, $data_source['local_data_id']);
            form_selectable_cell("<a class='linkEditMain' href='" . htmlspecialchars("data_sources.php?action=ds_edit&id=" . $data_source["local_data_id"]) . "' title='" . $data_source["name_cache"] . "'>" . ((get_request_var_request("filter") != "") ? preg_replace("/(" . preg_quote(get_request_var_request("filter"), "/") . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim(htmlspecialchars($data_source["name_cache"]), read_config_option("max_title_data_source"))) : title_trim(htmlspecialchars($data_source["name_cache"]), read_config_option("max_title_data_source"))) . "</a>", $data_source["local_data_id"]);
            form_selectable_cell($data_source['local_data_id'], $data_source['local_data_id']);
            form_selectable_cell($data_input_name, $data_source["local_data_id"]);
            form_selectable_cell(get_poller_interval($poller_interval), $data_source["local_data_id"]);
            form_selectable_cell(($data_source['active'] == "on" ? "Yes" : "No"), $data_source["local_data_id"]);
            form_selectable_cell($data_template_name, $data_source["local_data_id"]);
            form_checkbox_cell($data_source["name_cache"], $data_source["local_data_id"]);
            form_end_row();
        }

        /* put the nav bar on the bottom as well */
        print $nav;
    }else{
        print "<tr><td><em>No Data Sources</em></td></tr>";
    }

    html_end_box(false);

    form_hidden_box("id", get_request_var_request("id"), "");
    form_hidden_box("save_datasource", "1", "");

    /* draw the dropdown containing a list of available actions for this form */
    draw_actions_dropdown($assoc_actions);

    print "</form>\n";
}

function get_poller_interval($seconds) {
    if ($seconds == 0) {
        return "<em>External</em>";
    }else if ($seconds < 60) {
        return "<em>" . $seconds . " Seconds</em>";
    }else if ($seconds == 60) {
        return "1 Minute";
    }else{
        return "<em>" . ($seconds / 60) . " Minutes</em>";
    }
}

function get_header_label() {
    if (!empty($_REQUEST["id"])) {
        $_GET["id"] = $_REQUEST["id"];
        $list = db_fetch_row("SELECT * FROM plugin_rrdexport_schedules WHERE id=" . $_REQUEST["id"]);
        $header_label = "[edit: " . $list["name"] . "]";
    } else {
        $header_label = "[new]";
    }

    return $header_label;
}

function schedule_delete() {
    $selected_items = unserialize(stripslashes($_POST["selected_items"]));
    foreach($selected_items as $id) {
        input_validate_input_number($id);
        db_fetch_assoc("DELETE FROM plugin_rrdexport_schedules WHERE id = $id LIMIT 1");
        db_fetch_assoc("DELETE FROM plugin_rrdexport_datasource WHERE schedule = $id");
    }

    Header('Location: rrdexport.php');
    exit;
}

function schedule_list() {
    global $colors, $schedule_actions, $sc_intervals;

    html_start_box('<strong>RRD Export Schedules</strong>', '100%', $colors['header'], '3', 'center', 'rrdexport.php?tab=general&action=edit');

    html_header_checkbox(array('Name',  'Start', 'Last Execute', 'Interval', 'Enabled'));

    $schedules = db_fetch_assoc('SELECT * FROM plugin_rrdexport_schedules ORDER BY name');

    $i = 0;
    if (sizeof($schedules) > 0) {
        foreach ($schedules as $schedule) {
            form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $schedule["id"]); $i++;
            form_selectable_cell('<a class="linkEditMain" href="rrdexport.php?action=edit&id=' . $schedule['id'] . '">' . $schedule['name'] . '</a>', $schedule["id"]);
            form_selectable_cell(date("F j, Y, G:i", $schedule['stime']), $schedule["id"]);
            form_selectable_cell(date("F j, Y, G:i", $schedule['ltime']), $schedule["id"]);
            form_selectable_cell($sc_intervals[$schedule['sc_interval']], $schedule["id"]);
            form_selectable_cell($schedule['enabled'], $schedule["id"]);
            form_checkbox_cell($schedule['name'], $schedule["id"]);
            form_end_row();
        }
    }else{
        print "<tr><td><em>No RRD Export Schedules</em></td></tr>\n";
    }
    html_end_box(false);

    form_hidden_box('save_list', '1', '');

    draw_actions_dropdown($schedule_actions);

    print "</form>\n";
}


