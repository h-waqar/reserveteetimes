<?php
/*
**********************************************************************************************************
* SHORTCODE FOR HOMEPAGE COURSES SEACH FORM
**********************************************************************************************************
*/

//add_shortcode('reserveteetimefw_searchcoursesform','reserveteetimefw_load_searchcoursesform');
function reserveteetimefw_load_searchcoursesform($atts) {
	
	global $wpdb;
	
	if(pll_current_language() == "es")
	{
		$url_results = "/es/resultados/";
		$cadena_name = "NOMBRE";
		$cadena_country = "PAÍS";
		$cadena_city = "CIUDAD";
		$cadena_search = "BUSCAR";
	}
	else
	{
		$url_results = "/results/";
		$cadena_name = "NAME";
		$cadena_country = "COUNTRY";
		$cadena_city = "CITY";
		$cadena_search = "SEARCH";
	}
	
	$countries = get_terms(array('taxonomy' => 'course-destination','hide_empty' => false));
	$cities = get_terms(array('taxonomy' => 'course-city','hide_empty' => false));
	
	$array_countriescities = array();
	$search_countriescities = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix.'rttfw_countriescities');
	foreach($search_countriescities as $row_countriescities)
	{
		$array_countriescities[$row_countriescities->rttfwadd_cityid] = $row_countriescities->rttfwadd_countryid;
	}
	
	ob_start();

	?>
	<form class="rttsearchcoursesform" action="<?= $url_results ?>">
		<div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
			<input type="text" name="rttsearchname" class="rttsearchcoursesform-textfield" placeholder="<?= $cadena_name ?>">
		</div>
		<div class="rttsearchcoursesform-col50 rttsearchcoursesform-field">
			<select name="rttsearchcountry" class="rttsearchcoursesform-dropdown">
				<option value=""><?= $cadena_country ?></option>
				<?php foreach($countries as $term_country) { ?>
					<option value="<?= $term_country->slug ?>" data-countryid="<?= $term_country->term_id ?>"><?= $term_country->name ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="rttsearchcoursesform-col50 rttsearchcoursesform-field">
			<select name="rttsearchcity" class="rttsearchcoursesform-dropdown">
				<option value=""><?= $cadena_city ?></option>
				<?php foreach($cities as $term_city) { ?>
					<option value="<?= $term_city->slug ?>" <?= ((array_key_exists($term_city->term_id,$array_countriescities))?("data-country='".$array_countriescities[$term_city->term_id]."'"):("")) ?>><?= $term_city->name ?></option>
				<?php } ?>
			</select>
		</div>
		<div class="rttsearchcoursesform-col100 rttsearchcoursesform-field">
			<button type="submit" class="rttsearchcoursesform-button"><?= $cadena_search ?></button>
		</div>
	</form>
	<?php

	return ob_get_clean();
}
?>