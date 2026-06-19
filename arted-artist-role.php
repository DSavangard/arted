<?php
// WPCode-ID: 3098
// WPCode-Name: arted-artist-role
// ── Скрыть админбар для художников ───────────────────────────────────────
add_filter('show_admin_bar', function($show) {
    if (!is_user_logged_in()) return $show;
    $user = wp_get_current_user();
    if (in_array('artist', (array) $user->roles)) return false;
    return $show;
});

// ── Редирект из wp-admin в кабинет ───────────────────────────────────────
add_action('admin_init', function() {
    if (!is_user_logged_in() || wp_doing_ajax()) return;
    $user = wp_get_current_user();
    if (in_array('artist', (array) $user->roles)) {
        wp_safe_redirect(home_url('/my-account/artist-dashboard/'));
        exit;
    }
});
