console.log("Admin Sync JS Called");

jQuery(document).ready(function ($) {
  $("#golf-sync-btn").on("click", function () {
    console.log("Sync button clicked");

    const $btn = $(this);
    $btn.prop("disabled", true).text("Syncing...");

    $.post(GolfSync.ajax_url, {
      action: "sync_golf_courses",
      _ajax_nonce: GolfSync.nonce,
    })
      .done(function (response) {
        if (response.success) {
          alert(response.data.message);
        } else {
          alert("Error: " + (response.data?.message || "Unknown error"));
        }
      })
      .fail(function (xhr) {
        console.error('AJAX Failed', xhr.status, xhr.responseText);
        alert("AJAX request failed.");
      })
      .always(function () {
        $btn.prop("disabled", false).text("Sync Golfmanager API");
      });
  });
});
