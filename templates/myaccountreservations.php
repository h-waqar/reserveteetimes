<?php
/*
**********************************************************************************************************
* TEMPLATE FOR MY RESERVATIONS SECTION IN CUSTOMER'S MY ACCOUNT SECTION
**********************************************************************************************************
*/

// Do not allow directly accessing this file.
if (!defined('ABSPATH')) {
	exit('Direct script access denied.');
}

global $reserveteetimefw_classes,$reserveteetimefw_cancelresult;

$array_reservations = array();

$user_id = get_current_user_id();

$args = array(
    'customer_id' => $user_id,
    'limit' => -1,
);
$orders = wc_get_orders($args);

foreach($orders as $order)
{
	$order_id = $order->get_id();
	$order_items = $order->get_items();
	foreach ($order_items as $item) 
	{
		$array_meta_data = $item->get_meta_data();
		$array_meta = array();
		foreach($array_meta_data as $item_data)
		{
			$meta_data = $item_data->get_data();
			$array_meta[$meta_data["key"]] = $meta_data["value"];
		}
		
		if(isset($array_meta["rtt_platform"]) && in_array($array_meta["rtt_platform"],$reserveteetimefw_classes))
		{
			$classname = "rtt".$array_meta["rtt_platform"];
			$platform = new $classname();
			
			if(isset($array_meta["rtt_result"]) && $platform->is_valid_result($array_meta["rtt_result"]))
			{
				if(isset($array_meta["rtt_canceldateunix"]))
					$timestamp_cancellable = $array_meta["rtt_canceldateunix"];
				else
					$timestamp_cancellable = strtotime($array_meta["rtt_datetime"]);
				
				$timestamp_reservation = strtotime($array_meta["rtt_datetime"]);
				
				$current_timestamp = current_time("timestamp",true);
				
				$array_current_reservation = array(
				"course" => $array_meta["rtt_facility"],
				"datetime" => $array_meta["rtt_datetime"],
				"timestamp" => $timestamp_reservation,
				"players" => $array_meta["rtt_players"],
				"holes" => $array_meta["rtt_holes"],
				"canceldate" => $array_meta["rtt_canceldate"],
				"order_id" => $order_id,
				"reservationid" => $array_meta["rtt_result"]
				);
				
				if(!isset($array_meta["rtt_cancelled"]))
				{
					if($current_timestamp<$timestamp_cancellable)
						$array_current_reservation["is_cancellable"] = true;
					else
						$array_current_reservation["is_cancellable"] = false;
					
					if($current_timestamp<$timestamp_reservation)
						$array_current_reservation["status"] = __("Booked","reserveteetimefw");
					else
						$array_current_reservation["status"] = __("Played","reserveteetimefw");
				}
				else
				{
					$array_current_reservation["status"] = __("Cancelled","reserveteetimefw");
					$array_current_reservation["is_cancellable"] = false;
				}
				
				$array_reservations[] = $array_current_reservation;
			}
		}
	}
}

if($reserveteetimefw_cancelresult)
{
	?>
	<div class="rtt_golfcoursemessagecontainer">
		<div id="<?= $reserveteetimefw_cancelresult["id_message"] ?>"><?= $reserveteetimefw_cancelresult["message"] ?></div>
	</div>
	<?php
}

if(!empty($array_reservations))
{
	usort($array_reservations, fn($a,$b) => $a["timestamp"] <=> $b["timestamp"]);
	$array_reservations = array_reverse($array_reservations);
	?>
	<table class="woocommerce-reservations-table woocommerce-MyAccount-reservations shop_table shop_table_responsive my_account_reservations account-reservations-table">
		<thead>
			<tr>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-course"><?= __("Course","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-datetime"><?= __("Date & Time","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-players"><?= __("Players","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-holes"><?= __("Holes","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-cancellable"><?= __("Cancellable until","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-status"><?= __("Status","reserveteetimefw") ?></th>
				<th class="woocommerce-orders-table__header woocommerce-orders-table__header-reservation-actions"><?= __("Actions","reserveteetimefw") ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach($array_reservations as $row_reservation) { ?>
				<tr>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-course"><?= $row_reservation["course"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-datetime"><?= $row_reservation["datetime"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-players"><?= $row_reservation["players"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-holes"><?= $row_reservation["holes"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-cancellable"><?= $row_reservation["canceldate"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status"><?= $row_reservation["status"] ?></td>
					<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
						<?php if($row_reservation["is_cancellable"]) { ?>
							<form class="rtt-cancelform" method="post" onsubmit="return confirm('<?= __("Are you sure?","reserveteetimefw") ?>');">
								<input type="hidden" name="reservationorder" value="<?= $row_reservation["order_id"] ?>" />
								<input type="hidden" name="reservationid" value="<?= $row_reservation["reservationid"] ?>" />
								<input type="hidden" name="action" value="rtt-cancelteetime" />
								<input type="submit" value="<?= __("Cancel","reserveteetimefw") ?>" />
							</form>
						<?php } ?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php
}
else
{
	?>
	<p><?= __("You have currently not made any reservations.","reserveteetimefw") ?></p>
	<?php
}
?>