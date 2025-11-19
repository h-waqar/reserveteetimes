<?php
// api\post-reservations.php

add_action('rest_api_init', function () {
  register_rest_route('golf/v1', '/reservations', [
    'methods'  => 'POST',
    'callback' => 'golf_make_reservation',
    'permission_callback' => '__return_true',
  ]);
});

/**
 * Internal API: Accepts JSON body, converts to URL params for external Golf Manager API
 */
function golf_make_reservation(WP_REST_Request $request)
{
  // --- 1. Get and Validate Incoming JSON Data from Client ---
  $json_params = $request->get_json_params();

  if (empty($json_params)) {
    return new WP_Error('invalid_json_body', 'Request body is empty or not valid JSON.', ['status' => 400]);
  }

  $tenant = isset($json_params['tenant']) ? sanitize_text_field($json_params['tenant']) : null;
  $reservations_array = isset($json_params['reservations']) && is_array($json_params['reservations']) ? $json_params['reservations'] : null;

  if (empty($tenant) || empty($reservations_array)) {
    return new WP_Error('missing_params', 'The JSON body must contain a "tenant" string and a "reservations" array.', ['status' => 400]);
  }

  // --- 2. Build URL with Query Parameters (matching the cURL example) ---
  $api_url = add_query_arg([
    'tenant' => $tenant,
    'reservations' => wp_json_encode($reservations_array) // JSON-encode and URL-encode
  ], GOLF_MANAGER_API_URL . '/makeReservation');

  // --- 3. Prepare the Request Args ---
  $args = [
    'timeout' => 20,
    'headers' => [
      'Authorization' => GOLF_MANAGER_AUTH,
      'Accept'        => 'application/json',
    ],
    'sslverify' => GOLF_MANAGER_SSL,
    // NO BODY - data is in URL query parameters
  ];

  // --- DEBUG LOGS ---
  error_log("=== GM RESERVATION REQUEST START ===");
  error_log("URL: $api_url");
  error_log("HEADERS: " . print_r($args['headers'], true));
  error_log("=== SENDING REQUEST ===");

  // --- 4. Make the HTTPS POST Request ---
  $response = wp_remote_post($api_url, $args);

  // --- 5. Handle the Response ---
  if (is_wp_error($response)) {
    error_log("=== GM REQUEST ERROR ===");
    error_log($response->get_error_message());
    return new WP_Error('wp_error', 'WordPress could not complete the request.', [
      'status' => 500,
      'details' => $response->get_error_message()
    ]);
  }

  $response_code = wp_remote_retrieve_response_code($response);
  $response_body = wp_remote_retrieve_body($response);

  error_log("=== GM RAW RESPONSE ===");
  error_log("Response Code: $response_code");
  error_log("Response Body: $response_body");

  $data = json_decode($response_body, true);

  error_log("=== GM PARSED JSON ===");
  error_log(print_r($data, true));
  error_log("=== GM RESERVATION REQUEST END ===");

  if ($response_code !== 200) {
    // Pass the error from the Golf Manager API directly back to the client
    return new WP_Error('external_api_error', 'The Golf Manager API returned an error.', [
      'status' => $response_code,
      'response_from_gm' => $data
    ]);
  }

  if (json_last_error() !== JSON_ERROR_NONE) {
    return new WP_Error('invalid_json_response', 'Invalid JSON received from Golf Manager API.', [
      'status' => 500,
      'raw_response' => $response_body
    ]);
  }

  if (!isset($data['reservations'])) {
    return new WP_Error('unexpected_response_structure', 'Golf Manager API did not return the expected reservation data.', [
      'status' => 500,
      'response' => $data
    ]);
  }

  return rest_ensure_response($data);
}
