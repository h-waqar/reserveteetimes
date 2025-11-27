<?php
function gm_make_reservation(string $tenant, array $reservations)
{
  if (empty($tenant) || empty($reservations)) {
    return new WP_Error(
      'missing_params',
      'Tenant and reservations array are required.',
      ['status' => 400]
    );
  }

  $tenant = sanitize_text_field($tenant);

  // --- Encode reservations array as JSON then URL-encode ---
  $reservations_param = urlencode(wp_json_encode($reservations));

  // --- Build the full URL with query parameters ---
  $url = add_query_arg([
    'tenant'       => $tenant,
    'reservations' => $reservations_param,
  ], GOLF_MANAGER_API_URL . '/makeReservation');

  // --- Prepare headers and args for POST ---
  $args = [
    'timeout'   => 20,
    'headers'   => [
      'Authorization' => GOLF_MANAGER_AUTH,
      'Accept'        => 'application/json',
    ],
    'sslverify' => GOLF_MANAGER_SSL,
    // No body, all data is in query params
  ];

  // --- Call API ---
  $response = wp_remote_post($url, $args);

  if (is_wp_error($response)) {
    return new WP_Error('gm_external_error', 'External API returned a non-success code', [
      'status'   => 500,
      'response' => $response->get_error_message()
    ]);
  }

  $response_code = wp_remote_retrieve_response_code($response);
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if ($response_code !== 200) {
    return new WP_Error('gm_external_error', 'External API returned a non-success code', [
      'status'   => $response_code,
      'response' => $data,
    ]);
  }

  if (json_last_error() !== JSON_ERROR_NONE) {
    return new WP_Error('gm_invalid_json', 'Invalid JSON from Golf Manager API', [
      'raw_response' => $body
    ]);
  }

  return $data;
}
