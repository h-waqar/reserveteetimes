<?php

/**
 * RTT_Admin_Order_Meta_Box Class
 *
 * Handles the creation and rendering of a custom meta box on the WooCommerce
 * "Edit Order" screen to display detailed tee time booking information.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class RTT_Admin_Order_Meta_Box
{
    public function __construct()
    {
        // Add meta box to both HPOS and traditional order screens.
        add_action('add_meta_boxes_woocommerce_page_wc-orders', [$this, 'maybe_add_meta_box']);
        add_action('add_meta_boxes_shop_order', [$this, 'maybe_add_meta_box']);
    }

    public function maybe_add_meta_box()
    {
        $order = $this->get_order_object();
        if (!$order) return;

        $provisional_response = $order->get_meta('_gm_provisional_response');
        $has_item_booking = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('rtt_platform')) {
                $has_item_booking = true;
                break;
            }
        }

        if (!empty($provisional_response) || $has_item_booking) {
            $screen = get_current_screen();
            add_meta_box(
                'rtt_booking_details_meta_box',
                __('Tee Time Booking Details', 'reserveteetimefw'),
                [$this, 'render_meta_box_content'],
                $screen->id,
                'normal', // CHANGED: Positioned in the main column
                'high'
            );
        }
    }

    public function render_meta_box_content()
    {
        $order = $this->get_order_object();
        if (!$order) {
            echo '<p>' . __('Could not load order data.', 'reserveteetimefw') . '</p>';
            return;
        }

        $provisional_response = $order->get_meta('_gm_provisional_response');

        // --- 1. Display Provisional Booking Details ---
        if ($provisional_response && is_array($provisional_response)) {
            echo '<h3>' . __('Provisional Booking Details (Golf Manager)', 'reserveteetimefw') . '</h3>';

            // Display Cart/Sale Info
            if (isset($provisional_response['cart'])) {
                $this->render_details_table($provisional_response['cart'], 'Sale Info');
            }

            // Display each reservation within the provisional booking
            if (isset($provisional_response['reservations']) && is_array($provisional_response['reservations'])) {
                foreach ($provisional_response['reservations'] as $index => $reservation) {
                    $this->render_details_table($reservation, 'Reservation #' . ($index + 1));
                }
            }
        }

        // --- 2. Display Final Confirmation Details ---
        $found_booking_item = false;
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('rtt_platform')) {
                $found_booking_item = true;

                echo '<hr><h3>' . __('Final Confirmation Summary', 'reserveteetimefw') . '</h3>';

                $final_details = [
                    'Platform' => $item->get_meta('rtt_platform'),
                    'Booking ID' => $item->get_meta('rtt_result'),
                ];

                // Intelligently handle the confirmation response
                $confirmation_response = $item->get_meta('rtt_confirmation_response');
                if (isset($confirmation_response['success']) && $confirmation_response['success'] == true) {
                    $final_details['Confirmation Status'] = 'Success';
                } else if ($confirmation_response) {
                    // If the response exists but isn't a success, show the raw data for debugging.
                    $final_details['Confirmation Status'] = 'Failed';
                    $final_details['Confirmation Response'] = $confirmation_response;
                } else {
                    $final_details['Confirmation Status'] = 'Pending';
                }

                $this->render_details_table($final_details, 'Booking Status');
                break;
            }
        }

        if (!$provisional_response && !$found_booking_item) {
            echo '<p>' . __('This order does not contain tee time booking data.', 'reserveteetimefw') . '</p>';
        }
    }

    /**
     * Helper function to render an array of data into a clean HTML table.
     *
     * @param array $data The data to display.
     * @param string $title The title for the table section.
     */
    private function render_details_table($data, $title = '')
    {
        if (empty($data) || !is_array($data)) return;

        echo '<div style="margin-bottom: 15px;">';
        if ($title) {
            echo '<h4 style="margin-bottom: 5px;">' . esc_html($title) . '</h4>';
        }
        echo '<table class="widefat" style="border: 1px solid #e5e5e5;"><tbody>';

        foreach ($data as $key => $value) {
            // Prettify the key for display
            $label = ucwords(str_replace('_', ' ', $key));

            // Format the value for display
            if (is_array($value) || is_object($value)) {
                $display_value = '<pre style="margin:0; font-size:11px;">' . esc_html(print_r($value, true)) . '</pre>';
            } else {
                $display_value = esc_html($value);
            }

            echo '<tr>';
            echo '<th style="width: 30%; background: #f9f9f9;">' . esc_html($label) . '</th>';
            echo '<td>' . $display_value . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    private function get_order_object()
    {
        global $post;
        $order_id = 0;
        if (isset($_GET['id'])) {
            $order_id = intval($_GET['id']);
        } elseif ($post && $post->post_type === 'shop_order') {
            $order_id = $post->ID;
        }
        return $order_id ? wc_get_order($order_id) : false;
    }
}
