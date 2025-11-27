<?php
/*
**********************************************************************************************************
* RESERVATION PLATFORM ZEST GOLF class
* API DOCUMENTATION: https://cm.zest.golf/api/docs/introduction.html
**********************************************************************************************************
*/

class rttzestgolf
{

  function __construct()
  {

    $this->name = "Zest Golf";

    $this->apiurlsandbox = "https://sandbox-cm.zest.golf/api/v4";
    $this->apiurl = "https://cm.zest.golf/api/v4";

    $this->valid_modes = array("sandbox", "real");

    $this->active = get_option("rttzestgolf_active");
    $this->mode = get_option("rttzestgolf_mode");

    if (!$this->mode || !in_array($this->mode, $this->valid_modes))
      $this->mode = "sandbox";

    $this->sandboxuser = get_option("rttzestgolf_sandboxuser");
    $this->sandboxpass = get_option("rttzestgolf_sandboxpass");
    $this->realuser = get_option("rttzestgolf_realuser");
    $this->realpass = get_option("rttzestgolf_realpass");

    $this->status = 0;
    if ($this->mode == "sandbox" && $this->sandboxuser && $this->sandboxuser != "" && $this->sandboxpass && $this->sandboxpass != "")
      $this->status = 1;
    else if ($this->mode == "real" && $this->realuser && $this->realuser != "" && $this->realpass && $this->realpass != "")
      $this->status = 1;

    $this->golfcourses = $this->getgolfcourses();
  }

  /*
	* Gets the platform golf courses
	*
	* @param string $mode			Mode (Sandbox, Real...) of the golf courses, defaults to the current mode of the platform chosen in SETTINGS
	*
	* @return Array 				Array of golf courses, the keys are the post IDs, the values are the IDs in the reservation platform
	*/
  public function getgolfcourses($mode = "")
  {

    if (!$mode || !in_array($mode, $this->valid_modes))
      $mode = $this->mode;

    $array_golfcourses = array();

    $args = array(
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'post_type' => 'courses',
      'meta_query' => array(
        array(
          'key'     => 'plataforma',
          'value'   => $this->name,
          'compare' => 'LIKE'
        ),
        array(
          'key'     => 'modo',
          'value'   => $mode,
          'compare' => 'LIKE'
        )
      ),
      'lang' => 'en',
      'orderby' => 'date',
      'order' => 'desc'
    );

    $current_golfcourses = new WP_Query($args);

    if ($current_golfcourses->have_posts()) :
      while ($current_golfcourses->have_posts()) :
        $current_golfcourses->the_post();
        $current_post_id = get_the_ID();
        $array_golfcourses[$current_post_id] = get_post_meta($current_post_id, "id-plataforma", true);
      endwhile;
    endif;
    wp_reset_postdata();

    return $array_golfcourses;
  }

  /*
	* Displays the platform-specific settings in the SETTINGS section of the backend
	*/
  public function displaysettings()
  {

?>
    <h3><?= __("Zest Golf", "reserveteetimefw") ?></h3>
    <label for="rttzestgolf_active">
      <input type="checkbox" id="rttzestgolf_active" name="rttzestgolf_active" value="1"
        <?= (($this->active) ? ("checked=\"checked\"") : ("")) ?> /> <?= __("Activated", "reserveteetimefw") ?>
    </label>
    <label for="rttzestgolf_mode">
      <span class="reserveteetimefw-fieldtitle"><?= __("Mode", "reserveteetimefw") ?>:</span>
      <select id="rttzestgolf_mode" name="rttzestgolf_mode">
        <option value="sandbox" <?= (($this->mode == "sandbox") ? ("selected=\"selected\"") : ("")) ?>>
          <?= __("Sandbox mode", "reserveteetimefw") ?></option>
        <option value="real" <?= (($this->mode == "real") ? ("selected=\"selected\"") : ("")) ?>>
          <?= __("Real mode", "reserveteetimefw") ?></option>
      </select>
    </label>
    <label for="rttzestgolf_sandbox">
      <span class="reserveteetimefw-fieldtitle"><?= __("Sandbox API credentials", "reserveteetimefw") ?>:</span>
      <?= __("Username", "reserveteetimefw") ?>: <input type="text" id="rttzestgolf_sandboxuser"
        name="rttzestgolf_sandboxuser" value="<?= $this->sandboxuser ?>" /><br />
      <?= __("Password", "reserveteetimefw") ?>: <input type="text" id="rttzestgolf_sandboxpass"
        name="rttzestgolf_sandboxpass" value="<?= $this->sandboxpass ?>" />
    </label>
    <label for="rttzestgolf_real">
      <span class="reserveteetimefw-fieldtitle"><?= __("API credentials", "reserveteetimefw") ?>:</span>
      <?= __("Username", "reserveteetimefw") ?>: <input type="text" id="rttzestgolf_realuser" name="rttzestgolf_realuser"
        value="<?= $this->realuser ?>" /><br />
      <?= __("Password", "reserveteetimefw") ?>: <input type="text" id="rttzestgolf_realpass" name="rttzestgolf_realpass"
        value="<?= $this->realpass ?>" />
    </label>
    <a href="<?= admin_url('admin.php?page=main-reserveteetimeaddedpercentages&platform=zestgolf') ?>"
      class="button"><?= __("Manage added percentage to prices", "reserveteetimefw") ?></a>
  <?php
  }

  /*
	* Saves the platform-specific settings in the SETTINGS section of the backend
	*/
  public function savesettings()
  {

    if (isset($_POST["rttzestgolf_active"]) && intval($_POST["rttzestgolf_active"]) == 1)
      update_option("rttzestgolf_active", true);
    else
      update_option("rttzestgolf_active", false);

    if (isset($_POST["rttzestgolf_mode"]) && in_array($_POST["rttzestgolf_mode"], $this->valid_modes))
      update_option("rttzestgolf_mode", $_POST["rttzestgolf_mode"]);
    else
      update_option("rttzestgolf_active", "sandbox");

    update_option("rttzestgolf_sandboxuser", $_POST["rttzestgolf_sandboxuser"]);
    update_option("rttzestgolf_sandboxpass", $_POST["rttzestgolf_sandboxpass"]);
    update_option("rttzestgolf_realuser", $_POST["rttzestgolf_realuser"]);
    update_option("rttzestgolf_realpass", $_POST["rttzestgolf_realpass"]);
  }

  /*
	* Displays the ADDED PERCANTAGES settings subpage for this platform
	*
	* @param string $mode			Mode (Sandbox, Real...) of the golf courses, defaults to the current mode of the platform chosen in SETTINGS
	*/
  public function displayaddedpercentages($mode = "")
  {

    if (!$mode || !in_array($mode, $this->valid_modes))
      $mode = $this->mode;
  ?>
    <h2><?= __("Global Percentages", "reserveteetimefw") ?></h2>
    <form class="reserveteetimefw-percentagesform reserveteetimefw-form" method="post" action="admin-post.php">
      <label for="rttzestgolf_defaultadd">
        <span class="reserveteetimefw-fieldtitle"><?= __("Default added percentage to prices", "reserveteetimefw") ?>
          (%):</span>
        <input type="text" id="rttzestgolf_defaultadd" name="rttzestgolf_defaultadd"
          value="<?= get_option("rttzestgolf_defaultadd") ?>" autocomplete="off" />
        <?php wp_nonce_field('cc_op_verify'); ?>
        <input type="hidden" name="platform" value="zestgolf" />
        <input type="hidden" name="subaction" value="savezestdefaultpercentage" />
        <input type="hidden" name="action" value="rttfwpercentagesform" />
        <input type="submit" value="<?= __("Change", "reserveteetimefw") ?>" class="button-secondary" />
      </label>
    </form>
    <b><?= __("Season Percentages", "reserveteetimefw") ?></b>
    <form class="reserveteetimefw-percentagesform reserveteetimefw-form" method="post" action="admin-post.php">
      <label for="rttfwadd_startdate">
        <span class="reserveteetimefw-fieldtitle"><?= __("Add new season percentage", "reserveteetimefw") ?>:</span>
        <?= __("Starting date", "reserveteetimefw") ?>: <input type="text" name="rttfwadd_startdate"
          class="reserveteetimefw-datefield" value="" autocomplete="off" />
        <?= __("Ending date", "reserveteetimefw") ?>: <input type="text" name="rttfwadd_enddate"
          class="reserveteetimefw-datefield" value="" autocomplete="off" />
        <?= __("Percentage", "reserveteetimefw") ?> (%): <input type="text" name="rttfwadd_value" value=""
          autocomplete="off" />
        <?php wp_nonce_field('cc_op_verify'); ?>
        <input type="hidden" name="platform" value="zestgolf" />
        <input type="hidden" name="subaction" value="addzestseasonpercentage" />
        <input type="hidden" name="action" value="rttfwpercentagesform" />
        <input type="submit" value="<?= __("Add", "reserveteetimefw") ?>" class="button-secondary" />
      </label>
    </form>
    <?php
    $zest_season_percentages = $this->getseasonaddedpercentages();
    if (!empty($zest_season_percentages)) {
    ?>
      <table class="reserveteetimefw-table wp-list-table widefat fixed striped pages">
        <thead>
          <tr>
            <th><?= __("Start date", "reserveteetimefw") ?></th>
            <th><?= __("End date", "reserveteetimefw") ?></th>
            <th><?= __("Percentage", "reserveteetimefw") ?> (%)</th>
            <th><?= __("Actions", "reserveteetimefw") ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($zest_season_percentages as $row_id => $data_percentage) {
          ?>
            <tr>
              <td><input type="text" form="reserveteetimefw-editseasonpercentage-<?= $row_id ?>" name="rttfwadd_startdate"
                  class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data_percentage["startdate"]) ?>"
                  autocomplete="off" /></td>
              <td><input type="text" form="reserveteetimefw-editseasonpercentage-<?= $row_id ?>" name="rttfwadd_enddate"
                  class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data_percentage["enddate"]) ?>"
                  autocomplete="off" /></td>
              <td><input type="text" form="reserveteetimefw-editseasonpercentage-<?= $row_id ?>" name="rttfwadd_value"
                  value="<?= $data_percentage["value"] ?>" autocomplete="off" /></td>
              <td>
                <form id="reserveteetimefw-editseasonpercentage-<?= $row_id ?>"
                  class="reserveteetimefw-inlineform reserveteetimefw-percentagesform reserveteetimefw-form" method="post"
                  action="admin-post.php">
                  <?php wp_nonce_field('cc_op_verify'); ?>
                  <input type="hidden" name="platform" value="zestgolf" />
                  <input type="hidden" name="rttfwadd_id" value="<?= $row_id ?>" />
                  <input type="hidden" name="subaction" value="editseasonpercentage" />
                  <input type="hidden" name="action" value="rttfwpercentagesform" />
                  <input type="submit" value="<?= __("Edit", "reserveteetimefw") ?>" class="button-secondary" />
                </form>
                <form class="reserveteetimefw-inlineform reserveteetimefw-percentagesform reserveteetimefw-form" method="post"
                  action="admin-post.php">
                  <?php wp_nonce_field('cc_op_verify'); ?>
                  <input type="hidden" name="platform" value="zestgolf" />
                  <input type="hidden" name="rttfwadd_id" value="<?= $row_id ?>" />
                  <input type="hidden" name="subaction" value="deleteseasonpercentage" />
                  <input type="hidden" name="action" value="rttfwpercentagesform" />
                  <input type="submit" value="<?= __("Delete", "reserveteetimefw") ?>" class="button-secondary"
                    onclick="return confirm('<?= __("Are you sure?", "reserveteetimefw") ?>')" />
                </form>
              </td>
            </tr>
          <?php
          }
          ?>
        </tbody>
      </table>
    <?php
    } else {
    ?>
      <p><?= __("There are currently no season percentages for Zest Golf", "reserveteetimefw") ?></p>
    <?php
    }
    ?>
    <hr />
    <h2><?= __("Per golf course percentages", "reserveteetimefw") ?></h2>
    <?php
    $golf_courses = $this->getgolfcourses($mode);
    if (!empty($golf_courses)) {
      foreach ($golf_courses as $post_id => $course_id) {
    ?>
        <h3><?= get_the_title($post_id) ?></h3>
        <form class="reserveteetimefw-percentagesform reserveteetimefw-form" method="post" action="admin-post.php">
          <label for="rttzestgolf_coursedefaultadd">
            <span class="reserveteetimefw-fieldtitle"><?= __("Default added percentage to prices", "reserveteetimefw") ?>
              (%):</span>
            <input type="text" name="rttzestgolf_coursedefaultadd"
              value="<?= get_post_meta($post_id, 'rttgc_defaultadd', true) ?>" autocomplete="off" />
            <?php wp_nonce_field('cc_op_verify'); ?>
            <input type="hidden" name="platform" value="zestgolf" />
            <input type="hidden" name="course_id" value="<?= $post_id ?>" />
            <input type="hidden" name="subaction" value="savecoursedefaultpercentage" />
            <input type="hidden" name="action" value="rttfwpercentagesform" />
            <input type="submit" value="<?= __("Change", "reserveteetimefw") ?>" class="button-secondary" />
          </label>
        </form>
        <b><?= __("Season Percentages", "reserveteetimefw") ?></b>
        <form class="reserveteetimefw-percentagesform reserveteetimefw-form" method="post" action="admin-post.php">
          <label for="rttfwadd_startdate">
            <span class="reserveteetimefw-fieldtitle"><?= __("Add new season percentage", "reserveteetimefw") ?>:</span>
            <?= __("Starting date", "reserveteetimefw") ?>: <input type="text" name="rttfwadd_startdate"
              class="reserveteetimefw-datefield" value="" autocomplete="off" />
            <?= __("Ending date", "reserveteetimefw") ?>: <input type="text" name="rttfwadd_enddate"
              class="reserveteetimefw-datefield" value="" autocomplete="off" />
            <?= __("Percentage", "reserveteetimefw") ?> (%): <input type="text" name="rttfwadd_value" value=""
              autocomplete="off" />
            <?php wp_nonce_field('cc_op_verify'); ?>
            <input type="hidden" name="platform" value="zestgolf" />
            <input type="hidden" name="course_id" value="<?= $post_id ?>" />
            <input type="hidden" name="subaction" value="addcourseseasonpercentage" />
            <input type="hidden" name="action" value="rttfwpercentagesform" />
            <input type="submit" value="<?= __("Add", "reserveteetimefw") ?>" class="button-secondary" />
          </label>
        </form>
        <?php
        $season_percentages = $this->getseasonaddedpercentages($post_id);
        if (!empty($season_percentages)) {
        ?>
          <table class="reserveteetimefw-table wp-list-table widefat fixed striped pages">
            <thead>
              <tr>
                <th><?= __("Start date", "reserveteetimefw") ?></th>
                <th><?= __("End date", "reserveteetimefw") ?></th>
                <th><?= __("Percentage", "reserveteetimefw") ?> (%)</th>
                <th><?= __("Actions", "reserveteetimefw") ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($season_percentages as $row_id => $data_percentage) {
              ?>
                <tr>
                  <td><input type="text" form="reserveteetimefw-editcourseseasonpercentage-<?= $row_id ?>" name="rttfwadd_startdate"
                      class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data_percentage["startdate"]) ?>"
                      autocomplete="off" /></td>
                  <td><input type="text" form="reserveteetimefw-editcourseseasonpercentage-<?= $row_id ?>" name="rttfwadd_enddate"
                      class="reserveteetimefw-datefield" value="<?= date("d-m-Y", $data_percentage["enddate"]) ?>"
                      autocomplete="off" /></td>
                  <td><input type="text" form="reserveteetimefw-editcourseseasonpercentage-<?= $row_id ?>" name="rttfwadd_value"
                      value="<?= $data_percentage["value"] ?>" autocomplete="off" /></td>
                  <td>
                    <form id="reserveteetimefw-editcourseseasonpercentage-<?= $row_id ?>"
                      class="reserveteetimefw-inlineform reserveteetimefw-percentagesform reserveteetimefw-form" method="post"
                      action="admin-post.php">
                      <?php wp_nonce_field('cc_op_verify'); ?>
                      <input type="hidden" name="platform" value="zestgolf" />
                      <input type="hidden" name="rttfwadd_id" value="<?= $row_id ?>" />
                      <input type="hidden" name="subaction" value="editseasonpercentage" />
                      <input type="hidden" name="action" value="rttfwpercentagesform" />
                      <input type="submit" value="<?= __("Edit", "reserveteetimefw") ?>" class="button-secondary" />
                    </form>
                    <form class="reserveteetimefw-inlineform reserveteetimefw-percentagesform reserveteetimefw-form" method="post"
                      action="admin-post.php">
                      <?php wp_nonce_field('cc_op_verify'); ?>
                      <input type="hidden" name="platform" value="zestgolf" />
                      <input type="hidden" name="rttfwadd_id" value="<?= $row_id ?>" />
                      <input type="hidden" name="subaction" value="deleteseasonpercentage" />
                      <input type="hidden" name="action" value="rttfwpercentagesform" />
                      <input type="submit" value="<?= __("Delete", "reserveteetimefw") ?>" class="button-secondary"
                        onclick="return confirm('<?= __("Are you sure?", "reserveteetimefw") ?>')" />
                    </form>
                  </td>
                </tr>
              <?php
              }
              ?>
            </tbody>
          </table>
        <?php
        } else {
        ?>
          <p><?= __("There are currently no season percentages for this course", "reserveteetimefw") ?></p>
        <?php
        }
        ?>
        <hr />
      <?php
      }
    } else {
      ?>
      <div class="error notice">
        <p><?= __("There are currently no golf courses for this platform.", "reserveteetimefw") ?></p>
      </div>
<?php
    }
  }

  /*
	* Saves the default percentage to add to the prices of all this plataform tee times
	*
	* @return bool 				Result of the operation
	*/
  public function savezestdefaultpercentage()
  {

    if (isset($_POST["rttzestgolf_defaultadd"]))
      update_option("rttzestgolf_defaultadd", floatval($_POST["rttzestgolf_defaultadd"]));
    else
      delete_option("rttzestgolf_defaultadd");

    return true;
  }

  /*
	* Saves the default percentage to add to the prices of a specific golf course of this platform
	*
	* @return bool 				Result of the operation
	*/
  public function savecoursedefaultpercentage()
  {

    $course_id = intval($_POST["course_id"]);
    if (array_key_exists($course_id, $this->golfcourses)) {
      if (isset($_POST["rttzestgolf_coursedefaultadd"]))
        update_post_meta($course_id, "rttgc_defaultadd", floatval($_POST["rttzestgolf_coursedefaultadd"]));
      else
        delete_post_meta($course_id, "rttgc_defaultadd");

      return true;
    } else
      return false;
  }

  /*
	* Saves the default percentage to add to the prices of all this plataform tee times during a specific season
	*
	* @return bool 				Result of the operation
	*/
  public function addzestseasonpercentage()
  {

    global $wpdb;

    $startdate = strtotime($_POST["rttfwadd_startdate"]);
    $enddate = strtotime($_POST["rttfwadd_enddate"]);
    $value = floatval($_POST["rttfwadd_value"]);

    if ($startdate <= $enddate) {
      $array_insert = array(
        "rttfwadd_platform" => "Zest Golf",
        "rttfwadd_value" => $value,
        "rttfwadd_startdate" => $startdate,
        "rttfwadd_enddate" => $enddate
      );
      return $wpdb->insert($wpdb->prefix . 'rttfw_addedpercentages', $array_insert);
    } else
      return false;
  }

  /*
	* Saves the default percentage to add to the prices of a specific golf course of this platform during a specific season
	*
	* @return bool 				Result of the operation
	*/
  public function addcourseseasonpercentage()
  {

    global $wpdb;

    $course_id = intval($_POST["course_id"]);
    if (array_key_exists($course_id, $this->golfcourses)) {
      $startdate = strtotime($_POST["rttfwadd_startdate"]);
      $enddate = strtotime($_POST["rttfwadd_enddate"]);
      $value = floatval($_POST["rttfwadd_value"]);

      if ($startdate <= $enddate) {
        $array_insert = array(
          "rttfwadd_platform" => "Zest Golf",
          "rttfwadd_course" => $course_id,
          "rttfwadd_value" => $value,
          "rttfwadd_startdate" => $startdate,
          "rttfwadd_enddate" => $enddate
        );
        return $wpdb->insert($wpdb->prefix . 'rttfw_addedpercentages', $array_insert);
      } else
        return false;
    } else
      return false;
  }

  /*
	* Edits the default percentage to add to the prices of all this plataform tee times during a specific season
	*
	* @return bool 				Result of the operation
	*/
  public function editseasonpercentage()
  {

    global $wpdb;

    $row_id = intval($_POST["rttfwadd_id"]);
    $all_seasonpercentages = $this->getseasonaddedpercentages("all");
    if (array_key_exists($row_id, $all_seasonpercentages)) {
      $startdate = strtotime($_POST["rttfwadd_startdate"]);
      $enddate = strtotime($_POST["rttfwadd_enddate"]);
      $value = floatval($_POST["rttfwadd_value"]);

      if ($startdate <= $enddate) {
        $array_update = array(
          "rttfwadd_value" => $value,
          "rttfwadd_startdate" => $startdate,
          "rttfwadd_enddate" => $enddate
        );
        $wpdb->update($wpdb->prefix . 'rttfw_addedpercentages', $array_update, array("rttfwadd_id" => $row_id));

        return true;
      } else
        return false;
    } else
      return false;
  }

  /*
	* Deletes the default percentage to add to the prices of all this plataform tee times during a specific season
	*
	* @return bool 				Result of the operation
	*/
  public function deleteseasonpercentage()
  {

    global $wpdb;

    $row_id = intval($_POST["rttfwadd_id"]);
    $all_seasonpercentages = $this->getseasonaddedpercentages("all");
    if (array_key_exists($row_id, $all_seasonpercentages))
      return $wpdb->delete($wpdb->prefix . 'rttfw_addedpercentages', array("rttfwadd_id" => $row_id));
    else
      return false;
  }

  /*
	* Retrieves the percentage to add to the prices
	*
	* @param int|string $course_id			Post ID of a specific course or "all" to retrieve all of them
	*
	* @return Array 						Multidimensional array with the requested information, keys will be the row ID in the table, each element contains an array with the following fields:
	*										startdate => Start of the season in UNIX timestamp
	*										enddate => End of the season in UNIX timestamp
	*										value => Value of the percentage
	*/
  public function getseasonaddedpercentages($course_id = 0)
  {

    global $wpdb;

    if ($course_id != "all")
      $course_id = intval($course_id);

    $array_results = array();

    if ($course_id == "all" || $course_id == 0 || array_key_exists($course_id, $this->golfcourses)) {
      if ($course_id === "all")
        $added_percentages = $wpdb->get_results("SELECT *  FROM " . $wpdb->prefix . "rttfw_addedpercentages WHERE rttfwadd_platform='Zest Golf' ORDER BY rttfwadd_startdate ASC");
      else
        $added_percentages = $wpdb->get_results("SELECT *  FROM " . $wpdb->prefix . "rttfw_addedpercentages WHERE rttfwadd_platform='Zest Golf' AND rttfwadd_course='" . $course_id . "' ORDER BY rttfwadd_startdate ASC");

      foreach ($added_percentages as $row_percentage)
        $array_results[$row_percentage->rttfwadd_id] = array("startdate" => $row_percentage->rttfwadd_startdate, "enddate" => $row_percentage->rttfwadd_enddate, "value" => $row_percentage->rttfwadd_value);
    }

    return $array_results;
  }

  /*
	* Retrieves the percentage to apply to a tee time price in a specific date
	*
	* @param int $course_id			Post ID of the golf course
	* @param int $date_timestamp	Date of the tee time, in UNIX timestamp
	*
	* @return float 				Value of the percentage to apply
	*/
  public function getaddedpercentagefordate($course_id, $date_timestamp)
  {
    global $wpdb;

    $course_id = intval($course_id);
    $result = 0;

    if ($course_id == 0 || array_key_exists($course_id, $this->golfcourses)) {
      //Priority 1: Season percentage in course
      $row_courseseason = $wpdb->get_row("SELECT *  FROM " . $wpdb->prefix . "rttfw_addedpercentages WHERE rttfwadd_platform='Zest Golf' AND rttfwadd_course='" . $course_id . "' AND rttfwadd_startdate<='" . intval($date_timestamp) . "' AND rttfwadd_enddate>='" . intval($date_timestamp) . "' ORDER BY rttfwadd_value DESC");
      if ($row_courseseason)
        $result = $row_courseseason->rttfwadd_value;
      else {
        //Priority 2: Season percentage in Zest
        $row_zestseason = $wpdb->get_row("SELECT *  FROM " . $wpdb->prefix . "rttfw_addedpercentages WHERE rttfwadd_platform='Zest Golf' AND rttfwadd_course='0' AND rttfwadd_startdate<='" . intval($date_timestamp) . "' AND rttfwadd_enddate>='" . intval($date_timestamp) . "' ORDER BY rttfwadd_value DESC");
        if ($row_zestseason)
          $result = $row_zestseason->rttfwadd_value;
        else {
          //Priority 3: Default percentage in course
          $coursedefault = get_post_meta($course_id, 'rttgc_defaultadd', true);
          if ($coursedefault && $coursedefault != "")
            $result = $coursedefault;
          else {
            //Priority 4: Default percentage in Zest
            $zest_default = get_option("rttzestgolf_defaultadd");
            if ($zest_default)
              $result = $zest_default;
            else {
              //Priority 5: Default global percentage
              $global_default = get_option("rtt_defaultadd");
              if ($global_default)
                $result = $global_default;
            }
          }
        }
      }
    }

    return $result;
  }

  /*
	* Syncs golf courses with the reservation platform, adding and deleting them as needed
	*/
  public function syncgolfcourses()
  {

    if ($this->active == 1 && $this->status == 1) {
      reserveteetimefw_savelog(1, "Cron started successfully.", "Zest Golf");

      require_once(ABSPATH . "wp-admin" . '/includes/image.php');

      $upload_dir = wp_upload_dir();
      $current_year = date("Y");
      $current_month = date("m");
      $full_upload_dir = $upload_dir['basedir'] . "/rttfw/" . $current_year . "/" . $current_month;

      $array_golfcourses = $this->golfcourses;
      $array_golfcourses_found = array();

      $current_countries = get_terms(array("taxonomy" => "course-destination", "hide_empty" => false));
      $current_states = get_terms(array("taxonomy" => "course-state", "hide_empty" => false));
      $current_regions = get_terms(array("taxonomy" => "course-region", "hide_empty" => false));
      $current_cities = get_terms(array("taxonomy" => "course-city", "hide_empty" => false));
      $array_current_countries = array();
      $array_current_states = array();
      $array_current_regions = array();
      $array_current_cities = array();
      foreach ($current_countries as $term_country)
        $array_current_countries[$term_country->term_id] = $term_country->name;
      foreach ($current_states as $term_state)
        $array_current_states[$term_state->term_id] = $term_state->name;
      foreach ($current_regions as $term_region)
        $array_current_regions[$term_region->term_id] = $term_region->name;
      foreach ($current_cities as $term_city)
        $array_current_cities[$term_city->term_id] = $term_city->name;

      $countries_url = "/countries?connected=true";
      $facilites_url = "/facilities?country=[[country_name]]&connected=true";
      $facility_details_url = "/facilities/details/[[id_facility]]";

      $countries_call = $this->apicall($countries_url);
      if ($countries_call["httpcode"] == 200) {
        $countries_result = json_decode($countries_call["result"]);
        if ($countries_result->success) {
          $array_countries = $countries_result->data;
          foreach ($array_countries as $country_name) {
            $facilites_call = $this->apicall(str_replace("[[country_name]]", urlencode($country_name), $facilites_url));
            if ($facilites_call["httpcode"] == 200) {
              $facilities_result = json_decode($facilites_call["result"]);
              if ($facilities_result->success) {
                $array_facilities = $facilities_result->data;
                foreach ($array_facilities as $facility_data) {
                  $id_facility = intval($facility_data->id);
                  if (!in_array($id_facility, $array_golfcourses)) //New golf course found
                  {
                    $details_call = $this->apicall(str_replace("[[id_facility]]", $id_facility, $facility_details_url));
                    if ($details_call["httpcode"] == 200) {
                      $details_result = json_decode($details_call["result"]);
                      if ($details_result->success) {
                        $facility_data = $details_result->data;

                        $post_id = wp_insert_post(array(
                          'post_type' => 'courses',
                          'post_title' => $facility_data->name,
                          'post_status' => 'publish'
                        ));

                        if ($post_id && $post_id > 0) {
                          /*$post_id_es = wp_insert_post(array (
														'post_type' => 'courses',
														'post_title' => $facility_data->name,
														'post_name' => sanitize_title($facility_data->name)."_es",
														'post_status' => 'publish'
													));

													pll_set_post_language($post_id,"en");
													pll_set_post_language($post_id_es,"es");
													pll_save_post_translations(array("en"=>$post_id,"es"=>$post_id_es));*/

                          add_post_meta($post_id, 'plataforma', 'Zest Golf', true);
                          //add_post_meta($post_id_es,'plataforma','Zest Golf',true);

                          add_post_meta($post_id, 'id-plataforma', $id_facility, true);
                          //add_post_meta($post_id_es,'id-plataforma',$id_facility,true);

                          add_post_meta($post_id, 'modo', $this->mode, true);
                          //add_post_meta($post_id_es,'modo',$this->mode,true);

                          if (isset($facility_data->description) && $facility_data->description != "") {
                            add_post_meta($post_id, 'descripcion', $facility_data->description, true);
                            //add_post_meta($post_id_es,'descripcion',$facility_data->description,true);
                          }

                          if (isset($facility_data->latitude) && $facility_data->latitude != "" && isset($facility_data->longitude) && $facility_data->longitude != "") {
                            add_post_meta($post_id, 'ubicacon-mapa', $facility_data->latitude . "," . $facility_data->longitude, true);
                            //add_post_meta($post_id_es,'ubicacon-mapa',$facility_data->latitude.",".$facility_data->longitude,true);
                          }

                          if (isset($facility_data->address) && is_object($facility_data->address)) {
                            $current_country_termid = false;
                            $current_state_termid = false;
                            $current_region_termid = false;

                            if (isset($facility_data->address->country) && $facility_data->address->country != "") {
                              if (!in_array($facility_data->address->country, $array_current_countries)) {
                                $insert_term = wp_insert_term($facility_data->address->country, "course-destination", array("slug" => sanitize_title($facility_data->address->country)));
                                if (is_array($insert_term))
                                  $array_current_countries[$insert_term["term_id"]] = $facility_data->address->country;
                              }

                              $current_country_termid = array_search($facility_data->address->country, $array_current_countries);
                              wp_set_post_terms($post_id, array($current_country_termid), "course-destination");
                              //wp_set_post_terms($post_id_es,array($current_country_termid),"course-destination");
                            }

                            if (isset($facility_data->address->state) && $facility_data->address->state != "") {
                              if (!in_array($facility_data->address->state, $array_current_states)) {
                                $insert_term = wp_insert_term($facility_data->address->state, "course-state", array("slug" => sanitize_title($facility_data->address->state)));
                                if (is_array($insert_term))
                                  $array_current_states[$insert_term["term_id"]] = $facility_data->address->state;
                              }

                              $current_state_termid = array_search($facility_data->address->state, $array_current_states);
                              wp_set_post_terms($post_id, array($current_state_termid), "course-state");
                              //wp_set_post_terms($post_id_es,array($current_state_termid),"course-state");
                            }

                            if (isset($facility_data->address->region) && $facility_data->address->region != "") {
                              if (!in_array($facility_data->address->region, $array_current_regions)) {
                                $insert_term = wp_insert_term($facility_data->address->region, "course-region", array("slug" => sanitize_title($facility_data->address->region)));
                                if (is_array($insert_term))
                                  $array_current_regions[$insert_term["term_id"]] = $facility_data->address->region;
                              }

                              $current_region_termid = array_search($facility_data->address->region, $array_current_regions);
                              wp_set_post_terms($post_id, array($current_region_termid), "course-region");
                              //wp_set_post_terms($post_id_es,array($current_region_termid),"course-region");
                            }

                            if (isset($facility_data->address->place) && $facility_data->address->place != "") {
                              if (!in_array($facility_data->address->place, $array_current_cities)) {
                                $insert_term = wp_insert_term($facility_data->address->place, "course-city", array("slug" => sanitize_title($facility_data->address->place)));
                                if (is_array($insert_term))
                                  $array_current_cities[$insert_term["term_id"]] = $facility_data->address->place;
                              }

                              $current_city_termid = array_search($facility_data->address->place, $array_current_cities);
                              wp_set_post_terms($post_id, array($current_city_termid), "course-city");
                              //wp_set_post_terms($post_id_es,array($current_city_termid),"course-city");
                            }

                            $array_address = array();

                            if (isset($facility_data->address->address1) && $facility_data->address->address1 != "")
                              $array_address[] = $facility_data->address->address1;

                            if (isset($facility_data->address->place) && $facility_data->address->place != "")
                              $array_address[] = $facility_data->address->place;

                            if (isset($facility_data->address->region) && $facility_data->address->region != "")
                              $array_address[] = $facility_data->address->region;

                            if (isset($facility_data->address->state) && $facility_data->address->state != "")
                              $array_address[] = $facility_data->address->state;

                            if (isset($facility_data->address->country) && $facility_data->address->country != "")
                              $array_address[] = $facility_data->address->country;

                            if (!empty($array_address)) {
                              add_post_meta($post_id, 'ubicacion', implode(", ", $array_address), true);
                              //add_post_meta($post_id_es,'ubicacion',implode(", ",$array_address),true);
                            }

                            /*if(isset($facility_data->address->zipcode) && $facility_data->address->zipcode!="")
															add_post_meta($post_id,'rttgc_zipcode',$facility_data->address->zipcode,true);*/
                          }

                          if (isset($facility_data->phoneNumber) && $facility_data->phoneNumber != "") {
                            add_post_meta($post_id, 'telefono', $facility_data->phoneNumber, true);
                            //add_post_meta($post_id_es,'telefono',$facility_data->phoneNumber,true);
                          }

                          if (isset($facility_data->email) && $facility_data->email != "") {
                            add_post_meta($post_id, 'email', $facility_data->email, true);
                            //add_post_meta($post_id_es,'email',$facility_data->email,true);
                          }

                          if (isset($facility_data->website) && $facility_data->website != "") {
                            add_post_meta($post_id, 'web', $facility_data->website, true);
                            //add_post_meta($post_id_es,'web',$facility_data->website,true);
                          }

                          if (isset($facility_data->reservationsContact) && is_object($facility_data->reservationsContact)) {
                            if (isset($facility_data->reservationsContact->name) && $facility_data->reservationsContact->name != "") {
                              add_post_meta($post_id, 'contacto-reservas', $facility_data->reservationsContact->name, true);
                              //add_post_meta($post_id_es,'contacto-reservas',$facility_data->reservationsContact->name,true);
                            }

                            if (isset($facility_data->reservationsContact->email) && $facility_data->reservationsContact->email != "") {
                              add_post_meta($post_id, 'email-contacto-reservas', $facility_data->reservationsContact->email, true);
                              //add_post_meta($post_id_es,'email-contacto-reservas',$facility_data->reservationsContact->email,true);
                            }

                            if (isset($facility_data->reservationsContact->phoneNumber) && $facility_data->reservationsContact->phoneNumber != "") {
                              add_post_meta($post_id, 'telefono-contacto-reservas', $facility_data->reservationsContact->phoneNumber, true);
                              //add_post_meta($post_id_es,'telefono-contacto-reservas',$facility_data->reservationsContact->phoneNumber,true);
                            }
                          }

                          if (isset($facility_data->iagto) && $facility_data->iagto) {
                            add_post_meta($post_id, 'pertenece-a-iagto', "si", true);
                            //add_post_meta($post_id_es,'pertenece-a-iagto',"si",true);
                          } else {
                            add_post_meta($post_id, 'pertenece-a-iagto', "no", true);
                            //add_post_meta($post_id_es,'pertenece-a-iagto',"no",true);
                          }

                          if (isset($facility_data->foundedIn) && $facility_data->foundedIn != "") {
                            add_post_meta($post_id, 'ano-fundacion', $facility_data->foundedIn, true);
                            //add_post_meta($post_id_es,'ano-fundacion',$facility_data->foundedIn,true);
                          }

                          if (isset($facility_data->architect) && $facility_data->architect != "") {
                            add_post_meta($post_id, 'arquitecto', $facility_data->architect, true);
                            //add_post_meta($post_id_es,'arquitecto',$facility_data->architect,true);
                          }

                          $array_services = array();
                          if (isset($facility_data->services) && is_object($facility_data->services)) {
                            foreach ($facility_data->services as $service_name => $service_available) {
                              if ($service_name != "mid") {
                                if ($service_available)
                                  $array_services[$service_name] = "true";
                                else
                                  $array_services[$service_name] = "false";
                              }
                            }
                          }
                          add_post_meta($post_id, 'servicios', $array_services);
                          //add_post_meta($post_id_es,'servicios',$array_services);

                          $inserted_thumbnail = false;

                          if (!file_exists($full_upload_dir))
                            mkdir($full_upload_dir, 0755, true);

                          if (isset($facility_data->images) && is_array($facility_data->images)) {
                            $cont_imagen = 1;
                            $array_galeria = array();
                            foreach ($facility_data->images as $image_url) {
                              $image_url = str_replace("//branding", "/branding", $image_url);
                              $image_type = exif_imagetype($image_url);
                              $image_name = "galeria_" . $post_id . "_" . $cont_imagen . "_" . basename($image_url) . image_type_to_extension($image_type);
                              copy($image_url, $full_upload_dir . '/' . $image_name);
                              $filetype = wp_check_filetype($image_name, null);
                              $attachment_id = wp_insert_attachment(array("post_title" => $image_name, "post_content" => "", "post_status" => "inherit", "post_mime_type" => $filetype['type']), $full_upload_dir . '/' . $image_name, $post_id);
                              if ($attachment_id && intval($attachment_id) > 0) {
                                $metadata = wp_generate_attachment_metadata($attachment_id, $full_upload_dir . '/' . $image_name);
                                wp_update_attachment_metadata($attachment_id, $metadata);

                                if (!$inserted_thumbnail) {
                                  set_post_thumbnail($post_id, $attachment_id);
                                  //set_post_thumbnail($post_id_es,$attachment_id);
                                  $inserted_thumbnail = true;
                                }

                                $array_galeria[] = $attachment_id;
                              }
                              $cont_imagen++;
                            }
                            add_post_meta($post_id, 'galeria', implode(",", $array_galeria));
                            //add_post_meta($post_id_es,'galeria',implode(",",$array_galeria));
                          }

                          if (isset($facility_data->logo) && $facility_data->logo != "") {
                            $url_logo = str_replace("//branding", "/branding", $facility_data->logo);
                            $image_type = exif_imagetype($url_logo);
                            $image_name = "logo_" . $post_id . "_" . basename($url_logo) . image_type_to_extension($image_type);
                            copy($url_logo, $full_upload_dir . '/' . $image_name);
                            $filetype = wp_check_filetype($image_name, null);
                            $attachment_id = wp_insert_attachment(array("post_title" => $image_name, "post_content" => "", "post_status" => "inherit", "post_mime_type" => $filetype['type']), $full_upload_dir . '/' . $image_name, $post_id);
                            if ($attachment_id && intval($attachment_id) > 0) {
                              $metadata = wp_generate_attachment_metadata($attachment_id, $full_upload_dir . '/' . $image_name);
                              wp_update_attachment_metadata($attachment_id, $metadata);

                              if (!$inserted_thumbnail) {
                                set_post_thumbnail($post_id, $attachment_id);
                                //set_post_thumbnail($post_id_es,$attachment_id);
                                $inserted_thumbnail = true;
                              }

                              add_post_meta($post_id, 'logo', $attachment_id, true);
                              //add_post_meta($post_id_es,'logo',$attachment_id,true);
                            }
                          }

                          reserveteetimefw_savelog(1, "Inserted golf course " . $facility_data->name . " successfully.", "Zest Golf");
                        }
                      } else {
                        reserveteetimefw_savelog(2, "Unexpected result calling the facility details method", "Zest Golf");
                        exit;
                      }
                    } else {
                      reserveteetimefw_savelog(2, "Error " . $details_call["httpcode"] . " calling the facility details method", "Zest Golf");
                      exit;
                    }
                  }
                  $array_golfcourses_found[] = $id_facility;
                }
              } else {
                reserveteetimefw_savelog(2, "Unexpected result calling the facilities method", "Zest Golf");
                exit;
              }
            } else {
              reserveteetimefw_savelog(2, "Error " . $facilites_call["httpcode"] . " calling the facilities method", "Zest Golf");
              exit;
            }
          }
        } else {
          reserveteetimefw_savelog(2, "Unexpected result calling the countries method", "Zest Golf");
          exit;
        }
      } else {
        reserveteetimefw_savelog(2, "Error " . $countries_call["httpcode"] . " calling the countries method", "Zest Golf");
        exit;
      }

      //Deleting golf courses that no longer appear in the API call
      foreach ($array_golfcourses as $golfcourse_id => $golfcourse_zestid) {
        if (!in_array($golfcourse_zestid, $array_golfcourses_found)) {
          reserveteetimefw_savelog(1, "Deleting golf course " . get_the_title($golfcourse_id) . " successfully.", "Zest Golf");

          $array_translations = pll_get_post_translations($golfcourse_id);
          foreach ($array_translations as $language => $post_id)
            wp_delete_post($post_id, true);
        }
      }

      reserveteetimefw_savelog(1, "Cron finished successfully.", "Zest Golf");
    }
  }

  /*
	* Performs a tee time search
	*
	* @param int id_facility				Post ID of a specific golf course
	* @param string date					Date of the tee time in the DD-MM-YYYY format
	* @param int players					Number of players (from 1 to 4)
	* @param int holes						Number of holes for the tee time (9 or 18)
	*
	* @return Array 						Multidimensional array with the tee times information, it has the following fields:
	*										code => numerical code of the operation or error:
	*													-1: inactive platform
	*													-2: invalid golf course
	*													200: success
	*													429: system busy
	*													Other: other failure codes returned by the API
	*										results => error message in case of error, or an array of tee times with the info, containing the following fields:
	*													id: internal ID of the tee time
	*													time: Date and time of the tee time, YYYY-MM-DDTHH:ii:ss format
	*													players: number of players
	*													course: name of the course
	*													holes: number of holes
	*													productid: internal ID of the reservation product
	*													name: name of the reservation product
	*													currency: currency of the price
	*													price: price of the reservation
	*													publicprice: public price of the reservation
	*													extraproducts: array with the info regarding the extra products (buggies, etc...)
	*/
  public function getteetimes($id_facility, $date, $players, $holes)
  {
    $array_results = array();

    if ($this->active == 1 && $this->status == 1) {
      if (array_key_exists($id_facility, $this->golfcourses)) {
        $num_tries = 5;
        $array_teetimes = array();
        $teetimes_url = "/teetimes/[[id_facility]]/?bookingDate=[[booking_date]]&players=[[booking_num_players]]&holes=[[booking_num_holes]]";

        $teetimes_url = str_replace("[[id_facility]]", $this->golfcourses[$id_facility], $teetimes_url);
        $teetimes_url = str_replace("[[booking_date]]", $date, $teetimes_url);
        $teetimes_url = str_replace("[[booking_num_players]]", $players, $teetimes_url);
        $teetimes_url = str_replace("[[booking_num_holes]]", $holes, $teetimes_url);

        $addpercentage = $this->getaddedpercentagefordate($id_facility, strtotime($date));

        do {
          $teetimes_call = $this->apicall($teetimes_url);
          if ($teetimes_call["httpcode"] == 200) {
            $teetimes_result = json_decode($teetimes_call["result"]);
            if ($teetimes_result->success) {
              $cancelperiod = 0;
              if (isset($teetimes_result->data->facilityCancellationPolicyRange)) {
                foreach ($teetimes_result->data->facilityCancellationPolicyRange as $element_cancelpolicy) {
                  if ($element_cancelpolicy->minimumPlayer <= $players && $element_cancelpolicy->maximumPlayer >= $players)
                    $cancelperiod = $element_cancelpolicy->timePeriod;
                }
              }

              foreach ($teetimes_result->data->teeTimeV3 as $element_teetime) {
                if ($element_teetime->products && !empty($element_teetime->products)) {
                  $found_pricing = false;
                  $currency = false;
                  $price = false;
                  $publicprice = false;
                  $pricing = $element_teetime->products[0]->pricing;
                  foreach ($pricing as $element_pricing) {
                    if ($element_pricing->quantity == $players) {
                      $found_pricing = true;
                      $currency = $element_pricing->price->currency;
                      $price = $element_pricing->price->amount;
                      $publicprice = $element_pricing->publicRate->amount;
                      $price = $price + ($price * $addpercentage / 100);
                      $price = round($price, 2);
                    }
                  }

                  if ($found_pricing) {
                    if ($element_teetime->extraProducts && !empty($element_teetime->extraProducts)) {
                      $extraproducts = array();
                      foreach ($element_teetime->extraProducts as $extraproduct_data) {
                        if (!isset($extraproducts[$extraproduct_data->category]))
                          $extraproducts[$extraproduct_data->category] = array();

                        $extraprice = $extraproduct_data->price->amount;
                        $extraprice = $extraprice + ($extraprice * $addpercentage / 100);
                        $extraprice = round($extraprice, 2);

                        $extraproducts[$extraproduct_data->category][] = array(
                          "id" => $extraproduct_data->mid,
                          "name" => $extraproduct_data->name,
                          "currency" => $extraproduct_data->price->currency,
                          "price" => $extraprice,
                          "publicprice" => $extraproduct_data->publicRate->amount
                        );
                      }
                    } else
                      $extraproducts = false;

                    $array_teetimes[] = array(
                      "id" => $element_teetime->teeid,
                      "time" => $element_teetime->time,
                      "cancelperiod" => $cancelperiod,
                      "players" => $players,
                      "course" => $element_teetime->course,
                      "holes" => $element_teetime->holes,
                      "productid" => $element_teetime->products[0]->mid,
                      "name" => $element_teetime->products[0]->name,
                      "currency" => $currency,
                      "price" => $price,
                      "publicprice" => $publicprice,
                      "extraproducts" => $extraproducts
                    );
                  }
                }
              }
            }
          } else if ($teetimes_call["httpcode"] == 429) {
            sleep(5);
            $num_tries--;
          }
        } while ($teetimes_call["httpcode"] == 429 && $num_tries > 0);

        if ($teetimes_call["httpcode"] == 200) {
          $array_results["code"] = 200;
          $array_results["results"] = $array_teetimes;
        } else if ($teetimes_call["httpcode"] == 429) {
          $array_results["code"] = 429;
          $array_results["results"] = __("The system is busy at the moment. Please try again later.", "reserveteetimefw");
        } else {
          $array_results["code"] = $teetimes_call["httpcode"];
          $array_results["results"] = __("There has been a problem retrieving the tee times. Please contact an administrator", "reserveteetimefw");
        }
      } else {
        $array_results["code"] = -2;
        $array_results["results"] = __("Invalid golf course", "reserveteetimefw");
      }
    } else {
      $array_results["code"] = -1;
      $array_results["results"] = __("Inactive platform", "reserveteetimefw");
    }

    return $array_results;
  }

  /*
	* Checks whether a tee time is still available
	*
	* @param string id_teetime				Internal ID of the tee time
	* @param string id_facility				Post ID of a specific golf course
	* @param string date					Date of the tee time in the DD-MM-YYYY format
	* @param int players					Number of players (from 1 to 4)
	* @param int holes						Number of holes for the tee time (9 or 18)
	*
	* @return bool 							Whether the tee time is still available or not
	*/
  public function checkteetime($id_teetime, $id_facility, $date, $players, $holes)
  {
    $found_it = false;

    $array_teetimes = $this->getteetimes($id_facility, $date, $players, $holes);
    if ($array_teetimes["code"] == 200) {
      foreach ($array_teetimes["results"] as $row_teetime) {
        if ($row_teetime["id"] == $id_teetime) {
          $found_it = $row_teetime;
          break;
        }
      }
    }

    return $found_it;
  }

  /*
	* Submits the reservation of a tee time
	*
	* @param Array array_reserve_data		Array with the necessary data for the reservation, contains the following fields:
	*										id_facility => Post ID of a specific golf course
	*										date => Date of the tee time in the DD-MM-YYYY format
	*										course => Name of the course as obtained from the tee time search
	*										players => Number of players (from 1 to 4)
	*										id_teetime => Internal ID of the tee time as obtained from the tee time search
	*										holes => Number of holes for the tee time (9 or 18)
	*										product_id => internal ID of the reservation product as obtained from the tee time search
	*										name => Name of the customer
	*										lastname => Last name of the customer
	*										phone => Phone number of the customer
	*										email => Email address of the customer
	*
	* @return Array 						Multidimensional array with the result of the operation, contains the following fields:
	*										success => Whether the reservation was successful or not
	*										results => Error message or the API call return info
	*/
  public function reserveteetime($array_reserve_data)
  {
    $array_results = array();

    $url_booking = "/bookings";

    if (array_key_exists($array_reserve_data["id_facility"], $this->golfcourses)) {
      $date_new = date("Y-m-d H:i:s", strtotime($array_reserve_data["date"]));

      $reserve_fields = array(
        "facilityId" => intval($this->golfcourses[$array_reserve_data["id_facility"]]),
        "teetime" => $date_new,
        "course" => $array_reserve_data["course"],
        "players" => intval($array_reserve_data["players"]),
        "teeId" => intval($array_reserve_data["id_teetime"]),
        "holes" => intval($array_reserve_data["holes"]),
        "productId" => intval($array_reserve_data["product_id"]),
        "contactFirstName" => $array_reserve_data["name"],
        "contactLastName" => $array_reserve_data["lastname"],
        "contactPhone" => $array_reserve_data["phone"],
        "contactEmail" => $array_reserve_data["email"],
        "isKClub" => "",
        "withGroupDetails" => ""
      );

      if (isset($array_reserve_data["extraproducts"]))
        $reserve_fields["extraProduct"] = json_decode($array_reserve_data["extraproducts"], true);

      //reserveteetimefw_savelog(1,"Se van a enviar los siguientes datos ".print_r($reserve_fields,true),"Zest Golf");

      $result = $this->apicall($url_booking, $reserve_fields);

      return json_decode($result["result"]);
    } else {
      $array_results["success"] = false;
      $array_results["results"] = __("Invalid facility", "reserveteetimefw");
    }

    return $array_results;
  }

  /*
	* Submits the cancellation of a tee time
	*
	* @param string reservation_id		Internal ID of the reservation, as obtained when submitting a succesful reservation
	*
	* @return Array 					Multidimensional array with the result of the operation, contains the following fields:
	*									success => Whether the cancellation was successful or not
	*									results => Error message or the API call return info
	*/
  public function cancelteetime($reservation_id)
  {
    $array_results = array();

    if ($reservation_id && $reservation_id != "") {
      $url_cancel = "/bookings/" . $reservation_id;

      $result = $this->apicall($url_cancel, false, true);

      return json_decode($result["result"]);
    } else {
      $array_results["success"] = false;
      $array_results["results"] = __("Invalid reservation ID", "reserveteetimefw");
    }

    return $array_results;
  }

  /*
	* Identifies the return result of the API call of a reservation as a success or not
	*
	* @param string result_string		Result returned from the API call
	*
	* @return bool 						Whether the result is a success or not
	*/
  public function is_valid_result($result_string)
  {
    return (substr($result_string, 0, 3) === "ZG_");
  }

  /*
	* Submits an API call to the reservation platform
	*
	* @param string url				URL of the API endpoint
	* @param Array post_fields		Array of POST fields to send
	* @param bool is_cancel			Whether we're submitting a cancellation or not
	*
	* @return Array 				Multidimensional array with the result of the call, contains the following fields:
	*								httpcode => HTTP code returned by the API call
	*								result => Response returned by the API call
	*								header_length => Length of the result that belongs to the header
	*/
  public function apicall($url, $post_fields = false, $is_cancel = false)
  {
    if ($this->mode == "sandbox") {
      $url = $this->apiurlsandbox . $url;
      $api_username = $this->sandboxuser;
      $api_password = $this->sandboxpass;
    } else {
      $url = $this->apiurl . $url;
      $api_username = $this->realuser;
      $api_password = $this->realpass;
    }

    $headers = array(
      "Content-Type: application/json"
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($post_fields && is_array($post_fields)) {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    }

    if ($is_cancel)
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

    curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $api_username . ":" . $api_password);

    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_result_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    return array("httpcode" => $httpcode, "result" => $result, "header_length" => $header_result_length);
  }
}

?>
