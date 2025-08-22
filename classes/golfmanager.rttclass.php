<?php
class rttgolfmanager
{
	public $name;
	public $active;
	public $status;
	public $mode;
	public $golfcourses;

	public function __construct()
	{
		$this->name   = "Golf Manager";
		$this->active = 1;
		$this->status = 1;
		$this->mode   = 'real';
		$this->golfcourses = [];
		$this->load_golfcourses();

		$apiFiles = [
			'get-availability.php',
			'get-extras.php',
			'post-reservations.php',
			'post-confirm-reservation.php',
			'post-cancel-reservation.php',
		];

		// Loop through the array and include each file
		foreach ($apiFiles as $file) {
			$path = GOLF_MANAGER_DIR . 'api/' . $file;
			if (file_exists($path)) {
				require_once $path;
			}
		}
	}

	private function load_golfcourses()
	{
		if (!post_type_exists('courses')) return;
		$courses = get_posts(['post_type' => 'courses', 'post_status' => 'publish', 'numberposts' => -1, 'meta_query' => [['key' => 'plataforma', 'value' => 'Golf Manager', 'compare' => '=']]]);
		if (empty($courses)) return;
		foreach ($courses as $course) {
			$tenant = get_post_meta($course->ID, 'tenant', true);
			$idResourceType = get_post_meta($course->ID, 'idResourceType', true);
			if (!empty($tenant) && !empty($idResourceType)) {
				$this->golfcourses[$course->ID] = ['id' => $course->ID, 'title' => $course->post_title, 'tenant' => $tenant, 'idResourceType' => $idResourceType];
			}
		}
	}

	public function is_valid_course($course_id)
	{
		return isset($this->golfcourses[$course_id]);
	}

	public function is_valid_result($result_string)
	{
		return (strpos($result_string, 'GM-') === 0);
	}

	// THIS FUNCTION IS NOW PRESENT AND CORRECT
	private function normalize_currency($currency)
	{
		$map = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP'];
		return $map[$currency] ?? strtoupper($currency);
	}



	/**
	 * FINAL, DEFINITIVE VERSION: This function now correctly simulates the Zest Golf data structure
	 * by MANUALLY parsing the extras data, bypassing any `unserialize` errors.
	 */
	public function checkteetime($id_teetime, $id_facility, $date, $players, $holes)
	{
		// Determine the correct base price from the submitted form.
		$price = 0;
		if (isset($_POST['baseprice'])) {
			$price = floatval($_POST['baseprice']); // From the popup
		} elseif (isset($_POST['price'])) {
			$price = floatval($_POST['price']); // From a direct click
		}

		// --- Brute-force extras parsing ---
		$extraproducts_array = [];
		if (isset($_POST['extraproducts'])) {
			// Get the raw serialized string from the POST data
			$serialized_extras = stripslashes(htmlspecialchars_decode($_POST['extraproducts']));

			// Instead of unserializing, we will find the data we need with regular expressions.
			// This is not pretty, but it is robust against serialization errors.
			preg_match('/s:16:\\\\"Available Extras\\\\";a:(\d+):{(.*)}/s', $serialized_extras, $matches);

			if (isset($matches[2])) {
				$extras_content = $matches[2];
				preg_match_all('/s:2:\\\\"id\\\\";i:(\d+);s:4:\\\\"name\\\\";s:\d+:\\\\"(.*?)\\\\";s:8:\\\\"currency\\\\";s:\d+:\\\\"(.*?)\\\\";s:5:\\\\"price\\\\";d:(\d+);s:11:\\\\"publicprice\\\\";d:(\d+);/s', $extras_content, $item_matches, PREG_SET_ORDER);

				if (!empty($item_matches)) {
					$category_name = __("Available Extras", "reserveteetimefw");
					$extraproducts_array[$category_name] = [];
					foreach ($item_matches as $item) {
						$extraproducts_array[$category_name][] = [
							'id'          => intval($item[1]),
							'name'        => $item[2],
							'currency'    => $this->normalize_currency($item[3]),
							'price'       => floatval($item[4]),
							'publicprice' => floatval($item[5]),
						];
					}
				}
			}
		}

		return [
			'id'            => $id_teetime,
			'time'          => $_POST['time'] ?? null,
			'currency'      => $this->normalize_currency($_POST['currency'] ?? 'EUR'),
			'price'         => $price,
			'extraproducts' => $extraproducts_array // Return the manually parsed array.
		];
	}


	private function get_and_format_extras($tenant, $date, $idResourceType)
	{
		// This function is correct.
		$request = new WP_REST_Request('GET', '/golf/v1/extras');
		$request->set_param('tenant', $tenant);
		$request->set_param('start', $date);
		$request->set_param('idResourceType', $idResourceType);
		$response = golf_get_extras($request);
		if (is_wp_error($response) || empty($response->get_data())) {
			return [];
		}
		$raw_extras = $response->get_data();
		$categorized_extras = [];
		$category_name = __("Available Extras", "reserveteetimefw");
		$categorized_extras[$category_name] = [];
		foreach ($raw_extras as $extra) {
			$categorized_extras[$category_name][] = [
				'id'          => $extra['id'] ?? uniqid(),
				'name'        => $extra['name'] ?? 'Unknown Extra',
				'currency'    => $extra['currency'] ?? '€',
				'price'       => floatval($extra['price'] ?? 0),
				'publicprice' => floatval($extra['rack'] ?? $extra['price'] ?? 0),
			];
		}
		return $categorized_extras;
	}

	public function getteetimes($post_id, $date, $players = 0, $holes = 0)
	{
		if (!($this->active && $this->status)) return ['code' => -1, 'results' => []];
		if (!$this->is_valid_course($post_id)) return ['code' => -2, 'results' => []];

		$tenant = $this->golfcourses[$post_id]['tenant'];
		$idResourceType = $this->golfcourses[$post_id]['idResourceType'];
		$clean_date = date('Y-m-d', strtotime($date));
		$start_date = $clean_date . 'T00:00:00';
		$end_date   = $clean_date . 'T23:59:59';

		$request = new WP_REST_Request('GET', '/golf/v1/availability');
		$request->set_param('tenant', $tenant);
		$request->set_param('start', $start_date);
		$request->set_param('end', $end_date);
		$request->set_param('idResourceType', $idResourceType);
		if ($players > 0) $request->set_param('players', $players);
		if ($holes > 0) $request->set_param('holes', $holes);

		$availability_response = golf_get_availability($request);
		if (is_wp_error($availability_response)) return ['code' => 500, 'results' => []];

		$availability_data = $availability_response->get_data();
		$formatted_results = [];
		$formatted_extras = $this->get_and_format_extras($tenant, $start_date, $idResourceType);

		if (is_array($availability_data) && !empty($availability_data)) {
			foreach ($availability_data as $time_group) {
				if (isset($time_group['types']) && is_array($time_group['types'])) {
					foreach ($time_group['types'] as $rate) {
						// =================== THIS IS THE ONLY CHANGE ===================
						// We are adding all the new fields to the array we return.
						$formatted_results[] = [
							'id'            => $rate['id'] ?? uniqid(),
							'time'          => $rate['start'],
							'name'          => $rate['name'],
							'course'        => $rate['resourceName'] ?? get_the_title($post_id),
							'players'       => $players ?: ($rate['max'] ?? 4),
							'holes'         => $holes ?: 18,
							'price'         => floatval($rate['price'] ?? 0),
							'publicprice'   => floatval($rate['rack'] ?? $rate['price'] ?? 0),
							'currency'      => '€',
							'productid'     => $rate['id'] ?? $post_id,
							'cancelperiod'  => 24,
							'extraproducts' => $formatted_extras,
							// --- NEWLY ADDED FIELDS ---
							'slots'         => $time_group['slots'] ?? null,
							'idResource'    => $rate['idResource'] ?? null,
							'min_players'   => $rate['min'] ?? null,
							'max_players'   => $rate['max'] ?? null,
							'multiple'      => $rate['multiple'] ?? null,
							'idType'        => $rate['idType'] ?? null
						];
						// =================== END OF CHANGE ============================
					}
				}
			}
		}

		if (!empty($formatted_results)) {
			usort($formatted_results, function ($a, $b) {
				return strtotime($a['time']) <=> strtotime($b['time']);
			});
		}

		return ['code' => 200, 'results' => $formatted_results];
	}



	/*
	====================================================================================================
	* CORRECTED BOOKING API METHODS (USING DIRECT FUNCTION CALLS)
	====================================================================================================
	*/

	public function make_provisional_booking($payload)
	{
		$api_url = get_rest_url(null, 'golf/v1/reservations');
		$args = [
			'method'  => 'POST',
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8'
			],
			'body'    => json_encode($payload),
		];

		$response = wp_remote_post($api_url, $args);

		if (is_wp_error($response)) {
			return ['success' => false, 'error' => $response->get_error_message()];
		}

		$response_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if ($response_code === 200 && isset($data['reservations'][0]['id'])) {
			return ['success' => true, 'full_response' => $data];
		}

		// If it fails, capture the error message from the API response
		$error_message = $data['message'] ?? 'API did not return a provisional ID.';
		return ['success' => false, 'error' => $error_message, 'response' => $data];
	}



	/**
	 * Confirms a provisional reservation using the proven wp_remote_post method.
	 * This is called by `reserveteetimefw_sendreservation` after payment.
	 */

	public function reserveteetime($booking_data)
	{
		_logger("--- DEBUG #3: Inside reserveteetime() method ---");
		_logger("Received booking_data:");
		_logger($booking_data);

		$api_url = get_rest_url(null, 'golf/v1/reservations/confirm');

		$body = json_encode([
			'tenant' => $booking_data['tenant'],
			'ids'    => $booking_data['provisional_ids'],
			// 'paymentMethod' => $booking_data['payment_method']
		]);

		$args = [
			'method'  => 'POST',
			'timeout' => 20,
			'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
			'body'    => $body,
		];

		_logger("Data being sent to wp_remote_post:");
		_logger("URL: " . $api_url);
		_logger($args);

		$response = wp_remote_post($api_url, $args);

		_logger("Response from wp_remote_post:");
		_logger($response);

		$final_response = new stdClass();

		if (is_wp_error($response)) {
			$final_response->success = false;
			$final_response->data = $response->get_error_message();
			return $final_response;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		$final_response->raw_response = $data;

		if (isset($data['success']) && $data['success'] === true) {
			$final_response->success = true;
			$final_response->data = 'GM-' . implode(',', $booking_data['provisional_ids']);
		} else {
			$final_response->success = false;
			$final_response->data = $data['message'] ?? 'Confirmation failed.';
		}

		// --- THIS IS THE CHANGE ---
		// We add the raw response data to our return object.



		return $final_response;
	}
	// /**
	//  * FINAL VERSION: Confirms a provisional reservation using the proven wp_remote_post method.
	//  * This is called by `reserveteetimefw_sendreservation` after payment.
	//  */

	// public function reserveteetime($booking_data)
	// {
	// 	$api_url = get_rest_url(null, 'golf/v1/reservations/confirm');

	// 	// The body of the request is a simple JSON object
	// 	$body = json_encode([
	// 		'tenant' => $booking_data['tenant'],
	// 		'ids'    => $booking_data['provisional_ids']
	// 	]);

	// 	$args = [
	// 		'method'  => 'POST',
	// 		'timeout' => 20,
	// 		'headers' => [
	// 			'Content-Type' => 'application/json; charset=utf-8'
	// 		],
	// 		'body'    => $body,
	// 	];

	// 	$response = wp_remote_post($api_url, $args);
	// 	$final_response = new stdClass(); // Mimic the Zest Golf response structure

	// 	if (is_wp_error($response)) {
	// 		$final_response->success = false;
	// 		$final_response->data = $response->get_error_message();
	// 		return $final_response;
	// 	}

	// 	$body = wp_remote_retrieve_body($response);
	// 	$data = json_decode($body, true);

	// 	if (isset($data['success']) && $data['success'] === true) {
	// 		$final_response->success = true;
	// 		// Use the confirmed ID(s) as the final booking reference
	// 		$final_response->data = 'GM-' . implode(',', $booking_data['provisional_ids']);
	// 	} else {
	// 		$final_response->success = false;
	// 		$final_response->data = $data['message'] ?? 'Confirmation failed.';
	// 	}

	// 	return $final_response;
	// }

	public function cancelteetime($reservation_id)
	{
		$numeric_ids = array_map('intval', explode(',', str_replace('GM-', '', $reservation_id)));
		$tenant = 'demo'; // Placeholder - this needs to be retrieved from the order in a real scenario

		$api_url = get_rest_url(null, 'golf/v1/reservations/cancel');
		$body = json_encode(['tenant' => $tenant, 'ids' => $numeric_ids]);
		$args = [
			'method'  => 'POST',
			'timeout' => 20,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8'
			],
			'body'    => $body,
		];

		$response = wp_remote_post($api_url, $args);
		$final_response = new stdClass();

		if (is_wp_error($response)) {
			$final_response->success = false;
			return $final_response;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (isset($data['success']) && $data['success'] === true) {
			$final_response->success = true;
		} else {
			$final_response->success = false;
		}

		return $final_response;
	}
}
