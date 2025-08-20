<?php
// api/cancel-reservation.php

add_action('rest_api_init', function () {
    register_rest_route('golf/v1', '/reservations/cancel', [
        'methods'  => 'POST',
        'callback' => 'golf_cancel_reservation',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * FINAL, DEFINITIVE VERSION: Cancels a booking on the Golf Manager API.
 * Accepts a raw JSON body and sends a correctly formatted request to the external API.
 */
function golf_cancel_reservation(WP_REST_Request $request)
{
    // --- 1. Get and Validate Incoming JSON Data from Client ---
    $json_params = $request->get_json_params();
    if (empty($json_params)) {
        return new WP_Error('invalid_json_body', 'Request body is empty or not valid JSON.', ['status' => 400]);
    }

    $tenant = isset($json_params['tenant']) ? sanitize_text_field($json_params['tenant']) : null;
    $ids = isset($json_params['ids']) && is_array($json_params['ids']) ? $json_params['ids'] : null;

    if (empty($tenant) || empty($ids)) {
        return new WP_Error('missing_params', 'The JSON body must contain a "tenant" string and an "ids" array.', ['status' => 400]);
    }

    // --- 2. Prepare the Request for the External Golf Manager API ---

    // The API URL includes 'tenant' and 'ids' as query parameters.
    // The 'ids' parameter must be a JSON-encoded string of the array.
    $api_url = add_query_arg([
        'tenant' => $tenant,
        'ids'    => json_encode($ids)
    ], 'https://eu.golfmanager.com/main/apimt/cancelReservation');

    $args = [
        'timeout' => 20,
        'headers' => [
            'Authorization' => GOLF_MANAGER_AUTH
        ],
        'body' => [], // The POST body is empty for this request
    ];

    // --- 3. Make the HTTPS POST Request ---
    $response = wp_remote_post($api_url, $args);

    // --- 4. Handle the Response ---
    if (is_wp_error($response)) {
        return new WP_Error('wp_error', 'WordPress could not complete the request.', ['status' => 500, 'details' => $response->get_error_message()]);
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    if ($response_code !== 200) {
        return new WP_Error('external_api_error', 'The Golf Manager API returned an error.', ['status' => $response_code, 'response_from_gm' => $data]);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json_response', 'Invalid JSON received from Golf Manager API.', ['status' => 500, 'raw_response' => $response_body]);
    }

    if (!isset($data['success']) || $data['success'] !== true) {
        return new WP_Error('api_cancellation_error', 'Golf Manager API did not return a success response.', ['status' => 500, 'response' => $data]);
    }

    return rest_ensure_response($data);
}
