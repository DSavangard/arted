<?php
// ── Передаём данные автора в JS (работает с Elementor Loop Item) ──────────
add_action('wp_footer', 'arted_product_author_data');
function arted_product_author_data() {
    if (!is_shop() && !is_product_category() && !is_product_tag() && !is_front_page() && !is_home() && !is_page()) return;

    $products = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $map = [];
    foreach ($products as $id) {
        $post      = get_post($id);
        $author_id = (int) $post->post_author;
        $user      = get_user_by('id', $author_id);

        // Сначала пробуем user meta (продукты художников из кабинета)
        if ($user && in_array('artist', (array) $user->roles)) {
            $name = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
            $city = get_user_meta($author_id, 'arted_artist_city', true);
            $url  = home_url('/artist/' . $user->user_nicename . '/');
        } else {
            // Старые продукты с ACF полями
            $name = function_exists('get_field') ? get_field('author_name', $id) : '';
            $city = function_exists('get_field') ? get_field('author_city', $id) : '';
            $url  = '';
            // Ищем пользователя-художника по имени
            if ($name) {
                $users = get_users(['role' => 'artist', 'meta_key' => 'arted_artist_name', 'meta_value' => $name]);
                if (!empty($users)) {
                    $url = home_url('/artist/' . $users[0]->user_nicename . '/');
                }
            }
        }

        if ($name) {
            $map[$id] = ['name' => $name, 'city' => $city, 'url' => $url];
        }
    }

    if (empty($map)) return;
    echo '<script>window.artedAuthorData = ' . json_encode($map) . ';</script>';
}

// ── Автор на странице одного товара (WC хук работает на single) ───────────
add_action('woocommerce_single_product_summary', 'arted_product_author_single', 4);
function arted_product_author_single() {
    global $product;
    if (!$product) return;
    $id        = $product->get_id();
    $post      = get_post($id);
    $author_id = (int) $post->post_author;
    $user      = get_user_by('id', $author_id);

    if ($user && in_array('artist', (array) $user->roles)) {
        $name      = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
        $city      = get_user_meta($author_id, 'arted_artist_city', true);
        $url       = home_url('/artist/' . $user->user_nicename . '/');
        $photo_id  = get_user_meta($author_id, 'arted_photo_id', true);
        $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    } else {
        $name      = function_exists('get_field') ? get_field('author_name', $id) : '';
        $city      = function_exists('get_field') ? get_field('author_city', $id) : '';
        $url       = '';
        $photo_url = '';
        if ($name) {
            $users = get_users(['role' => 'artist', 'meta_key' => 'arted_artist_name', 'meta_value' => $name]);
            if (!empty($users)) {
                $url      = home_url('/artist/' . $users[0]->user_nicename . '/');
                $photo_id = get_user_meta($users[0]->ID, 'arted_photo_id', true);
                $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
            }
        }
    }

    if (!$name) return;
    echo '<div class="arted-product-author-single">';
    if ($photo_url) echo '<img src="' . esc_url($photo_url) . '" class="arted-product-author-avatar" alt="' . esc_attr($name) . '">';
    $inner = esc_html($name);
    if ($city) $inner .= '<span class="arted-product-author-city">, ' . esc_html($city) . '</span>';
    if ($url) {
        echo '<a href="' . esc_url($url) . '" class="arted-product-author-name">' . $inner . '</a>';
    } else {
        echo '<span class="arted-product-author-name">' . $inner . '</span>';
    }
    echo '</div>';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.artedAuthorData) return;

    // Ищем карточки товаров — поддерживаем Elementor Loop Item и стандартный WC
    document.querySelectorAll('[data-product_id], .product[class*="post-"], li.product').forEach(function(card) {
        var id = card.getAttribute('data-product_id')
               || card.className.match(/post-(\d+)/)?.[1];
        if (!id || !artedAuthorData[id]) return;

        var data = artedAuthorData[id];

        // Не добавляем дважды
        if (card.querySelector('.arted-product-author')) return;

        var div = document.createElement('div');
        div.className = 'arted-product-author';

        var text = data.name;
        if (data.city) text += ', ' + data.city;

        if (data.url) {
            var a = document.createElement('a');
            a.href = data.url;
            a.textContent = text;
            div.appendChild(a);
        } else {
            div.textContent = text;
        }

        // Вставляем после заголовка
        var title = card.querySelector('.woocommerce-loop-product__title, h2.product_title, h3');
        if (title) {
            title.parentNode.insertBefore(div, title.nextSibling);
        } else {
            card.appendChild(div);
        }
    });
});
</script>
