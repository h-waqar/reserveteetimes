<?php
/*
**********************************************************************************************************
* TEMPLATE FOR GOLF COURSES' PAGES
**********************************************************************************************************
*/

// Do not allow directly accessing this file.
if (!defined('ABSPATH')) {
	exit('Direct script access denied.');
}

global $reserveteetimefw_classes, $reserveteetimefw_reserveresult, $post;

wp_enqueue_style('dashicons');

get_header();

$valid_golfcourse = false;

// Ensure we are on a 'rttcourse' custom post type page.
if ($post && $post->post_type === 'rttcourse') {
	// Get all necessary data from the post and its metadata.
	$course_id = $post->ID;
	$post_platform = get_post_meta($course_id, 'plataforma', true); // e.g., 'Golf Manager' or 'Zest Golf'
	$post_mode = get_post_meta($course_id, 'modo', true);
	$tenant = get_post_meta($course_id, 'tenant', true);
	$idResourceType = get_post_meta($course_id, 'idResourceType', true);

	$platformname = false;
	if ($post_platform === 'Zest Golf') $platformname = 'zestgolf';
	if ($post_platform === 'Golf Manager') $platformname = 'golfmanager';

	// Validate that the platform is supported and configured.
	if ($platformname && in_array($platformname, $reserveteetimefw_classes)) {
		$classname = 'rtt' . $platformname;
		$platform = new $classname();

		// Further validation can be added here, e.g., checking mode.
		// if ($platform->mode === $post_mode) {
		$valid_golfcourse = true;
		// }
	}

	// If the course is valid, prepare its content for display.
	if ($valid_golfcourse) {
		$course_title = get_the_title($course_id);
		$course_description = get_the_content();
		$address = get_post_meta($course_id, 'rttgc_address', true);
		$services = get_post_meta($course_id, 'rttgc_services', true); // Assuming this is an array of service IDs.
		$latitude = get_post_meta($course_id, 'rttgc_latitude', true);
		$longitude = get_post_meta($course_id, 'rttgc_longitude', true);
		$imggallery = get_post_meta($course_id, 'rttgc_imagegallery');
		$image_url = (!empty($imggallery)) ? wp_get_attachment_image_src($imggallery[0], 'full') : false;

		// Location terms
		$terms_city = get_the_terms($course_id, 'rttcity') ?: [];
		$terms_country = get_the_terms($course_id, 'rttcountry') ?: [];
		$array_location = array_filter([
			$address,
			$terms_city[0]->name ?? null,
			$terms_country[0]->name ?? null,
		]);
	}
}
?>
<main id="content">
	<div class="page-content">
		<?php if ($valid_golfcourse) : ?>
			<div class="rtt_golfcourseheader" <?= $image_url ? "style=\"background-image:url('{$image_url[0]}')\"" : '' ?>>
				<div class="rtt_golfcourseheaderinner rtt_golfcourseboxed">
					<h1 class="rtt_golfcoursetitle"><?= esc_html($course_title) ?></h1>
					<div class="rtt_golfcourselocation"><span class="dashicons dashicons-location"></span> <?= esc_html(implode(', ', $array_location)) ?></div>
				</div>
			</div>

			<div class="rtt_golfcoursebody">
				<div class="rtt_golfcoursebodycol1">
					<?php if ($course_description) : ?>
						<div class="rtt_golfcoursedescription">
							<h2 class="rtt_golfcoursesubtitle"><?= __("About This Course", "reserveteetimefw") ?></h2>
							<?= wp_kses_post($course_description) ?>
						</div>
					<?php endif; ?>

					<?php if (!empty($services) && is_array($services)) : ?>
						<div class="rtt_golfcourseservices">
							<h2 class="rtt_golfcoursesubtitle"><?= __("Course Services & Facilities", "reserveteetimefw") ?></h2>
							<div class="rtt_golfcourseservicelist">
								<?php foreach ($services as $id_service) : ?>
									<div class="rtt_golfcourseservicesingle">
										<div class="rtt_golfcourseserviceicon_<?= esc_attr($id_service) ?> rtt_golfcourseserviceicon"></div>
										<div class="rtt_golfcourseservicename"><?= esc_html(reserveteetimefw_getservicename($id_service)) ?></div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<div class="rtt_golfcoursebodycol2">
					<?php
					// --- IMPROVED: Dynamically build and call the shortcode. ---
					if ($post_platform === 'Golf Manager' || $post_platform === 'Zest Golf') {

						// Base attributes for all platforms.
						$shortcode_atts = [
							'id_facility'          => $course_id,
							'include_resultsblock' => 0, // Tell the shortcode NOT to render the results div, as we have one below.
						];

						// Conditionally add attributes required only for 'Golf Manager'.
						if ($post_platform === 'Golf Manager') {
							$shortcode_atts['tenant'] = $tenant;
							$shortcode_atts['idResourceType'] = $idResourceType;
						}

						// Build the shortcode string from the attributes array.
						$shortcode_string = '[reserveteetimefw_teetimesearch';
						foreach ($shortcode_atts as $key => $value) {
							$shortcode_string .= " {$key}='" . esc_attr($value) . "'";
						}
						$shortcode_string .= ']';

						// Execute the shortcode.
						echo do_shortcode($shortcode_string);
					}
					?>
				</div>

				<div id="rttsearch-results"></div>
			</div>

			<?php if ($latitude && $longitude) : ?>
				<div class="rtt_golfcoursemap">
					<iframe src="https://maps.google.com/maps?q=<?= esc_attr($latitude) ?>,<?= esc_attr($longitude) ?>&z=15&output=embed" width="100%" height="350" frameborder="0" style="border:0" allowfullscreen></iframe>
				</div>
			<?php endif; ?>

		<?php else : ?>
			<div class="rtt_golfcourseinvalid"><?= __("Invalid golf course", "reserveteetimefw") ?></div>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>