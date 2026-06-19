<?php
// ── Автор с ссылкой на карточках товаров (вне <a> Elementor) ──────────────
//
// Elementor Loop Builder рендерит шаблон Loop Item внутри
// <a class="woocommerce-LoopProduct-link"> (добавляется WC на priority 10
// хука woocommerce_before_shop_loop_item). Поэтому любой <a> внутри этого
// враппера браузер игнорирует — вложенные ссылки запрещены в HTML.
//
// Решение:
//   1. CSS скрывает .product-author-name / .product-author-city внутри <a>
//   2. woocommerce_after_shop_loop_item priority 6 выводит блок автора
//      ПОСЛЕ закрытия </a> (WC закрывает её на priority 5) — полноценная ссылка.

add_action('woocommerce_after_shop_loop_item', 'arted_product_author_loop', 6);
function arted_product_author_loop() {
    global $product;
    if (!$product) return;

    $id        = $product->get_id();
    $post_obj  = get_post($id);
    $author_id = (int) $post_obj->post_author;
    $user      = get_user_by('id', $author_id);

    if ($user && in_array('artist', (array) $user->roles)) {
        $name = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
        $city = get_user_meta($author_id, 'arted_artist_city', true);
        $url  = home_url('/artist/' . $user->user_nicename . '/');
    } else {
        $name = function_exists('get_field') ? get_field('author_name', $id) : get_post_meta($id, 'author_name', true);
        $city = function_exists('get_field') ? get_field('author_city', $id) : get_post_meta($id, 'author_city', true);
        $url  = '';
        if ($name) {
            $users = get_users(['role' => 'artist', 'meta_key' => 'arted_artist_name', 'meta_value' => $name, 'number' => 1]);
            if (!empty($users)) {
                $url = home_url('/artist/' . $users[0]->user_nicename . '/');
            }
        }
    }

    if (!$name) return;

    echo '<div class="product-author-block">';
    if ($url) {
        echo '<a href="' . esc_url($url) . '" class="product-author-name">' . esc_html($name) . '</a>';
    } else {
        echo '<span class="product-author-name">' . esc_html($name) . '</span>';
    }
    if ($city) {
        echo '<span class="product-author-city">' . esc_html($city) . '</span>';
    }
    echo '</div>';
}

// ── Автор на странице одного товара ───────────────────────────────────────
add_action('woocommerce_single_product_summary', 'arted_product_author_single', 4);
function arted_product_author_single() {
    if (!is_product()) return;
    global $product;
    if (!$product) return;

    $id        = $product->get_id();
    $post_obj  = get_post($id);
    $author_id = (int) $post_obj->post_author;
    $user      = get_user_by('id', $author_id);

    if ($user && in_array('artist', (array) $user->roles)) {
        $name      = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
        $city      = get_user_meta($author_id, 'arted_artist_city', true);
        $url       = home_url('/artist/' . $user->user_nicename . '/');
        $photo_id  = get_user_meta($author_id, 'arted_photo_id', true);
        $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    } else {
        $name      = function_exists('get_field') ? get_field('author_name', $id) : get_post_meta($id, 'author_name', true);
        $city      = function_exists('get_field') ? get_field('author_city', $id) : get_post_meta($id, 'author_city', true);
        $url       = '';
        $photo_url = '';
        if ($name) {
            $users = get_users(['role' => 'artist', 'meta_key' => 'arted_artist_name', 'meta_value' => $name, 'number' => 1]);
            if (!empty($users)) {
                $url      = home_url('/artist/' . $users[0]->user_nicename . '/');
                $photo_id = get_user_meta($users[0]->ID, 'arted_photo_id', true);
                $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
            }
        }
    }

    if (!$name) return;

    echo '<div class="arted-product-author-single">';
    if ($photo_url) {
        echo '<img src="' . esc_url($photo_url) . '" class="arted-product-author-avatar" alt="' . esc_attr($name) . '">';
    }
    $inner = esc_html($name);
    if ($city) {
        $inner .= '<span class="arted-product-author-city">, ' . esc_html($city) . '</span>';
    }
    if ($url) {
        echo '<a href="' . esc_url($url) . '" class="arted-product-author-name">' . $inner . '</a>';
    } else {
        echo '<span class="arted-product-author-name">' . $inner . '</span>';
    }
    echo '</div>';
}
