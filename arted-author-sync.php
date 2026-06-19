<?php
// ── Автозапись полей Автор/Город из профиля художника в ACF ──────────────
// Срабатывает при любом сохранении product (из кабинета или из админки).
// Приоритет 999 — после ACF (приоритет 1) чтобы не затиралось.

add_action('save_post_product', 'arted_author_sync', 999, 1);

function arted_author_sync($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    $post = get_post($post_id);
    if (!$post) return;

    $author_id = (int) $post->post_author;
    $user      = get_user_by('id', $author_id);
    if (!$user || !in_array('artist', (array) $user->roles)) return;

    $name = get_user_meta($author_id, 'arted_artist_name', true) ?: $user->display_name;
    $city = get_user_meta($author_id, 'arted_artist_city', true);

    if (!$name) return;

    // Получаем ACF ключи полей прямо из БД (из любого товара где они уже заполнены).
    // Это надёжнее чем acf_get_field() — работает независимо от контекста.
    global $wpdb;
    static $name_key = null;
    static $city_key = null;

    if ($name_key === null) {
        $name_key = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_author_name' AND meta_value LIKE 'field_%'
             LIMIT 1"
        );
    }
    if ($city_key === null) {
        $city_key = $wpdb->get_var(
            "SELECT meta_value FROM {$wpdb->postmeta}
             WHERE meta_key = '_author_city' AND meta_value LIKE 'field_%'
             LIMIT 1"
        );
    }

    // Пишем значение + ссылку на ключ поля (ACF требует оба для отображения в админке)
    update_post_meta($post_id, 'author_name', $name);
    if ($name_key) update_post_meta($post_id, '_author_name', $name_key);

    if ($city) {
        update_post_meta($post_id, 'author_city', $city);
        if ($city_key) update_post_meta($post_id, '_author_city', $city_key);
    }
}
