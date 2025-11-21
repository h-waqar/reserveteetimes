<?php

/**
 * RTT Golf Manager Platform Class
 *
 * Handles all API communication, data formatting, and settings
 * for the Golf Manager integration.
 */
class rttgolfmanager
{
  public $name;
  public $active;
  public $status;
  public $mode;
  public $golfcourses;

  /**
   * Constructor. Initializes the platform.
   */
  public function __construct()
  {
    $this->name   = "Golf Manager";
    $this->active = get_option("rttgolfmanager_active", true);
    $this->status = 1;
    $this->mode   = 'real';
    $this->golfcourses = [];
    if ($this->active) {
      $this->load_golfcourses();
    }
  }

  /**
   * Loads all WordPress posts of type 'courses' configured for Golf Manager.
   */
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

  // --- Core Platform Helper Methods ---

  public function is_valid_course($course_id)
  {
    return isset($this->golfcourses[$course_id]);
  }
  public function is_valid_result($result_string)
  {
    return (strpos($result_string, 'GM-') === 0);
  }
  private function normalize_currency($currency)
  {
    $map = ['€' => 'EUR', '$' => 'USD', '£' => 'GBP'];
    return $map[$currency] ?? strtoupper($currency);
  }

  // --- Tee Time & Booking Logic ---

  /**
   * Retrieves and formats tee times from the Golf Manager API, applying any price markups.
   */
  public function getteetimes($post_id, $date, $players = 0, $holes = 0)
  {
    if (!($this->active && $this->status)) return ['code' => -1, 'results' => []];
    if (!$this->is_valid_course($post_id)) return ['code' => -2, 'results' => []];

    $tenant = $this->golfcourses[$post_id]['tenant'];
    $idResourceType = $this->golfcourses[$post_id]['idResourceType'];
    $clean_date = date('Y-m-d', strtotime($date));
    $start_date = $clean_date . 'T00:00:00';
    $end_date   = $clean_date . 'T23:59:59';

    // $request = new WP_REST_Request('GET', '/golf/v1/availability');
    // $request->set_param('tenant', $tenant);
    // $request->set_param('start', $start_date);
    // $request->set_param('end', $end_date);
    // $request->set_param('idResourceType', $idResourceType);
    // if ($players > 0) $request->set_param('players', $players);
    // if ($holes > 0) $request->set_param('holes', $holes);

    // $availability_response = golf_get_availability($request);
    // if (is_wp_error($availability_response)) return ['code' => 500, 'results' => []];
    // $availability_data = $availability_response->get_data();
    // ====================================
    $availability_data_or_error = gm_fetch_availability(
      $tenant,
      $start_date,
      $end_date,
      null,           // idResource if needed
      $idResourceType,
      $players,
      $holes
    );

    if (is_wp_error($availability_data_or_error)) {
      // handle error (log / fallback / return empty)
      error_log('GM availability error: ' . $availability_data_or_error->get_error_message());
      return ['code' => 500, 'results' => []];
    }

    $availability_data = $availability_data_or_error;
    // ====================================

    $formatted_results = [];
    $formatted_extras = $this->get_and_format_extras($post_id, $date);
    $addpercentage = $this->getaddedpercentagefordate($post_id, strtotime($date));

    if (is_array($availability_data) && !empty($availability_data)) {
      foreach ($availability_data as $time_group) {
        if (isset($time_group['types']) && is_array($time_group['types'])) {
          foreach ($time_group['types'] as $rate) {
            $price = floatval($rate['price'] ?? 0);
            $public_price = floatval($rate['rack'] ?? $price);

            $final_price = round($price + ($price * $addpercentage / 100), 2);
            $final_public_price = round($public_price + ($public_price * $addpercentage / 100), 2);

            $formatted_results[] = [
              'id'            => $rate['id'] ?? uniqid(),
              'time'          => $rate['start'],
              'name'          => $rate['name'],
              'course'        => $rate['resourceName'] ?? get_the_title($post_id),
              'players'       => $players ?: ($rate['max'] ?? 4),
              'holes'         => $holes ?: 18,
              'price'         => $final_price,
              'publicprice'   => $final_public_price,
              'currency'      => '€',
              'productid'     => $rate['id'] ?? $post_id,
              'cancelperiod'  => 24,
              'extraproducts' => $formatted_extras,
              'slots'         => $time_group['slots'] ?? null,
              'idResource'    => $rate['idResource'] ?? null,
              'min_players'   => $rate['min'] ?? null,
              'max_players'   => $rate['max'] ?? null,
              'multiple'      => $rate['multiple'] ?? null,
              'idType'        => $rate['idType'] ?? null
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
   * Retrieves and formats extras, applying any price markups.
   */
  private function get_and_format_extras($post_id, $date)
  {
    $tenant = $this->golfcourses[$post_id]['tenant'];
    $idResourceType = $this->golfcourses[$post_id]['idResourceType'];
    $start_date = date('Y-m-d', strtotime($date)) . 'T00:00:00';
    $addpercentage = $this->getaddedpercentagefordate($post_id, strtotime($date));

    // $request = new WP_REST_Request('GET', '/golf/v1/extras');
    // $request->set_param('tenant', $tenant);
    // $request->set_param('start', $start_date);
    // $request->set_param('idResourceType', $idResourceType);
    // $response = golf_get_extras($request);
    // if (is_wp_error($response) || empty($response->get_data())) return [];
    // $raw_extras = $response->get_data();

    // --- DIRECT CALL (no REST, no WP_REST_Request)
    $raw_extras = gm_fetch_extras($tenant, $start_date, $idResourceType);

    if (is_wp_error($raw_extras) || empty($raw_extras)) {
      return [];
    }


    $categorized_extras = [];
    $category_name = __("Available Extras", "reserveteetimefw");
    $categorized_extras[$category_name] = [];
    foreach ($raw_extras as $extra) {
      $price = floatval($extra['price'] ?? 0);
      $public_price = floatval($extra['rack'] ?? $price);
      $final_price = round($price + ($price * $addpercentage / 100), 2);
      $final_public_price = round($public_price + ($public_price * $addpercentage / 100), 2);

      $categorized_extras[$category_name][] = [
        'id'          => $extra['id'] ?? uniqid(),
        'name'        => $extra['name'] ?? 'Unknown Extra',
        'currency'    => $extra['currency'] ?? '€',
        'price'       => $final_price,
        'publicprice' => $final_public_price,
      ];
    }
    return $categorized_extras;
  }


  /**
   * This function simulates the Zest Golf data structure
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


  public function cancelteetime($reservation_id)
  {
    $numeric_ids = array_map('intval', explode(',', str_replace('GM-', '', $reservation_id)));
    $tenant = GOLF_MANAGER_TENANT; // Placeholder - this needs to be retrieved from the order in a real scenario

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


  /*
	====================================================================================================
	* COMPLETE SETTINGS & PERCENTAGES METHODS
	====================================================================================================
	*/

  public function displaysettings()
  {
?>
    <h3><?= __("Golf Manager", "reserveteetimefw") ?></h3>
    <label for="rttgolfmanager_active">
      <input type="checkbox" id="rttgolfmanager_active" name="rttgolfmanager_active" value="1"
        <?= ($this->active) ? ("checked=\"checked\"") : ("") ?> /> <?= __("Activated", "reserveteetimefw") ?>
    </label>
    <p style="padding-left: 25px; font-style: italic; color: #666;">
      <?= __("Configuration (Tenant, Resource ID) is managed on each individual course page.", "reserveteetimefw") ?>
    </p>
    <a href="<?= admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=golfmanager') ?>" class="button"
      style="margin-left: 25px;"><?= __("Manage added percentage to prices", "reserveteetimefw") ?></a>
  <?php
  }

  public function savesettings()
  {
    update_option("rttgolfmanager_active", isset($_POST["rttgolfmanager_active"]));
    return true;
  }

  public function displayaddedpercentages()
  {
  ?>
    <h2><?= __("Global Percentages", "reserveteetimefw") ?></h2>
    <form class="reserveteetimefw-percentagesform" method="post" action="admin-post.php">
      <label>
        <span class="reserveteetimefw-fieldtitle"><?= __("Default added percentage", "reserveteetimefw") ?> (%):</span>
        <input type="text" name="rttgm_defaultadd" value="<?= get_option("rttgm_defaultadd") ?>" autocomplete="off" />
        <?php wp_nonce_field('cc_op_verify'); ?>
        <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="subaction"
          value="savegmdefaultpercentage" />
        <input type="hidden" name="action" value="rttfwpercentagesform" /><input type="submit"
          value="<?= __("Change", "reserveteetimefw") ?>" class="button-secondary" />
      </label>
    </form>
    <b><?= __("Season Percentages", "reserveteetimefw") ?></b>
    <form class="reserveteetimefw-percentagesform" method="post" action="admin-post.php">
      <label>
        <span><?= __("Add new:", "reserveteetimefw") ?></span>
        <?= __("Start:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_startdate"
          class="reserveteetimefw-datefield" />
        <?= __("End:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_enddate"
          class="reserveteetimefw-datefield" />
        <?= __("Percentage:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_value" /> %
        <?php wp_nonce_field('cc_op_verify'); ?>
        <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="subaction"
          value="addgmseasonpercentage" />
        <input type="hidden" name="action" value="rttfwpercentagesform" /><input type="submit"
          value="<?= __("Add", "reserveteetimefw") ?>" class="button-secondary" />
      </label>
    </form>
    <?php
    $gm_season_percentages = $this->getseasonaddedpercentages();
    if (!empty($gm_season_percentages)) : ?>
      <table class="reserveteetimefw-table wp-list-table widefat fixed striped pages">
        <thead>
          <tr>
            <th>Start date</th>
            <th>End date</th>
            <th>Percentage (%)</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($gm_season_percentages as $row_id => $data) : ?>
            <tr>
              <td><input type="text" form="form-edit-<?= $row_id ?>" name="rttfwadd_startdate"
                  class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data["startdate"]) ?>" /></td>
              <td><input type="text" form="form-edit-<?= $row_id ?>" name="rttfwadd_enddate" class="reserveteetimefw-datefield"
                  value="<?= date("d-m-Y", $data["enddate"]) ?>" /></td>
              <td><input type="text" form="form-edit-<?= $row_id ?>" name="rttfwadd_value" value="<?= $data["value"] ?>" /></td>
              <td>
                <form id="form-edit-<?= $row_id ?>" class="reserveteetimefw-inlineform" method="post" action="admin-post.php">
                  <?php wp_nonce_field('cc_op_verify'); ?>
                  <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="rttfwadd_id"
                    value="<?= $row_id ?>" />
                  <input type="hidden" name="subaction" value="editseasonpercentage" /><input type="hidden" name="action"
                    value="rttfwpercentagesform" />
                  <input type="submit" value="<?= __("Edit", "reserveteetimefw") ?>" class="button-secondary" />
                </form>
                <form class="reserveteetimefw-inlineform" method="post" action="admin-post.php">
                  <?php wp_nonce_field('cc_op_verify'); ?>
                  <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="rttfwadd_id"
                    value="<?= $row_id ?>" />
                  <input type="hidden" name="subaction" value="deleteseasonpercentage" /><input type="hidden" name="action"
                    value="rttfwpercentagesform" />
                  <input type="submit" value="<?= __("Delete", "reserveteetimefw") ?>" class="button-secondary"
                    onclick="return confirm('<?= __("Are you sure?", "reserveteetimefw") ?>')" />
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <hr />
    <h2><?= __("Per Golf Course Percentages", "reserveteetimefw") ?></h2>
    <?php
    if (!empty($this->golfcourses)) {
      foreach ($this->golfcourses as $post_id => $course_data) {
    ?>
        <h3><?= get_the_title($post_id) ?></h3>
        <form class="reserveteetimefw-percentagesform" method="post" action="admin-post.php">
          <label>
            <span><?= __("Default percentage", "reserveteetimefw") ?> (%):</span>
            <input type="text" name="rttgm_coursedefaultadd"
              value="<?= get_post_meta($post_id, 'rttgc_defaultadd_gm', true) ?>" />
            <?php wp_nonce_field('cc_op_verify'); ?>
            <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="course_id"
              value="<?= $post_id ?>" />
            <input type="hidden" name="subaction" value="savecoursedefaultpercentage" /><input type="hidden" name="action"
              value="rttfwpercentagesform" />
            <input type="submit" value="<?= __("Change", "reserveteetimefw") ?>" class="button-secondary" />
          </label>
        </form>
        <b><?= __("Season Percentages", "reserveteetimefw") ?></b>
        <form class="reserveteetimefw-percentagesform" method="post" action="admin-post.php">
          <label>
            <span><?= __("Add new:", "reserveteetimefw") ?></span>
            <?= __("Start:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_startdate"
              class="reserveteetimefw-datefield" />
            <?= __("End:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_enddate"
              class="reserveteetimefw-datefield" />
            <?= __("Percentage:", "reserveteetimefw") ?> <input type="text" name="rttfwadd_value" /> %
            <?php wp_nonce_field('cc_op_verify'); ?>
            <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="course_id"
              value="<?= $post_id ?>" />
            <input type="hidden" name="subaction" value="addcourseseasonpercentage" /><input type="hidden" name="action"
              value="rttfwpercentagesform" />
            <input type="submit" value="<?= __("Add", "reserveteetimefw") ?>" class="button-secondary" />
          </label>
        </form>
        <?php
        $season_percentages = $this->getseasonaddedpercentages($post_id);
        if (!empty($season_percentages)) : ?>
          <table class="reserveteetimefw-table wp-list-table widefat fixed striped pages">
            <thead>
              <tr>
                <th>Start date</th>
                <th>End date</th>
                <th>Percentage (%)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($season_percentages as $row_id => $data) : ?>
                <tr>
                  <td><input type="text" form="form-course-edit-<?= $row_id ?>" name="rttfwadd_startdate"
                      class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data["startdate"]) ?>" /></td>
                  <td><input type="text" form="form-course-edit-<?= $row_id ?>" name="rttfwadd_enddate"
                      class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data["enddate"]) ?>" /></td>
                  <td><input type="text" form="form-course-edit-<?= $row_id ?>" name="rttfwadd_value"
                      value="<?= $data["value"] ?>" /></td>
                  <td>
                    <form id="form-course-edit-<?= $row_id ?>" class="reserveteetimefw-inlineform" method="post"
                      action="admin-post.php">
                      <?php wp_nonce_field('cc_op_verify'); ?>
                      <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="rttfwadd_id"
                        value="<?= $row_id ?>" />
                      <input type="hidden" name="subaction" value="editseasonpercentage" /><input type="hidden" name="action"
                        value="rttfwpercentagesform" />
                      <input type="submit" value="<?= __("Edit", "reserveteetimefw") ?>" class="button-secondary" />
                    </form>
                    <form class="reserveteetimefw-inlineform" method="post" action="admin-post.php">
                      <?php wp_nonce_field('cc_op_verify'); ?>
                      <input type="hidden" name="platform" value="golfmanager" /><input type="hidden" name="rttfwadd_id"
                        value="<?= $row_id ?>" />
                      <input type="hidden" name="subaction" value="deleteseasonpercentage" /><input type="hidden" name="action"
                        value="rttfwpercentagesform" />
                      <input type="submit" value="<?= __("Delete", "reserveteetimefw") ?>" class="button-secondary"
                        onclick="return confirm('<?= __("Are you sure?", "reserveteetimefw") ?>')" />
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else : echo '<p>' . __("No seasonal percentages for this course.", "reserveteetimefw") . '</p>';
        endif; ?>
        <hr />
<?php
      }
    }
  }

  // --- Percentage Saving and Data Retrieval Methods ---
  public function savegmdefaultpercentage()
  {
    update_option("rttgm_defaultadd", floatval($_POST["rttgm_defaultadd"] ?? 0));
    return true;
  }


  public function addgmseasonpercentage()
  {
    global $wpdb;
    $startdate = strtotime($_POST["rttfwadd_startdate"]);
    $enddate = strtotime($_POST["rttfwadd_enddate"]);
    $value = floatval($_POST["rttfwadd_value"]);
    if ($startdate <= $enddate) {
      return $wpdb->insert($wpdb->prefix . 'rttfw_addedpercentages', [
        "rttfwadd_platform" => "Golf Manager",
        "rttfwadd_course" => 0,
        "rttfwadd_value" => $value,
        "rttfwadd_startdate" => $startdate,
        "rttfwadd_enddate" => $enddate
      ]);
    }
    return false;
  }


  public function savecoursedefaultpercentage()
  {
    $course_id = intval($_POST["course_id"]);
    if (isset($this->golfcourses[$course_id])) {
      update_post_meta($course_id, "rttgc_defaultadd_gm", floatval($_POST["rttgm_coursedefaultadd"] ?? 0));
      return true;
    }
    return false;
  }

  public function addcourseseasonpercentage()
  {
    global $wpdb;
    $course_id = intval($_POST["course_id"]);
    if (isset($this->golfcourses[$course_id])) {
      $startdate = strtotime($_POST["rttfwadd_startdate"]);
      $enddate = strtotime($_POST["rttfwadd_enddate"]);
      $value = floatval($_POST["rttfwadd_value"]);
      if ($startdate <= $enddate) {
        return $wpdb->insert($wpdb->prefix . 'rttfw_addedpercentages', [
          "rttfwadd_platform" => "Golf Manager",
          "rttfwadd_course" => $course_id,
          "rttfwadd_value" => $value,
          "rttfwadd_startdate" => $startdate,
          "rttfwadd_enddate" => $enddate
        ]);
      }
    }
    return false;
  }

  public function editseasonpercentage()
  {
    global $wpdb;
    $row_id = intval($_POST["rttfwadd_id"]);
    $startdate = strtotime($_POST["rttfwadd_startdate"]);
    $enddate = strtotime($_POST["rttfwadd_enddate"]);
    $value = floatval($_POST["rttfwadd_value"]);
    if ($startdate <= $enddate) {
      $wpdb->update($wpdb->prefix . 'rttfw_addedpercentages', ["rttfwadd_value" => $value, "rttfwadd_startdate" => $startdate, "rttfwadd_enddate" => $enddate], ["rttfwadd_id" => $row_id]);
      return true;
    }
    return false;
  }
  public function deleteseasonpercentage()
  {
    global $wpdb;
    return $wpdb->delete($wpdb->prefix . 'rttfw_addedpercentages', ["rttfwadd_id" => intval($_POST["rttfwadd_id"])]);
  }
  public function getseasonaddedpercentages($course_id = 0)
  {
    global $wpdb;
    $course_id = intval($course_id);
    $array_results = array();
    $sql = $wpdb->prepare(
      "SELECT * FROM {$wpdb->prefix}rttfw_addedpercentages WHERE rttfwadd_platform = 'Golf Manager' AND rttfwadd_course = %d ORDER BY rttfwadd_startdate ASC",
      $course_id
    );
    $added_percentages = $wpdb->get_results($sql);
    foreach ($added_percentages as $row_percentage) {
      $array_results[$row_percentage->rttfwadd_id] = ["startdate" => $row_percentage->rttfwadd_startdate, "enddate" => $row_percentage->rttfwadd_enddate, "value" => $row_percentage->rttfwadd_value];
    }
    return $array_results;
  }

  public function getaddedpercentagefordate($course_id, $date_timestamp)
  {
    global $wpdb;
    $result = 0;
    // Priority 1: Course seasonal
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}rttfw_addedpercentages WHERE rttfwadd_platform = 'Golf Manager' AND rttfwadd_course = %d AND rttfwadd_startdate <= %d AND rttfwadd_enddate >= %d", $course_id, $date_timestamp, $date_timestamp));
    if ($row) return floatval($row->rttfwadd_value);

    // Priority 2: Global seasonal for GM
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}rttfw_addedpercentages WHERE rttfwadd_platform = 'Golf Manager' AND rttfwadd_course = 0 AND rttfwadd_startdate <= %d AND rttfwadd_enddate >= %d", $date_timestamp, $date_timestamp));
    if ($row) return floatval($row->rttfwadd_value);

    // Priority 3: Course default
    $val = get_post_meta($course_id, 'rttgc_defaultadd_gm', true);
    if (is_numeric($val)) return floatval($val);

    // Priority 4: GM global default
    $val = get_option("rttgm_defaultadd");
    if (is_numeric($val)) return floatval($val);

    // Priority 5: Plugin global default
    $val = get_option("rtt_defaultadd");
    if (is_numeric($val)) return floatval($val);

    return $result;
  }
}
