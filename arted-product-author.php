<?php
// ── Карта product_id → URL профиля художника ─────────────────────────────
//
// Продукты рендерятся через кастомный AJAX-фильтр (action: ajax_filter_products).
// PHP-хуки WooCommerce loop не стреляют в этом контексте.
//
// Решение:
//   1. wp_footer выводит карту artedAuthorUrls {post_id: url} для всех продуктов
//   2. JS capture-phase listener на document перехватывает клик по .product-author-name
//      до того как <a class="woocommerce-LoopProduct-link"> его получит
//   3. Находит li.product.post-{ID} → берёт URL из карты → redirect

add_action('wp_footer', 'arted_product_author_data');
function arted_product_author_data() {
    if (is_admin() || is_product()) return;

    $products = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $map = [];
    foreach ($products as $id) {
        $author_id = (int) get_post_field('post_author', $id);
        $user      = get_user_by('id', $author_id);

        if ($user && in_array('artist', (array) $user->roles)) {
            $url = home_url('/artist/' . $user->user_nicename . '/');
        } else {
            $name = get_post_meta($id, 'author_name', true);
            if (!$name && function_exists('get_field')) {
                $name = get_field('author_name', $id);
            }
            $url = '';
            if ($name) {
                $found = get_users([
                    'role'       => 'artist',
                    'meta_key'   => 'arted_artist_name',
                    'meta_value' => $name,
                    'number'     => 1,
                ]);
                if (!empty($found)) {
                    $url = home_url('/artist/' . $found[0]->user_nicename . '/');
                }
            }
        }

        if ($url) $map[$id] = $url;
    }

    if (empty($map)) return;
    ?>
    <script>
    window.artedAuthorUrls = <?= json_encode($map) ?>;

    // Capture phase: e-con-inner (Elementor) перехватывает все клики на карточке.
    // e.target всегда e-con-inner, closest() не находит .product-author-name (сестринский элемент).
    // Решение: проверяем координаты клика через getBoundingClientRect.
    // CSS display:none убран — элемент видим и имеет реальные размеры.
    document.addEventListener('click', function(e) {
        var target = e.target.nodeType === 3 ? e.target.parentElement : e.target;
        if (!target) return;

        var liProduct  = target.closest('li.product');
        var divProduct = target.closest('div.product');
        var anyProduct = liProduct || divProduct;

        console.log('[arted] tag:', target.tagName, '| cls:', (target.className+'').substring(0,60), '| li.product:', !!liProduct, '| div.product:', !!divProduct);

        if (!anyProduct) return;

        var m = anyProduct.className.match(/\bpost-(\d+)\b/);
        if (!m) return;

        var url = window.artedAuthorUrls && window.artedAuthorUrls[m[1]];
        var authorEl = anyProduct.querySelector('.product-author-name');
        var rect = authorEl ? authorEl.getBoundingClientRect() : null;

        console.log('[arted] id:', m[1], '| url:', url, '| rect:', rect ? rect.top+'..'+rect.bottom : 'no el', '| Y:', e.clientY, '| match:', rect ? (e.clientY >= rect.top && e.clientY <= rect.bottom) : false);

        if (!url || !rect || !rect.width || !rect.height) return;

        if (e.clientX >= rect.left && e.clientX <= rect.right &&
            e.clientY >= rect.top  && e.clientY <= rect.bottom) {
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location.href = url;
        }
    }, true);
    </script>
    <style>
    .product-author-name,
    .product-author-city {
        cursor: pointer;
    }
    .product-author-name:hover {
        text-decoration: underline;
    }
    </style>
    <?php
}

// ── Автор на странице одного товара ───────────────────────────────────────
add_action('woocommerce_single_product_summary', 'arted_product_author_single', 4);
function arted_product_author_single() {
    if (!is_product()) return;
    global $product;
    if (!$product) return;

    $id        = $product->get_id();
    $author_id = (int) get_post_field('post_author', $id);
    $user      = get_user_by('id', $author_id);

    if ($user && in_array('artist', (array) $user->roles)) {
        $name      = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
        $city      = get_user_meta($author_id, 'arted_artist_city', true);
        $url       = home_url('/artist/' . $user->user_nicename . '/');
        $photo_id  = get_user_meta($author_id, 'arted_photo_id', true);
        $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    } else {
        $name      = get_post_meta($id, 'author_name', true);
        if (!$name && function_exists('get_field')) $name = get_field('author_name', $id);
        $city      = get_post_meta($id, 'author_city', true);
        if (!$city && function_exists('get_field')) $city = get_field('author_city', $id);
        $url       = '';
        $photo_url = '';
        if ($name) {
            $found = get_users([
                'role'       => 'artist',
                'meta_key'   => 'arted_artist_name',
                'meta_value' => $name,
                'number'     => 1,
            ]);
            if (!empty($found)) {
                $url      = home_url('/artist/' . $found[0]->user_nicename . '/');
                $pid      = get_user_meta($found[0]->ID, 'arted_photo_id', true);
                $photo_url = $pid ? wp_get_attachment_image_url($pid, 'thumbnail') : '';
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
