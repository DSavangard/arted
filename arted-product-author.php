<?php
// ── Автор на карточках и странице товара ─────────────────────────────────
//
// woocommerce_single_product_summary стреляет в Elementor Loop Builder
// (подтверждено: без is_product() давал дубли на всех карточках).
// woocommerce_after_shop_loop_item_title и woocommerce_after_shop_loop_item
// в Elementor Loop НЕ стреляют.
//
// На карточках: выводим .product-author-block с data-url.
// JS в wp_footer перехватывает клик в фазе capture (до <a> враппера).
// CSS скрывает Elementor ACF версию внутри <a> через прямой дочерний селектор.

add_action('woocommerce_single_product_summary', 'arted_product_author_display', 4);
function arted_product_author_display() {
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
            $found = get_users(['role' => 'artist', 'meta_key' => 'arted_artist_name', 'meta_value' => $name, 'number' => 1]);
            if (!empty($found)) {
                $url      = home_url('/artist/' . $found[0]->user_nicename . '/');
                $pid      = get_user_meta($found[0]->ID, 'arted_photo_id', true);
                $photo_url = $pid ? wp_get_attachment_image_url($pid, 'thumbnail') : '';
            }
        }
    }

    if (!$name) return;

    if (is_product()) {
        // Страница одного товара
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
    } else {
        // Карточка в сетке товаров
        echo '<div class="product-author-block"' . ($url ? ' data-url="' . esc_attr($url) . '"' : '') . '>';
        echo '<span class="product-author-name">' . esc_html($name) . '</span>';
        if ($city) {
            echo '<span class="product-author-city">' . esc_html($city) . '</span>';
        }
        echo '</div>';
    }
}

// ── JS: перехват клика в фазе захвата ────────────────────────────────────
add_action('wp_footer', 'arted_author_click_capture');
function arted_author_click_capture() {
    if (is_admin()) return;
    ?>
    <script>
    document.addEventListener('click', function(e) {
        var block = e.target.closest('.product-author-block[data-url]');
        if (!block) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.href = block.getAttribute('data-url');
    }, true);
    </script>
    <?php
}
