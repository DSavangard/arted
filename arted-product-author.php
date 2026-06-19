<?php
// ── Автор на карточках товаров ────────────────────────────────────────────
//
// Elementor Loop Builder рендерит контент внутри <a class="woocommerce-LoopProduct-link">.
// woocommerce_after_shop_loop_item в Elementor не стреляет.
// woocommerce_after_shop_loop_item_title стреляет, но мы внутри <a> — нельзя вложить <a>.
//
// Решение:
//   1. woocommerce_after_shop_loop_item_title → выводим .product-author-block с data-url
//   2. wp_footer → JS-обработчик на document в фазе ЗАХВАТА (capture=true),
//      перехватывает клик ДО того как <a> его получит → redirect на страницу художника
//   3. CSS скрывает Elementor-версию автора через прямой дочерний селектор >
//      (наша версия вложена в .product-author-block, поэтому не скрывается)

add_action('woocommerce_after_shop_loop_item_title', 'arted_product_author_loop', 5);
function arted_product_author_loop() {
    global $product;
    if (!$product || is_product()) return;

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

    echo '<div class="product-author-block"' . ($url ? ' data-url="' . esc_attr($url) . '"' : '') . '>';
    echo '<span class="product-author-name">' . esc_html($name) . '</span>';
    if ($city) {
        echo '<span class="product-author-city">' . esc_html($city) . '</span>';
    }
    echo '</div>';
}

// ── JS: перехват клика в фазе захвата (capture), до <a> ──────────────────
add_action('wp_footer', 'arted_author_click_capture');
function arted_author_click_capture() {
    if (is_admin() || is_product()) return;
    ?>
    <script>
    document.addEventListener('click', function(e) {
        var block = e.target.closest('.product-author-block[data-url]');
        if (!block) return;
        e.preventDefault();
        e.stopPropagation();
        window.location.href = block.getAttribute('data-url');
    }, true); // true = фаза захвата — срабатывает ДО <a>
    </script>
    <?php
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
