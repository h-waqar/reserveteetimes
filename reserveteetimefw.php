<?php
/*
Plugin Name: ! Reserve Tee Time Framework
Description: Plugin to integrate the site with golf platforms to display golf courses info and reserve tee times
Author: Agencia Adhoc
Version: 1.0
*/


if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

$GOLF_API_USER = 'info@reserveteetime.com';
$GOLF_API_PASS = 'wDIbkYM73s0PDzEHDOjdJGM4MoZBb3';
$GOLF_MANAGER_AUTH = 'Basic ' . base64_encode($GOLF_API_USER . ':' . $GOLF_API_PASS);

define('GOLF_MANAGER_AUTH', $GOLF_MANAGER_AUTH);
define('GOLF_MANAGER_DIR', plugin_dir_path(__FILE__));
define('GOLF_MANAGER_URL', plugin_dir_url(__FILE__));
define('RTT_DEBUG', true);


// Require all API endpoint files
foreach (glob(plugin_dir_path(__FILE__) . 'api/*.php') as $file) {
	require_once $file;
}

require_once GOLF_MANAGER_DIR . 'sync/sync-courses.php';


add_action('init', 'test', 999);

function test()
{
	if (isset($_GET['hamza'])) {
		$post_id = 3131;

		echo '<pre>';
		print_r(get_post_meta($post_id));
		echo '</pre>';
		wp_die();
	}
}


// add_action('admin_init', function () {
// 	if (!current_user_can('manage_options')) return;

// 	global $rttgolfmanager;
// 	require_once GOLF_MANAGER_DIR . 'classes/golfmanager.rttclass.php';

// 	$rttgolfmanager = new rttgolfmanager();

// 	$post_id = 3130;
// 	$start_date = '2025-09-18T08:00:00';
// 	$results = $rttgolfmanager->getteetimes($post_id, $start_date);

// 	echo "<pre>";
// 	print_r($results);
// 	echo "</pre>";

// 	wp_die(); // stops execution, only for testing
// });




/*
**********************************************************************************************************
* ACTIVATION/DEACTIVATION FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that runs on plugin activation
*/
register_activation_hook(__FILE__, 'reserveteetimefw_install');
function reserveteetimefw_install()
{

	global $wpdb;

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	//Creates the table for saving logs if it doesn't exist
	$table_name = $wpdb->prefix . 'rttfw_logs';
	if ($wpdb->get_var("show tables like '" . $table_name . "'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
			rttfwlog_id int(12) NOT NULL AUTO_INCREMENT,
			rttfwlog_date int(12) NOT NULL,
			rttfwlog_platform varchar(100) NOT NULL,
			rttfwlog_status int(12) NOT NULL,
			rttfwlog_details text NOT NULL,
			PRIMARY KEY  (rttfwlog_id)
		) DEFAULT CHARSET=utf8;";
		dbDelta($sql);
	}

	//Creates the table for saving added percentages to reservation prices if it doesn't exist
	$table_name = $wpdb->prefix . 'rttfw_addedpercentages';
	if ($wpdb->get_var("show tables like '" . $table_name . "'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
			rttfwadd_id int(12) NOT NULL AUTO_INCREMENT,
			rttfwadd_platform varchar(100) NOT NULL,
			rttfwadd_course int(12) NOT NULL,
			rttfwadd_value float NOT NULL,
			rttfwadd_startdate int(12) NOT NULL,
			rttfwadd_enddate int(12) NOT NULL,
			PRIMARY KEY  (rttfwadd_id)
		) DEFAULT CHARSET=utf8;";
		dbDelta($sql);
	}

	//Creates the table for saving relations between countries and cities if it doesn't exist
	$table_name = $wpdb->prefix . 'rttfw_countriescities';
	if ($wpdb->get_var("show tables like '" . $table_name . "'") != $table_name) {
		$sql = "CREATE TABLE IF NOT EXISTS " . $table_name . " (
			rttfwadd_cityid int(12) NOT NULL,
			rttfwadd_countryid int(12) NOT NULL,
			PRIMARY KEY  (rttfwadd_cityid)
		) DEFAULT CHARSET=utf8;";
		dbDelta($sql);
	}

	//Creates the table for saving currency conversions if it doesn't exist
	$table_name = $wpdb->prefix . 'rttfw_curconv';
	if ($wpdb->get_var("show tables like '" . $table_name . "'") != $table_name) {
		$sql = "CREATE TABLE " . $table_name . " (
			rttfwconv_id varchar(255) NOT NULL,
			rttfwconv_value text NOT NULL,
			PRIMARY KEY  (rttfwconv_id)
		) DEFAULT CHARSET=utf8;";
		dbDelta($sql);

		//Populates the currency conversions table
		$req_url = 'https://v6.exchangerate-api.com/v6/0b1c187ce02f03a5391a97e7/latest/EUR';
		$response_json = file_get_contents($req_url);
		if ($response_json) {
			$response_object = json_decode($response_json);
			if (is_object($response_object)) {
				foreach ($response_object->conversion_rates as $currency => $value)
					$wpdb->insert($wpdb->prefix . 'rttfw_curconv', array("rttfwconv_id" => $currency, "rttfwconv_value" => $value));
			}
		}
	}

	//Leftover from when the plugin used its own course custom post type
	//reserveteetimefw_registergolfcoursecpt();
	//flush_rewrite_rules();

	//Sets a cron job every 30 minutes for various tasks
	if (!wp_next_scheduled('reserveteetimefw_cronhook'))
		wp_schedule_event(time(), 'every-30-minutes', 'reserveteetimefw_cronhook');

	//Sets a daily cron job for the currency conversion updates
	if (!wp_next_scheduled('reserveteetimefw_croncurrency'))
		wp_schedule_event(time(), 'daily', 'reserveteetimefw_croncurrency');
}

/*
* Function that runs on plugin deactivation
*/
register_deactivation_hook(__FILE__, 'reserveteetimefw_uninstall');
function reserveteetimefw_uninstall()
{

	//Unsets the cron jobs
	wp_clear_scheduled_hook('reserveteetimefw_cronhook');
	wp_clear_scheduled_hook('reserveteetimefw_croncurrency');
}

/*
**********************************************************************************************************
* INCLUDES AND ASSETS FUNCTIONS
**********************************************************************************************************
*/

/*
* Adds custom CSS and JS to the backend
*/
function reserveteetimefw_admincustomcssjs()
{
	$plugin_url = plugin_dir_url(__FILE__);
	wp_enqueue_style('reserveteetimefw-adminstyle', $plugin_url . 'css/reserveteetimefw-admin.css');
	wp_enqueue_script('reserveteetimefw-adminjs', $plugin_url . 'js/reserveteetimefw-admin.js');
}
add_action('admin_enqueue_scripts', 'reserveteetimefw_admincustomcssjs', 999);

/*
* Adds custom CSS and JS to the frontend
*/
function reserveteetimefw_customcssjs()
{
	$plugin_url = plugin_dir_url(__FILE__);
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('reserveteetimefw-jqueryuistyle', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css', false, "1.9.0", false);
	wp_enqueue_style('reserveteetimefw-style', $plugin_url . 'css/reserveteetimefw.css', false, time());
	wp_enqueue_style('reserveteetimefw-selectizestyle', $plugin_url . 'css/selectize.default.min.css');
	wp_enqueue_script('reserveteetimefw-js', $plugin_url . 'js/reserveteetimefw.js', false, time());
	wp_enqueue_script('reserveteetimefw-selectize', $plugin_url . 'js/selectize.js', "1.1");
	wp_localize_script('jquery', 'reserveteetimefw_ajax', array('ajaxurl' => admin_url('admin-ajax.php'), 'loadingurl' => $plugin_url . 'assets/loading.gif'));
}
add_action('wp_enqueue_scripts', 'reserveteetimefw_customcssjs', 999);

/*
* Includes for the backend sections
*/
include('inc/logs.inc.php');
//include('inc/viewgcinfo.inc.php');

/*
* Global array of reservation platforms
*/
// $reserveteetimefw_classes = array("zestgolf",);
$reserveteetimefw_classes = array("zestgolf", "golfmanager");

/*
* Includes for the reservation platforms' classes
*/
include('classes/zestgolf.rttclass.php');
include('classes/golfmanager.rttclass.php');

/*
* Includes for the frontend templates
*/
include('templates/golfcourseslist.php');
include('templates/golfsearchteetime.php');
include('templates/homepagesearchform.php');

/*
**********************************************************************************************************
* CRON JOB FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that specifies the frequency for the cron job that runs every 30 minutes
*/
add_filter('cron_schedules', function ($schedules) {
	$schedules['every-30-minutes'] = array(
		'interval' => 30 * MINUTE_IN_SECONDS,
		'display'  => __('Every 30 minutes')
	);
	return $schedules;
});

/*
* Function that runs the golf course sync script for every single reservation platform
* Connected to the cron job that runs every 30 minutes
*/
add_action('reserveteetimefw_cronhook', 'reserveteetimefw_syncgolfcourses');
function reserveteetimefw_syncgolfcourses()
{

	global $reserveteetimefw_classes;

	foreach ($reserveteetimefw_classes as $platform_name) {
		$classname = "rtt" . $platform_name;
		$platform = new $classname();
		$platform->syncgolfcourses();
	}
}

/*
* Function that runs the script that saves the relation between countries and cities for the homepage search form
* Connected to the cron job that runs every 30 minutes
*/
add_action('reserveteetimefw_cronhook', 'reserveteetimefw_synccitiescountries');
function reserveteetimefw_synccitiescountries()
{

	global $wpdb;

	//reserveteetimefw_savelog(1,"Start sync cities and countries.");

	$args = array(
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'post_type' => 'courses',
		'meta_query' => array(),
		'tax_query' => array(
			'relation' => 'AND'
		),
		'orderby' => 'date',
		'order' => 'desc'
	);

	$array_citiescountries = array();

	$search_golfcourses = new WP_Query($args);

	if ($search_golfcourses->have_posts()) :
		while ($search_golfcourses->have_posts()) :

			$search_golfcourses->the_post();

			$id_post = get_the_ID();

			$cities = get_the_terms($id_post, 'course-city');
			$countries = get_the_terms($id_post, 'course-destination');

			if ($cities && !empty($cities) && $countries && !empty($countries)) {
				$city = array_pop($cities);
				$country = array_pop($countries);

				$array_citiescountries[$city->term_id] = $country->term_id;
			}

		endwhile;
	endif;

	$wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . 'rttfw_countriescities');
	foreach ($array_citiescountries as $city_id => $country_id) {
		$wpdb->insert($wpdb->prefix . 'rttfw_countriescities', array("rttfwadd_cityid" => $city_id, "rttfwadd_countryid" => $country_id));
	}

	//reserveteetimefw_savelog(1,"End sync cities and countries.");
}

/*
* Function that updates the currency conversion rates
* Connected to the cron job that runs daily
*/
add_action('reserveteetimefw_croncurrency', 'reserveteetimefw_updatecurrencies');
function reserveteetimefw_updatecurrencies()
{

	global $wpdb;

	$req_url = 'https://v6.exchangerate-api.com/v6/0b1c187ce02f03a5391a97e7/latest/EUR';
	$response_json = file_get_contents($req_url);
	if ($response_json) {
		$response_object = json_decode($response_json);
		if (is_object($response_object)) {
			foreach ($response_object->conversion_rates as $currency => $value)
				$wpdb->update($wpdb->prefix . 'rttfw_curconv', array("rttfwconv_value" => $value), array("rttfwconv_id" => $currency));

			reserveteetimefw_savelog(1, "Currencies updated successfully.");
		}
	}
}

/*
**********************************************************************************************************
* MENU FUNCTIONS
**********************************************************************************************************
*/

/*
* Functions for setting up the admin menu
*/
function reserveteetimefw_setup_menu()
{
	add_menu_page(__("RTT", "reserveteetimefw"), __("RTT", "reserveteetimefw"), "manage_options", "main-reserveteetimemenu", "reserveteetimefwconf_init", "dashicons-admin-settings");
	add_submenu_page("main-reserveteetimemenu", __("Settings", "reserveteetimefw"), __("Settings", "reserveteetimefw"), "manage_options", "main-reserveteetimemenu", "reserveteetimefwconf_init");
	add_submenu_page("main-reserveteetimemenu", __("Logs", "reserveteetimefw"), __("Logs", "reserveteetimefw"), "manage_options", "main-reserveteetimemenulog", "reserveteetimefwlogs_init");
	add_submenu_page("main-reserveteetimemenu", __("Platform added percentages", "reserveteetimefw"), '', "manage_options", "main-reserveteetimeaddedpercentages", "reserveteetimeaddedpercentages_init");
	//add_submenu_page("edit.php?post_type=rttcourse",__("View golf course info","reserveteetimefw"),'',"manage_options","main-reserveteetimemenuviewgcinfo","reserveteetimemenuviewgcinfo_init");
}
add_action('admin_menu', 'reserveteetimefw_setup_menu');

function reserveteetimefw_submenu_filter($submenu_file)
{

	global $plugin_page;

	/*$hidden_submenus = array(
	"main-reserveteetimemenuviewgcinfo" => 'edit.php?post_type=rttcourse',
	"main-reserveteetimeaddedpercentages" => 'main-reserveteetimemenu'
	);*/

	$hidden_submenus = array(
		"main-reserveteetimeaddedpercentages" => 'main-reserveteetimemenu'
	);

	if ($plugin_page && isset($hidden_submenus[$plugin_page]))
		$submenu_file = $hidden_submenus[$plugin_page];

	foreach ($hidden_submenus as $submenu => $submenu_id)
		remove_submenu_page($submenu_id, $submenu);

	return $submenu_file;
}
add_filter('submenu_file', 'reserveteetimefw_submenu_filter');

/*
**********************************************************************************************************
* SETTINGS FUNCTIONS
**********************************************************************************************************
*/

/*
* Function for displaying the SETTINGS section in the backend
*/
function reserveteetimefwconf_init()
{
	global $reserveteetimefw_classes;

	if (!current_user_can('manage_options'))
		wp_die(__("You have no permissions to visualize this page", "reserveteetimefw"));

?>
	<div class="reserveteetimefw-adminpage wrap">
		<h1>
			<div id="icon-edit-pages" class="icon32"></div><?= __("Settings", "reserveteetimefw") ?>
		</h1>
		<?php if (isset($_GET["m"]) && intval($_GET["m"]) == 1) { ?>
			<div class="updated notice">
				<p><?= __("The operation was successful.", "reserveteetimefw") ?></p>
			</div>
		<?php } else if (isset($_GET["m"]) && intval($_GET["m"]) == 2) { ?>
			<div class="error notice">
				<p><?= __("There was a problem performing the operation.", "reserveteetimefw") ?></p>
			</div>
		<?php } ?>
		<form class="reserveteetimefw-confform reserveteetimefw-form" method="post" action="admin-post.php">
			<h3><?= __("Global settings", "reserveteetimefw") ?></h3>
			<label for="rtt_defaultadd">
				<span class="reserveteetimefw-fieldtitle"><?= __("Default added percentage to prices", "reserveteetimefw") ?> (%):</span>
				<input type="text" id="rtt_defaultadd" name="rtt_defaultadd" value="<?= get_option("rtt_defaultadd") ?>" />
			</label>
			<?php
			//Displays the settings for every single reservation platform
			foreach ($reserveteetimefw_classes as $platform_name) {
				$classname = "rtt" . $platform_name;
				$platform = new $classname();

				echo $platform->displaysettings();
				echo "<hr/>";
			}
			?>
			<?php wp_nonce_field('cc_op_verify'); ?>
			<input type="hidden" name="action" value="rttfwconfform" />
			<input type="submit" value="<?= __("Submit", "reserveteetimefw") ?>" class="button-secondary" />
		</form>
	</div>
<?php
}

/*
* Function that processes the form in the SETTINGS section in the backend
*/
add_action('admin_post_rttfwconfform', 'process_rttfwconfform');
function process_rttfwconfform()
{

	global $reserveteetimefw_classes;

	if (!current_user_can('manage_options'))
		wp_die(__("You have no permissions to visualize this page", "reserveteetimefw"));

	check_admin_referer('cc_op_verify');

	if (isset($_POST["rtt_defaultadd"]))
		update_option("rtt_defaultadd", floatval($_POST["rtt_defaultadd"]));
	else
		delete_option("rtt_defaultadd");

	//Processes the settings for every single reservation platform
	foreach ($reserveteetimefw_classes as $platform_name) {
		$classname = "rtt" . $platform_name;
		$platform = new $classname();
		$platform->savesettings();
	}

	wp_redirect(admin_url('admin.php?page=main-reserveteetimemenu&m=1'));

	exit;
}

/*
* Function for displaying the ADDED PERCENTAGES TO PRICES section for a given reservation platform in the backend
*/
function reserveteetimeaddedpercentages_init()
{
	global $reserveteetimefw_classes;

	if (!current_user_can('manage_options'))
		wp_die(__("You have no permissions to visualize this page", "reserveteetimefw"));

	wp_enqueue_script('jquery-ui-datepicker');
	wp_enqueue_style('jfathletes-jqueryuistyle', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css', false, "1.9.0", false);
?>
	<div class="reserveteetimefw-adminpage wrap">
		<h1>
			<div id="icon-edit-pages" class="icon32"></div><?= __("Added percentages to prices", "reserveteetimefw") ?>
		</h1>
		<?php if (isset($_GET["m"]) && intval($_GET["m"]) == 1) { ?>
			<div class="updated notice">
				<p><?= __("The operation was successful.", "reserveteetimefw") ?></p>
			</div>
		<?php } else if (isset($_GET["m"]) && intval($_GET["m"]) == 2) { ?>
			<div class="error notice">
				<p><?= __("There was a problem performing the operation.", "reserveteetimefw") ?></p>
			</div>
		<?php } ?>
		<?php
		$platform_name = $_GET["platform"];
		if (in_array($platform_name, $reserveteetimefw_classes)) {
			$classname = "rtt" . $platform_name;
			$platform = new $classname();
			echo $platform->displayaddedpercentages();
		} else {
		?>
			<div class="error notice">
				<p><?= __("Invalid platform.", "reserveteetimefw") ?></p>
			</div>
		<?php
		}
		?>
	</div>
	<?php
}

/*
* Function that processes the form in the ADDED PERCENTAGES TO PRICES section for a given reservation platform in the backend
*/
add_action('admin_post_rttfwpercentagesform', 'process_rttfwpercentagesform');
function process_rttfwpercentagesform()
{

	global $reserveteetimefw_classes;

	if (!current_user_can('manage_options'))
		wp_die(__("You have no permissions to visualize this page", "reserveteetimefw"));

	check_admin_referer('cc_op_verify');

	$platform_name = $_POST["platform"];
	if (in_array($platform_name, $reserveteetimefw_classes)) {
		$subaction = $_POST["subaction"];
		$classname = "rtt" . $platform_name;
		$platform = new $classname();
		if (method_exists($platform, $subaction)) {
			$result = $platform->$subaction();
			if ($result)
				wp_redirect(admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=' . $platform_name . '&m=1'));
			else
				wp_redirect(admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=' . $platform_name . '&m=2'));
		} else
			wp_redirect(admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=' . $platform_name . '&m=2'));
	} else
		wp_redirect(admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=' . $platform_name . '&m=2'));

	exit;
}

/*
**********************************************************************************************************
* LOGS FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that adds a log entry
* 
* @param int $status							1 = Green row, 2 = Red row
* @param string $details						Text for the log entry
* @param string $platform						Name of the reservation platform related to the log entry, defaults to "General"
*
* @return bool 									Whether the entry was added successfully or not
*/
function reserveteetimefw_savelog($status, $details, $platform = "General")
{
	global $wpdb;

	$array_insert = array(
		"rttfwlog_date" => time(),
		"rttfwlog_platform" => esc_sql($platform),
		"rttfwlog_status" => intval($status),
		"rttfwlog_details" => esc_sql($details)
	);
	$wpdb->insert($wpdb->prefix . 'rttfw_logs', $array_insert);
}

/*
**********************************************************************************************************
* REMINDER EMAILS FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that sends reminder emails to orders that has been pending payment for longer than an hour
* Connected to the cron job that runs every 30 minutes
*/
add_action('reserveteetimefw_cronhook', 'reserveteetimefw_sendreminderemails');
function reserveteetimefw_sendreminderemails()
{

	//reserveteetimefw_savelog(1,"Start reminder log.");

	$args = array(
		'status' => 'wc-pending',
		'limit' => -1
	);

	$mailer = WC()->mailer();
	$subject = "You have an incomplete reservation";
	ob_start();
	wc_get_template('emails/email-header.php', array('email_heading' => 'Incomplete reservation'));
	echo "<p>We noticed you tried to make a reservation but did not complete it. If you are having any problems do not hesitate to <a href='tel:+18038669545'>call us</a> or <a href='mailto:golf@reserveteetime.com'>contact us via email</a>.</p>";
	wc_get_template('emails/email-footer.php');
	$message = ob_get_clean();

	$current_date = time();
	$pending_orders = wc_get_orders($args);
	foreach ($pending_orders as $order) {
		$order_id = $order->get_id();
		$sent_reminder = get_post_meta($order_id, "sent_reminder", true);
		if (!$sent_reminder) //Don't do anything if a reminder email has already been sent for this order
		{
			$order_date = strtotime($order->get_date_created());
			$date_diff = $current_date - $order_date;
			if ($date_diff > 3600) //Only send the email if it's been more than an hour since the order creation
			{
				$customer_email = $order->get_billing_email();
				$mailer->send($customer_email, $subject, $message);
				update_post_meta($order_id, "sent_reminder", $current_date);
				reserveteetimefw_savelog(1, "Sent reminder to order " . $order_id . ".");
			}
		}
	}
	wp_reset_postdata();

	//reserveteetimefw_savelog(1,"Finished reminder log.");
}

/*
* Function that adds the information regarding the reminder email to the order page
*/
add_action('woocommerce_admin_order_data_after_order_details', 'reserveteetimefw_additionalorderinfo');
function reserveteetimefw_additionalorderinfo($order)
{

	$reminder_date = get_post_meta($order->id, 'sent_reminder', true);
	if (($reminder_date && $reminder_date != "")) {
	?>
		<div class="order_data_column">
			<h4><?php _e('Additional information'); ?></h4>
			<div class="paddyfw_infoadicional">
				<?php
				echo '<p><strong>' . __('Sent reminder') . ':</strong> ' . date("d-m-Y H:i:s", $reminder_date) . '</p>';
				?>
			</div>
		</div>
	<?php
	}
}

/*
**********************************************************************************************************
* GOLF COURSE PAGE FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that returns the full, clean name of the golf course service from the truncated, slugified identifier
* 
* @param string $id_service						Service identifier
*
* @return string 								Full name of the golf course service
*/
function reserveteetimefw_getservicename($id_service)
{
	switch ($id_service) {
		case "drivingRange":
			return __("Driving Range", "reserveteetimefw");
			break;
		case "chippingArea":
			return __("Chipping Area", "reserveteetimefw");
			break;
		case "puttingGreen":
			return __("Putting Green", "reserveteetimefw");
			break;
		case "proShop":
			return __("Pro Shop", "reserveteetimefw");
			break;
		case "golfSchool":
			return __("Golf School", "reserveteetimefw");
			break;
		case "golfBuggy":
			return __("Golf Buggy", "reserveteetimefw");
			break;
		case "pushTrolley":
			return __("Push Trolley", "reserveteetimefw");
			break;
		case "electricTrolley":
			return __("Electric Trolley", "reserveteetimefw");
			break;
		case "golfClubRentals":
			return __("Golf Club Rentals", "reserveteetimefw");
			break;
		case "caddies":
			return __("Caddies", "reserveteetimefw");
			break;
		case "restaurant":
			return __("Restaurant", "reserveteetimefw");
			break;
		case "bar":
			return __("Bar", "reserveteetimefw");
			break;
		case "lockerRoom":
			return __("Locker Room", "reserveteetimefw");
			break;
		case "wifi":
			return __("WiFi", "reserveteetimefw");
			break;
		case "shuttleService":
			return __("Shuttle Service", "reserveteetimefw");
			break;
		default:
			return $id_service;
			break;
	}
}

/*
* Shortcode that adds the reservation form to a golf course page
*/
add_shortcode('rtt-bookingform', 'reserveteetimefw_load_bookingform');
function reserveteetimefw_load_bookingform()
{
	global $reserveteetimefw_classes;

	$id_currentcourse = get_the_ID();
	$name_currentcourse = get_the_title();
	$platform_currentcourse = get_post_meta($id_currentcourse, "plataforma", true);
	$mode_currentcourse = get_post_meta($id_currentcourse, "modo", true);

	$default_date = "";
	$default_players = "";
	$default_holes = "";

	if (isset($_GET["date"]) && $_GET["date"] != "")
		$default_date = $_GET["date"];

	if (isset($_GET["players"]) && intval($_GET["players"]) > 0)
		$default_players = intval($_GET["players"]);

	if (isset($_GET["holes"]) && intval($_GET["holes"]) > 0)
		$default_holes = intval($_GET["holes"]);

	ob_start();

	if ($platform_currentcourse && $platform_currentcourse != "") //If the golf course belongs to a reservation platform, loads its proper form
	{
		$valid_golfcourse = false;

		if ($platform_currentcourse == 'Zest Golf')
			$platformname = "zestgolf";

		if ($platform_currentcourse == 'Golf Manager')
			$platformname = "golfmanager";

		if (in_array($platformname, $reserveteetimefw_classes))
			$classname = "rtt" . $platformname;

		if ($classname) {
			$platform = new $classname();

			if ($platform->mode == $mode_currentcourse) {
				$valid_golfcourse = true;
				echo do_shortcode("[reserveteetimefw_teetimesearch id_facility='" . $id_currentcourse . "' date='" . $default_date . "' players='" . $default_players . "' holes='" . $default_holes . "']");
			}
		}

		if (!$valid_golfcourse)
			echo __("Invalid golf course - global", "reserveteetimefw");
	} else //If the golf course doesn't belongs to a reservation platform, loads a default Gravity Forms form
	{
	?>
		<h2 class="rttsearch-title"><?= __("Book your tee time", "reserveteetimefw") ?></h2>
		<div id="rttsearch-filters"><?= do_shortcode('[gravityform id="1" title="false" description="false" field_values="coursename=' . $name_currentcourse . '&date=' . $default_date . '&players=' . $default_players . '"]') ?></div>
	<?php
	}

	return ob_get_clean();
}

/*
* Shortcode that displays the message in the golf course page after adding the reservation to the cart
*/
add_shortcode('rtt-bookingmessage', 'reserveteetimefw_load_bookingmessage');
function reserveteetimefw_load_bookingmessage()
{
	global $reserveteetimefw_reserveresult;

	ob_start();

	if ($reserveteetimefw_reserveresult) {
	?>
		<div class="rtt_golfcoursemessagecontainer">
			<div id="<?= $reserveteetimefw_reserveresult["id_message"] ?>"><?= $reserveteetimefw_reserveresult["message"] ?></div>
		</div>
<?php
	}

	return ob_get_clean();
}

/*
**********************************************************************************************************
* ADD TO CART FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that returns the ID of the Woocommerce product for the reservations
* 
* @return int|bool 				Numerical ID of the product, or false if Woocommerce isn't active
*/
function reserveteetimefw_getreserveproduct()
{
	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	if ($active_woocommerce) {
		$must_create_product = false;
		$product_id = get_option("rtt_reserveproduct");
		if ($product_id && intval($product_id) > 0) {
			$product = wc_get_product($product_id);
			if (!$product || empty($product))
				$must_create_product = true;
		} else
			$must_create_product = true;

		if ($must_create_product) //If it doesn't exist, we create a new product
		{
			$product = new WC_Product_Simple();
			$product->set_name('Tee Time Reservation');
			$product->set_slug('tee-time-reservation');
			$product->set_regular_price(1.00);
			$product->save();

			$new_product_id = $product->get_id();

			update_option("rtt_reserveproduct", $new_product_id);

			return $new_product_id;
		} else
			return $product_id;
	} else
		return false;
}

/*
* Function that converts a price to EUR
* 
* @param float $price						Price amount
* @param string $currency					Identifier of the origin currency
*
* @return float 							Converted price to EUR
*/
function reserveteetimefw_convertprice($price, $currency)
{
	global $wpdb;

	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	if ($active_woocommerce) {
		$row_conversion = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "rttfw_curconv WHERE rttfwconv_id='" . esc_sql($currency) . "'");
		if ($row_conversion)
			return round($price / $row_conversion->rttfwconv_value, 2);
		else
			return false;
	} else
		return false;
}

/*
* Function that sets the proper price of the reservation when it's added to the cart
*/
add_action('woocommerce_before_calculate_totals', 'reserveteetimefw_setreserverealprice', 99);
function reserveteetimefw_setreserverealprice($cart_object)
{
	if (!WC()->session->__isset("reload_checkout")) {
		foreach ($cart_object->cart_contents as $key => $value) {
			if (isset($value["new_price"]))
				$value['data']->set_price($value["new_price"]);
		}
	}
}

/*
* Function that adds the reservation info to the cart item
*/
add_filter('woocommerce_get_item_data', 'reserveteetimefw_displaycartitemdata', 10, 2);
function reserveteetimefw_displaycartitemdata($item_data, $cart_item)
{
	if (isset($cart_item['facility']) && $cart_item['facility'] != "")
		$item_data[] = array('key' => __("Golf course", "reserveteetimefw"), 'value' => $cart_item['facility']); //Golf course name

	if (isset($cart_item['datetime']) && $cart_item['datetime'] != "")
		$item_data[] = array('key' => __("Date & Time", "reserveteetimefw"), 'value' => $cart_item['datetime']); //Reservation date/time

	if (isset($cart_item['players']) && $cart_item['players'] != "")
		$item_data[] = array('key' => __("Players", "reserveteetimefw"), 'value' => $cart_item['players']); //Number of players

	if (isset($cart_item['holes']) && $cart_item['holes'] != "")
		$item_data[] = array('key' => __("Holes", "reserveteetimefw"), 'value' => $cart_item['holes']); //Number of holes

	if (isset($cart_item['extraproductsdesc']) && $cart_item['extraproductsdesc'] != "")
		$item_data[] = array('key' => __("Extra products", "reserveteetimefw"), 'value' => $cart_item['extraproductsdesc']); //Extra products info

	if (isset($cart_item['cancel_date']) && $cart_item['cancel_date'] != "")
		$item_data[] = array('key' => __("Can cancel before", "reserveteetimefw"), 'value' => $cart_item['cancel_date']); //Max. cancel date/time

	/*if(isset($cart_item['pricebreakdown']) && $cart_item['pricebreakdown']!="")
        $item_data[] = array('key' => __("Price breakdown","reserveteetimefw"),'value' => $cart_item['pricebreakdown']);*/

	return $item_data;
}

/*
* Function that processes the ADD TO CART action for the reservations
*/
add_action('init', 'reserveteetimefw_processreserve');
function reserveteetimefw_processreserve()
{
	global $reserveteetimefw_classes, $reserveteetimefw_reserveresult, $woocommerce;

	$reserveteetimefw_reserveresult = false;

	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	if (isset($_POST["action"]) && $_POST["action"] == "rtt-reserveteetime" && $active_woocommerce) {
		$id_teetime = isset($_POST["id_teetime"]) ? sanitize_text_field($_POST["id_teetime"]) : "";
		$id_facility = intval(isset($_POST["facility_id"]) ? $_POST["facility_id"] : 0);
		$date = isset($_POST["date"]) ? sanitize_text_field($_POST["date"]) : "";
		$cancelperiod = intval(isset($_POST["cancelperiod"]) ? $_POST["cancelperiod"] : 0);
		$players = intval(isset($_POST["players"]) ? $_POST["players"] : 0);
		$productId = isset($_POST["productId"]) ? intval($_POST["productId"]) : 0;
		$course = isset($_POST["course"]) ? sanitize_text_field($_POST["course"]) : "";
		$holes = intval(isset($_POST["holes"]) ? $_POST["holes"] : 0);
		$has_extra_products = isset($_POST["has_extraproducts"]) && $_POST["has_extraproducts"] == 1 ? true : false;

		if (isset($_POST["extraproduct"]) && is_array($_POST["extraproduct"])) {
			$has_extra_products = true;
			$array_extraproductscheck = array();

			foreach ($_POST["extraproduct"] as $extraproductindex => $extraproductid) {
				if (isset($_POST["extraproductqty"][$extraproductindex]) && intval($_POST["extraproductqty"][$extraproductindex]) > 0)
					$array_extraproductscheck[$extraproductid] = intval($_POST["extraproductqty"][$extraproductindex]);
			}
		}

		$platformname = false;
		$classname = false;

		$post_type = get_post_type($id_facility);
		if ($post_type && $post_type == "courses") {
			$post_platform = get_post_meta($id_facility, "plataforma", true);
			if ($post_platform == 'Zest Golf')
				$platformname = "zestgolf";

			if ($post_platform == 'Golf Manager')
				$platformname = "golfmanager";

			if (in_array($platformname, $reserveteetimefw_classes))
				$classname = "rtt" . $platformname;

			if ($classname) {
				$platform = new $classname();
				if (array_key_exists($id_facility, $platform->golfcourses)) {
					$valid_teetime = $platform->checkteetime($id_teetime, $id_facility, $date, $players, $holes);
					if ($valid_teetime) {
						$valid_teetime["currency"] = rtt_normalize_currency($valid_teetime["currency"]);
						$new_price = reserveteetimefw_convertprice($valid_teetime["price"], $valid_teetime["currency"]);
						if ($new_price && floatval($new_price) > 0) {
							if ($has_extra_products && is_array($valid_teetime["extraproducts"])) {
								$extra_price = 0;

								$array_extraproducts = array();
								$array_extraproductsdesc = array();
								$reservation_breakdown = __("Reservation fee", "reserveteetimefw") . ": " . $new_price . "€";
								$array_breakdown = array($reservation_breakdown);

								foreach ($valid_teetime["extraproducts"] as $category_name => $category_data) {
									foreach ($category_data as $product_data) {
										if (array_key_exists($product_data["id"], $array_extraproductscheck)) {
											$valid_teetime["currency"] = rtt_normalize_currency($valid_teetime["currency"]);
											$product_price = reserveteetimefw_convertprice($product_data["price"], $valid_teetime["currency"]);
											$array_extraproducts[] = array("mid" => $product_data["id"], "quantity" => $array_extraproductscheck[$product_data["id"]]);
											$array_extraproductsdesc[] = $product_data["name"] . ": " . $array_extraproductscheck[$product_data["id"]];
											$extra_price += ($product_price * $array_extraproductscheck[$product_data["id"]]);
											$array_breakdown[] = $product_data["name"] . " x " . $array_extraproductscheck[$product_data["id"]] . ": " . ($product_price * $array_extraproductscheck[$product_data["id"]]) . "€";
										}
									}
								}

								$new_price += $extra_price;
							}

							$cart_item_data = array(
								"platform" => $platformname,
								"id_teetime" => $id_teetime,
								"id_facility" => $id_facility,
								"facility" => get_the_title($id_facility),
								"date" => $date,
								"datetime" => $valid_teetime["time"],
								"productId" => $productId,
								"players" => $players,
								"course" => $course,
								"holes" => $holes,
								"new_price" => $new_price
							);

							if ($cancelperiod > 0) {
								$cancel_date_unix = strtotime($valid_teetime["time"]) - $cancelperiod * 3600;
								$cart_item_data["cancel_date_unix"] = $cancel_date_unix;
								$cart_item_data["cancel_date"] = date("Y-m-d\TH:i:s", $cancel_date_unix);
							}

							if (isset($array_extraproducts) && !empty($array_extraproducts))
								$cart_item_data["extraproducts"] = json_encode($array_extraproducts);

							if (isset($array_extraproductsdesc) && !empty($array_extraproductsdesc))
								$cart_item_data["extraproductsdesc"] = implode(", ", $array_extraproductsdesc);

							if (isset($array_breakdown) && !empty($array_breakdown))
								$cart_item_data["pricebreakdown"] = implode("<br/>", $array_breakdown);

							$product_id = reserveteetimefw_getreserveproduct();

							foreach (WC()->cart->get_cart() as $key => $item) {
								if ($item["id_teetime"] == $id_teetime)
									WC()->cart->remove_cart_item($key);
							}

							$woocommerce->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
							$woocommerce->cart->calculate_totals();
							$woocommerce->cart->set_session();
							$woocommerce->cart->maybe_set_cart_cookies();

							//$reserveteetimefw_reserveresult = array("id_message" => "rttsearch-msgaddedtocart","message" => __("The reserve was added to the cart successfully. <a href='".wc_get_cart_url()."'>Go to the cart</a>.","reserveteetimefw"));
							wp_safe_redirect(esc_url(wc_get_cart_url())); //Redirection to the cart
							exit;
						} else
							$reserveteetimefw_reserveresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("There was an error with the price. Please contact an administrator.", "reserveteetimefw"));
					} else
						$reserveteetimefw_reserveresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("Apologies. That tee time is no longer available.", "reserveteetimefw"));
				}
			}
		}
	}
}


/*
* Function that processes the ADD TO CART action for the reservations
*/
// add_action('init', 'reserveteetimefw_processreserve');
// function reserveteetimefw_processreserve()
// {
// 	global $reserveteetimefw_classes, $reserveteetimefw_reserveresult, $woocommerce;

// 	$reserveteetimefw_reserveresult = false;

// 	if (class_exists('WooCommerce'))
// 		$active_woocommerce = true;
// 	else
// 		$active_woocommerce = false;

// 	if (isset($_POST["action"]) && $_POST["action"] == "rtt-reserveteetime" && $active_woocommerce) {
// 		$id_teetime = $_POST["teetime_id"];
// 		$id_facility = intval($_POST["id_facility"]);
// 		$date = $_POST["date"];
// 		$cancelperiod = intval($_POST["cancelperiod"]);
// 		$players = intval($_POST["players"]);
// 		$productId = $_POST["productId"];
// 		$course = $_POST["course"];
// 		$holes = intval($_POST["holes"]);
// 		$has_extra_products = false;

// 		if (isset($_POST["extraproduct"]) && is_array($_POST["extraproduct"])) {
// 			$has_extra_products = true;
// 			$array_extraproductscheck = array();

// 			foreach ($_POST["extraproduct"] as $extraproductindex => $extraproductid) {
// 				if (isset($_POST["extraproductqty"][$extraproductindex]) && intval($_POST["extraproductqty"][$extraproductindex]) > 0)
// 					$array_extraproductscheck[$extraproductid] = intval($_POST["extraproductqty"][$extraproductindex]);
// 			}
// 		}

// 		$platformname = false;
// 		$classname = false;

// 		$post_type = get_post_type($id_facility);
// 		if ($post_type && $post_type == "courses") {
// 			$post_platform = get_post_meta($id_facility, "plataforma", true);
// 			// =================== MODIFICATION START ===================
// 			if ($post_platform == 'Zest Golf')
// 				$platformname = "zestgolf";
// 			if ($post_platform == 'Golf Manager') // Add Golf Manager support
// 				$platformname = "golfmanager";
// 			// =================== MODIFICATION END =====================

// 			if (in_array($platformname, $reserveteetimefw_classes))
// 				$classname = "rtt" . $platformname;

// 			if ($classname) {
// 				$platform = new $classname();

// 				// =================== MODIFICATION START ===================
// 				// Use the more robust validation that works for both classes
// 				$is_course_valid = false;
// 				if (method_exists($platform, 'is_valid_course') && $platform->is_valid_course($id_facility)) {
// 					$is_course_valid = true;
// 				} else if (isset($platform->golfcourses) && array_key_exists($id_facility, $platform->golfcourses)) {
// 					$is_course_valid = true;
// 				}
// 				// =================== MODIFICATION END =====================

// 				if ($is_course_valid) {
// 					$valid_teetime = $platform->checkteetime($id_teetime, $id_facility, $date, $players, $holes);
// 					if ($valid_teetime) {
// 						// =================== MODIFICATION START ===================
// 						// For Golf Manager, the price comes from the form. For Zest, it's in the checked teetime.
// 						$base_price = isset($_POST['price']) ? floatval($_POST['price']) : $valid_teetime["price"];
// 						$new_price = reserveteetimefw_convertprice($base_price, $valid_teetime["currency"]);
// 						// =================== MODIFICATION END =====================

// 						if ($new_price && floatval($new_price) > 0) {
// 							if ($has_extra_products && is_array($valid_teetime["extraproducts"])) {
// 								$extra_price = 0;
// 								$array_extraproducts = array();
// 								$array_extraproductsdesc = array();
// 								$reservation_breakdown = __("Reservation fee", "reserveteetimefw") . ": " . $new_price . "€";
// 								$array_breakdown = array($reservation_breakdown);

// 								foreach ($valid_teetime["extraproducts"] as $category_name => $category_data) {
// 									foreach ($category_data as $product_data) {
// 										if (array_key_exists($product_data["id"], $array_extraproductscheck)) {
// 											$product_price = reserveteetimefw_convertprice($product_data["price"], $valid_teetime["currency"]);
// 											$array_extraproducts[] = array("mid" => $product_data["id"], "quantity" => $array_extraproductscheck[$product_data["id"]]);
// 											$array_extraproductsdesc[] = $product_data["name"] . ": " . $array_extraproductscheck[$product_data["id"]];
// 											$extra_price += ($product_price * $array_extraproductscheck[$product_data["id"]]);
// 											$array_breakdown[] = $product_data["name"] . " x " . $array_extraproductscheck[$product_data["id"]] . ": " . ($product_price * $array_extraproductscheck[$product_data["id"]]) . "€";
// 										}
// 									}
// 								}
// 								$new_price += $extra_price;
// 							}

// 							$cart_item_data = array(
// 								"platform" => $platformname,
// 								"id_teetime" => $id_teetime,
// 								"id_facility" => $id_facility,
// 								"facility" => get_the_title($id_facility),
// 								"date" => $date,
// 								"datetime" => $valid_teetime["time"],
// 								"productId" => $productId,
// 								"players" => $players,
// 								"course" => $course,
// 								"holes" => $holes,
// 								"new_price" => $new_price
// 							);

// 							if ($cancelperiod > 0) {
// 								$cancel_date_unix = strtotime($valid_teetime["time"]) - $cancelperiod * 3600;
// 								$cart_item_data["cancel_date_unix"] = $cancel_date_unix;
// 								$cart_item_data["cancel_date"] = date("Y-m-d\TH:i:s", $cancel_date_unix);
// 							}
// 							if (isset($array_extraproducts)) $cart_item_data["extraproducts"] = json_encode($array_extraproducts);
// 							if (isset($array_extraproductsdesc)) $cart_item_data["extraproductsdesc"] = implode(", ", $array_extraproductsdesc);
// 							if (isset($array_breakdown)) $cart_item_data["pricebreakdown"] = implode("<br/>", $array_breakdown);

// 							$product_id = reserveteetimefw_getreserveproduct();
// 							WC()->cart->empty_cart(); // Clear cart before adding new item
// 							WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

// 							wp_safe_redirect(esc_url(wc_get_checkout_url()));
// 							exit;
// 						} else {
// 							$reserveteetimefw_reserveresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("There was an error with the price. Please contact an administrator.", "reserveteetimefw"));
// 						}
// 					} else {
// 						$reserveteetimefw_reserveresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("Apologies. That tee time is no longer available.", "reserveteetimefw"));
// 					}
// 				}
// 			}
// 		}
// 	}
// }




/*
* Function to show the golf course image next to the reservation in the cart
*/
add_filter('woocommerce_cart_item_thumbnail', 'reserveteetimefw_customcartimage', 10, 3);
function reserveteetimefw_customcartimage($product_image, $cart_item, $cart_item_key)
{
	if (isset($cart_item["id_facility"]) && intval($cart_item["id_facility"]) > 0) {
		$image_logo = get_post_meta($cart_item["id_facility"], "logo", true);
		if ($image_logo && intval($image_logo) > 0)
			$product_image = "<img src='" . wp_get_attachment_url($image_logo) . "' class='wp-post-image' />";
		else {
			$image_featured = get_the_post_thumbnail_url($cart_item["id_facility"]);
			if ($image_featured)
				$product_image = "<img src='" . $image_featured . "' class='wp-post-image' />";
		}
	}

	return $product_image;
}

/*
**********************************************************************************************************
* CHECKOUT FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that adds the reservation info to the order once it's created
*/
add_action('woocommerce_checkout_create_order_line_item', 'reserveteetimefw_savecartitemdata', 10, 4);
function reserveteetimefw_savecartitemdata($item, $cart_item_key, $values, $order)
{

	if (isset($values['platform']) && $values['platform'] != "")
		$item->update_meta_data('rtt_platform', $values['platform']); //Reservation platform

	if (isset($values['id_teetime']) && $values['id_teetime'] != "")
		$item->update_meta_data('rtt_idteetime', $values['id_teetime']); //Identifier of the tee time in its platform

	if (isset($values['id_facility']) && $values['id_facility'] != "")
		$item->update_meta_data('rtt_idfacility', $values['id_facility']); //Identifier of the golf course in its platform

	if (isset($values['facility']) && $values['facility'] != "")
		$item->update_meta_data('rtt_facility', $values['facility']); //Golf course name

	if (isset($values['productId']) && $values['productId'] != "")
		$item->update_meta_data('rtt_productid', $values['productId']); //Identifier of the reservation item in its platform

	if (isset($values['date']) && $values['date'] != "")
		$item->update_meta_data('rtt_date', $values['date']); //Reservation date

	if (isset($values['datetime']) && $values['datetime'] != "")
		$item->update_meta_data('rtt_datetime', $values['datetime']); //Reservation date/time

	if (isset($values['players']) && $values['players'] != "")
		$item->update_meta_data('rtt_players', $values['players']); //Number of players

	if (isset($values['course']) && $values['course'] != "")
		$item->update_meta_data('rtt_course', $values['course']); //Name of the reservation course

	if (isset($values['holes']) && $values['holes'] != "")
		$item->update_meta_data('rtt_holes', $values['holes']); //Number of holes

	if (isset($values['extraproducts']) && $values['extraproducts'] != "")
		$item->update_meta_data('rtt_extraproducts', $values['extraproducts']); //Extra products

	if (isset($values['extraproductsdesc']) && $values['extraproductsdesc'] != "")
		$item->update_meta_data('rtt_extraproductsdesc', $values['extraproductsdesc']); //Extra products description

	if (isset($values['pricebreakdown']) && $values['pricebreakdown'] != "")
		$item->update_meta_data('rtt_pricebreakdown', $values['pricebreakdown']); //Price breakdown

	if (isset($values['cancel_date_unix']) && $values['cancel_date_unix'] != "")
		$item->update_meta_data('rtt_canceldateunix', $values['cancel_date_unix']); //Max. cancel date/time in UNIX format

	if (isset($values['cancel_date']) && $values['cancel_date'] != "")
		$item->update_meta_data('rtt_canceldate', $values['cancel_date']); //Max. cancel date/time in YYYY-MM-DD HH:MM:SS format
}

/*
* Function that checks that every tee time is still available when checking out
*/
add_action('woocommerce_after_checkout_validation', 'reserveteetimefw_recheckteetimes');
function reserveteetimefw_recheckteetimes($data)
{

	global $reserveteetimefw_classes;

	$invalid_teetimes = array();
	$checked_one = false;
	foreach (WC()->cart->get_cart() as $cart_item) {
		if (isset($cart_item['id_teetime']) && $cart_item['id_teetime'] != "") {
			$platform = $cart_item["platform"];
			$id_teetime = $cart_item["id_teetime"];
			$id_facility = $cart_item["id_facility"];
			$date = $cart_item["date"];
			$players = $cart_item["players"];
			$holes = $cart_item["holes"];
			$check_extraproducts = false;

			if (isset($cart_item["extraproducts"])) {
				$check_extraproducts = true;
				$extraproducts = json_decode($cart_item["extraproducts"], true);
				$array_extraproducts_ids = array();
				foreach ($extraproducts as $extraproductdata)
					$array_extraproducts_ids[] = $extraproductdata["mid"];
			}

			if (in_array($platform, $reserveteetimefw_classes)) {
				$classname = "rtt" . $platform;
				$platform = new $classname();
				if (array_key_exists($id_facility, $platform->golfcourses)) {
					if ($checked_one)
						sleep(2);

					$checked_teetime = $platform->checkteetime($id_teetime, $id_facility, $date, $players, $holes);
					$valid_teetime = $checked_teetime;
					if ($valid_teetime && $check_extraproducts) {
						$array_all_extraproducts_ids = array();
						foreach ($checked_teetime["extraproducts"] as $category_name => $category_data) {
							foreach ($category_data as $product_data)
								$array_all_extraproducts_ids[] = $product_data["id"];
						}
						$valid_teetime = !array_diff($array_extraproducts_ids, $array_all_extraproducts_ids);
					}
					$checked_one = true;
					if (!$valid_teetime)
						$invalid_teetimes[] = $cart_item["facility"] . ", " . $cart_item["datetime"] . ", " . $cart_item["players"] . " " . __("players", "reserveteetimefw") . ", " . $cart_item["holes"] . " " . __("holes", "reserveteetimefw");
				}
			} else
				$invalid_teetimes[] = $cart_item["facility"] . ", " . $cart_item["datetime"] . ", " . $cart_item["players"] . " " . __("players", "reserveteetimefw") . ", " . $cart_item["holes"] . " " . __("holes", "reserveteetimefw");
		}
	}

	if (!empty($invalid_teetimes)) //If unavailable tee times are present, don't proceed with the checkout
		wc_add_notice(__("The following tee times or their extra products are no longer available", "reserveteetimefw") . ": <ul><li>" . implode("</li><li>", $invalid_teetimes) . "</li></ul>", "error");
}

/*
* Function that submits the reservation to the reservation platform once the order is paid
*/
add_action('woocommerce_order_status_processing', 'reserveteetimefw_sendreservation');
function reserveteetimefw_sendreservation($order_id)
{
	global $reserveteetimefw_classes;

	$order = wc_get_order($order_id);

	$order_items = $order->get_items();
	foreach ($order_items as $item) {
		$array_meta_data = $item->get_meta_data();
		$array_meta = array();
		foreach ($array_meta_data as $item_data) {
			$meta_data = $item_data->get_data();
			$array_meta[$meta_data["key"]] = $meta_data["value"];
		}

		if (isset($array_meta["rtt_platform"]) && !isset($array_meta["rtt_result"])) {
			if (in_array($array_meta["rtt_platform"], $reserveteetimefw_classes)) {
				$array_reservation_data = array(
					"id_facility" => $array_meta["rtt_idfacility"],
					"date" => $array_meta["rtt_datetime"],
					"course" => $array_meta["rtt_course"],
					"players" => $array_meta["rtt_players"],
					"id_teetime" => $array_meta["rtt_idteetime"],
					"holes" => $array_meta["rtt_holes"],
					"product_id" => $array_meta["rtt_productid"],
					"name" => $order->get_billing_first_name(),
					"lastname" => $order->get_billing_last_name(),
					"phone" => $order->get_billing_phone(),
					"email" => $order->get_billing_email()
				);

				if (isset($array_meta["rtt_extraproducts"]) && $array_meta["rtt_extraproducts"] != "")
					$array_reservation_data["extraproducts"] = $array_meta["rtt_extraproducts"];

				$mailer = WC()->mailer();

				$emails_array = $mailer->get_emails();
				$new_order_email = $emails_array['WC_Email_New_Order'];
				$new_order_recipient = $new_order_email->recipient;

				$classname = "rtt" . $array_meta["rtt_platform"];
				$platform = new $classname();
				$result_reservation = $platform->reserveteetime($array_reservation_data);

				if ($result_reservation->success) {
					ob_start();
					wc_get_template('emails/email-header.php', array('email_heading' => 'Reservation successful'));
					echo "<p>A reservation made in order #" . $order->get_order_number() . " was registered successfully</p>";
					wc_get_template('emails/email-footer.php');
					$message = ob_get_clean();
					$subject = "Successful reservation";

					$item->update_meta_data("rtt_result", $result_reservation->data);
					reserveteetimefw_savelog(1, "Reservation confirmed for order #" . $order_id . ", ID Reservation: " . $result_reservation->data, $platform->name);
				} else {
					ob_start();
					wc_get_template('emails/email-header.php', array('email_heading' => 'Reservation failed'));
					echo "<p>A reservation made in order #" . $order->get_order_number() . " has failed to register</p>";
					echo "<p>The response was: " . print_r($result_reservation, true) . "</p>";
					wc_get_template('emails/email-footer.php');
					$message = ob_get_clean();
					$subject = "Failed reservation";

					reserveteetimefw_savelog(2, "ERROR making reservation for order #" . $order_id . ", Errors: " . print_r($result_reservation, true), $platform->name);

					/*$array_errors = array();
					foreach($result_reservation->errors as $error_element)
					{
						$array_errors = $error_element->message;
					}
					
					reserveteetimefw_savelog(2,"ERROR making reservation for order #".$order_id.", Errors: ".implode(", ",$array_errors),$platform->name);*/

					//$item->update_meta_data("rtt_result","ERROR: ".implode(", ",$array_errors));
					$item->update_meta_data("rtt_result", "ERROR");
				}

				$item->save();

				$mailer->send($new_order_recipient, $subject, $message);
			}
		}
	}
}

/*
**********************************************************************************************************
* CANCELLATION FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that processes the cancellation of the reservation from the customer's MY ACCOUNT section
*/
add_action('init', 'reserveteetimefw_processcancel');
function reserveteetimefw_processcancel()
{
	global $reserveteetimefw_classes, $reserveteetimefw_cancelresult, $woocommerce;

	$reserveteetimefw_cancelresult = false;

	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	if (isset($_POST["action"]) && $_POST["action"] == "rtt-cancelteetime" && $active_woocommerce) {
		$user_id = get_current_user_id();

		$order_id = intval($_POST["reservationorder"]);
		$order = new WC_Order($order_id);

		if ($order && $order->get_user_id() == $user_id) {
			$found_reserve = false;

			$order_items = $order->get_items();
			foreach ($order_items as $item) {
				$array_meta_data = $item->get_meta_data();
				$array_meta = array();
				foreach ($array_meta_data as $item_data) {
					$meta_data = $item_data->get_data();
					$array_meta[$meta_data["key"]] = $meta_data["value"];
				}

				if (isset($array_meta["rtt_result"]) && $array_meta["rtt_result"] == $_POST["reservationid"]) {
					$found_reserve = true;

					$mailer = WC()->mailer();

					$emails_array = $mailer->get_emails();
					$new_order_email = $emails_array['WC_Email_New_Order'];
					$new_order_recipient = $new_order_email->recipient;

					$classname = "rtt" . $array_meta["rtt_platform"];
					$platform = new $classname();

					$result_cancellation = $platform->cancelteetime($array_meta["rtt_result"]);

					if ($result_cancellation->success) {
						$item->update_meta_data("rtt_cancelled", date("r", time()));
						$item->save();
						reserveteetimefw_savelog(1, "Reservation cancelled for order #" . $order_id . ", ID Reservation: " . $array_meta["rtt_result"], $platform->name);

						ob_start();
						wc_get_template('emails/email-header.php', array('email_heading' => 'Reservation cancelled'));
						echo "<p>User " . $order->get_billing_first_name() . " " . $order->get_billing_last_name() . " has cancelled a reservation made in order #" . $order->get_order_number() . "</p>";
						wc_get_template('emails/email-footer.php');
						$message = ob_get_clean();
						$subject = "A user has cancelled their reservation";

						$reserveteetimefw_cancelresult = array("id_message" => "rttsearch-msgaddedtocart", "message" => __("The reservation was cancelled successfully.", "reserveteetimefw"));
					} else {
						ob_start();
						wc_get_template('emails/email-header.php', array('email_heading' => 'Error cancelling reservation'));
						echo "<p>User " . $order->get_billing_first_name() . " " . $order->get_billing_last_name() . " has attempted to cancel a reservation made in order #" . $order->get_order_number() . " and has failed.</p>";
						echo "<p>The response was the following: " . print_r($result_cancellation, true) . "</p>";
						wc_get_template('emails/email-footer.php');
						$message = ob_get_clean();
						$subject = "A reservation cancellation has failed";

						reserveteetimefw_savelog(2, "ERROR cancelling reservation for order #" . $order_id . ", Errors: " . print_r($result_cancellation, true), $platform->name);
						$reserveteetimefw_cancelresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("There was a problem cancelling the reservation. Please contact us directly.", "reserveteetimefw"));
					}

					$mailer->send($new_order_recipient, $subject, $message);
				}
			}

			if (!$found_reserve)
				$reserveteetimefw_cancelresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("Invalid reservation.", "reserveteetimefw"));
		} else
			$reserveteetimefw_cancelresult = array("id_message" => "rttsearch-msginvalidteetime", "message" => __("Invalid reservation.", "reserveteetimefw"));
	}
}

/*
**********************************************************************************************************
* WOOCOMMERCE OVERRIDE FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that redirects users to the homepage if they try to access the reservation product page
*/
add_action('template_redirect', 'reserveteetimefw_redirectproductpage', 100);
function reserveteetimefw_redirectproductpage()
{
	if (class_exists('WooCommerce')) {
		$product_id = get_option("rtt_reserveproduct");
		if ($product_id && is_product() && get_the_id() == intval($product_id)) {
			wp_redirect(home_url());
			exit();
		}
	}
}

/*
* Function that disables the quantity field in the cart
*/
add_filter('woocommerce_cart_item_quantity', 'reserveteetimefw_disablecartqty', 10, 3);
function reserveteetimefw_disablecartqty($product_quantity, $cart_item_key, $cart_item)
{
	if (is_cart())
		$product_quantity = sprintf('<input type="hidden" name="cart[%s][qty]" value="%s" />', $cart_item_key, $cart_item['quantity']);

	return $product_quantity;
}

/*
**********************************************************************************************************
* WOOCOMMERCE BACKEND FUNCTIONS
**********************************************************************************************************
*/

/*
* Function that adds the reservation result(s) column to the list of orders in the backend
*/
add_filter('manage_woocommerce_page_wc-orders_columns', 'reserveteetimefw_addresultreservationcolumn');
function reserveteetimefw_addresultreservationcolumn($columns)
{
	$reordered_columns = array();

	foreach ($columns as $key => $column) {
		$reordered_columns[$key] = $column;

		if ($key ===  'order_status') {
			$reordered_columns['reservation_result'] = __('Reservation result', 'reserveteetimefw');
		}
	}
	return $reordered_columns;
}

/*
* Function that retrieves the reservation result(s) for the column of the list of orders in the backend
*/
add_action('manage_woocommerce_page_wc-orders_custom_column', 'reserveteetimefw_addresultreservationvalue', 10, 2);
function reserveteetimefw_addresultreservationvalue($column, $order)
{

	global $reserveteetimefw_classes;

	switch ($column) {
		case 'reservation_result':

			$valid_status = array("processing", "completed");
			$order_status = $order->get_status();

			if (in_array($order_status, $valid_status)) {
				$reservations_present = false;
				$all_reservations_success = true;

				$order_items = $order->get_items();
				foreach ($order_items as $item) {
					$array_meta_data = $item->get_meta_data();
					$array_meta = array();
					foreach ($array_meta_data as $item_data) {
						$meta_data = $item_data->get_data();
						$array_meta[$meta_data["key"]] = $meta_data["value"];
					}

					if (in_array($array_meta["rtt_platform"], $reserveteetimefw_classes)) {
						$reservations_present = true;
						$classname = "rtt" . $array_meta["rtt_platform"];
						$platform = new $classname();

						if (!isset($array_meta["rtt_result"]) || !$platform->is_valid_result($array_meta["rtt_result"]))
							$all_reservations_success = false;
					}
				}

				if ($reservations_present) {
					if ($all_reservations_success)
						echo __('Success', 'reserveteetimefw');
					else
						echo __('Error submitting reservation', 'reserveteetimefw');
				} else
					echo __('No reservations', 'reserveteetimefw');
			}

			break;
	}
}

/*
* Function that adds a custom CSS class to rows in the order list with failed reservation in order to highlight them
*/
add_filter('woocommerce_shop_order_list_table_order_css_classes', 'reserveteetimefw_addresultreservationrowclass', 10, 2);
function reserveteetimefw_addresultreservationrowclass($classes, $order)
{

	global $reserveteetimefw_classes;

	$valid_status = array("processing", "completed");
	$order_status = $order->get_status();

	if (in_array($order_status, $valid_status)) {
		$reservations_present = false;
		$all_reservations_success = true;

		$order_items = $order->get_items();
		foreach ($order_items as $item) {
			$array_meta_data = $item->get_meta_data();
			$array_meta = array();
			foreach ($array_meta_data as $item_data) {
				$meta_data = $item_data->get_data();
				$array_meta[$meta_data["key"]] = $meta_data["value"];
			}

			if (isset($array_meta["rtt_platform"]) && in_array($array_meta["rtt_platform"], $reserveteetimefw_classes)) {
				$reservations_present = true;
				$classname = "rtt" . $array_meta["rtt_platform"];
				$platform = new $classname();

				if (!isset($array_meta["rtt_result"]) || !$platform->is_valid_result($array_meta["rtt_result"]))
					$all_reservations_success = false;
			}
		}

		if ($reservations_present && !$all_reservations_success)
			$classes[] = 'reserveteetimefw-ordererror';
	}

	return $classes;
}

/*
**********************************************************************************************************
* WOOCOMMERCE FRONTEND FUNCTIONS
**********************************************************************************************************
*/

/*
* Redirects users to checkout after using Social Login Pro
*/
add_filter('woo_slp_login_redirection_url', 'reserveteetime_custom_login_url', 10, 1);
function reserveteetime_custom_login_url($redirect_url)
{
	global $woo_slp_options;
	if (! empty($woo_slp_options['woo_slp_redirect_url'])) {
		$redirect_url = $woo_slp_options['woo_slp_redirect_url'];
	}

	return $redirect_url;
}

/* 
* Displays price breakdown when reservation has extra products 
*/

function reserveteetimefw_displaypricebreakdowncart($subtotal, $cart_item, $cart_item_key)
{
	$new_subtotal = "<b>" . $subtotal . "</b>";
	if (isset($cart_item['pricebreakdown']) && $cart_item['pricebreakdown'] != "")
		$new_subtotal .= "<br/><span class=\"rtt_pricebreakdown\">" . $cart_item['pricebreakdown'] . "</span>";

	return $new_subtotal;
}
add_filter('woocommerce_cart_item_subtotal', 'reserveteetimefw_displaypricebreakdowncart', 10, 3);

function reserveteetimefw_displaypricebreakdownorder($subtotal, $item, $that)
{
	$new_subtotal = "<b>" . $subtotal . "</b>";
	$price_breakdown = $item->get_meta('rtt_pricebreakdown');
	if ($price_breakdown && $price_breakdown != "")
		$new_subtotal .= "<br/><span class=\"rtt_pricebreakdown\">" . $price_breakdown . "</span>";

	return $new_subtotal;
}
add_filter('woocommerce_order_formatted_line_subtotal', 'reserveteetimefw_displaypricebreakdownorder', 10, 3);

/*
* Function that filters which order item metadata is visible to the customer (order review, email, etc...)
*/
function reserveteetimefw_filteritemmeta($html, $item, $args)
{

	$array_meta_data = $item->get_meta_data();

	$html = '';

	//Only fields with an assigned name will be visible to the customer
	$array_rtt_fields = array(
		"rtt_platform" => "",
		"rtt_idteetime" => "",
		"rtt_idfacility" => "",
		"rtt_facility" => "Golf course",
		"rtt_productid" => "",
		"rtt_date" => "",
		"rtt_datetime" => "Date & Time",
		"rtt_players" => "Players",
		"rtt_course" => "Course",
		"rtt_holes" => "Holes",
		"rtt_extraproducts" => "",
		"rtt_extraproductsdesc" => "Extra products",
		"rtt_pricebreakdown" => "",
		"rtt_canceldateunix" => "",
		"rtt_canceldate" => "Can cancel before",
		"rtt_result" => ""
	);

	if (!empty($array_meta_data)) {
		$array_final_meta = array();

		foreach ($array_meta_data as $item_data) {
			$meta_data = $item_data->get_data();
			if (!array_key_exists($meta_data["key"], $array_rtt_fields))
				$array_final_meta[$meta_data["key"]] = $meta_data["value"];
			else if ($array_rtt_fields[$meta_data["key"]] != "")
				$array_final_meta[$array_rtt_fields[$meta_data["key"]]] = $meta_data["value"];
		}

		if (!empty($array_final_meta)) {
			$html .= '<ul class="wc-item-meta">';

			foreach ($array_final_meta as $field_name => $field_value)
				$html .= '<li><strong class="wc-item-meta-label">' . $field_name . ':</strong> <p>' . $field_value . '</p></li>';

			$html .= '</ul>';
		}
	}

	return $html;
}
add_filter('woocommerce_display_item_meta', 'reserveteetimefw_filteritemmeta', 10, 3);

/*
* Functions that adds the RESERVATIONS section to the customer's MY ACCOUNT section
*/
add_filter('woocommerce_account_menu_items', 'reserveteetimefw_addreservationsmyaccountmenu', 40);
function reserveteetimefw_addreservationsmyaccountmenu($menu_items)
{
	$new_item = array("rttreservations" => __("Reservations", "reserveteetimefw"));

	$menu_items = array_slice($menu_items, 0, 2, true) + $new_item + array_slice($menu_items, 2, null, true);

	unset($menu_items['downloads']);
	unset($menu_items['edit-address']);
	unset($menu_items['payment-methods']);

	return $menu_items;
}

add_filter('woocommerce_get_query_vars', 'reserveteetimefw_addreservationsmyaccountendpoint');
function reserveteetimefw_addreservationsmyaccountendpoint($query_vars)
{
	$query_vars['rttreservations']  = 'rttreservations';

	return $query_vars;
}

add_action('woocommerce_account_rttreservations_endpoint', 'reserveteetimefw_addreservationsmyaccountendpointcontent');
function reserveteetimefw_addreservationsmyaccountendpointcontent()
{
	include(plugin_dir_path(__FILE__) . 'templates/myaccountreservations.php');
}

add_filter('woocommerce_endpoint_rttreservations_title', 'reserveteetimefw_addreservationsmyaccountendpointtitle', 10, 2);
function reserveteetimefw_addreservationsmyaccountendpointtitle($title, $endpoint)
{
	$title = __("Reservations", "reserveteetimefw");

	return $title;
}

add_action('pre_get_posts', 'reserveteetimefw_autofiltercourses', 1);
function reserveteetimefw_autofiltercourses($query)
{

	global $reserveteetimefw_classes;

	if (!is_admin() && $query->is_main_query() && ($query->is_tax('course-city') || $query->is_tax('course-region') || $query->is_tax('course-state') || $query->is_tax('course-destination'))) {
		//Manual courses are always allowed
		$array_meta_query = array("relation" => "OR", array('relation' => 'AND', array('key' => 'plataforma', 'compare' => 'NOT EXISTS', 'value' => '')), array('relation' => 'AND', array('key' => 'plataforma', 'value' => '')));
		foreach ($reserveteetimefw_classes as $platform_name) {
			//Only retrieve golf courses from platforms that are active and whose courses are the same mode as the current mode of the platform (sandbox/real)
			$classname = "rtt" . $platform_name;
			$platform = new $classname();
			if ($platform->active)
				$array_meta_query[] = array('relation' => 'AND', array('key' => 'plataforma', 'value' => $platform->name), array('key' => 'modo', 'value' => $platform->mode));
		}

		$query->set('meta_query', $array_meta_query);
	}
}

// helper
function rtt_normalize_currency($c)
{
	$c = trim((string)$c);
	$map = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP'];
	return $map[$c] ?? strtoupper($c); // fallback to uppercased code
}
