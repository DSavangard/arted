<?php
// ── Выплаты художникам ────────────────────────────────────────────────────

// ── Вспомогательные функции ───────────────────────────────────────────────

function arted_get_artist_completed_orders($user_id) {
    $product_ids = get_posts([
        'post_type'      => 'product',
        'author'         => $user_id,
        'post_status'    => ['publish','pending','draft'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    if (empty($product_ids)) return [];

    global $wpdb;
    $ids_in = implode(',', array_map('intval', $product_ids));

    return $wpdb->get_results("
        SELECT
            oi.order_id,
            oim_prod.meta_value  AS product_id,
            p.post_date          AS order_date,
            pm_price.meta_value  AS artist_price
        FROM {$wpdb->prefix}woocommerce_order_items oi
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_prod
            ON oim_prod.order_item_id = oi.order_item_id AND oim_prod.meta_key = '_product_id'
        JOIN {$wpdb->posts} p ON p.ID = oi.order_id
        LEFT JOIN {$wpdb->postmeta} pm_price
            ON pm_price.post_id = oim_prod.meta_value AND pm_price.meta_key = 'arted_artist_price'
        WHERE oi.order_type = 'line_item'
          AND oim_prod.meta_value IN ({$ids_in})
          AND p.post_type IN ('shop_order','wc_order')
          AND p.post_status = 'wc-completed'
        ORDER BY p.post_date DESC
    ");
}

function arted_get_paid_order_ids($user_id) {
    $payouts = get_user_meta($user_id, 'arted_payouts', true) ?: [];
    $paid = [];
    foreach ($payouts as $p) $paid = array_merge($paid, $p['order_ids'] ?? []);
    return array_map('intval', $paid);
}

// ── Вкладка художника ─────────────────────────────────────────────────────

function arted_tab_payouts() {
    $user_id  = get_current_user_id();
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $all      = arted_get_artist_completed_orders($user_id);
    $paid_ids = arted_get_paid_order_ids($user_id);
    $payouts  = get_user_meta($user_id, 'arted_payouts', true) ?: [];
    $bank     = get_user_meta($user_id, 'arted_bank_details', true) ?: [];

    $pending_orders = [];
    $total_earned   = 0.0;
    $total_paid     = 0.0;

    foreach ($all as $order) {
        $price = (float)($order->artist_price ?: 0);
        $total_earned += $price;
        if (in_array((int)$order->order_id, $paid_ids)) {
            $total_paid += $price;
        } else {
            $pending_orders[] = $order;
        }
    }
    $pending_amount = $total_earned - $total_paid;

    // Сохранение реквизитов
    if (isset($_POST['arted_save_bank']) && check_admin_referer('arted_bank_' . $user_id)) {
        $bank = [
            'fullname' => sanitize_text_field($_POST['bank_fullname'] ?? ''),
            'account'  => sanitize_text_field($_POST['bank_account']  ?? ''),
            'bik'      => sanitize_text_field($_POST['bank_bik']      ?? ''),
            'bank'     => sanitize_text_field($_POST['bank_bank']     ?? ''),
        ];
        update_user_meta($user_id, 'arted_bank_details', $bank);
        wc_add_notice('Реквизиты сохранены.', 'success');
        wp_redirect(wc_get_account_endpoint_url('artist-payouts'));
        exit;
    }

    echo '<div class="arted-tab-content">';
    echo '<h2 class="arted-works-title">Выплаты</h2>';

    // Сводка
    echo '<div class="arted-metrics" style="margin-bottom:32px">';
    foreach ([
        ['Заработано всего', number_format($total_earned,   0, '.', ' ') . ' ₽', ''],
        ['Выплачено',        number_format($total_paid,     0, '.', ' ') . ' ₽', ''],
        ['Ожидает выплаты',  number_format($pending_amount, 0, '.', ' ') . ' ₽', $pending_amount > 0 ? ' accent' : ''],
    ] as [$label, $value, $cls]):
        echo "<div class='arted-metric'><div class='arted-metric-label'>{$label}</div><div class='arted-metric-value{$cls}'>{$value}</div></div>";
    endforeach;
    echo '</div>';

    // Ожидает выплаты
    if (!empty($pending_orders)):
        echo '<div class="arted-section-title">Ожидает выплаты</div>';
        echo '<div class="arted-orders-table-wrap"><table class="arted-orders-table">';
        echo '<thead><tr><th>Дата заказа</th><th>Работа</th><th>Сумма</th></tr></thead><tbody>';
        foreach ($pending_orders as $o):
            $product = wc_get_product((int)$o->product_id);
            $name    = $product ? $product->get_name() : '#' . $o->product_id;
            echo '<tr>';
            echo '<td class="arted-orders-date">' . date_i18n('d.m.Y', strtotime($o->order_date)) . '</td>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td class="arted-orders-price">' . number_format((float)($o->artist_price ?: 0), 0, '.', ' ') . ' ₽</td>';
            echo '</tr>';
        endforeach;
        echo '</tbody></table></div>';
    endif;

    // История выплат
    if (!empty($payouts)):
        echo '<div class="arted-section-title" style="margin-top:32px">История выплат</div>';
        echo '<div class="arted-orders-table-wrap"><table class="arted-orders-table">';
        echo '<thead><tr><th>Дата</th><th>Сумма</th><th>Заказов</th><th>Заметка</th></tr></thead><tbody>';
        foreach (array_reverse($payouts) as $p):
            echo '<tr>';
            echo '<td class="arted-orders-date">' . date_i18n('d.m.Y', strtotime($p['date'])) . '</td>';
            echo '<td class="arted-orders-price">' . number_format((float)$p['amount'], 0, '.', ' ') . ' ₽</td>';
            echo '<td>' . count($p['order_ids']) . '</td>';
            echo '<td style="color:var(--cab-text-muted)">' . esc_html($p['note'] ?? '') . '</td>';
            echo '</tr>';
        endforeach;
        echo '</tbody></table></div>';
    endif;

    // Реквизиты
    echo '<div class="arted-section-title" style="margin-top:32px">Реквизиты для выплат</div>';
    echo '<form method="post" style="max-width:480px;margin-top:16px">';
    wp_nonce_field('arted_bank_' . $user_id);
    $fields = [
        'bank_fullname' => ['ФИО получателя', $bank['fullname'] ?? ''],
        'bank_account'  => ['Расчётный счёт (р/с)', $bank['account'] ?? ''],
        'bank_bik'      => ['БИК банка', $bank['bik'] ?? ''],
        'bank_bank'     => ['Название банка', $bank['bank'] ?? ''],
    ];
    foreach ($fields as $name => [$label, $value]):
        echo '<div style="margin-bottom:14px">';
        echo '<label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:var(--cab-text-muted)">' . $label . '</label>';
        echo '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '" style="width:100%;padding:8px 12px;border:1px solid var(--cab-border);border-radius:6px;background:var(--cab-surface);color:var(--cab-text);font-size:14px">';
        echo '</div>';
    endforeach;
    echo '<button type="submit" name="arted_save_bank" value="1" class="arted-btn-primary">Сохранить реквизиты</button>';
    echo '</form>';

    echo '</div>';
}

// ── Страница Выплаты в WP Admin ───────────────────────────────────────────

add_action('admin_post_arted_mark_paid', 'arted_admin_mark_paid');

function arted_admin_mark_paid() {
    if (!current_user_can('manage_options')) wp_die('Нет доступа');
    check_admin_referer('arted_mark_paid');

    $artist_id = (int)($_POST['artist_id'] ?? 0);
    $note      = sanitize_text_field($_POST['payout_note'] ?? '');

    if ($artist_id) {
        $all      = arted_get_artist_completed_orders($artist_id);
        $paid_ids = arted_get_paid_order_ids($artist_id);

        $new_order_ids = [];
        $amount        = 0.0;
        foreach ($all as $o) {
            if (!in_array((int)$o->order_id, $paid_ids)) {
                $new_order_ids[] = (int)$o->order_id;
                $amount += (float)($o->artist_price ?: 0);
            }
        }

        if (!empty($new_order_ids)) {
            $payouts   = get_user_meta($artist_id, 'arted_payouts', true) ?: [];
            $payouts[] = [
                'id'        => uniqid('pay_'),
                'amount'    => $amount,
                'date'      => current_time('mysql'),
                'order_ids' => $new_order_ids,
                'note'      => $note,
            ];
            update_user_meta($artist_id, 'arted_payouts', $payouts);

            if (function_exists('arted_artist_add_message')) {
                $msg = '💰 Выплата ' . number_format($amount, 0, '.', ' ') . ' ₽ отправлена.' .
                       ($note ? " Заметка: {$note}" : '');
                arted_artist_add_message($artist_id, $msg);
            }
        }
    }

    wp_redirect(add_query_arg(['page' => 'arted-payouts-admin', 'paid' => 1], admin_url('admin.php')));
    exit;
}

function arted_payouts_admin_page() {
    $artists = get_users(['role' => 'artist', 'orderby' => 'display_name']);
    ?>
    <div class="wrap">
        <h1>Выплаты художникам</h1>

        <?php if (!empty($_GET['paid'])): ?>
        <div class="notice notice-success is-dismissible"><p>Выплата зафиксирована.</p></div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped" style="table-layout:auto">
            <thead>
                <tr>
                    <th>Художник</th>
                    <th style="width:130px">Ожидает выплаты</th>
                    <th style="width:130px">Выплачено всего</th>
                    <th style="width:120px">Последняя выплата</th>
                    <th style="width:120px">Реквизиты</th>
                    <th style="width:280px">Отметить выплачено</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($artists as $artist):
                $name     = get_user_meta($artist->ID, 'arted_artist_name', true) ?: $artist->display_name;
                $all      = arted_get_artist_completed_orders($artist->ID);
                $paid_ids = arted_get_paid_order_ids($artist->ID);
                $payouts  = get_user_meta($artist->ID, 'arted_payouts', true) ?: [];
                $bank     = get_user_meta($artist->ID, 'arted_bank_details', true) ?: [];

                $pending  = 0.0;
                $total_p  = 0.0;
                foreach ($all as $o) {
                    $pr = (float)($o->artist_price ?: 0);
                    if (in_array((int)$o->order_id, $paid_ids)) $total_p  += $pr;
                    else                                          $pending  += $pr;
                }
                $last_payout = !empty($payouts) ? end($payouts) : null;
                $has_bank    = !empty($bank['account']);
            ?>
                <tr>
                    <td>
                        <strong><?= esc_html($name) ?></strong>
                        <div style="font-size:11px;color:#888"><?= esc_html($artist->user_email) ?></div>
                    </td>
                    <td>
                        <?php if ($pending > 0): ?>
                        <span style="font-weight:700;color:#00a32a"><?= number_format($pending, 0, '.', ' ') ?> ₽</span>
                        <?php else: ?>
                        <span style="color:#888">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $total_p > 0 ? number_format($total_p, 0, '.', ' ') . ' ₽' : '—' ?></td>
                    <td style="font-size:12px;color:#888">
                        <?= $last_payout ? date_i18n('d.m.Y', strtotime($last_payout['date'])) : '—' ?>
                    </td>
                    <td>
                        <?php if ($has_bank): ?>
                        <span style="color:#00a32a;font-size:12px">✓ Заполнены</span>
                        <?php else: ?>
                        <span style="color:#d63638;font-size:12px">Не указаны</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($pending > 0): ?>
                        <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>"
                              style="display:flex;gap:6px;align-items:flex-start"
                              onsubmit="return confirm('Отметить выплату <?= number_format($pending, 0, '.', ' ') ?> ₽ для <?= esc_js($name) ?>?')">
                            <?php wp_nonce_field('arted_mark_paid'); ?>
                            <input type="hidden" name="action"    value="arted_mark_paid">
                            <input type="hidden" name="artist_id" value="<?= $artist->ID ?>">
                            <input type="text"   name="payout_note" placeholder="Заметка (необязательно)"
                                   style="width:160px;font-size:12px" class="regular-text">
                            <button type="submit" class="button button-primary button-small">
                                ✓ <?= number_format($pending, 0, '.', ' ') ?> ₽
                            </button>
                        </form>
                        <?php else: ?>
                        <span style="color:#888;font-size:12px">Нет к выплате</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
