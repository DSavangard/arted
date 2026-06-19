<?php
// WPCode-ID: 3103
// WPCode-Name: arted-artist-on-register
// ── Email + Telegram при регистрации художника ────────────────────────────
add_action('woocommerce_created_customer', 'arted_artist_on_register', 20, 3);
function arted_artist_on_register($customer_id, $new_customer_data, $password_generated) {
    if (empty($_POST['arted_user_role']) || $_POST['arted_user_role'] !== 'artist') return;

    $user = get_userdata($customer_id);
    $name = get_user_meta($customer_id, 'arted_artist_name', true) ?: $user->display_name;
    $lang = get_user_meta($customer_id, 'arted_lang', true) ?: 'ru';

    // ── Email художнику ───────────────────────────────────────────────────
    $defaults = [
        'ru' => ['subject' => 'Добро пожаловать в arted.gallery', 'body' => "Привет, {name}!\n\nВы зарегистрированы как художник на arted.gallery.\n\nЗаполните профиль: {url}", 'cta' => 'Перейти в кабинет'],
        'en' => ['subject' => 'Welcome to arted.gallery', 'body' => "Hello, {name}!\n\nYou are registered as an artist on arted.gallery.\n\nFill in your profile: {url}", 'cta' => 'Go to cabinet'],
        'fr' => ['subject' => 'Bienvenue sur arted.gallery', 'body' => "Bonjour, {name}!\n\nVous êtes inscrit en tant qu'artiste sur arted.gallery.\n\nComplétez votre profil: {url}", 'cta' => 'Accéder à l\'espace'],
    ];

    $option  = get_option('arted_email_welcome_' . $lang, []);
    $subject = $option['subject'] ?? $defaults[$lang]['subject'] ?? $defaults['ru']['subject'];
    $body    = $option['body']    ?? $defaults[$lang]['body']    ?? $defaults['ru']['body'];
    $url     = home_url('/my-account/artist-profile/');

    $body = str_replace(['{name}', '{url}'], [$name, $url], $body);

    wp_mail($user->user_email, $subject, $body, ['Content-Type: text/plain; charset=UTF-8']);

    // ── Telegram уведомление администратору ───────────────────────────────
    $settings = get_option('arted_settings', []);
    $token    = $settings['tg_token']   ?? '';
    $chat_id  = $settings['tg_chat_id'] ?? '';
    if (!$token || !$chat_id) return;

    $city    = get_user_meta($customer_id, 'arted_artist_city',    true);
    $contact = get_user_meta($customer_id, 'arted_artist_contact', true);
    $admin_url = admin_url('user-edit.php?user_id=' . $customer_id);

    $text  = "🎨 *Новый художник*\n\n";
    $text .= "*Имя:* " . $name . "\n";
    $text .= "*Email:* " . $user->user_email . "\n";
    if ($city)    $text .= "*Город:* " . $city . "\n";
    if ($contact) $text .= "*Контакт:* " . $contact . "\n";
    $text .= "\n[Профиль в админке](" . $admin_url . ")";

    wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", ['body' => [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ]]);
}
