<?php
// ── Child theme functions.php additions ───────────────────────────────────
// Добавить в: /wp-content/themes/hello-elementor-child/functions.php

// Убираем вкладку Dashboard для покупателей (не для художников)
add_filter('woocommerce_account_menu_items', 'remove_dashboard_tab', 999);
function remove_dashboard_tab($menu_links) {
    $user = wp_get_current_user();
    if (in_array('artist', (array) $user->roles)) return $menu_links;
    unset($menu_links['dashboard']);
    return $menu_links;
}

// Редирект с /my-account/ на первую вкладку
add_action('template_redirect', 'redirect_my_account_to_first_tab');
function redirect_my_account_to_first_tab() {
    if (!is_account_page() || !is_user_logged_in()) return;
    if (!empty(WC()->query->get_current_endpoint())) return;

    $user = wp_get_current_user();
    if (in_array('artist', (array) $user->roles)) {
        if (strpos($_SERVER['REQUEST_URI'], 'artist-dashboard') === false) {
            wp_safe_redirect(home_url('/my-account/artist-dashboard/'));
            exit;
        }
        return;
    }

    $tabs = wc_get_account_menu_items();
    unset($tabs['customer-logout']);
    $first = key($tabs);
    if ($first) {
        wp_safe_redirect(wc_get_account_endpoint_url($first));
        exit;
    }
}
