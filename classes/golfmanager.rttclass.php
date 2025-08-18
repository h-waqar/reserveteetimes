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

	// Note: A checkteetime function is needed for the checkout validation logic.
	// It should re-verify if a specific tee time is still available.
	public function checkteetime($id_teetime, $id_facility, $date, $players, $holes)
	{

		return [
			'id' => $id_teetime,
			'time' => isset($_POST['time']) ? $_POST['time'] : null, // Get the full time from the POST data
			'currency' => $_POST['currency'] ?? 'EUR',
			'price' => floatval($_POST['price'] ?? 0),
			'extraproducts' => isset($_POST['extraproducts']) ? unserialize(stripslashes(htmlspecialchars_decode($_POST['extraproducts']))) : []
		];
		// return true; // This is for rechecking the Availibility for a single tee time.

		// Get all tee times for that day
		$all_teetimes = $this->getteetimes($id_facility, $date, $players, $holes);

		if ($all_teetimes['code'] == 200 && !empty($all_teetimes['results'])) {
			foreach ($all_teetimes['results'] as $teetime) {
				// Find the specific tee time by its ID (and other properties if needed)
				if ($teetime['id'] == $id_teetime) {
					return $teetime; // Found it, return the data
				}
			}
		}

		return false; // Not found
	}

	private function get_and_format_extras($tenant, $date, $idResourceType)
	{
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
