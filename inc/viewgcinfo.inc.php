<?php
/*
**********************************************************************************************************
* UNUSED: Leftover from when the plugin used its own course custom post type
**********************************************************************************************************
*/

function reserveteetimemenuviewgcinfo_init()
{	
	$post_id = intval($_GET["id"]);
	if(get_post_type($post_id) == "rttcourse")
	{
		?>
		<div class="reserveteetimefw-adminpage wrap">
			<h1><div id="icon-edit-pages" class="icon32"></div><?= get_the_title($post_id) ?></h1>
			<div class="reserveteetimefw-viewcginfopage">
				<div class="reserveteetimefw-cginfoblock">
					<div class="reserveteetimefw-cginfotitle"><?= __("Platform","reserveteetimefw") ?>:</div>
					<div class="reserveteetimefw-cginfovalue"><?= get_post_meta($post_id,'rttgc_platform',true); ?></div>
					<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_platform</div>
				</div>
				<div class="reserveteetimefw-cginfoblock">
					<div class="reserveteetimefw-cginfotitle"><?= __("Platform ID","reserveteetimefw") ?>:</div>
					<div class="reserveteetimefw-cginfovalue"><?= get_post_meta($post_id,'rttgc_platformid',true); ?></div>
					<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_platformid</div>
				</div>
				<div class="reserveteetimefw-cginfoblock">
					<div class="reserveteetimefw-cginfotitle"><?= __("Mode","reserveteetimefw") ?>:</div>
					<div class="reserveteetimefw-cginfovalue"><?= get_post_meta($post_id,'rttgc_mode',true); ?></div>
					<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_mode</div>
				</div>
				<?php
				$name = get_the_title($post_id);
				if($name && $name!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Name","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $name ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Post title","reserveteetimefw") ?></div>
					</div>
					<?php
				}
				$description = get_the_content(null,false,$post_id);
				if($description && $description!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Description","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $description ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Post content","reserveteetimefw") ?></div>
					</div>
					<?php
				}
				$latitude = get_post_meta($post_id,'rttgc_latitude',true);
				if($latitude!==false && $latitude!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Latitude","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $latitude ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_latitude</div>
					</div>
					<?php
				}
				$longitude = get_post_meta($post_id,'rttgc_longitude',true);
				if($longitude!==false && $longitude!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Longitude","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $longitude ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_longitude</div>
					</div>
					<?php
				}
				$terms_country = get_the_terms($post_id,'rttcountry');
				if(is_array($terms_country) && !empty($terms_country))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Country","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $terms_country[0]->name ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Taxonomy","reserveteetimefw") ?>: rttcountry</div>
					</div>
					<?php
				}
				$terms_state = get_the_terms($post_id,'rttstate');
				if(is_array($terms_state) && !empty($terms_state))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("State","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $terms_state[0]->name ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Taxonomy","reserveteetimefw") ?>: rttstate</div>
					</div>
					<?php
				}
				$terms_region = get_the_terms($post_id,'rttregion');
				if(is_array($terms_region) && !empty($terms_region))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Region","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $terms_region[0]->name ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Taxonomy","reserveteetimefw") ?>: rttregion</div>
					</div>
					<?php
				}
				$terms_city = get_the_terms($post_id,'rttcity');
				if(is_array($terms_city) && !empty($terms_city))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("City","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $terms_city[0]->name ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Taxonomy","reserveteetimefw") ?>: rttcity</div>
					</div>
					<?php
				}
				$address = get_post_meta($post_id,'rttgc_address',true);
				if($address!==false && $address!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Address","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $address ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_address</div>
					</div>
					<?php
				}
				$zipcode = get_post_meta($post_id,'rttgc_zipcode',true);
				if($zipcode!==false && $zipcode!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Zipcode","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $zipcode ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_zipcode</div>
					</div>
					<?php
				}
				$phonenumber = get_post_meta($post_id,'rttgc_phonenumber',true);
				if($phonenumber!==false && $phonenumber!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Phone number","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $phonenumber ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_phonenumber</div>
					</div>
					<?php
				}
				$email = get_post_meta($post_id,'rttgc_email',true);
				if($email!==false && $email!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Email","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $email ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_email</div>
					</div>
					<?php
				}
				$website = get_post_meta($post_id,'rttgc_website',true);
				if($website!==false && $website!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Website","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $website ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_website</div>
					</div>
					<?php
				}
				$contactname = get_post_meta($post_id,'rttgc_reservationcontactname',true);
				if($contactname!==false && $contactname!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Reservations' contact name","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $contactname ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_reservationcontactname</div>
					</div>
					<?php
				}
				$contactemail = get_post_meta($post_id,'rttgc_reservationcontactemail',true);
				if($contactemail!==false && $contactemail!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Reservations' contact email","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $contactemail ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_reservationcontactemail</div>
					</div>
					<?php
				}
				$contactphone = get_post_meta($post_id,'rttgc_reservationcontactphonenumber',true);
				if($contactphone!==false && $contactphone!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Reservations' contact phone number","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $contactphone ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_reservationcontactphonenumber</div>
					</div>
					<?php
				}
				$belongstoiagto = get_post_meta($post_id,'rttgc_iagto',true);
				if($belongstoiagto!==false && $belongstoiagto!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Belongs to IAGTO","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= (($belongstoiagto==1)?(__("Yes","reserveteetimefw")):((__("No","reserveteetimefw")))) ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_iagto</div>
					</div>
					<?php
				}
				$foundedin = get_post_meta($post_id,'rttgc_foundedin',true);
				if($foundedin!==false && $foundedin!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Founded in","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $foundedin ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_foundedin</div>
					</div>
					<?php
				}
				$architect = get_post_meta($post_id,'rttgc_architect',true);
				if($architect!==false && $architect!="")
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Architect","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= $architect ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_architect</div>
					</div>
					<?php
				}
				$services = get_post_meta($post_id,'rttgc_services');
				if(is_array($services) && !empty($services))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Services","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><?= implode("<br/>",$services) ?></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_services</div>
					</div>
					<?php
				}
				if(has_post_thumbnail($post_id))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Logo","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue"><a href="<?= admin_url("post.php?post=".get_post_thumbnail_id($post_id)."&action=edit") ?>" target="_blank"><img src="<?= get_the_post_thumbnail_url($post_id,"thumbnail") ?>" /></a></div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Post thumbnail","reserveteetimefw") ?></div>
					</div>
					<?php
				}
				$imggallery = get_post_meta($post_id,'rttgc_imagegallery');
				if(is_array($imggallery) && !empty($imggallery))
				{
					?>
					<div class="reserveteetimefw-cginfoblock">
						<div class="reserveteetimefw-cginfotitle"><?= __("Image gallery","reserveteetimefw") ?>:</div>
						<div class="reserveteetimefw-cginfovalue">
							<?php foreach($imggallery as $attachment_id)
							{
								$image_url = wp_get_attachment_image_src($attachment_id);
								?>
								<a href="<?= admin_url("post.php?post=".$attachment_id."&action=edit") ?>" target="_blank"><img src="<?= $image_url[0] ?>" /></a>
								<?php 
							}
							?>
						</div>
						<div class="reserveteetimefw-cginfohelp"><?= __("Meta field","reserveteetimefw") ?>: rttgc_imagegallery</div>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
		
	}
	else
	{
		?>
		<div class="error notice"><p><?= __("Invalid golf course.","reserveteetimefw") ?></p></div>
		<?php
	}
}

?>