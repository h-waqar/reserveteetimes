<?php
// add_action('rest_api_init', function () {
//   register_rest_route('golf/v1', ' ', [
//     'methods'  => 'GET',
//     'callback' => 'golf_get_availability',
//     'permission_callback' => '__return_true',
//   ]);
// });

// function golf_get_availability(WP_REST_Request $request)
// {
//   $tenant = sanitize_text_field($request->get_param('tenant'));
//   $start  = sanitize_text_field($request->get_param('start'));
//   $end    = sanitize_text_field($request->get_param('end'));
//   $idResource = sanitize_text_field($request->get_param('idResource'));
//   $idResourceType = sanitize_text_field($request->get_param('idResourceType'));

//   if (empty($start) || empty($end) || empty($tenant)) {
//     return new WP_Error('missing_params', 'Tenant, start and end parameters are required.', ['status' => 400]);
//   }

//   $url = add_query_arg([
//     'tenant' => $tenant,
//     'start'  => $start,
//     'end'    => $end,
//     'idResource' => $idResource,
//     'idResourceType' => $idResourceType,
//   ], GOLF_MANAGER_API_URL . '/searchAvailability');


//   $response = wp_remote_get($url, [
//     'timeout' => 15,
//     'headers' => [
//       'Accept' => 'application/json',
//       'Authorization' => GOLF_MANAGER_AUTH
//     ],
//     'sslverify' => GOLF_MANAGER_SSL,
//   ]);

//   if (is_wp_error($response)) {
//     return new WP_Error('api_error', 'Unable to fetch availability.', ['status' => 500]);
//   }

//   $body = wp_remote_retrieve_body($response);
//   $data = json_decode($body, true);

//   if (json_last_error() !== JSON_ERROR_NONE) {
//     return new WP_Error('invalid_json', 'Invalid JSON received from API.', ['status' => 500]);
//   }

//   return rest_ensure_response($data);
// }



// ///////////////////////////////////////////////
// ///////////////////////////////////////////////
// ///////////////////////////////////////////////

/**
 * Fetch availability via gm_api_call wrapper.
 *
 * @param string      $tenant
 * @param string      $start
 * @param string      $end
 * @param int|null    $idResource
 * @param int|null    $idResourceType
 * @param int|null    $players
 * @param int|null    $holes
 *
 * @return array|WP_Error
 */
function gm_fetch_availability($tenant, $start, $end, $idResource = null, $idResourceType = null, $players = null, $holes = null)
{
  // Basic validation & sanitize
  if (empty($tenant) || empty($start) || empty($end)) {
    return new WP_Error('missing_params', 'Tenant, start, and end are required.', ['status' => 400]);
  }

  $params = [
    'tenant' => sanitize_text_field($tenant),
    'start'  => sanitize_text_field($start),
    'end'    => sanitize_text_field($end),
  ];

  if (!is_null($idResource) && $idResource !== '') {
    $params['idResource'] = intval($idResource);
  }
  if (!is_null($idResourceType) && $idResourceType !== '') {
    $params['idResourceType'] = intval($idResourceType);
  }
  if (!is_null($players) && $players !== '') {
    $params['players'] = intval($players);
  }
  if (!is_null($holes) && $holes !== '') {
    $params['holes'] = intval($holes);
  }

  // Call centralized client. allow 2 retries for flakiness.
  $result = gm_api_call('/searchAvailability', 'GET', $params, null, ['timeout' => 20, 'retries' => 2, 'log' => false]);

  return $result;
}
