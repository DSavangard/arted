<?php
// ── Страница настроек галереи (Telegram и др.) ────────────────────────────
// Меню добавляется как submenu в arted-gallery-admin.php

add_action('admin_init', function() {
    register_setting('arted_settings_group', 'arted_settings');
    add_settings_section('arted_main',       'Telegram',   null, 'arted-settings');
    add_settings_field('tg_token',           'Bot Token',  'arted_field_tg_token',        'arted-settings', 'arted_main');
    add_settings_field('tg_chat_id',         'Chat ID',    'arted_field_tg_chat_id',       'arted-settings', 'arted_main');
    add_settings_section('arted_commission', 'Комиссия',   null, 'arted-settings');
    add_settings_field('commission_rate',    'Комиссия галереи (%)', 'arted_field_commission_rate', 'arted-settings', 'arted_commission');
    add_settings_section('arted_github',     'GitHub Sync', null, 'arted-settings');
    add_settings_field('github_token',       'GitHub Token', 'arted_field_github_token', 'arted-settings', 'arted_github');
    add_settings_section('arted_cdek',       'СДЭК',        null, 'arted-settings');
    add_settings_field('cdek_client_id',     'Client ID',    'arted_field_cdek_client_id',     'arted-settings', 'arted_cdek');
    add_settings_field('cdek_client_secret', 'Client Secret','arted_field_cdek_client_secret', 'arted-settings', 'arted_cdek');
    add_settings_field('cdek_test_mode',     'Тестовый режим','arted_field_cdek_test_mode',    'arted-settings', 'arted_cdek');
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

function arted_field_commission_rate() {
    $v = (float)(get_option('arted_settings', [])['commission_rate'] ?? 10);
    echo '<input type="number" name="arted_settings[commission_rate]" value="' . esc_attr($v) . '" class="small-text" min="0" max="100" step="0.1"> %';
    echo '<p class="description">Прибавляется к цене художника. Пример: художник ставит 1 000 ₽ → покупатель платит 1 100 ₽</p>';
}

function arted_field_cdek_client_id() {
    $v = get_option('arted_settings', [])['cdek_client_id'] ?? '';
    echo '<input type="text" name="arted_settings[cdek_client_id]" value="' . esc_attr($v) . '" class="regular-text">';
}
function arted_field_cdek_client_secret() {
    $v = get_option('arted_settings', [])['cdek_client_secret'] ?? '';
    echo '<input type="password" name="arted_settings[cdek_client_secret]" value="' . esc_attr($v) . '" class="regular-text">';
}
function arted_field_cdek_test_mode() {
    $v = get_option('arted_settings', [])['cdek_test_mode'] ?? '1';
    echo '<input type="checkbox" name="arted_settings[cdek_test_mode]" value="1"' . checked($v, '1', false) . '>';
    echo '<p class="description">Тестовый API: api.edu.cdek.ru (тест: EMscd6r9JnFiQ3bLoyjJY6eM / PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG)</p>';
}

function arted_field_github_token() {
    $v = get_option('arted_settings', [])['github_token'] ?? '';
    echo '<input type="password" name="arted_settings[github_token]" value="' . esc_attr($v) . '" class="regular-text">';
    echo '<p class="description">Personal Access Token для GitHub API (лимит без токена: 60 запросов/час)</p>';
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
