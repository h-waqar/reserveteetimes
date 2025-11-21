<?php

/**
 * Centralized Golf Manager HTTP client.
 *
 * @param string       $path     Path after base API URL (e.g. '/searchAvailability' or 'makeReservation')
 * @param string       $method   HTTP method (GET, POST, PUT, DELETE)
 * @param array|null   $params   Query params for GET or merged into URL for others
 * @param mixed|null   $body     For POST/PUT: array (will be json_encoded) or raw string
 * @param array        $opts     Optional: ['timeout'=>int, 'retries'=>int, 'log'=>bool]
 *
 * @return array|WP_Error Decoded JSON array on success, WP_Error on failure
 */
function gm_api_call($path, $method = 'GET', $params = null, $body = null, $opts = [])
{
  $method = strtoupper($method);
  $timeout = isset($opts['timeout']) ? intval($opts['timeout']) : 20;
  $retries = isset($opts['retries']) ? intval($opts['retries']) : 1;
  $do_log  = !empty($opts['log']);

  // Build base url
  $base = rtrim(GOLF_MANAGER_API_URL, '/');
  $path = ltrim($path, '/');
  $url = "{$base}/{$path}";

  // Attach query params for GET or if params provided explicitly
  if (!empty($params) && $method === 'GET') {
    $url = add_query_arg($params, $url);
  }

  // Prepare request args
  $headers = [
    'Accept' => 'application/json',
  ];
  if (defined('GOLF_MANAGER_AUTH') && !empty(GOLF_MANAGER_AUTH)) {
    $headers['Authorization'] = GOLF_MANAGER_AUTH;
  }

  $args = [
    'method'    => $method,
    'timeout'   => $timeout,
    'headers'   => $headers,
    'sslverify' => defined('GOLF_MANAGER_SSL') ? GOLF_MANAGER_SSL : true,
  ];

  // Body handling for POST/PUT/PATCH/DELETE with payload
  if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    if (is_array($body)) {
      $args['body'] = wp_json_encode($body);
      $args['headers']['Content-Type'] = 'application/json';
    } elseif (!is_null($body)) {
      $args['body'] = $body;
      // allow caller to set content-type via $opts if needed
      if (!empty($opts['content_type'])) {
        $args['headers']['Content-Type'] = $opts['content_type'];
      } else {
        $args['headers']['Content-Type'] = 'application/json';
      }
    } elseif (!empty($params)) {
      // fallback: send params as query string when body missing
      $url = add_query_arg($params, $url);
    }
  }

  // Simple retry loop with exponential backoff
  $attempt = 0;
  do {
    $attempt++;
    if ($do_log) {
      error_log("GM API CALL attempt {$attempt} -> {$method} {$url}");
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
      $err_msg = $response->get_error_message();
      if ($do_log) error_log("GM API WP_ERROR: {$err_msg}");
      // Retryable network errors can be retried
      if ($attempt < $retries) {
        sleep(pow(2, $attempt)); // backoff
        continue;
      }

      return new WP_Error('gm_wp_error', 'HTTP request failed', [
        'status' => 500,
        'error'  => $err_msg,
      ]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($do_log) {
      error_log("GM API RESPONSE [{$code}]: " . substr($body, 0, 2000));
    }

    // Non-2xx -> return WP_Error with parsed body if possible
    if ($code < 200 || $code >= 300) {
      $decoded = json_decode($body, true);
      $details = is_array($decoded) ? $decoded : ['raw' => $body];
      return new WP_Error('gm_external_error', 'External API returned a non-success code', [
        'status' => $code,
        'response' => $details,
      ]);
    }

    // Try to decode JSON
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return new WP_Error('gm_invalid_json', 'Invalid JSON from Golf Manager', [
        'status' => 500,
        'raw_response' => $body,
      ]);
    }

    return $decoded;
  } while ($attempt < $retries);

  // fallback (should not reach)
  return new WP_Error('gm_unknown', 'Unknown error contacting Golf Manager');
}
