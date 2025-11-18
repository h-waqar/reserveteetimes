<?php
// api/post-confirm-reservation.php

add_action('rest_api_init', function () {
  register_rest_route('golf/v1', '/reservations/confirm', [
    'methods'  => 'POST',
    'callback' => 'golf_confirm_reservation',
    'permission_callback' => '__return_true',
  ]);
});

function golf_confirm_reservation(WP_REST_Request $request)
{
  // --- 1. Get and Validate Incoming JSON Data ---
  $json_params = $request->get_json_params();
  if (empty($json_params)) {
    return new WP_Error('invalid_json_body', 'Request body is empty or not valid JSON.', ['status' => 400]);
  }

  $tenant = $json_params['tenant'] ?? null;
  $ids = $json_params['ids'] ?? null;
  $paymentMethod = $json_params['paymentMethod'] ?? null;

  if (empty($tenant) || empty($ids) || !is_array($ids)) {
    return new WP_Error('missing_params', 'The JSON body must contain "tenant" and an "ids" array.', ['status' => 400]);
  }

  // --- 2. Prepare the Request for the External API ---

  // CORRECTED: The API URL includes 'tenant' and 'ids' as query parameters.
  $api_url = add_query_arg([
    'tenant' => $tenant,
    'ids'    => json_encode($ids) // The API expects a JSON-encoded string for the array
  ], GOLF_MANAGER_API_URL . '/confirmReservation');

  // The optional 'paymentMethod' is sent in the POST body.
  $body = [];
  if (!empty($paymentMethod)) {
    $body['paymentMethod'] = $paymentMethod;
  }

  $args = [
    'timeout' => 20,
    'headers' => ['Authorization' => GOLF_MANAGER_AUTH],
    'body' => $body,
    'sslverify' => GOLF_MANAGER_SSL,
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

  if (!isset($data['success']) || $data['success'] !== true) {
    return new WP_Error('api_confirmation_error', 'Golf Manager API did not return a success response.', ['status' => 500, 'response' => $data]);
  }

  return rest_ensure_response($data);
}
