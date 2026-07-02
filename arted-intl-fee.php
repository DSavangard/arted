<?php
// ── Фиксированный сбор за международный заказ: $200 в рублях ─────────────
// Применяется когда страна доставки НЕ Россия (не 'RU').
// Курс USD/RUB берётся с ЦБ РФ, кэшируется на 12 часов.

// ── Получение курса USD/RUB с ЦБ РФ ─────────────────────────────────────
function arted_get_usd_rub_rate() {
    $cached = get_transient('arted_usd_rub_rate');
    if ($cached !== false) return (float) $cached;

    $response = wp_remote_get('https://www.cbr-xml-daily.ru/daily_json.js', [
        'timeout' => 8,
    ]);

    if (is_wp_error($response)) {
        return arted_intl_fallback_rate();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['Valute']['USD']['Value'])) {
        return arted_intl_fallback_rate();
    }

    $rate = (float) $data['Valute']['USD']['Value'];
    set_transient('arted_usd_rub_rate', $rate, 12 * HOUR_IN_SECONDS);
    return $rate;
}

// ── Запасной курс если API недоступен ────────────────────────────────────
function arted_intl_fallback_rate() {
    // Обновлять вручную при больших колебаниях курса
    return 90.0;
}

// ── Добавление сбора в корзину ─────────────────────────────────────────
add_action('woocommerce_cart_calculate_fees', 'arted_add_intl_fee');
function arted_add_intl_fee(WC_Cart $cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $country = WC()->customer->get_shipping_country();
    if (!$country || $country === 'RU') return;

    $usd_amount = 200;
    $rate       = arted_get_usd_rub_rate();
    $rub_amount = round($usd_amount * $rate, 2);

    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $labels = [
        'ru' => 'Международная доставка и оформление',
        'en' => 'International shipping & handling',
        'fr' => 'Livraison et traitement international',
    ];
    $label = $labels[$lang] ?? $labels['ru'];

    $cart->add_fee($label, $rub_amount, true);
}

// ── Показываем пояснение к сбору в корзине ──────────────────────────────
add_filter('woocommerce_cart_totals_fee_html', 'arted_intl_fee_note', 10, 2);
function arted_intl_fee_note($fee_html, $fee) {
    if (!str_contains($fee->name, 'International') && !str_contains($fee->name, 'Международная') && !str_contains($fee->name, 'Livraison')) {
        return $fee_html;
    }
    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $notes = [
        'ru' => ' <small style="color:#888;font-size:11px">(≈ $200 по курсу ЦБ)</small>',
        'en' => ' <small style="color:#888;font-size:11px">(≈ $200 at CBR rate)</small>',
        'fr' => ' <small style="color:#888;font-size:11px">(≈ 200$ au taux CBR)</small>',
    ];
    return $fee_html . ($notes[$lang] ?? $notes['ru']);
}
