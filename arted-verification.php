<?php
// ── 1. AJAX: художник запрашивает верификацию ─────────────────────────────
add_action('wp_ajax_arted_request_verification', function() {
    if (!is_user_logged_in() || !arted_is_artist()) wp_send_json_error('unauthorized');
    check_ajax_referer('arted_verification', 'nonce');

    $user_id  = get_current_user_id();
    $verified = get_user_meta($user_id, 'arted_artist_verified', true);
    if ($verified == 1) wp_send_json_error('already_verified');
    if ($verified == 2) wp_send_json_error('already_pending');

    update_user_meta($user_id, 'arted_artist_verified', 2);
    update_user_meta($user_id, 'arted_verification_requested', time());

    arted_verification_send_tg($user_id);
    wp_send_json_success();
});

// ── 2. Telegram-уведомление с кнопками ───────────────────────────────────
function arted_verification_send_tg($user_id) {
    $settings = get_option('arted_settings', []);
    $token    = $settings['tg_token'] ?? '';
    $chat_id  = $settings['tg_chat_id'] ?? '';
    if (!$token || !$chat_id) return;

    $user    = get_userdata($user_id);
    $name    = get_user_meta($user_id, 'arted_artist_name', true) ?: $user->display_name;
    $city    = get_user_meta($user_id, 'arted_artist_city', true);
    $bio     = get_user_meta($user_id, 'arted_bio', true);
    $styles  = (array)(get_user_meta($user_id, 'arted_styles', true) ?: []);

    $text  = "🎨 *Заявка на верификацию*\n\n";
    $text .= "*Художник:* " . $name . "\n";
    $text .= "*Email:* " . $user->user_email . "\n";
    if ($city)   $text .= "*Город:* " . $city . "\n";
    if ($styles) $text .= "*Стили:* " . implode(', ', $styles) . "\n";
    if ($bio)    $text .= "\n" . mb_substr(strip_tags($bio), 0, 300) . (mb_strlen($bio) > 300 ? '…' : '');

    $keyboard = ['inline_keyboard' => [[
        ['text' => '✅ Верифицировать', 'callback_data' => 'verify_' . $user_id],
        ['text' => '❌ Отклонить',      'callback_data' => 'reject_' . $user_id],
    ]]];

    wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", ['body' => [
        'chat_id'      => $chat_id,
        'text'         => $text,
        'parse_mode'   => 'Markdown',
        'reply_markup' => json_encode($keyboard),
    ]]);
}

// ── 3. Webhook — Telegram нажал кнопку ───────────────────────────────────
add_action('rest_api_init', function() {
    register_rest_route('arted/v1', '/tg-webhook', [
        'methods'             => 'POST',
        'callback'            => 'arted_tg_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function arted_tg_webhook(WP_REST_Request $req) {
    $body = $req->get_json_params();
    if (empty($body['callback_query'])) return new WP_REST_Response('ok');

    $cb      = $body['callback_query'];
    $data    = $cb['data'] ?? '';
    $msg_id  = $cb['message']['message_id'] ?? '';
    $chat_id = $cb['message']['chat']['id'] ?? '';
    $token   = get_option('arted_settings', [])['tg_token'] ?? '';

    if (!preg_match('/^(verify|reject)_(\d+)$/', $data, $m)) return new WP_REST_Response('ok');

    $action  = $m[1];
    $user_id = (int)$m[2];
    $user    = get_userdata($user_id);
    $name    = $user ? (get_user_meta($user_id, 'arted_artist_name', true) ?: $user->display_name) : '#' . $user_id;

    if ($action === 'verify') {
        update_user_meta($user_id, 'arted_artist_verified', 1);
        arted_verification_email($user_id, true);
        $result_text = '✅ ' . $name . ' верифицирован';
    } else {
        update_user_meta($user_id, 'arted_artist_verified', 0);
        arted_verification_email($user_id, false);
        $result_text = '❌ ' . $name . ' — заявка отклонена';
    }

    wp_remote_post("https://api.telegram.org/bot{$token}/answerCallbackQuery", ['body' => [
        'callback_query_id' => $cb['id'],
        'text'              => $result_text,
    ]]);

    wp_remote_post("https://api.telegram.org/bot{$token}/editMessageReplyMarkup", ['body' => [
        'chat_id'      => $chat_id,
        'message_id'   => $msg_id,
        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => $result_text, 'callback_data' => 'done']]]]),
    ]]);

    return new WP_REST_Response('ok');
}

// ── 4. Email художнику о результате ──────────────────────────────────────
function arted_verification_email($user_id, $approved) {
    $user = get_userdata($user_id);
    if (!$user) return;

    $name = get_user_meta($user_id, 'arted_artist_name', true) ?: $user->display_name;
    $lang = get_user_meta($user_id, 'arted_lang', true) ?: 'ru';

    if ($approved) {
        $subjects = ['ru' => 'Ваш профиль верифицирован — arted.gallery', 'en' => 'Your profile is verified — arted.gallery', 'fr' => 'Votre profil est vérifié — arted.gallery'];
        $messages = [
            'ru' => "Поздравляем, {$name}!\n\nВаш профиль художника успешно верифицирован.\n\n" . home_url('/my-account/artist-dashboard/'),
            'en' => "Congratulations, {$name}!\n\nYour artist profile has been verified.\n\n" . home_url('/my-account/artist-dashboard/'),
            'fr' => "Félicitations, {$name}!\n\nVotre profil d'artiste a été vérifié.\n\n" . home_url('/my-account/artist-dashboard/'),
        ];
    } else {
        $subjects = ['ru' => 'Профиль требует доработки — arted.gallery', 'en' => 'Profile needs revision — arted.gallery', 'fr' => 'Profil à compléter — arted.gallery'];
        $messages = [
            'ru' => "Здравствуйте, {$name}.\n\nВаш профиль пока не прошёл верификацию. Пожалуйста, дополните информацию.\n\n" . home_url('/my-account/artist-profile/'),
            'en' => "Hello, {$name}.\n\nYour profile has not passed verification yet. Please complete your profile.\n\n" . home_url('/my-account/artist-profile/'),
            'fr' => "Bonjour, {$name}.\n\nVotre profil n'a pas encore passé la vérification.\n\n" . home_url('/my-account/artist-profile/'),
        ];
    }

    wp_mail($user->user_email, $subjects[$lang] ?? $subjects['ru'], $messages[$lang] ?? $messages['ru'],
        ['Content-Type: text/plain; charset=UTF-8']);
}

// ── 5. Страница Художники в wp-admin ─────────────────────────────────────
add_action('admin_menu', function() {
    add_menu_page('Художники', 'Художники', 'manage_options', 'arted-artists',
        'arted_admin_artists_page', 'dashicons-art', 79);
});

function arted_admin_artists_page() {
    if (!empty($_POST['arted_verify_action']) && check_admin_referer('arted_admin_verify')) {
        $uid    = (int)$_POST['arted_user_id'];
        $action = sanitize_key($_POST['arted_verify_action']);
        if ($action === 'approve') {
            update_user_meta($uid, 'arted_artist_verified', 1);
            arted_verification_email($uid, true);
            echo '<div class="notice notice-success is-dismissible"><p>✅ Художник верифицирован.</p></div>';
        } elseif ($action === 'reject') {
            update_user_meta($uid, 'arted_artist_verified', 0);
            arted_verification_email($uid, false);
            echo '<div class="notice notice-warning is-dismissible"><p>Заявка отклонена.</p></div>';
        }
    }

    $filter  = isset($_GET['filter']) ? sanitize_key($_GET['filter']) : 'all';
    $artists = get_users(['role' => 'artist', 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC']);

    $counts = ['all' => 0, 'pending' => 0, 'verified' => 0, 'new' => 0];
    foreach ($artists as $a) {
        $v = get_user_meta($a->ID, 'arted_artist_verified', true);
        $counts['all']++;
        if ($v == 2) $counts['pending']++;
        elseif ($v == 1) $counts['verified']++;
        else $counts['new']++;
    }

    $page_url = admin_url('admin.php?page=arted-artists');
    ?>
    <div class="wrap">
    <h1>Художники</h1>
    <ul class="subsubsub">
        <li><a href="<?= $page_url ?>" <?= $filter === 'all' ? 'class="current"' : '' ?>>Все <span class="count">(<?= $counts['all'] ?>)</span></a> |</li>
        <li><a href="<?= $page_url ?>&filter=pending" <?= $filter === 'pending' ? 'class="current"' : '' ?>>На проверке <span class="count">(<?= $counts['pending'] ?>)</span></a> |</li>
        <li><a href="<?= $page_url ?>&filter=verified" <?= $filter === 'verified' ? 'class="current"' : '' ?>>Верифицированы <span class="count">(<?= $counts['verified'] ?>)</span></a> |</li>
        <li><a href="<?= $page_url ?>&filter=new" <?= $filter === 'new' ? 'class="current"' : '' ?>>Новые <span class="count">(<?= $counts['new'] ?>)</span></a></li>
    </ul>
    <table class="wp-list-table widefat fixed striped" style="margin-top:12px;">
    <thead><tr><th>Художник</th><th>Email</th><th>Город</th><th>Зарегистрирован</th><th>Статус</th><th>Действия</th></tr></thead>
    <tbody>
    <?php foreach ($artists as $artist):
        $v = get_user_meta($artist->ID, 'arted_artist_verified', true);
        if ($filter === 'pending'  && $v != 2) continue;
        if ($filter === 'verified' && $v != 1) continue;
        if ($filter === 'new'      && $v != 0 && $v !== '') continue;

        $name      = get_user_meta($artist->ID, 'arted_artist_name', true) ?: $artist->display_name;
        $city      = get_user_meta($artist->ID, 'arted_artist_city', true);
        $photo_id  = get_user_meta($artist->ID, 'arted_photo_id', true);
        $photo_url = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';

        if ($v == 1)     $badge = '<span style="color:#2d6a2d;background:#f0faf0;padding:3px 10px;border-radius:20px;font-size:12px;">✓ Верифицирован</span>';
        elseif ($v == 2) $badge = '<span style="color:#7a5a00;background:#fffbea;padding:3px 10px;border-radius:20px;font-size:12px;">⏳ На проверке</span>';
        else             $badge = '<span style="color:#888;background:#f5f5f5;padding:3px 10px;border-radius:20px;font-size:12px;">Новый</span>';
    ?>
    <tr>
        <td>
            <div style="display:flex;align-items:center;gap:10px;">
                <?php if ($photo_url): ?><img src="<?= esc_url($photo_url) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><?php endif; ?>
                <strong><a href="<?= admin_url('user-edit.php?user_id=' . $artist->ID) ?>"><?= esc_html($name) ?></a></strong>
            </div>
        </td>
        <td><?= esc_html($artist->user_email) ?></td>
        <td><?= esc_html($city) ?></td>
        <td><?= date_i18n('d.m.Y', strtotime($artist->user_registered)) ?></td>
        <td><?= $badge ?></td>
        <td>
            <?php if ($v != 1): ?>
            <form method="post" style="display:inline;"><?php wp_nonce_field('arted_admin_verify'); ?>
                <input type="hidden" name="arted_user_id" value="<?= $artist->ID ?>">
                <input type="hidden" name="arted_verify_action" value="approve">
                <button class="button button-primary" style="margin-right:4px;">✅ Верифицировать</button>
            </form>
            <?php endif; ?>
            <?php if ($v == 1 || $v == 2): ?>
            <form method="post" style="display:inline;"><?php wp_nonce_field('arted_admin_verify'); ?>
                <input type="hidden" name="arted_user_id" value="<?= $artist->ID ?>">
                <input type="hidden" name="arted_verify_action" value="reject">
                <button class="button" onclick="return confirm('Отклонить?')">❌ Отклонить</button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>

    <div style="margin-top:24px;padding:16px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;max-width:600px;">
        <strong>Webhook URL для Telegram-бота:</strong><br>
        <code style="display:block;margin-top:6px;padding:8px;background:#fff;border:1px solid #ddd;border-radius:4px;"><?= esc_html(rest_url('arted/v1/tg-webhook')) ?></code>
        <?php $token = get_option('arted_settings', [])['tg_token'] ?? ''; if ($token): ?>
        <a href="<?= esc_url('https://api.telegram.org/bot' . $token . '/setWebhook?url=' . urlencode(rest_url('arted/v1/tg-webhook'))) ?>" target="_blank" class="button" style="margin-top:8px;">🔗 Зарегистрировать webhook</a>
        <?php endif; ?>
    </div>
    </div>
    <?php
}
