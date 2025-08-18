<?php
/*
**********************************************************************************************************
* LOGS BACKEND SECTION
**********************************************************************************************************
*/

//Processes the action to empty the log registry
add_action('admin_post_rttfwemptylogs','process_rttfwemptylogs');

function process_rttfwemptylogs() {

	global $wpdb;

	if (!current_user_can('manage_options'))
		wp_die(__("You have no permissions to visualize this page","reserveteetimefw"));
	
	check_admin_referer('cc_op_verify');
	
	$wpdb->query('TRUNCATE TABLE '.$wpdb->prefix.'rttfw_logs');
	
	wp_redirect(admin_url('admin.php?page=main-reserveteetimemenulog&m=1'));
	exit;
}

//Displays the log registry
function reserveteetimefwlogs_init()
{	
	global $wpdb;
	?>
	<div class="reserveteetimefw-adminpage wrap">
		<h1><div id="icon-edit-pages" class="icon32"></div><?= __("Logs","reserveteetimefw") ?></h1>
		<?php if(isset($_GET["m"]) && intval($_GET["m"])==1) { ?>
			<div class="updated notice"><p><?= __("The operation was successful.","reserveteetimefw") ?></p></div>
		<?php } else if(isset($_GET["m"]) && intval($_GET["m"])==2) { ?>
			<div class="error notice"><p><?= __("There was a problem performing the operation.","reserveteetimefw") ?></p></div>
		<?php } ?>
		<?php
		$perpage = 100;
	
		if(isset($_GET["pag"]) && intval($_GET["pag"])>0)
			$pag = intval($_GET["pag"]);
		else
			$pag = 1;
			
		$offset = $perpage*($pag-1);
		
		$search_logs = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."rttfw_logs` ORDER BY rttfwlog_id DESC LIMIT ".$offset.",".$perpage);
		$search_logs_total = $wpdb->get_results("SELECT * FROM `".$wpdb->prefix."rttfw_logs`");
		$total = sizeof($search_logs_total);
		
		if($search_logs)
		{
			?>
			<table class="reserveteetimefw-table wp-list-table widefat fixed striped pages">
				<thead>
					<tr>
						<th><?= __("Date","reserveteetimefw") ?></th>
						<th><?= __("Platform","reserveteetimefw") ?></th>
						<th><?= __("Details","reserveteetimefw") ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($search_logs as $row_log) { ?>
					<tr class="<?= (($row_log->rttfwlog_status==1)?("reserveteetimefw-rowsuccess"):("reserveteetimefw-rowerror")) ?>">
						<td><?= wp_date("d-m-Y H:i:s",$row_log->rttfwlog_date) ?></td>
						<td><?= $row_log->rttfwlog_platform ?></td>
						<td><?= $row_log->rttfwlog_details ?></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
			<form class="reserveteetimefw-emptylogsform reserveteetimefw-form" method="post" action="admin-post.php" onsubmit="return confirm('<?= __("Are you sure? This operation cannot be undone.","reserveteetimefw") ?>');">
				<?php wp_nonce_field( 'cc_op_verify' ); ?>
				<input type="hidden" name="action" value="rttfwemptylogs" />
				<input type="submit" value="<?= __("Empty logs","reserveteetimefw") ?>" class="button" />
			</form>
			<?php
			$totalpags = ceil($total/$perpage);
			if($totalpags>1)
			{
				$page_links = paginate_links( array(
					'base' => add_query_arg( 'pag', '%#%' ),
					'format' => '',
					'prev_text' => __( '&laquo;', 'reserveteetimefw' ),
					'next_text' => __( '&raquo;', 'reserveteetimefw' ),
					'total' => $totalpags,
					'current' => $pag
				) );

				if ( $page_links ) {
					echo '<div class="reserveteetimefw-paginator tablenav"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
				}
			}
		}
		else
		{
			?>
			<div class="error notice"><p><?= __("There are currently no log entries.","reserveteetimefw") ?></p></div>
			<?php
		}
		?>
	</div>
	<?php
}

?>