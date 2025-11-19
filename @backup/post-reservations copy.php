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
 * FINAL, DEFINITIVE VERSION: Creates a provisional booking on the Golf Manager API.
 * This function accepts a raw JSON body from the client (e.g., Postman) and then
 * constructs and sends a correctly formatted raw JSON request to the external API.
 */
// function golf_make_reservation(WP_REST_Request $request)
// {

//   // --- 1. Get and Validate Incoming JSON Data from Client ---
//   $json_params = $request->get_json_params();

//   if (empty($json_params)) {
//     return new WP_Error('invalid_json_body', 'Request body is empty or not valid JSON.', ['status' => 400]);
//   }

//   $tenant = isset($json_params['tenant']) ? sanitize_text_field($json_params['tenant']) : null;
//   $reservations_array = isset($json_params['reservations']) && is_array($json_params['reservations']) ? $json_params['reservations'] : null;

//   if (empty($tenant) || empty($reservations_array)) {
//     return new WP_Error('missing_params', 'The JSON body must contain a "tenant" string and a "reservations" array.', ['status' => 400]);
//   }

//   // --- 2. Prepare the Request for the External Golf Manager API ---

//   // CORRECTED: The API URL includes the tenant as a query parameter.
//   $api_url = add_query_arg(['tenant' => $tenant], GOLF_MANAGER_API_URL . '/makeReservation');

//   // CORRECTED: The body of the request is a JSON object containing the "reservations" key.
//   $body = json_encode(['reservations' => $reservations_array]);

//   $args = [
//     'timeout' => 20,
//     'headers' => [
//       'Authorization' => GOLF_MANAGER_AUTH,
//       'Content-Type'  => 'application/json; charset=utf-8', //E CRUCIAL: Tell the external API we are sending JSON.
//     ],
//     'sslverify' => GOLF_MANAGER_SSL,
//     'body' => $body,
//   ];

//   // --- 3. Make the HTTPS POST Request ---
//   $response = wp_remote_post($api_url, $args);

//   // --- 4. Handle the Response ---
//   if (is_wp_error($response)) {
//     return new WP_Error('wp_error', 'WordPress could not complete the request.', ['status' => 500, 'details' => $response->get_error_message()]);
//   }

//   $response_code = wp_remote_retrieve_response_code($response);
//   $response_body = wp_remote_retrieve_body($response);
//   $data = json_decode($response_body, true);

//   if ($response_code !== 200) {
//     // Pass the error from the Golf Manager API directly back to the client.
//     return new WP_Error('external_api_error', 'The Golf Manager API returned an error.', ['status' => $response_code, 'response_from_gm' => $data]);
//   }

//   if (json_last_error() !== JSON_ERROR_NONE) {
//     return new WP_Error('invalid_json_response', 'Invalid JSON received from Golf Manager API.', ['status' => 500, 'raw_response' => $response_body]);
//   }

//   if (!isset($data['reservations'])) {
//     return new WP_Error('unexpected_response_structure', 'Golf Manager API did not return the expected reservation data.', ['status' => 500, 'response' => $data]);
//   }

//   return rest_ensure_response($data);
// }

// ************************************************************************
function golf_make_reservation(WP_REST_Request $request)
{
  // Get incoming data
  $json_params = $request->get_json_params();

  if (empty($json_params)) {
    return new WP_Error('invalid_json_body', 'Request body is empty or not valid JSON.', ['status' => 400]);
  }

  $tenant = isset($json_params['tenant']) ? sanitize_text_field($json_params['tenant']) : null;
  $reservations_array = isset($json_params['reservations']) && is_array($json_params['reservations']) ? $json_params['reservations'] : null;

  if (empty($tenant) || empty($reservations_array)) {
    return new WP_Error('missing_params', 'Missing tenant or reservations.', ['status' => 400]);
  }

  // Build the URL with query parameters (like the cURL example)
  $url = add_query_arg([
    'tenant' => $tenant,
    'reservations' => wp_json_encode($reservations_array) // URL-encode the JSON
  ], GOLF_MANAGER_API_URL . '/makeReservation');

  error_log("=== GM RESERVATION REQUEST ===");
  error_log("URL: $url");

  $response = wp_remote_post($url, [
    'timeout'   => 20,
    'headers'   => [
      'Authorization' => GOLF_MANAGER_AUTH,
      'Accept'        => 'application/json',
    ],
    'sslverify' => GOLF_MANAGER_SSL,
    // NO BODY - everything is in the URL!
  ]);

  if (is_wp_error($response)) {
    error_log("GM Error: " . $response->get_error_message());
    return new WP_Error('api_error', $response->get_error_message(), ['status' => 500]);
  }

  $response_code = wp_remote_retrieve_response_code($response);
  $response_body = wp_remote_retrieve_body($response);
  $data = json_decode($response_body, true);

  error_log("GM Response Code: $response_code");
  error_log("GM Response: " . print_r($data, true));

  if ($response_code !== 200) {
    return new WP_Error('external_api_error', 'Golf Manager API error', ['status' => $response_code, 'response' => $data]);
  }

  return rest_ensure_response($data);
}
