<?php
// ── СДЭК: авторасчёт доставки от адреса художника ────────────────────────

// ── API-клиент ────────────────────────────────────────────────────────────

if (!function_exists('arted_cdek_settings')) :
function arted_cdek_settings() {
    $s = get_option('arted_settings', []);
    $test = !empty($s['cdek_test_mode']);
    return [
        'client_id'     => ($s['cdek_client_id']     ?: ($test ? 'EMscd6r9JnFiQ3bLoyjJY6eM'        : '')),
        'client_secret' => ($s['cdek_client_secret'] ?: ($test ? 'PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG' : '')),
        'api_base'      => $test
            ? 'https://api.edu.cdek.ru/v2'
            : 'https://api.cdek.ru/v2',
        'widget_api'    => $test
            ? 'https://api.edu.cdek.ru'
            : 'https://api.cdek.ru',
    ];
}
endif;

if (!function_exists('arted_cdek_token')) :
function arted_cdek_token() {
    $cached = get_transient('arted_cdek_token');
    if ($cached) return $cached;

    $cfg  = arted_cdek_settings();
    $resp = wp_remote_post($cfg['api_base'] . '/oauth/token?parameters', [
        'timeout' => 15,
        'body'    => [
            'grant_type'    => 'client_credentials',
            'client_id'     => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
        ],
    ]);
    if (is_wp_error($resp)) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($data['access_token'])) return null;

    $token = $data['access_token'];
    set_transient('arted_cdek_token', $token, (int)($data['expires_in'] ?? 3600) - 60);
    return $token;
}
endif;

if (!function_exists('arted_cdek_city_code')) :
function arted_cdek_city_code($city_name) {
    if (!$city_name) return null;
    $key    = 'arted_cdek_city_' . md5($city_name);
    $cached = get_transient($key);
    if ($cached !== false) return $cached ?: null;

    $token  = arted_cdek_token();
    if (!$token) return null;
    $cfg    = arted_cdek_settings();

    $resp = wp_remote_get(add_query_arg(['city' => $city_name, 'size' => 1], $cfg['api_base'] . '/location/cities'), [
        'timeout' => 10,
        'headers' => ['Authorization' => 'Bearer ' . $token],
    ]);
    if (is_wp_error($resp)) return null;
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    $code = $data[0]['code'] ?? null;

    set_transient($key, $code ?: '', DAY_IN_SECONDS);
    return $code;
}
endif;

if (!function_exists('arted_cdek_calculate_rate')) :
function arted_cdek_calculate_rate($from_city, $to_city_code, $weight_g = 2000, $length_cm = 50, $width_cm = 40, $height_cm = 5) {
    $from_code = arted_cdek_city_code($from_city);
    if (!$from_code || !$to_city_code) return null;

    $token = arted_cdek_token();
    if (!$token) return null;
    $cfg   = arted_cdek_settings();

    // Пробуем тарифы по порядку: склад-склад, склад-ПВЗ, дверь-склад, дверь-ПВЗ
    $tariffs = [136, 137, 138, 139];

    $packages = [[
        'weight' => max(100, $weight_g),
        'length' => max(1, (int)$length_cm),
        'width'  => max(1, (int)$width_cm),
        'height' => max(1, (int)$height_cm),
    ]];

    foreach ($tariffs as $tariff_code) {
        $body = [
            'tariff_code'   => $tariff_code,
            'from_location' => ['code' => (int)$from_code],
            'to_location'   => ['code' => (int)$to_city_code],
            'packages'      => $packages,
        ];

        $resp = wp_remote_post($cfg['api_base'] . '/calculator/tariff', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($resp)) continue;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        $sum  = (float)($data['delivery_sum'] ?? $data['total_sum'] ?? 0);
        if ($sum > 0) return $sum;
    }

    return null;
}
endif;

// ── WooCommerce Shipping Method ───────────────────────────────────────────
// Класс определяется на верхнем уровне — WC_Shipping_Method уже доступен
// (WooCommerce загружается на plugins_loaded, WPCode запускает сниппеты на init)

if (!class_exists('Arted_CDEK_Shipping') && class_exists('WC_Shipping_Method')) :
class Arted_CDEK_Shipping extends WC_Shipping_Method {

        public function __construct($instance_id = 0) {
            $this->id                 = 'arted_cdek';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = 'СДЭК (от художника)';
            $this->method_description = 'Доставка до пункта СДЭК, расчёт от города художника';
            $this->supports           = ['shipping-zones', 'instance-settings'];
            $this->title              = 'Доставка СДЭК';
            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'   => 'Включить',
                    'type'    => 'checkbox',
                    'label'   => 'Включить доставку СДЭК',
                    'default' => 'yes',
                ],
                'title' => [
                    'title'   => 'Название',
                    'type'    => 'text',
                    'default' => 'Доставка СДЭК',
                ],
            ];
        }

        public function calculate_shipping($package = []) {
            $to_city  = $package['destination']['city'] ?? '';
            $to_code  = $to_city ? arted_cdek_city_code($to_city) : null;

            $dbg = ['to_city' => $to_city, 'to_code' => $to_code, 'artists' => []];

            $total_cost   = 0.0;
            $artist_count = 0;

            if ($to_code) {
                $by_artist = [];
                foreach ($package['contents'] as $item) {
                    $author_id = (int)get_post_field('post_author', $item['product_id']);
                    $by_artist[$author_id][] = $item;
                }

                foreach ($by_artist as $author_id => $items) {
                    $artist_city = get_user_meta($author_id, 'arted_artist_city', true)
                        ?: get_user_meta($author_id, 'billing_city', true)
                        ?: get_user_meta($author_id, 'shipping_city', true);

                    // Суммируем вес, берём максимальные размеры по каждой оси
                    $total_weight_g = 0;
                    $max_l = 10; $max_w = 10; $max_h = 5;
                    foreach ($items as $item) {
                        $p = wc_get_product($item['product_id']);
                        if (!$p) continue;
                        $qty = (int)($item['quantity'] ?? 1);
                        $w_kg = floatval($p->get_weight());
                        $total_weight_g += max(100, $w_kg > 0 ? $w_kg * 1000 : 1000) * $qty;
                        $max_l = max($max_l, floatval($p->get_length()) ?: 10);
                        $max_w = max($max_w, floatval($p->get_width())  ?: 10);
                        $max_h = max($max_h, floatval($p->get_height()) ?: 5);
                    }
                    if ($total_weight_g === 0) $total_weight_g = 2000;

                    $rate = $artist_city ? arted_cdek_calculate_rate($artist_city, $to_code, $total_weight_g, $max_l, $max_w, $max_h) : null;
                    $dbg['artists'][] = ['id' => $author_id, 'city' => $artist_city, 'rate' => $rate];
                    if ($rate === null) continue;

                    $total_cost += $rate;
                    $artist_count++;
                }
            }

            set_transient('arted_cdek_last_calc', $dbg, 300);

            if ($artist_count === 0) {
                $this->add_rate([
                    'id'    => $this->get_rate_id(),
                    'label' => 'Доставка СДЭК (стоимость уточняется)',
                    'cost'  => 0,
                ]);
                return;
            }

            $label = $artist_count > 1
                ? "Доставка СДЭК ({$artist_count} отправления)"
                : 'Доставка СДЭК до ПВЗ';

            $this->add_rate([
                'id'    => $this->get_rate_id(),
                'label' => $label,
                'cost'  => $total_cost,
            ]);
        }
}
endif;

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['arted_cdek'] = 'Arted_CDEK_Shipping';
    return $methods;
});

// ── Поле выбора ПВЗ на странице оформления заказа ────────────────────────

if (!function_exists('arted_cdek_pvz_field')) :
function arted_cdek_pvz_field() {
    $chosen = WC()->session ? WC()->session->get('chosen_shipping_methods') : [];
    if (!$chosen || strpos(implode(',', $chosen), 'arted_cdek') === false) return;

    $cfg = arted_cdek_settings();
    $to_city = WC()->customer ? WC()->customer->get_shipping_city() : '';

    $from_city = '';
    foreach (WC()->cart->get_cart() as $item) {
        $aid = (int)get_post_field('post_author', $item['product_id']);
        $c   = get_user_meta($aid, 'arted_artist_city', true);
        if ($c) { $from_city = $c; break; }
    }

    $saved_pvz  = WC()->session->get('arted_cdek_pvz_code', '');
    $saved_addr = WC()->session->get('arted_cdek_pvz_address', '');

    ?>
    <div id="arted-cdek-pvz-wrap" style="margin:20px 0;padding:20px;border:1px solid #e0e0e0;border-radius:8px">
        <h3 style="margin:0 0 12px;font-size:16px">Выберите пункт выдачи СДЭК</h3>

        <?php if ($saved_addr): ?>
        <div id="arted-cdek-selected" style="margin-bottom:12px;padding:10px 14px;background:#f0f9f0;border:1px solid #00a32a;border-radius:6px">
            <strong>Выбран ПВЗ:</strong> <?= esc_html($saved_addr) ?>
            <button type="button" onclick="document.getElementById('arted-cdek-map-wrap').style.display='block'"
                    style="margin-left:12px;font-size:12px;background:none;border:none;color:#2271b1;cursor:pointer;text-decoration:underline">изменить</button>
        </div>
        <?php endif; ?>

        <div id="arted-cdek-map-wrap" style="<?= $saved_addr ? 'display:none' : '' ?>">
            <div id="arted-cdek-map" style="width:100%;height:460px;border-radius:6px;overflow:hidden"></div>
        </div>

        <input type="hidden" id="arted_cdek_pvz_code"    name="arted_cdek_pvz_code"    value="<?= esc_attr($saved_pvz) ?>">
        <input type="hidden" id="arted_cdek_pvz_address" name="arted_cdek_pvz_address" value="<?= esc_attr($saved_addr) ?>">
    </div>

    <script>
    (function() {
        function initCdekWidget() {
            if (!window.CDEKWidget) return setTimeout(initCdekWidget, 300);
            new window.CDEKWidget({
                from:    { city: <?= wp_json_encode($from_city) ?> },
                root:    'arted-cdek-map',
                apiKey:  '',
                forPvz:  true,
                servicePath: <?= wp_json_encode($cfg['widget_api']) ?>,
                onChoose: function(type, tariff, address) {
                    document.getElementById('arted_cdek_pvz_code').value    = address.code || '';
                    document.getElementById('arted_cdek_pvz_address').value = address.address || '';
                    document.getElementById('arted-cdek-map-wrap').style.display = 'none';
                    var sel = document.getElementById('arted-cdek-selected');
                    if (!sel) {
                        sel = document.createElement('div');
                        sel.id = 'arted-cdek-selected';
                        sel.style.cssText = 'margin-bottom:12px;padding:10px 14px;background:#f0f9f0;border:1px solid #00a32a;border-radius:6px';
                        document.getElementById('arted-cdek-pvz-wrap').insertBefore(sel, document.getElementById('arted-cdek-map-wrap'));
                    }
                    sel.innerHTML = '<strong>Выбран ПВЗ:</strong> ' + (address.address || '') +
                        ' <button type="button" onclick="document.getElementById(\'arted-cdek-map-wrap\').style.display=\'block\'" style="margin-left:12px;font-size:12px;background:none;border:none;color:#2271b1;cursor:pointer;text-decoration:underline">изменить</button>';
                    jQuery('body').trigger('update_checkout');
                }
            });
        }
        initCdekWidget();
    })();
    </script>
    <?php

    wp_enqueue_script('cdek-widget', 'https://cdn.jsdelivr.net/npm/@cdek-it/widget@3', [], null, true);
}
endif;

add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    $dbg = get_transient('arted_cdek_last_calc');
    if (!$dbg) return;
    echo '<div class="notice notice-info"><p><b>CDEK последний расчёт:</b> ';
    echo 'Город покупателя: <b>' . esc_html($dbg['to_city']) . '</b> → код: <b>' . esc_html($dbg['to_code'] ?? 'null') . '</b> | ';
    foreach ($dbg['artists'] as $a) {
        echo 'Художник #' . $a['id'] . ' город: <b>' . esc_html($a['city'] ?: 'пусто') . '</b> ставка: <b>' . ($a['rate'] ?? 'null') . '</b> | ';
    }
    echo '</p></div>';
});

// Поле быстрой смены города под строкой доставки
add_action('woocommerce_review_order_after_shipping', function() {
    $chosen = WC()->session ? WC()->session->get('chosen_shipping_methods') : [];
    if (!$chosen || strpos(implode(',', $chosen), 'arted_cdek') === false) return;

    $city = WC()->customer ? (WC()->customer->get_shipping_city() ?: WC()->customer->get_billing_city()) : '';
    ?>
    <tr class="arted-cdek-city-row">
        <th style="padding:4px 0 8px;font-size:12px;font-weight:400;color:#888">Город доставки</th>
        <td style="padding:4px 0 8px">
            <div style="display:flex;gap:6px;align-items:center">
                <input type="text" id="arted_cdek_city_quick" value="<?= esc_attr($city) ?>"
                       placeholder="Введите город"
                       style="flex:1;padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:13px">
                <button type="button" id="arted_cdek_city_apply"
                        style="padding:5px 10px;background:#222;color:#fff;border:none;border-radius:4px;font-size:12px;cursor:pointer;white-space:nowrap">
                    Рассчитать
                </button>
            </div>
        </td>
    </tr>
    <script>
    (function() {
        var input = document.getElementById('arted_cdek_city_quick');
        var btn   = document.getElementById('arted_cdek_city_apply');
        if (!input || !btn) return;

        function applyCity() {
            var city = input.value.trim();
            if (!city) return;
            // Обновляем основные поля города в форме
            var fields = ['#billing_city', '#shipping_city', '[name="billing_city"]', '[name="shipping_city"]'];
            fields.forEach(function(sel) {
                var el = document.querySelector(sel);
                if (el) el.value = city;
            });
            jQuery('body').trigger('update_checkout');
        }

        btn.addEventListener('click', applyCity);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); applyCity(); }
        });

        // Синхронизируем с основным полем города
        jQuery(document.body).on('updated_checkout', function() {
            var main = document.querySelector('#billing_city') || document.querySelector('[name="billing_city"]');
            if (main && main.value && main.value !== input.value) input.value = main.value;
        });
    })();
    </script>
    <?php
});

add_action('woocommerce_review_order_before_payment', 'arted_cdek_pvz_field');

// Сохраняем выбранный ПВЗ в сессию через AJAX
add_action('woocommerce_checkout_update_order_review', function($posted) {
    parse_str($posted, $data);
    if (!empty($data['arted_cdek_pvz_code'])) {
        WC()->session->set('arted_cdek_pvz_code',    sanitize_text_field($data['arted_cdek_pvz_code']));
        WC()->session->set('arted_cdek_pvz_address', sanitize_text_field($data['arted_cdek_pvz_address']));
    }
});

// Валидация: ПВЗ должен быть выбран
add_action('woocommerce_checkout_process', function() {
    $chosen = WC()->session->get('chosen_shipping_methods', []);
    if (!$chosen || strpos(implode(',', $chosen), 'arted_cdek') === false) return;
    if (!WC()->session->get('arted_cdek_pvz_code')) {
        wc_add_notice('Пожалуйста, выберите пункт выдачи СДЭК.', 'error');
    }
});

// Сохраняем ПВЗ в мета заказа
add_action('woocommerce_checkout_create_order', function($order) {
    $code    = WC()->session->get('arted_cdek_pvz_code', '');
    $address = WC()->session->get('arted_cdek_pvz_address', '');
    if ($code) {
        $order->update_meta_data('_cdek_pvz_code',    $code);
        $order->update_meta_data('_cdek_pvz_address', $address);
    }
});

// Показываем ПВЗ в деталях заказа (admin)
add_action('woocommerce_admin_order_data_after_shipping_address', function($order) {
    $code    = $order->get_meta('_cdek_pvz_code');
    $address = $order->get_meta('_cdek_pvz_address');
    if ($address) {
        echo '<p><strong>ПВЗ СДЭК:</strong> ' . esc_html($address) . ' <span style="color:#888">(' . esc_html($code) . ')</span></p>';
    }
});
