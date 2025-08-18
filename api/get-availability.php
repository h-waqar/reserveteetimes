<?php
add_action('rest_api_init', function () {
    register_rest_route('golf/v1', '/availability', [
        'methods'  => 'GET',
        'callback' => 'golf_get_availability',
        'permission_callback' => '__return_true', // change to auth in prod
    ]);
});

function golf_get_availability(WP_REST_Request $request)
{
    $tenant = sanitize_text_field($request->get_param('tenant'));
    $start  = sanitize_text_field($request->get_param('start'));
    $end    = sanitize_text_field($request->get_param('end'));

    // Validate required params
    if (empty($start) || empty($end) || empty($tenant)) {
        return new WP_Error('missing_params', 'Tenant, start and end parameters are required.', ['status' => 400]);
    }

    $url = add_query_arg([
        'tenant' => $tenant,
        'start'  => $start,
        'end'    => $end,
        'idResourceType' => 2,
    ], 'https://eu.golfmanager.com/main/apimt/searchAvailability');


    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Accept' => 'application/json',
            'Authorization' => GOLF_MANAGER_AUTH
        ],
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Unable to fetch availability.', ['status' => 500]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('invalid_json', 'Invalid JSON received from API.', ['status' => 500]);
    }

    return rest_ensure_response($data);
}
