<?php
/*
**********************************************************************************************************
* SHORTCODE FOR TEE TIME SEACH FORM
**********************************************************************************************************
*/

add_shortcode('reserveteetimefw_teetimesearch', 'reserveteetimefw_load_teetimesearch');
function reserveteetimefw_load_teetimesearch($atts)
{

	global $reserveteetimefw_classes;

	ob_start();

	$id_facility = intval($atts["id_facility"]);
	$include_resultsblock = intval(isset($atts["include_resultsblock"]) ? $atts["include_resultsblock"] : 0);
	$platformname = false;
	$classname = false;
	$valid_golfcourse = false;

	$post_type = get_post_type($id_facility);
	if ($post_type && $post_type == "courses") {
		$post_platform = get_post_meta($id_facility, "plataforma", true);
		if ($post_platform == 'Zest Golf')
			$platformname = "zestgolf";

		if ($post_platform == 'Golf Manager')
			$platformname = "golfmanager";

		if ($platformname && in_array($platformname, $reserveteetimefw_classes)) {
			$classname = "rtt" . $platformname;
		}

		if ($classname) {
			$platform = new $classname();

			$is_course_valid = false;
			if (method_exists($platform, 'is_valid_course') && $platform->is_valid_course($id_facility)) {
				$is_course_valid = true;
			} else if (isset($platform->golfcourses) && array_key_exists($id_facility, $platform->golfcourses)) {
				$is_course_valid = true;
			}

			if ($is_course_valid) {
				$default_date = "";
				$default_players = "";
				$default_holes = "";

				if (isset($atts["date"]) && $atts["date"] != "")
					$default_date = $atts["date"];

				if (isset($atts["players"]) && intval($atts["players"]) > 0)
					$default_players = intval($atts["players"]);

				if (isset($atts["holes"]) && intval($atts["holes"]) > 0)
					$default_holes = intval($atts["holes"]);

				$valid_golfcourse = true;
?>
				<div class="rttsearch-block">
					<h2 class="rttsearch-title"><?= __("Book your tee time", "reserveteetimefw") ?></h2>
					<div id="rttsearch-filters">
						<div class="rttsearch-filter">
							<div class="rttsearch-filterfield"><input type="text" class="rttsearch-datefield" name="date" value="<?= $default_date ?>" autocomplete="off" placeholder="<?= __("Date", "reserveteetimefw") ?>" /></div>
						</div>
						<div class="rttsearch-filter">
							<div class="rttsearch-filterfield">
								<select name="players">
									<option value=""><?= __("Number of players", "reserveteetimefw") ?></option>
									<option value="1" <?= (($default_players == 1) ? ("selected=\"selected\"") : ("")) ?>>1 <?= __("players", "reserveteetimefw") ?></option>
									<option value="2" <?= (($default_players == 2) ? ("selected=\"selected\"") : ("")) ?>>2 <?= __("players", "reserveteetimefw") ?></option>
									<option value="3" <?= (($default_players == 3) ? ("selected=\"selected\"") : ("")) ?>>3 <?= __("players", "reserveteetimefw") ?></option>
									<option value="4" <?= (($default_players == 4) ? ("selected=\"selected\"") : ("")) ?>>4 <?= __("players", "reserveteetimefw") ?></option>
								</select>
							</div>
						</div>
						<div class="rttsearch-filter">
							<div class="rttsearch-filterfield">
								<select name="holes">
									<option value=""><?= __("Number of holes", "reserveteetimefw") ?></option>
									<option value="9" <?= (($default_holes == 9) ? ("selected=\"selected\"") : ("")) ?>>9 <?= __("holes", "reserveteetimefw") ?></option>
									<option value="18" <?= (($default_holes == 18) ? ("selected=\"selected\"") : ("")) ?>>18 <?= __("holes", "reserveteetimefw") ?></option>
								</select>
							</div>
						</div>
						<div class="rttsearch-button">
							<input type="hidden" name="id_facility" value="<?= $id_facility ?>" />
							<button id="rttsearch-searchbutton" type="button"><?= __("Search", "reserveteetimefw") ?></button>
						</div>
					</div>
					<?php if ($include_resultsblock) { ?>
						<div id="rttsearch-results"></div>
					<?php } ?>
					<div class="rttsearch-overlay" style="display:none;">
						<div class="rttsearch-popup">
							<div class="rttsearch-popupcontent">
								<div class="rttsearch-popupclose"></div>
								<div id="rttsearch-popup-form"></div>
							</div>
						</div>
					</div>
				</div>
		<?php
			}
		}
	}

	if (!$valid_golfcourse) {
		?>
		<p><?= __("Invalid golf course", "reserveteetimefw") ?></p>
<?php
	}

	return ob_get_clean();
}

/*
**********************************************************************************************************
* AJAX FUNCTION FOR SEARCHING TEE TIMES
**********************************************************************************************************
*/
add_action('wp_ajax_teetimesearch', 'ajax_teetimesearch');
add_action('wp_ajax_nopriv_teetimesearch', 'ajax_teetimesearch');
function ajax_teetimesearch()
{
	global $reserveteetimefw_classes;

	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	$id_facility = isset($_POST["id_facility"]) ? intval($_POST["id_facility"]) : 0;
	$date = isset($_POST["date"]) ? $_POST["date"] : '';
	$players = isset($_POST["players"]) ? intval($_POST["players"]) : 0;
	$holes = isset($_POST["holes"]) ? intval($_POST["holes"]) : 0;

	$platformname = false;
	$classname = false;
	$valid_golfcourse = false;
	$array_results = [];

	$post_type = get_post_type($id_facility);
	if ($post_type && $post_type == "courses") {
		$post_platform = get_post_meta($id_facility, "plataforma", true);

		if ($post_platform == 'Zest Golf') $platformname = "zestgolf";
		if ($post_platform == 'Golf Manager') $platformname = "golfmanager";

		if ($platformname && in_array($platformname, $reserveteetimefw_classes)) {
			$classname = "rtt" . $platformname;
		}

		if ($classname) {
			$platform = new $classname();

			if (method_exists($platform, 'is_valid_course') && $platform->is_valid_course($id_facility)) {
				$valid_golfcourse = true;
			} else if (isset($platform->golfcourses) && array_key_exists($id_facility, $platform->golfcourses)) {
				$valid_golfcourse = true;
			}

			if ($valid_golfcourse) {
				$array_results = $platform->getteetimes($id_facility, $date, $players, $holes);
				// echo json_encode($array_results);
				// wp_die();
			}
		}
	}

	if (!$valid_golfcourse) {
		$array_results = array("code" => -2, "results" => __("Invalid golf course", "reserveteetimefw"));
	} else if (isset($array_results["code"]) && $array_results["code"] == 200 && !empty($array_results["results"])) {
		$html_results = "<div class='rtt-teetimesgrid'>";

		foreach ($array_results["results"] as $row_result) {
			$price = $row_result["price"];

			$extra_price = "";
			if ($players > 1 && $price > 0) {
				$individual_price = round($price / $players, 2);
				$extra_price = " (" . $individual_price . $row_result["currency"] . "/player)";
			}

			$html_results .= "<div class='rtt-teetimesblock'>
				<div class='rtt-teetimesblockhead'>
					<div class='rtt-teetimesblockdatetime'>" . (new DateTime($row_result["time"]))->format('H:i') . "</div>
					<div class='rtt-teetimesblockdata'>
						<div class='rtt-teetimesblockplayers'>" . $row_result["players"] . "</div>
						<div class='rtt-teetimesblockholes'>" . $row_result["holes"] . "</div>
					</div>
				</div>
				<div class='rtt-teetimesblockbody'>
					<div class='rtt-teetimesblockname'>
						<b>" . htmlspecialchars($row_result["name"]) . "</b><br/>
						" . htmlspecialchars($row_result["course"]) . "
					</div>
					<div class='rtt-teetimesblockprice'>
						" . (($row_result["publicprice"] > $row_result["price"]) ? ("<div class='rtt-teetimespublicprice'>" . $row_result["publicprice"] . $row_result["currency"] . "</div>") : ("")) . "
						" . $row_result["price"] . $row_result["currency"] . $extra_price . "
					</div>
				</div>";

			if ($active_woocommerce) {
				$has_extraproducts = (!empty($row_result["extraproducts"]) && is_array($row_result["extraproducts"])) ? 1 : 0;

				$html_results .= "<div class='rtt-teetimesblockfooter'>
						<form data-teetimeid='" . htmlspecialchars($row_result["id"]) . "' class='rtt-teetimesform' method='post'>
							<input type='hidden' name='teetime_id' value='" . htmlspecialchars($row_result["id"]) . "' />
							<input type='hidden' name='id_facility' value='" . $id_facility . "' />
							<input type='hidden' name='date' value='" . htmlspecialchars($date) . "' />
							<input type='hidden' name='time' value='" . htmlspecialchars($row_result["time"]) . "' />
							<input type='hidden' name='cancelperiod' value='" . htmlspecialchars($row_result["cancelperiod"]) . "' />
							<input type='hidden' name='players' value='" . $players . "' />
							<input type='hidden' name='productId' value='" . htmlspecialchars($row_result["productid"]) . "' />
							<input type='hidden' name='course' value='" . htmlspecialchars($row_result["course"]) . "' />
							<input type='hidden' name='holes' value='" . $holes . "' />
							<input type='hidden' name='price' value='" . htmlspecialchars($row_result["price"]) . "' />
							<input type='hidden' name='currency' value='" . htmlspecialchars($row_result["currency"]) . "' />
							<input type='hidden' name='has_extraproducts' value='" . $has_extraproducts . "' />
							<input type='hidden' name='extraproducts' value='" . htmlspecialchars(serialize($row_result["extraproducts"])) . "' />

							<!-- =================== NEW HIDDEN FIELDS =================== -->
							<input type='hidden' name='gm_slots' value='" . htmlspecialchars($row_result["slots"] ?? '') . "' />
							<input type='hidden' name='gm_idResource' value='" . htmlspecialchars($row_result["idResource"] ?? '') . "' />
							<input type='hidden' name='gm_idType' value='" . htmlspecialchars($row_result["idType"] ?? '') . "' />
							<!-- ========================================================= -->

							<input type='hidden' name='action' value='rtt-reserveteetime' />
							<input type='submit' value='" . __("Reserve", "reserveteetimefw") . "' />
						</form>
					</div>";
			}
			$html_results .= "</div>";
		}
		$html_results .= "</div>";
		$array_results["results"] = $html_results;
	} else if (isset($array_results["code"]) && $array_results["code"] == 200) {
		$html_message = "<div class='rttsearch-errorbox'><div class='rttsearch-errorboxicon'><img src='" . esc_url(plugins_url('assets/icon_info.png', __DIR__)) . "' /></div>" . __("We are sorry, we couldn't find any results matching your criteria. Please try selecting a different date, number of players or number of holes. If after several searches you still can't find availability, please <a href=\"#contactform\">click on this link</a> so we can help you find an alternative.", "reserveteetimefw") . "</div>";

		// Check if our debug info exists and the debug flag is enabled
		if (defined('RTT_DEBUG') && RTT_DEBUG && isset($array_results['debug_info']) && is_array($array_results['debug_info'])) {
			$debug_html = "<div class='rttsearch-debugbox' style='margin-top: 20px; padding: 10px; border: 1px dashed #ccc; background: #f9f9f9; font-family: monospace; font-size: 12px; text-align: left;'>";
			$debug_html .= "<b>Debug Info (API Parameters Sent):</b><br/>";
			foreach ($array_results['debug_info'] as $key => $value) {
				$display_value = is_array($value) ? print_r($value, true) : htmlspecialchars($value);
				$debug_html .= "<strong>" . htmlspecialchars($key) . ":</strong> <pre style='white-space: pre-wrap; word-break: break-all;'>" . $display_value . "</pre>";
			}
			$debug_html .= "</div>";
			$html_message .= $debug_html;
		}
		$array_results["results"] = $html_message;
	}

	echo json_encode($array_results);
	wp_die();
}

/*
**********************************************************************************************************
* AJAX FUNCTION FOR FILLING THE POPUP FOR RESERVATIONS WITH EXTRA PRODUCTS
**********************************************************************************************************
*/
add_action('wp_ajax_teetimesearchfillpopup', 'ajax_teetimesearchfillpopup');
add_action('wp_ajax_nopriv_teetimesearchfillpopup', 'ajax_teetimesearchfillpopup');
function ajax_teetimesearchfillpopup()
{
	$array_results = array("code" => 200, "results" => "");

	// Sanitize and get all POST data
	$teetime_id = $_POST["teetime_id"] ?? null;
	$id_facility = $_POST["id_facility"] ?? null;
	$date = $_POST["date"] ?? null;
	$time = $_POST["time"] ?? null;
	$cancelperiod = $_POST["cancelperiod"] ?? null;
	$players = $_POST["players"] ?? null;
	$productId = $_POST["productId"] ?? null;
	$course = $_POST["course"] ?? null;
	$holes = $_POST["holes"] ?? null;
	$price = $_POST["price"] ?? null;
	$currency = $_POST["currency"] ?? null;
	$extraproducts = isset($_POST["extraproducts"]) ? htmlspecialchars_decode($_POST["extraproducts"]) : null;

	$display_time = (new DateTime($time))->format('d-m-Y H:i');

	$html_results = "<div class='rttsearch-popuptitle'>" . __("Your tee time selected is:", "reserveteetimefw") . "</div>
	<div class='rttsearch-popupdata'>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Date & Time", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . $display_time . "</div>
		</div>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Players", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . htmlspecialchars($players) . "</div>
		</div>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Holes", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . htmlspecialchars($holes) . "</div>
		</div>
	</div>";

	$array_extraproducts = unserialize(stripslashes($extraproducts));

	$html_results .= "<form data-teetimeid='" . htmlspecialchars($teetime_id) . "' class='rtt-teetimesformpopup' method='post'>";
	$html_results .= "<input type='hidden' name='teetime_id' value='" . htmlspecialchars($teetime_id) . "' />
		<input type='hidden' name='id_facility' value='" . htmlspecialchars($id_facility) . "' />
		<input type='hidden' name='date' value='" . htmlspecialchars($date) . "' />
		<input type='hidden' name='time' value='" . htmlspecialchars($time) . "' />
		<input type='hidden' name='cancelperiod' value='" . htmlspecialchars($cancelperiod) . "' />
		<input type='hidden' name='players' value='" . htmlspecialchars($players) . "' />
		<input type='hidden' name='productId' value='" . htmlspecialchars($productId) . "' />
		<input type='hidden' name='course' value='" . htmlspecialchars($course) . "' />
		<input type='hidden' name='extraproducts' value='" . htmlspecialchars($extraproducts) . "' />

		<input type='hidden' name='holes' value='" . htmlspecialchars($holes) . "' />";

	if (is_array($array_extraproducts) && !empty($array_extraproducts)) {
		$html_results .= "<div class='rttsearch-popuptitle'>" . __("Select extra products available", "reserveteetimefw") . "</div>";
		foreach ($array_extraproducts as $category_name => $category_data) {
			$html_results .= "<div class='rttsearch-popupsubtitle'>" . htmlspecialchars($category_name) . "</div>";
			foreach ($category_data as $product_data) {
				$html_results .= "<div class='rttsearch-popupextraproduct' data-extraproduct='" . htmlspecialchars($product_data["id"]) . "'>
					<span class='rttsearch-popupextraproductname'>" . htmlspecialchars($product_data["name"]) . "</span>
					<span class='rttsearch-popupextraproductqty'>
						<button class='rttsearch-popupminus' type='button'></button>
						<input type='number' name='extraproductqty[]' min='0' value='0' />
						<button class='rttsearch-popupplus' type='button'></button>
					</span>
					<span class='rttsearch-popupextraproductprice'>" . __("Price:", "reserveteetimefw") . " " . htmlspecialchars($product_data["price"]) . htmlspecialchars($product_data["currency"]) . "</span>
					<input type='hidden' name='extraproduct[]' value='" . htmlspecialchars($product_data["id"]) . "' />
					<input type='hidden' name='extraproductprice[]' value='" . htmlspecialchars($product_data["price"]) . "' />
				</div>";
			}
		}
	}

	$html_results .= "<div class='rttsearch-popuptitle'>" . __("Total", "reserveteetimefw") . "</div>
		<div class='rttsearch-popuptotalcontainer'>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Reservation fee", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotalreservation' class='rttsearch-popuptotalamount'>" . htmlspecialchars($price) . "</span><span class='rttsearch-popuptotalcurrency'>" . htmlspecialchars($currency) . "</span>
			</div>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Extra products", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotalextraproducts' class='rttsearch-popuptotalamount'>0</span><span class='rttsearch-popuptotalcurrency'>" . htmlspecialchars($currency) . "</span>
			</div>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Total", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotal' class='rttsearch-popuptotalamount'>" . htmlspecialchars($price) . "</span><span class='rttsearch-popuptotalcurrency'>" . htmlspecialchars($currency) . "</span>
			</div>
		</div>
		<input type='hidden' name='baseprice' value='" . htmlspecialchars($price) . "' />
		<input type='hidden' name='action' value='rtt-reserveteetime' />
		<input type='submit' value='" . __("Reserve", "reserveteetimefw") . "' />
	</form>";

	$array_results["results"] = $html_results;

	echo json_encode($array_results);
	wp_die();
}
