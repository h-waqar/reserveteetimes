<?php
// sync/sync-courses.php

// Only load scripts on Courses CPT admin screen
add_action('admin_enqueue_scripts', function ($hook) {
    global $typenow;
    // if ($hook === 'edit.php' && $typenow === 'courses') {
    // if ($typenow === 'courses' && strpos($hook, 'edit.php') === 0) {
    wp_enqueue_script(
        'golf-sync-admin',
        GOLF_MANAGER_URL . 'js/admin-sync.js',
        ['jquery'],
        true
    );

    wp_localize_script('golf-sync-admin', 'GolfSync', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('golf_sync_nonce')
    ]);
    // }
});

// Add the "Sync" button next to "Add New"
add_action('restrict_manage_posts', function ($post_type) {
    if ($post_type === 'courses') {
        echo '<button type="button" class="button button-primary" id="golf-sync-btn" style="margin: 0 0.5rem">Sync GolfManager API</button>';
    }
});

// AJAX handler
add_action('wp_ajax_sync_golf_courses', function () {
    check_ajax_referer('golf_sync_nonce');

    $tenant = 'demo';

    $url = add_query_arg(
        ['tenant' => $tenant],
        rest_url('golf/v1/resources')
    );

    $response = wp_remote_get($url, [
        'headers' => [
            'Accept' => 'application/json',
        ]
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to fetch resources.']);
    }

    $resources = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($resources) || !is_array($resources)) {
        wp_send_json_error(['message' => 'No resources found or invalid data.']);
    }

    // Loop and insert/update CPT
    $synced_ids = [];
    foreach ($resources as $resource) {
        $post_id = golf_sync_upsert_course($resource, 'Golf Manager', $tenant);
        if ($post_id) {
            $synced_ids[] = $post_id;
        }
    }

    wp_send_json_success([
        'status'    => 200,
        'message'   => count($synced_ids) . " courses synced successfully.",
        'post_ids'  => $synced_ids,
        'resources' => $resources
    ]);
});


// Helper: insert/update course CPT
function golf_sync_upsert_course(array $resource, string $platform, $tenant): array
{
    if (empty($resource['id'])) {
        return ['post_id' => null, 'action' => 'skipped', 'reason' => 'missing_id'];
    }

    $existing = new WP_Query([
        'post_type'  => 'courses',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key'   => 'id-plataforma',
                'value' => $resource['id'],
            ],
            [
                'key'   => 'plataforma',
                'value' => $platform,
            ]
        ],
        'fields'         => 'ids',
        'posts_per_page' => 1
    ]);

    $new_title = sanitize_text_field($resource['resourceTypeName'] ?? 'Untitled');

    $meta_fields = [
        'id-plataforma'  => $resource['id'],
        'plataforma'     => $platform,
        'modo'           => $resource['modo'] ?? 'real',
        'tenant'         => $tenant ?? '',
        'idResourceType'     => $resource['idResourceType'] ?? '',
        'course_data'        => $resource,
        'course_sync'        => 'ok',
    ];

    // === Insert ===
    if (empty($existing->posts)) {
        $post_id = wp_insert_post([
            'post_title'  => $new_title,
            'post_type'   => 'courses',
            'post_status' => 'publish',
        ]);

        foreach ($meta_fields as $key => $value) {
            if ($value !== '') { // only set non-empty API values on insert
                update_post_meta($post_id, $key, $value);
            }
        }

        return ['post_id' => $post_id, 'action' => 'inserted'];
    }

    // === Update if changed ===
    $post_id = $existing->posts[0];
    $needs_update = false;

    // Check title
    if (get_the_title($post_id) !== $new_title) {
        $needs_update = true;
    }

    // Check meta fields with "no empty overwrite" rule
    foreach ($meta_fields as $key => $value) {
        $current_value = get_post_meta($post_id, $key, true);

        // Only consider it a change if:
        // - API value is non-empty and different from current
        // - OR current value is empty and API value is non-empty (fill missing)
        if ($value !== '' && $current_value != $value) {
            $needs_update = true;
            break;
        }
    }

    if ($needs_update) {
        wp_update_post([
            'ID'          => $post_id,
            'post_title'  => $new_title,
            'post_type'   => 'courses',
            'post_status' => 'publish',
        ]);

        foreach ($meta_fields as $key => $value) {
            if ($value !== '') { // skip empty API values
                update_post_meta($post_id, $key, $value);
            }
        }

        return ['post_id' => $post_id, 'action' => 'updated'];
    }

    return ['post_id' => $post_id, 'action' => 'skipped'];
}
