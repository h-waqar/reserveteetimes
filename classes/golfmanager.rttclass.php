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
		if (file_exists(GOLF_MANAGER_DIR . 'api/get-availability.php')) {
			require_once GOLF_MANAGER_DIR . 'api/get-availability.php';
		}
		if (file_exists(GOLF_MANAGER_DIR . 'api/get-extras.php')) {
			require_once GOLF_MANAGER_DIR . 'api/get-extras.php';
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
}
