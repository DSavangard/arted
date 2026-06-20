<?php
// ── Уведомления: Telegram + Email + сообщения художнику ──────────────────

// ── Вспомогательные функции ───────────────────────────────────────────────

function arted_tg_send($text) {
    $s = get_option('arted_settings', []);
    $token   = $s['tg_token']   ?? '';
    $chat_id = $s['tg_chat_id'] ?? '';
    if (!$token || !$chat_id) return;

    wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
        'timeout' => 10,
        'body'    => [
            'chat_id'    => $chat_id,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ],
    ]);
}

function arted_artist_add_message($user_id, $text) {
    $messages   = get_user_meta($user_id, 'arted_messages', true) ?: [];
    $messages[] = [
        'id'   => uniqid('msg_'),
        'text' => $text,
        'date' => current_time('mysql'),
        'read' => false,
    ];
    update_user_meta($user_id, 'arted_messages', $messages);
    $unread = (int) get_user_meta($user_id, 'arted_unread_messages', true);
    update_user_meta($user_id, 'arted_unread_messages', $unread + 1);
}

// ── A. Telegram-уведомление когда художник добавляет работу ──────────────
// Товар уходит на модерацию (статус pending) — бот пишет в чат галереи.

add_action('transition_post_status', 'arted_notify_new_artwork', 10, 3);

function arted_notify_new_artwork($new_status, $old_status, $post) {
    if ($post->post_type !== 'product') return;
    if ($new_status !== 'pending') return;
    if ($old_status === 'pending') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $author = get_userdata($post->post_author);
    if (!$author || !in_array('artist', (array) $author->roles)) return;

    $artist_name = get_user_meta($author->ID, 'arted_artist_name', true) ?: $author->display_name;
    $edit_url    = admin_url('post.php?post=' . $post->ID . '&action=edit');

    arted_tg_send(
        "🖼 <b>Новая работа на модерацию</b>\n\n" .
        "Художник: <b>" . htmlspecialchars($artist_name) . "</b>\n" .
        "Работа: <b>" . htmlspecialchars($post->post_title ?: 'без названия') . "</b>\n\n" .
        '<a href="' . $edit_url . '">Открыть в админ-панели</a>'
    );
}

// ── A2. Уведомление художнику о результате модерации ─────────────────────

add_action('transition_post_status', 'arted_notify_artist_moderation', 10, 3);

function arted_notify_artist_moderation($new_status, $old_status, $post) {
    if ($post->post_type !== 'product') return;
    if ($old_status !== 'pending') return;
    if ($new_status === $old_status) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $author = get_userdata($post->post_author);
    if (!$author || !in_array('artist', (array) $author->roles)) return;

    $work_name = $post->post_title ?: 'без названия';

    if ($new_status === 'publish') {
        $msg     = "✅ Ваша работа «{$work_name}» прошла модерацию и опубликована.";
        $subject = "Работа опубликована — {$work_name}";
        $body    = "Здравствуйте, {$author->display_name}!\n\n" .
                   "Ваша работа «{$work_name}» прошла модерацию и теперь доступна покупателям.\n\n" .
                   "Вы можете посмотреть её в кабинете художника в разделе «Мои работы».";
    } elseif (in_array($new_status, ['draft', 'trash', 'private'])) {
        $msg     = "❌ Ваша работа «{$work_name}» не прошла модерацию. Свяжитесь с галереей для уточнения.";
        $subject = "Работа не прошла модерацию — {$work_name}";
        $body    = "Здравствуйте, {$author->display_name}!\n\n" .
                   "К сожалению, работа «{$work_name}» не прошла модерацию.\n\n" .
                   "Пожалуйста, свяжитесь с галереей через вкладку «Сообщения» в кабинете художника.";
    } else {
        return;
    }

    arted_artist_add_message($post->post_author, $msg);
    wp_mail($author->user_email, $subject, $body);
}

// ── B. Сообщение художнику + Email при новом заказе ──────────────────────

add_action('woocommerce_checkout_order_created', 'arted_notify_artists_new_order');

function arted_notify_artists_new_order($order) {
    $notified = []; // не дублируем если художник продал несколько работ в одном заказе

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product    = wc_get_product($product_id);
        if (!$product) continue;

        $author_id = (int) get_post_field('post_author', $product_id);
        $author    = get_userdata($author_id);
        if (!$author || !in_array('artist', (array) $author->roles)) continue;
        if (in_array($author_id, $notified)) continue;
        $notified[] = $author_id;

        $artist_price = get_post_meta($product_id, 'arted_artist_price', true);
        $price_str    = $artist_price ? number_format((float)$artist_price, 0, '.', ' ') . ' ₽' : '—';
        $work_name    = $product->get_name();
        $order_id     = $order->get_id();

        $msg = "🛒 Новый заказ #{$order_id}\n" .
               "Работа: «{$work_name}»\n" .
               "Ваша цена: {$price_str}";

        arted_artist_add_message($author_id, $msg);

        // Email
        $subject = "Новый заказ на вашу работу — {$work_name}";
        $body    = "Здравствуйте, {$author->display_name}!\n\n" .
                   "Поступил новый заказ #{$order_id} на вашу работу «{$work_name}».\n" .
                   "Ваша цена: {$price_str}\n\n" .
                   "Подробности в вашем кабинете художника.";
        wp_mail($author->user_email, $subject, $body);
    }
}

// ── B2. Сообщение при изменении статуса заказа ───────────────────────────

add_action('woocommerce_order_status_changed', 'arted_notify_artists_order_status', 10, 3);

function arted_notify_artists_order_status($order_id, $old_status, $new_status) {
    $notify_statuses = ['cancelled', 'refunded', 'completed'];
    if (!in_array($new_status, $notify_statuses)) return;

    $order    = wc_get_order($order_id);
    if (!$order) return;

    $notified = [];

    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $author_id  = (int) get_post_field('post_author', $product_id);
        $author     = get_userdata($author_id);
        if (!$author || !in_array('artist', (array) $author->roles)) continue;
        if (in_array($author_id, $notified)) continue;
        $notified[] = $author_id;

        $product   = wc_get_product($product_id);
        $work_name = $product ? $product->get_name() : "#{$product_id}";

        $status_labels = [
            'cancelled' => 'отменён',
            'refunded'  => 'возврат средств',
            'completed' => 'выполнен',
        ];
        $label = $status_labels[$new_status] ?? $new_status;

        $msg = "📋 Статус заказа #{$order_id} изменился: {$label}\n" .
               "Работа: «{$work_name}»";

        arted_artist_add_message($author_id, $msg);
    }
}
