<?php
// ── Автор на карточке товара (архив/магазин) ──────────────────────────────
add_action('woocommerce_after_shop_loop_item_title', 'arted_product_author_loop', 5);
function arted_product_author_loop() {
    global $product;
    if (!$product) return;
    $post      = get_post($product->get_id());
    if (!$post) return;
    $author_id = (int) $post->post_author;
    $user      = get_user_by('id', $author_id);
    if (!$user || !in_array('artist', (array) $user->roles)) return;
    $name = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
    $url  = home_url('/artist/' . $user->user_nicename . '/');
    echo '<div class="arted-product-author"><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></div>';
}

// ── Автор на странице товара ───────────────────────────────────────────────
add_action('woocommerce_single_product_summary', 'arted_product_author_single', 4);
function arted_product_author_single() {
    global $product;
    if (!$product) return;
    $post      = get_post($product->get_id());
    if (!$post) return;
    $author_id = (int) $post->post_author;
    $user      = get_user_by('id', $author_id);
    if (!$user || !in_array('artist', (array) $user->roles)) return;
    $name      = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
    $url       = home_url('/artist/' . $user->user_nicename . '/');
    $photo_id  = get_user_meta($author_id, 'arted_photo_id', true);
    $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    echo '<div class="arted-product-author-single">';
    if ($photo_url) echo '<img src="' . esc_url($photo_url) . '" class="arted-product-author-avatar" alt="' . esc_attr($name) . '">';
    echo '<a href="' . esc_url($url) . '" class="arted-product-author-name">' . esc_html($name) . '</a>';
    echo '</div>';
}
