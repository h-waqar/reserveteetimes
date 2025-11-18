<?php
// api/get-tenants.php

add_action('rest_api_init', function () {
  register_rest_route('golf/v1', '/tenants', [
    'methods'  => 'GET',
    'callback' => 'golf_get_tenants',
    'permission_callback' => '__return_true', // change to auth in prod
  ]);
});

function golf_get_tenants(WP_REST_Request $request)
{
  $url = GOLF_MANAGER_API_URL . '/tenants';

  $response = wp_remote_get($url, [
    'timeout' => 15,
    'headers' => [
      'Accept'        => 'application/json',
      'Authorization' => GOLF_MANAGER_AUTH,
    ],
    'sslverify' => GOLF_MANAGER_SSL,
  ]);

  if (is_wp_error($response)) {
    return new WP_Error('api_error', 'Unable to fetch tenants.', ['url' => $url,], ['status' => 500]);
  }

  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    return new WP_Error('invalid_json', 'Invalid JSON received from API.', ['status' => 500]);
  }

  return rest_ensure_response($data);
}
