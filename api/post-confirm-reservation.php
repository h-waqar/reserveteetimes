<?php
/**
 * Confirm provisional bookings directly via Golf Manager API.
 *
 * @param string $tenant
 * @param array  $ids           Array of provisional reservation IDs
 * @param string $paymentMethod Optional payment method
 *
 * @return array|WP_Error
 */
function gm_confirm_reservation(string $tenant, array $ids, ?string $paymentMethod = null)
{
  if (empty($tenant) || empty($ids)) {
    return new WP_Error(
      'missing_params',
      'Tenant and provisional IDs are required.',
      ['status' => 400]
    );
  }

  // --- Sanitize tenant
  $tenant = sanitize_text_field($tenant);

  // --- Build query params (Golf Manager expects IDs as JSON string)
  $query_args = [
    'tenant' => $tenant,
    'ids'    => wp_json_encode($ids),
  ];

  $api_url = add_query_arg($query_args, GOLF_MANAGER_API_URL . '/confirmReservation');

  // --- Optional POST body for payment method
  $body = [];
  // GolfManager API fails if payment method is "Cash on delivery".
  // We omit it in that case, hoping the API accepts it (perhaps as unpaid/on credit).
  if ($paymentMethod && $paymentMethod !== 'Cash on delivery') {
    $body['paymentMethod'] = $paymentMethod;
  }

  $args = [
    'timeout'   => 20,
    'headers'   => ['Authorization' => GOLF_MANAGER_AUTH],
    'body'      => $body,
    'sslverify' => GOLF_MANAGER_SSL,
  ];

  // --- Send request
  $response = wp_remote_post($api_url, $args);

  // --- Handle errors
  if (is_wp_error($response)) {
    return new WP_Error(
      'gm_external_error',
      'External API request failed.',
      ['details' => $response->get_error_message()]
    );
  }

  $code = wp_remote_retrieve_response_code($response);
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if ($code !== 200) {
    return new WP_Error(
      'gm_external_error',
      'External API returned a non-success code',
      ['status' => $code, 'response' => $data]
    );
  }

  if (!isset($data['success']) || $data['success'] !== true) {
    return new WP_Error(
      'gm_api_error',
      'Confirmation failed.',
      ['response' => $data]
    );
  }

  return $data;
}
