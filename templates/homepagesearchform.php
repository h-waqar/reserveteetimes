<?php
/*
**********************************************************************************************************
* SHORTCODE FOR HOMEPAGE COURSES SEARCH FORM (Refactored for Multi-Tenant)
**********************************************************************************************************
*/

add_shortcode('reserveteetimefw_searchcoursesform', 'reserveteetimefw_load_searchcoursesform');


function reserveteetimefw_load_searchcoursesform($atts)
{
  global $wpdb, $reserveteetimefw_classes;

  $array_results = [];

  // Check platforms
  $array_platform_status = [];

  foreach ($reserveteetimefw_classes as $platform_name) {
    $classname = "rtt" . $platform_name;
    $platform = new $classname();
    $array_platform_status[$platform->name] = ["active" => $platform->active, "mode" => $platform->mode];
  }

  // Query all published courses
  $args = [
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'post_type'      => 'courses'
  ];

  $search_golfcourses = new WP_Query($args);

  if ($search_golfcourses->have_posts()) :
    while ($search_golfcourses->have_posts()) :
      $search_golfcourses->the_post();

      $course_id = get_the_ID();
      $valid_course = false;

      $course_platform = get_post_meta($course_id, "plataforma", true);

      if ($course_platform != "") {
        $course_platform_key = $course_platform;
        if (array_key_exists($course_platform_key, $array_platform_status)) {
          $course_mode = get_post_meta($course_id, "modo", true) ?: 'real';
          if (
            $array_platform_status[$course_platform_key]["active"] && // boolean check
            strtolower($array_platform_status[$course_platform_key]["mode"]) === strtolower($course_mode) // case-insensitive
          ) {
            $valid_course = true;
          }
        }
      } else {
        $valid_course = true;
      }


      if ($valid_course) {
        $course_title  = get_the_title($course_id);
        $course_url    = get_the_permalink($course_id);
        $terms_country = get_the_terms($course_id, 'course-destination');
        $terms_state   = get_the_terms($course_id, 'course-state');
        $terms_region  = get_the_terms($course_id, 'course-region');
        $terms_city    = get_the_terms($course_id, 'course-city');

        $array_location = [];
        $country_name = false;

        if (is_array($terms_country) && !empty($terms_country)) {
          $array_location[] = $terms_country[0]->name;
          $country_name = $terms_country[0]->name;
        }

        if (is_array($terms_state) && !empty($terms_state)) $array_location[] = $terms_state[0]->name;
        if (is_array($terms_region) && !empty($terms_region)) $array_location[] = $terms_region[0]->name;
        if (is_array($terms_city) && !empty($terms_city)) $array_location[] = $terms_city[0]->name;

        if (!empty($array_location)) {
          $course_title .= ", " . implode(", ", $array_location);
        }

        if ($country_name) {
          if (!array_key_exists($country_name, $array_results)) $array_results[$country_name] = [];
          $array_results[$country_name][$course_id] = [
            "name" => $course_title,
            "url"  => $course_url
          ];
        }
      }

    endwhile;
  endif;
  wp_reset_postdata();

  ksort($array_results);



  ob_start();
?>
  <form class="rttsearchcoursesform" action="#" method="get">
    <div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
      <select id="rttsearch-coursefield" name="course_id">
        <option value=""><?= esc_html__("Select a course", "reserveteetimefw") ?></option>
        <?php foreach ($array_results as $country_name => $array_country) : ?>
          <optgroup label="<?= esc_attr($country_name) ?>">
            <?php foreach ($array_country as $course_id => $course_data) : ?>
              <option value="<?= esc_attr($course_id) ?>" data-url="<?= esc_url($course_data["url"]) ?>">
                <?= esc_html($course_data["name"]) ?>
              </option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
      <input type="text" class="rttsearch-datefield rttsearchcoursesform-textfield" name="date" value=""
        autocomplete="off" placeholder="<?= esc_attr__("Date", "reserveteetimefw") ?>" />
    </div>

    <div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
      <select name="players" class="rttsearchcoursesform-dropdown">
        <option value=""><?= esc_html__("Number of players", "reserveteetimefw") ?></option>
        <?php for ($i = 1; $i <= 4; $i++) : ?>
          <option value="<?= $i ?>"><?= $i ?> <?= esc_html__("players", "reserveteetimefw") ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
      <select name="holes" class="rttsearchcoursesform-dropdown">
        <option value=""><?= esc_html__("Number of holes", "reserveteetimefw") ?></option>
        <option value="9">9 <?= esc_html__("holes", "reserveteetimefw") ?></option>
        <option value="18">18 <?= esc_html__("holes", "reserveteetimefw") ?></option>
      </select>
    </div>

    <div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
      <button type="submit"
        class="rttsearchcoursesform-button"><?= esc_html__("Go to course", "reserveteetimefw") ?></button>
    </div>
  </form>
<?php

  return ob_get_clean();
}
?>
