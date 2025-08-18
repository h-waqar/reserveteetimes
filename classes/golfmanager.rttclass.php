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
		$this->active = 1; // Assuming Golf Manager is always active if the plugin is on
		$this->status = 1; // Assuming status is good if active
		$this->mode   = 'real'; // Golf Manager doesn't have a sandbox/real mode distinction like Zest

		$this->golfcourses = [];
		$this->load_golfcourses();

		// Ensure the API wrapper function is available
		if (file_exists(GOLF_MANAGER_DIR . 'api/get-availability.php')) {
			require_once GOLF_MANAGER_DIR . 'api/get-availability.php';
		}
	}

	/**
	 * A stricter, simpler function to load valid Golf Manager courses.
	 * A course is only considered "valid" if it has the required meta fields.
	 */
	private function load_golfcourses()
	{
		if (!post_type_exists('courses')) {
			error_log('Golf Manager: Could not load courses because the "courses" post type does not exist.');
			return;
		}

		$args = [
			'post_type' => 'courses',
			'post_status' => 'publish',
			'numberposts' => -1,
			'meta_query' => [
				[
					'key' => 'plataforma',
					'value' => 'Golf Manager',
					'compare' => '=', // Strict check. No need for LIKE or other variations.
				],
			],
		];

		$courses = get_posts($args);

		if (empty($courses)) {
			error_log('Golf Manager: No posts found with "plataforma" set to "Golf Manager".');
			return;
		}

		foreach ($courses as $course) {
			$tenant = get_post_meta($course->ID, 'tenant', true);
			$idResourceType = get_post_meta($course->ID, 'idResourceType', true);

			// --- THIS IS THE CRITICAL VALIDATION ---
			// Only add the course to our list if it has the data we NEED for an API call.
			if (!empty($tenant) && !empty($idResourceType)) {
				$this->golfcourses[$course->ID] = [
					'id'             => $course->ID,
					'title'          => $course->post_title,
					'tenant'         => $tenant,
					'idResourceType' => $idResourceType,
				];
			} else {
				// Log which course is misconfigured so you can easily fix it.
				error_log("Golf Manager: Course '{$course->post_title}' (ID: {$course->ID}) was skipped because it is missing 'tenant' or 'idResourceType' meta fields.");
			}
		}

		error_log('Golf Manager: Final loaded courses: ' . print_r(array_keys($this->golfcourses), true));
	}

	/**
	 * Checks if a specific course ID was successfully loaded and is considered valid.
	 */
	public function is_valid_course($course_id)
	{
		return isset($this->golfcourses[$course_id]);
	}


	/**
	 * Get tee times from Golf Manager API and format them for the template.
	 */
	public function getteetimes($post_id, $date, $players = 0, $holes = 0)
	{
		if (!($this->active && $this->status)) {
			return ['code' => -1, 'results' => __("Inactive platform", "reserveteetimefw")];
		}

		if (!$this->is_valid_course($post_id)) {
			return ['code' => -2, 'results' => __("Invalid golf course or missing configuration", "reserveteetimefw")];
		}

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

		$response = golf_get_availability($request);

		if (is_wp_error($response)) {
			return ['code' => 500, 'results' => $response->get_error_message()];
		}

		$response_data = $response->get_data();
		$formatted_results = [];

		if (is_array($response_data) && !empty($response_data)) {
			foreach ($response_data as $time_group) {
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
							'extraproducts' => [],
						];
					}
				}
			}
		}

		if (!empty($formatted_results)) {
			usort($formatted_results, function ($a, $b) {
				// Convert the time strings to timestamps for a reliable comparison
				$time_a = strtotime($a['time']);
				$time_b = strtotime($b['time']);

				// The spaceship operator (<=>) returns -1, 0, or 1, which is exactly what usort needs.
				return $time_a <=> $time_b;
			});
		}

		return ['code' => 200, 'results' => $formatted_results];
	}
}
