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
    $cdek_test = isset($_GET['cdek_test']);
    ?>
    <div class="wrap">
        <h1>Настройки Gallery</h1>
        <form method="post" action="options.php">
            <?php settings_fields('arted_settings_group'); ?>
            <?php do_settings_sections('arted-settings'); ?>
            <?php submit_button(); ?>
        </form>

        <hr>
        <h2>Диагностика СДЭК API</h2>
        <a href="<?= esc_url(add_query_arg(['page' => 'arted-settings', 'cdek_test' => 1], admin_url('admin.php'))) ?>"
           class="button">Запустить тест API</a>

        <?php if ($cdek_test && function_exists('arted_cdek_settings')): ?>
        <div style="margin-top:16px;font-family:monospace;font-size:13px">
            <?php
            // Сбрасываем кэш токена для чистого теста
            delete_transient('arted_cdek_token');

            $cfg = arted_cdek_settings();
            echo '<p><b>Режим:</b> ' . ($cfg['api_base'] === 'https://api.edu.cdek.ru/v2' ? 'тестовый' : 'боевой') . '</p>';
            echo '<p><b>Client ID:</b> ' . esc_html($cfg['client_id'] ?: '❌ пусто') . '</p>';
            echo '<p><b>API base:</b> ' . esc_html($cfg['api_base']) . '</p>';

            // 1. Токен
            $token = arted_cdek_token();
            echo '<p><b>1. Токен:</b> ';
            if ($token) {
                echo '✅ получен (' . substr($token, 0, 20) . '...)';
            } else {
                // Повторный запрос для получения сырого ответа
                $resp = wp_remote_post($cfg['api_base'] . '/oauth/token?parameters', [
                    'timeout' => 15,
                    'body'    => [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $cfg['client_id'],
                        'client_secret' => $cfg['client_secret'],
                    ],
                ]);
                $body = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                echo '❌ ошибка. Ответ: <code>' . esc_html($body) . '</code>';
            }
            echo '</p>';

            if ($token) {
                // 2. Поиск города
                delete_transient('arted_cdek_city_' . md5('Москва'));
                $city_code = arted_cdek_city_code('Москва');
                echo '<p><b>2. Код города «Москва»:</b> ';
                if ($city_code) {
                    echo '✅ ' . esc_html($city_code);
                } else {
                    $resp = wp_remote_get(add_query_arg(['city' => 'Москва', 'size' => 1], $cfg['api_base'] . '/location/cities'), [
                        'timeout' => 10,
                        'headers' => ['Authorization' => 'Bearer ' . $token],
                    ]);
                    $body = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                    echo '❌ ошибка. Ответ: <code>' . esc_html(substr($body, 0, 300)) . '</code>';
                }
                echo '</p>';

                // 3. Расчёт тарифа
                if ($city_code) {
                    $rate = arted_cdek_calculate_rate('Москва', $city_code);
                    echo '<p><b>3. Тариф Москва→Москва:</b> ';
                    if ($rate !== null) {
                        echo '✅ ' . $rate . ' ₽';
                    } else {
                        $body = [
                            'tariff_code'   => 136,
                            'from_location' => ['code' => $city_code],
                            'to_location'   => ['code' => $city_code],
                            'packages'      => [['weight' => 2000, 'length' => 50, 'width' => 40, 'height' => 5]],
                        ];
                        $resp = wp_remote_post($cfg['api_base'] . '/calculator/tariff', [
                            'timeout' => 15,
                            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
                            'body'    => wp_json_encode($body),
                        ]);
                        $raw = is_wp_error($resp) ? $resp->get_error_message() : wp_remote_retrieve_body($resp);
                        echo '❌ ошибка. Ответ: <code>' . esc_html(substr($raw, 0, 500)) . '</code>';
                    }
                    echo '</p>';
                }
            }
            ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
