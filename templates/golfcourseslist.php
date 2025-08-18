<?php
/*
**********************************************************************************************************
* SHORTCODE FOR GOLF COURSES' LIST
**********************************************************************************************************
*/

add_shortcode('reserveteetimefw_listcourses','reserveteetimefw_load_listcourses');
function reserveteetimefw_load_listcourses($atts) {

	global $reserveteetimefw_classes;
	
	if(pll_current_language() == "es")
	{
		$cadena_featuredoffer = "Oferta destacada";
		$cadena_freecancellation = "Cancelación gratuita";
		$cadena_desde = "Desde";
		$cadena_lastavailable = "Solo queda 1 por este precio";
	}
	else
	{
		$cadena_featuredoffer = "Featured Offer";
		$cadena_freecancellation = "Free Cancellation";
		$cadena_desde = "From";
		$cadena_lastavailable = "Only 1 left at this price on our website";
	}

	$base_url 			 = parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH);
	$pag 				 = intval($_GET["pag"]);
	$per_page        	 = intval($atts["perpage"]);
	$filter_country  	 = $atts["country"];
	$filter_issearch 	 = intval($atts["issearch"]);
	$filter_featured 	 = $atts["featured"];
	$add_countryselector = intval($atts["countryselector"]);
	$add_paginator 	 	 = intval($atts["paginator"]);
	
	if(!$pag || $pag<=0)
		$pag = 1;

	if(!$per_page || $per_page<=0)
		$per_page = 12;

	$offset = $per_page*($pag-1);

	$array_countries = array();
	$array_results   = array();

	$array_platform_status = array();
	foreach($reserveteetimefw_classes as $platform_name)
	{
		$classname = "rtt".$platform_name;
		$platform = new $classname();
		$array_platform_status[$platform->name] = array("active" => $platform->active,"mode" => $platform->mode);
	}
	
	$args = array(
		'post_status' => 'publish',
		'posts_per_page' => '-1',
		'post_type' => 'courses',
		'meta_query' => array(),
		'tax_query' => array(
			'relation' => 'AND'
		),
		'orderby' => 'date',
		'order' => 'desc'
	);

	if($filter_issearch && $filter_issearch==1)
	{
		if(isset($_GET["rttsearchname"]))
			$searchname = $_GET["rttsearchname"];
		else
			$searchname = false;

		if(isset($_GET["rttsearchcountry"]))
			$searchcountry = $_GET["rttsearchcountry"];
		else
			$searchcountry = false;

		if(isset($_GET["rttsearchcity"]))
			$searchcity = $_GET["rttsearchcity"];
		else
			$searchcity = false;

		if($searchname && strlen($searchname)>3)
			$args['s'] = $searchname;

		if($searchcity && $searchcity!="")
			$args['tax_query'][] = array('taxonomy' => 'course-city', 'field' => 'slug', 'terms' => $searchcity);
		else if($searchcountry && $searchcountry!="")
			$args['tax_query'][] = array('taxonomy' => 'course-destination', 'field' => 'slug', 'terms' => $searchcountry);
	}
	else
	{
		if($filter_country && $filter_country!="")
			$args['tax_query'][] = array('taxonomy' => 'course-destination', 'field' => 'slug', 'terms' => $filter_country);

		if($filter_featured==1)
			$args['tax_query'][] = array('taxonomy' => 'last-minute-offer', 'field' => 'slug', 'terms' => 'yes');

		if($filter_featured==2)
			$args['tax_query'][] = array('taxonomy' => 'last-minute-offers', 'field' => 'slug', 'terms' => 'yes');
	}

	if(isset($_GET["country"]) && $_GET["country"]!="")
		$selected_country = $_GET["country"];
	else
		$selected_country = "";

	$search_golfcourses = new WP_Query($args);

	if($search_golfcourses->have_posts()) :
		while($search_golfcourses->have_posts()) :
			$search_golfcourses->the_post();

			$course_id = get_the_ID();

			$valid_course = false;

			$course_platform = get_post_meta($course_id,"plataforma",true);
			if($course_platform!="")
			{
				if(array_key_exists($course_platform,$array_platform_status)) //Valid platform
				{
					$course_mode = get_post_meta($course_id,"modo",true);
					if($array_platform_status[$course_platform]["active"] == 1 && $array_platform_status[$course_platform]["mode"] == $course_mode) //Platform is active and course has the correct current mode (Real/Sandbox)
						$valid_course = true;
				}
			}
			else
				$valid_course = true; //Manual course

			if($valid_course)
			{
				$course_title = get_the_title($course_id);
				$course_description = wp_trim_words(get_post_meta($course_id,"descripcion",true),20);
				$course_link = get_the_permalink($course_id);
				$course_location = get_post_meta($course_id,"ubicacion",true);
				$course_rating = intval(get_post_meta($course_id,"valoracion",true));
				$course_price = get_post_meta($course_id,"precio",true);
				$course_currency = get_post_meta($course_id,"moneda",true);
				$course_promo = get_post_meta($course_id,"promo");

				$course_country = "";
				$course_country_slug = "";
				$course_country_terms = get_the_terms($course_id,"course-destination");
				if($course_country_terms && !empty($course_country_terms))
				{
					if(!array_key_exists($course_country_terms[0]->slug,$array_countries))
						$array_countries[$course_country_terms[0]->slug] = $course_country_terms[0]->name;

					$course_country_slug = $course_country_terms[0]->slug;
					$course_country = $course_country_terms[0]->name;
				}

				$image_url = get_the_post_thumbnail_url($course_id,"full");

				$valid_country = true;

				if($selected_country && $selected_country!="" && $course_country_slug!=$selected_country)
					$valid_country = false;

				if($valid_country)
				{
					$array_results[$course_id] = array(
					"ID" => $course_id,
					"name" => $course_title,
					"description" => $course_description,
					"link" => $course_link,
					"country" => $course_country,
					"location" => $course_location,
					"rating" => $course_rating,
					"price" => $course_price,
					"currency" => $course_currency,
					"promo" => $course_promo[0],
					"image_url" => $image_url
					);
				}
			}

		endwhile;
	endif;
	wp_reset_postdata();

	asort($array_countries);
	uasort($array_results,'reserveteetimefw_reordergolfcourselist');
	
	wp_enqueue_style('dashicons');

	ob_start();

	if($add_countryselector == 1 && !empty($array_countries))
	{
		?>
		<div class="rtt_courselistcountryselectorwrapper">
			<select id="rtt_courselistcountryselector" onchange="location='<?= $base_url ?>?country='+this.options[this.selectedIndex].value;">
				<option value="">All countries</option>
				<?php foreach($array_countries as $country_slug => $country_name) { ?>
					<option value="<?= $country_slug ?>" <?= (($selected_country==$country_slug)?("selected=\"selected\""):("")) ?>><?= $country_name ?></option>
				<?php } ?>
			</select>
		</div>
		<?php
	}
	?>
	<div class="rtt_courselistblock">
	<?php

	$cont = 0;
	$number_added = 0;

	foreach($array_results as $course_data)
	{
		if($cont>=$offset && $number_added<$per_page)
		{
			?>
			<div class="rtt_courselistsingle">
				<div class="rtt_courselistimage" style="background-image:url('<?= $course_data["image_url"] ?>')">
	    <?php
	    $post_id = $course_data["ID"];

	    if ($post_id && has_term('yes', 'last-minute-offer', $post_id)) {
	        echo '<div class="rtt_courselistfeaturedoffer">' . $cadena_featuredoffer . '</div>';
	    }

	    if ($post_id && has_term('yes', 'last-minute-offers', $post_id)) {
	        echo '<div class="rtt_courselistlastavailable">Last available</div>';
	    }
	    ?>
	</div>
				<div class="rtt_courselistbody">
					<div class="rtt_courselistbody1">
						<?php /*
						<?php if($course_data["rating"]>0 && $course_data["rating"]<=5) { ?>
							<div class="rtt_courselistrating">
								<?php for($i=1;$i<=$course_data["rating"];$i++) { ?>
									<img src="<?= plugin_dir_url( __DIR__ ).'assets/yellowstar.png'; ?>" />
								<?php } ?>
								<?php for($i=1;$i<=(5-$course_data["rating"]);$i++) { ?>
									<img src="<?= plugin_dir_url( __DIR__ ).'assets/greystar.png'; ?>" />
								<?php } ?>
							</div>
						<?php } ?>
						*/ ?>
						<div class="rtt_courselisttitle"><a href="<?= $course_data["link"] ?>"><?= $course_data["name"] ?></a></div>
						<div class="rtt_courselistlocation"><span class="dashicons dashicons-location"></span> <?= $course_data["location"] ?></div>
						<div class="rtt_courselistdescription"><?= $course_data["description"] ?></div>
					</div>
					<div class="rtt_courselistbodyseparator"></div>
					<div class="rtt_courselistbody2">
						<?php /*
						<?php if($course_data["promo"]["Free Cancelation"] == "true") { ?>
							<div class="rtt_courselistfreecancellation"><?= $cadena_freecancellation ?></div>
						<?php } ?>
						<?php if($course_data["price"]!="") { ?>
							<div class="rtt_courselistprice"><?= $cadena_desde." ".$course_data["price"]." ".$course_data["currency"]." PP" ?></div>
						<?php } ?>
						<?php if($course_data["promo"]["Last Available"] == "true") { ?>
							<div class="rtt_courselistlastavailable"><?= $cadena_lastavailable ?></div>
						<?php } ?>
						*/ ?>
						<a class="rtt_courselistcallbutton rtt_courselistbutton" href="tel:+18038669545">Call us</a>
						<a class="rtt_courselistbookbutton rtt_courselistbutton" href="<?= $course_data["link"] ?>">Book now</a>
					</div>
				</div>
			</div>
			<?php

			$number_added++;
		}
		$cont++;
	}

	if($number_added==0)
	{
		?>
		<div class="rtt_courselistnoresults"><?= __("No golf courses found","reserveteetimefw") ?></div>
		<?php
	}

	?>
	</div>
	<?php
	$num_pages = ceil(sizeof($array_results)/$per_page);
	if($add_paginator==1 && $num_pages>1)
	{
		$upper_margin = 3;
		$lower_margin = 3;

		?>
		<div class="rtt_paginator">
			<div>
				<?php
				if($pag>1) {
					?>
					<span class="rtt_paginator_special rtt_paginator_first" data-pag="1"><a href="<?= $base_url ?>?<?= http_build_query(array_merge($_GET,["pag"=>1])) ?>" data-pag="1">First</a></span>
					<span class="rtt_paginator_special rtt_paginator_prev" data-pag="<?= ($pag-1) ?>"><a href="<?= $base_url ?>?<?= http_build_query(array_merge($_GET,["pag"=>($pag-1)])) ?>" data-pag="<?= ($pag-1) ?>">Prev</a></span>
					<?php
				}
				?>
			</div>
			<div>
				<?php
				if($pag>($lower_margin+1)) {
					?>
					<span class="rtt_paginator_special rtt_paginator_ellipsis">...</span>
					<?php
				}
				?>
			</div>
			<div>
				<?php
				for($i=1;$i<=$num_pages;$i++) {
					if($i>=($pag-$lower_margin) && $i<=($pag+$upper_margin)) {
						?>
						<span class="rtt_paginator_element<?= (($i==$pag)?(" rtt_paginator_current"):("")) ?>" data-pag="<?= $i ?>"><a href="<?= $base_url ?>?<?= http_build_query(array_merge($_GET,["pag"=>$i])) ?>" data-pag="<?= $i ?>"><?= $i ?></a></span>
						<?php
					}
				}
				?>
			</div>
			<div>
				<?php
				if($pag<($num_pages-$upper_margin)) {
					?>
					<span class="rtt_paginator_special rtt_paginator_ellipsis">...</span>
					<?php
				}
				?>
			</div>
			<div>
				<?php
				if($pag<$num_pages) {
					?>
					<span class="rtt_paginator_special rtt_paginator_next" data-pag="<?= ($pag+1) ?>"><a href="<?= $base_url ?>?<?= http_build_query(array_merge($_GET,["pag"=>($pag+1)])) ?>" data-pag="<?= ($pag+1) ?>">Next</a></span>
					<span class="rtt_paginator_special rtt_paginator_last" data-pag="<?= $num_pages ?>"><a href="<?= $base_url ?>?<?= http_build_query(array_merge($_GET,["pag"=>$num_pages])) ?>" data-pag="<?= $num_pages ?>">Last</a></span>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

	return ob_get_clean();
}

function reserveteetimefw_reordergolfcourselist($x,$y)
{
	if ( $x['country'] == $y['country'] )
		return 0;
	else if ( $x['country'] > $y['country'] )
		return 1;
	else
		return -1;
}
?>