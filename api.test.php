<?php
function gm_test_api_call_endpoint()
{
  if (!isset($_GET['gm_test_availibility'])) {
    return; // Only run when queried explicitly
  }

  // --- Build test params (small harmless request)
  $params = [
    'tenant' => 'parmagolf', // replace with real tenant
    'start' => '2025-11-27T00:00:00',
    'end' => '2025-11-27T23:59:59',
    // 'idResource' => 3,
    'idResourceType' => 7,
  ];

  echo "
<pre style='font-size:14px; background:#111; color:#0f0; padding:20px;'>";

  echo "=== GM API TEST ===\n";
  echo "URL: /searchAvailability\n\n";

  $result = gm_api_call(
    '/searchAvailability',
    'GET',
    $params,
    null,
    ['log' => true] // Logging ON for debugging
  );

  if (is_wp_error($result)) {
    echo "❌ ERROR\n";
    echo "Error Code: " . $result->get_error_code() . "\n";
    echo "Message:    " . $result->get_error_message() . "\n";
    echo "Details:\n";
    print_r($result->get_error_data());
    echo "</pre>";
    exit;
  }

  echo "✅ SUCCESS — API returned valid JSON\n\n";
  print_r($result);

  echo "</pre>";
  exit;
}
add_action('init', 'gm_test_api_call_endpoint');

function gm_test_extras_call_endpoint()
{
  if (!isset($_GET['gm_test_extras'])) {
    return; // Only run when explicitly called
  }

  // --- test params (replace tenant if needed)
  $params = [
    'tenant' => 'parmagolf',
    'start' => '2025-11-27T00:00:00',
    'idResourceType' => 7, // use real resourceType
  ];

  echo "
<pre style='font-size:14px; background:#111; color:#0f0; padding:20px;'>";

  echo "=== GM API TEST (EXTRAS) ===\n";
  echo "URL: /extras\n\n";

  // Direct call using new wrapper
  $result = gm_fetch_extras(
    $params['tenant'],
    $params['start'],
    $params['idResourceType']
  );

  if (is_wp_error($result)) {
    echo "❌ ERROR\n";
    echo "Code:    " . $result->get_error_code() . "\n";
    echo "Message: " . $result->get_error_message() . "\n";
    echo "Details:\n";
    print_r($result->get_error_data());
    echo "</pre>";
    exit;
  }

  echo "✅ SUCCESS — API returned valid JSON\n\n";
  print_r($result);

  echo "</pre>";
  exit;
}
add_action('init', 'gm_test_extras_call_endpoint');

/**
 * Test the Golf Manager Resources API.
 * Access via ?gm_test_resources=1 in the URL.
 */
function gm_test_resources()
{
  if (!isset($_GET['gm_test_resources'])) {
    return;
  }

  $tenant = GOLF_MANAGER_TENANT;

  echo "
<pre style='font-size:14px; background:#111; color:#0f0; padding:20px;'>";
  echo "=== GM RESOURCES API TEST ===\n";

  $result = gm_fetch_resources($tenant);

  if (is_wp_error($result)) {
    echo "❌ ERROR\n";
    echo "Code: " . $result->get_error_code() . "\n";
    echo "Message: " . $result->get_error_message() . "\n";
    echo "Details:\n";
    print_r($result->get_error_data());
    echo "</pre>";
    exit;
  }

  echo "✅ SUCCESS — API returned valid JSON\n\n";
  print_r($result);
  echo "</pre>";
  exit;
}
add_action('init', 'gm_test_resources');
add_action('wp_loaded', function () {
  if (!isset($_GET['test_gm_booking'])) return;

  // --- 1. Build test payload ---
  $tenant = 'parmagolf';
  $reservations = [
    [
      'idResource' => 3,
      'start' => '2025-11-27T00:00:00',
      'name' => 'Test API',
      'email' => 'support@golfmanager.com',
      'idType' => 164,
      'timeout' => '2025-12-29T08:20:00',
    ]
  ];

  // --- 2. Call the external GM API directly ---
  $result = gm_make_reservation($tenant, $reservations);

  // --- 3. Output result ---
  echo "
<pre style='background:#111;color:#0f0;padding:20px;'>";
  echo "=== GM EXTERNAL API TEST ===\n\n";

  if (is_wp_error($result)) {
    echo "❌ FAILED\n";
    echo "Error Code: " . $result->get_error_code() . "\n";
    echo "Message:    " . $result->get_error_message() . "\n";
    echo "Details:\n";
    print_r($result->get_error_data());
  } else {
    echo "✅ SUCCESS — External API returned valid response\n";
    print_r($result);
  }

  echo "</pre>";
  exit;
});

add_action('wp_loaded', function () {
  if (!isset($_GET['test_gm_confirm'])) return;

  echo "
<pre style='background:#111;color:#0f0;padding:20px;'>";
  echo "=== GM CONFIRM RESERVATION TEST ===\n\n";

  // --- 1. Build test data ---
  $tenant = 'parmagolf';
  $provisional_ids = [58753]; // replace with real provisional IDs
  $payment_method = 'credit_card'; // optional
  // $payment_method = 'credit_card'; // optional

  // --- 2. Call the direct API wrapper ---
  $result = gm_confirm_reservation($tenant, $provisional_ids,);

  // --- 3. Output result ---
  if (is_wp_error($result)) {
    echo "❌ FAILED\n";
    echo "Error Code: " . $result->get_error_code() . "\n";
    echo "Message:    " . $result->get_error_message() . "\n";
    echo "Details:\n";
    print_r($result->get_error_data());
  } else {
    echo "✅ SUCCESS\n";
    print_r($result);
  }

  echo "</pre>";
  exit;
});

add_action('wp_loaded', function () {
  if (!isset($_GET['test_gm_cancel'])) return;

  echo "
<pre style='background:#111;color:#0f0;padding:20px;'>";
  echo "=== GM CANCELLATION TEST ===\n\n";

  // --- 1. Build test payload ---
  $tenant = 'parmagolf';
  $reservation_ids = [58753, 58755, 58757]; // Replace with actual provisional ID(s) for testing

  // --- 2. Call the new cancellation function directly ---
  $result = gm_cancel_reservation($tenant, $reservation_ids);

  // --- 3. Output the result ---
  if (is_wp_error($result)) {
    echo "❌ FAILED\n";
    echo "Error Code: " . $result->get_error_code() . "\n";
    echo "Message:    " . $result->get_error_message() . "\n";
    echo "Details:    " . print_r($result->get_error_data(), true) . "\n";
  } else {
    echo "✅ SUCCESS\n";
    echo "Response:\n";
    print_r($result);
  }

  echo "</pre>";
  exit;
});
