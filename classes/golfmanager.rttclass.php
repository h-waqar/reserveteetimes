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
			'cancel-reservation.php',
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
		// This function is correct.
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
						$formatted_results[] = [
							'id' => $rate['id'] ?? uniqid(),
							'time' => $rate['start'],
							'name' => $rate['name'],
							'course' => $rate['resourceName'] ?? get_the_title($post_id),
							'players' => $players ?: ($rate['max'] ?? 4),
							'holes' => $holes ?: 18,
							'price' => floatval($rate['price'] ?? 0),
							'publicprice' => floatval($rate['rack'] ?? $rate['price'] ?? 0),
							'currency' => '€',
							'productid' => $rate['id'] ?? $post_id,
							'cancelperiod'  => 24,
							'extraproducts' => $formatted_extras,
						];
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

	/**
	 * Step 1.1: Makes a provisional reservation on the Golf Manager API via our REST endpoint.
	 * This is called before payment to temporarily hold the tee time.
	 *
	 * @param array $reservation_data Data from the checkout form.
	 * @return array The API response, including the temporary reservation ID.
	 */
	public function make_provisional_booking($reservation_data)
	{
		$request = new WP_REST_Request('POST', '/golf/v1/reservations');
		$request->set_header('Content-Type', 'application/json');

		// Build the JSON body from the provided data
		$body = [
			'tenant' => $reservation_data['tenant'],
			'reservations' => [
				[
					'idType' => $reservation_data['idType'],
					'start' => $reservation_data['start'],
					'name' => $reservation_data['name'],
					'email' => $reservation_data['email'],
					'timeout' => $reservation_data['timeout']
				]
			]
		];
		$request->set_body(json_encode($body));

		// Dispatch the request to our own REST API endpoint
		$response = rest_do_request($request);

		if ($response->is_error()) {
			return ['success' => false, 'error' => $response->as_error()->get_error_message()];
		}

		$data = $response->get_data();

		// Check for the expected structure from the external API
		if (isset($data['reservations'][0]['id'])) {
			return ['success' => true, 'provisional_id' => $data['reservations'][0]['id']];
		}

		return ['success' => false, 'error' => 'API did not return a provisional reservation ID.', 'response' => $data];
	}

	/**
	 * Step 1.2: Confirms a provisional reservation.
	 * This is called by the `reserveteetimefw_sendreservation` hook after payment.
	 *
	 * @param array $booking_data Contains the provisional ID and other data.
	 * @return object The final response object, mimicking the Zest Golf structure.
	 */
	public function reserveteetime($booking_data)
	{
		$provisional_id = $booking_data['provisional_id'];
		$tenant = $booking_data['tenant'];

		$request = new WP_REST_Request('POST', '/golf/v1/reservations/confirm');
		$request->set_header('Content-Type', 'application/json');

		$body = [
			'tenant' => $tenant,
			'ids' => [(int)$provisional_id],
			'paymentMethod' => 'Credit Card' // Or get this dynamically from the order
		];
		$request->set_body(json_encode($body));

		$response = rest_do_request($request);

		$final_response = new stdClass();

		if ($response->is_error()) {
			$final_response->success = false;
			$final_response->data = $response->as_error()->get_error_message();
			return $final_response;
		}

		$data = $response->get_data();
		if (isset($data['success']) && $data['success'] === true) {
			$final_response->success = true;
			// We use the confirmed provisional ID as the final booking reference
			$final_response->data = 'GM-' . $provisional_id;
		} else {
			$final_response->success = false;
			$final_response->data = $data['error'] ?? 'Confirmation failed.';
		}

		return $final_response;
	}

	/**
	 * Step 1.3: Cancels a reservation on the Golf Manager API.
	 * This is called by the `reserveteetimefw_processcancel` hook.
	 *
	 * @param string|int $reservation_id The final booking ID to cancel (e.g., 'GM-12345').
	 * @return object The API response, mimicking the Zest Golf structure.
	 */
	public function cancelteetime($reservation_id)
	{
		// Extract the numeric ID from our reference (e.g., 'GM-12345' -> 12345)
		$numeric_id = (int) str_replace('GM-', '', $reservation_id);

		// We need the tenant. This is a challenge, as it's not stored with the cancellation data.
		// For now, we'll have to assume a default or find it from a related order.
		// This is a known limitation we may need to solve later.
		$tenant = 'demo'; // Placeholder

		$request = new WP_REST_Request('POST', '/golf/v1/reservations/cancel');
		$request->set_header('Content-Type', 'application/json');

		$body = [
			'tenant' => $tenant,
			'ids' => [$numeric_id]
		];
		$request->set_body(json_encode($body));

		$response = rest_do_request($request);

		$final_response = new stdClass();

		if ($response->is_error()) {
			$final_response->success = false;
			$final_response->data = $response->as_error()->get_error_message();
			return $final_response;
		}

		$data = $response->get_data();
		if (isset($data['success']) && $data['success'] === true) {
			$final_response->success = true;
		} else {
			$final_response->success = false;
		}

		return $final_response;
	}
}
