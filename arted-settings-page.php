<?php
// ── Страница настроек галереи (Telegram и др.) ────────────────────────────
add_action('admin_menu', function() {
    add_menu_page('Настройки Gallery', 'Настройки Gallery', 'manage_options',
        'arted-settings', 'arted_settings_page', 'dashicons-admin-settings', 81);
});

add_action('admin_init', function() {
    register_setting('arted_settings_group', 'arted_settings');
    add_settings_section('arted_main', 'Telegram', null, 'arted-settings');
    add_settings_field('tg_token',   'Bot Token',  'arted_field_tg_token',   'arted-settings', 'arted_main');
    add_settings_field('tg_chat_id', 'Chat ID',    'arted_field_tg_chat_id', 'arted-settings', 'arted_main');
});

function arted_field_tg_token() {
    $v = get_option('arted_settings', [])['tg_token'] ?? '';
    echo '<input type="text" name="arted_settings[tg_token]" value="' . esc_attr($v) . '" class="regular-text">';
}

function arted_field_tg_chat_id() {
    $v = get_option('arted_settings', [])['tg_chat_id'] ?? '';
    echo '<input type="text" name="arted_settings[tg_chat_id]" value="' . esc_attr($v) . '" class="regular-text">';
    echo '<p class="description">ID чата или канала куда бот отправляет уведомления</p>';
}

function arted_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки Gallery</h1>
        <form method="post" action="options.php">
            <?php settings_fields('arted_settings_group'); ?>
            <?php do_settings_sections('arted-settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
