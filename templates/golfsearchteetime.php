<?php
/*
**********************************************************************************************************
* SHORTCODE FOR TEE TIME SEACH FORM
**********************************************************************************************************
*/

add_shortcode('reserveteetimefw_teetimesearch', 'reserveteetimefw_load_teetimesearch');
function reserveteetimefw_load_teetimesearch($atts)
{

	global $reserveteetimefw_classes, $reserveteetimefw_reserveresult, $woocommerce;

	if (class_exists('WooCommerce'))
		$active_woocommerce = true;
	else
		$active_woocommerce = false;

	ob_start();

	$id_facility = intval($atts["id_facility"]);
	$include_resultsblock = intval($atts["include_resultsblock"]);
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

		if (in_array($platformname, $reserveteetimefw_classes))
			$classname = "rtt" . $platformname;

		if ($classname) {
			$platform = new $classname();
			if (array_key_exists($id_facility, $platform->golfcourses)) {
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
							<!--<div class="rttsearch-filtername"><?= __("Date", "reserveteetimefw") ?>:</div>-->
							<div class="rttsearch-filterfield"><input type="text" class="rttsearch-datefield" name="date" value="<?= $default_date ?>" autocomplete="off" placeholder="<?= __("Date", "reserveteetimefw") ?>" /></div>
						</div>
						<div class="rttsearch-filter">
							<!--<div class="rttsearch-filtername"><?= __("Number of players", "reserveteetimefw") ?>:</div>-->
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
							<!--<div class="rttsearch-filtername"><?= __("Number of holes", "reserveteetimefw") ?>:</div>-->
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
	$array_results = []; // Initialize the results array

	$post_type = get_post_type($id_facility);
	if ($post_type && $post_type == "courses") {
		$post_platform = get_post_meta($id_facility, "plataforma", true);

		// Setup the platform name based on the custom field
		if ($post_platform == 'Zest Golf') $platformname = "zestgolf";
		if ($post_platform == 'Golf Manager') $platformname = "golfmanager";

		// Check if a valid platform class exists for the determined platform name
		if ($platformname && in_array($platformname, $reserveteetimefw_classes)) {
			$classname = "rtt" . $platformname;
		}

		if ($classname) {
			$platform = new $classname();

			// Use a robust validation method that works for both classes
			if (method_exists($platform, 'is_valid_course') && $platform->is_valid_course($id_facility)) {
				$valid_golfcourse = true;
			} else if (isset($platform->golfcourses) && array_key_exists($id_facility, $platform->golfcourses)) {
				$valid_golfcourse = true;
			}

			if ($valid_golfcourse) {
				$array_results = $platform->getteetimes($id_facility, $date, $players, $holes);
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
						<b>" . $row_result["name"] . "</b><br/>
						" . $row_result["course"] . "
					</div>
					<div class='rtt-teetimesblockprice'>
						" . (($row_result["publicprice"] > $row_result["price"]) ? ("<div class='rtt-teetimespublicprice'>" . $row_result["publicprice"] . $row_result["currency"] . "</div>") : ("")) . "
						" . $row_result["price"] . $row_result["currency"] . $extra_price . "
					</div>
				</div>";

			if ($active_woocommerce) {
				$has_extraproducts = (!empty($row_result["extraproducts"]) && is_array($row_result["extraproducts"])) ? 1 : 0;

				$html_results .= "<div class='rtt-teetimesblockfooter'>
						<form data-teetimeid='" . $row_result["id"] . "' class='rtt-teetimesform' method='post'>
							<input type='hidden' name='teetime_id' value='" . $row_result["id"] . "' />
							<input type='hidden' name='id_facility' value='" . $id_facility . "' />
							<input type='hidden' name='date' value='" . $date . "' />
							<input type='hidden' name='time' value='" . $row_result["time"] . "' />
							<input type='hidden' name='cancelperiod' value='" . $row_result["cancelperiod"] . "' />
							<input type='hidden' name='players' value='" . $players . "' />
							<input type='hidden' name='productId' value='" . $row_result["productid"] . "' />
							<input type='hidden' name='course' value='" . $row_result["course"] . "' />
							<input type='hidden' name='holes' value='" . $holes . "' />
							<input type='hidden' name='price' value='" . $row_result["price"] . "' />
							<input type='hidden' name='currency' value='" . $row_result["currency"] . "' />
							<input type='hidden' name='has_extraproducts' value='" . $has_extraproducts . "' />
							<input type='hidden' name='extraproducts' value='" . serialize($row_result["extraproducts"]) . "' />
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
		$array_results["results"] = "<div class='rttsearch-errorbox'><div class='rttsearch-errorboxicon'><img src='" . esc_url(plugins_url('assets/icon_info.png', __DIR__)) . "' /></div>" . __("We are sorry, we couldn't find any results matching your criteria. Please try selecting a different date, number of players or number of holes. If after several searches you still can't find availability, please <a href=\"#contactform\">click on this link</a> so we can help you find an alternative.", "reserveteetimefw") . "</div>";
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

	$teetime_id = isset($_POST["teetime_id"]) ? $_POST["teetime_id"] : null;
	$id_facility = isset($_POST["id_facility"]) ? $_POST["id_facility"] : null;
	$date = isset($_POST["date"]) ? $_POST["date"] : null;
	$time = isset($_POST["time"]) ? $_POST["time"] : null;
	$cancelperiod = isset($_POST["cancelperiod"]) ? $_POST["cancelperiod"] : null;
	$players = isset($_POST["players"]) ? $_POST["players"] : null;
	$productId = isset($_POST["productId"]) ? $_POST["productId"] : null;
	$course = isset($_POST["course"]) ? $_POST["course"] : null;
	$holes = isset($_POST["holes"]) ? $_POST["holes"] : null;
	$price = isset($_POST["price"]) ? $_POST["price"] : null;
	$currency = isset($_POST["currency"]) ? $_POST["currency"] : null;
	$extraproducts = isset($_POST["extraproducts"]) ? $_POST["extraproducts"] : null;

	$html_results = "<div class='rttsearch-popuptitle'>" . __("Your tee time selected is:", "reserveteetimefw") . "</div>
	<div class='rttsearch-popupdata'>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Date & Time", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . date("d-m-Y H:i", strtotime($time)) . "</div>
		</div>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Players", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . $players . "</div>
		</div>
		<div class='rttsearch-popupdatablock'>
			<div class='rttsearch-popupdatatitle'>" . __("Holes", "reserveteetimefw") . ":</div>
			<div class='rttsearch-popupdatavalue'>" . $holes . "</div>
		</div>
	</div>
	<div class='rttsearch-popuptitle'>" . __("Select extra products available", "reserveteetimefw") . "</div>
	<form data-teetimeid='" . $teetime_id . "' class='rtt-teetimesformpopup' method='post'>
		<input type='hidden' name='teetime_id' value='" . $teetime_id . "' />
		<input type='hidden' name='id_facility' value='" . $id_facility . "' />
		<input type='hidden' name='date' value='" . $date . "' />
		<input type='hidden' name='cancelperiod' value='" . $cancelperiod . "' />
		<input type='hidden' name='players' value='" . $players . "' />
		<input type='hidden' name='productId' value='" . $productId . "' />
		<input type='hidden' name='course' value='" . $course . "' />
		<input type='hidden' name='holes' value='" . $holes . "' />";

	$array_extraproducts = unserialize(stripslashes($extraproducts));
	foreach ($array_extraproducts as $category_name => $category_data) {
		$html_results .= "<div class='rttsearch-popupsubtitle'>" . $category_name . "</div>";
		foreach ($category_data as $product_data) {
			$html_results .= "<div class='rttsearch-popupextraproduct' data-extraproduct='" . $product_data["id"] . "'>
				<span class='rttsearch-popupextraproductname'>" . $product_data["name"] . "</span>
				<span class='rttsearch-popupextraproductqty'>
					<button class='rttsearch-popupminus' ></button>
					<input type='number' name='extraproductqty[]' min='0' value='0' />
					<button class='rttsearch-popupplus'></button>
				</span>
				<span class='rttsearch-popupextraproductprice'>" . __("Price:", "reserveteetimefw") . " " . $product_data["price"] . $product_data["currency"] . "</span>
				<input type='hidden' name='extraproduct[]' value='" . $product_data["id"] . "' />
				<input type='hidden' name='extraproductprice[]' value='" . $product_data["price"] . "' />
			</div>";
		}
	}

	$html_results .= "<div class='rttsearch-popuptitle'>" . __("Total", "reserveteetimefw") . "</div>
		<div class='rttsearch-popuptotalcontainer'>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Reservation fee", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotalreservation' class='rttsearch-popuptotalamount'>" . $price . "</span><span class='rttsearch-popuptotalcurrency'>" . $currency . "</span>
			</div>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Extra products", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotalextraproducts' class='rttsearch-popuptotalamount'>0</span><span class='rttsearch-popuptotalcurrency'>" . $currency . "</span>
			</div>
			<div class='rttsearch-popuptotalreserva'>
				<span>" . __("Total", "reserveteetimefw") . ":</span><br/>
				<span id='rttsearch-popuptotal' class='rttsearch-popuptotalamount'>" . $price . "</span><span class='rttsearch-popuptotalcurrency'>" . $currency . "</span>
			</div>
		</div>
		<input type='hidden' name='baseprice' value='" . $price . "' />
		<input type='hidden' name='action' value='rtt-reserveteetime' />
		<input type='submit' value='" . __("Reserve", "reserveteetimefw") . "' />
	</form>";

	$array_results["results"] = $html_results;

	echo json_encode($array_results);
	wp_die();
}
?>