<?php
// ── Хелпер проверки роли ──────────────────────────────────────────────────
function arted_is_artist() {
    if (!is_user_logged_in()) return false;
    $user = wp_get_current_user();
    return in_array('artist', (array) $user->roles);
}

// ── Регистрируем endpoints ────────────────────────────────────────────────
add_action('init', 'arted_register_endpoints');
function arted_register_endpoints() {
    foreach (['artist-dashboard','artist-works','artist-add-work','artist-orders','artist-payouts','artist-profile','artist-messages'] as $ep)
        add_rewrite_endpoint($ep, EP_ROOT | EP_PAGES);
}

add_filter('woocommerce_get_query_vars', function($vars) {
    foreach (['artist-dashboard','artist-works','artist-add-work','artist-orders','artist-payouts','artist-profile','artist-messages'] as $ep)
        $vars[$ep] = $ep;
    return $vars;
});

// ── Меню художника ────────────────────────────────────────────────────────
add_filter('woocommerce_account_menu_items', 'arted_account_menu_items', 1000);
function arted_account_menu_items($items) {
    if (!is_user_logged_in()) return $items;
    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $t = [
        'ru' => ['dashboard'=>'Дашборд','works'=>'Мои работы','add_work'=>'+ Добавить работу','orders'=>'Заказы','payouts'=>'Выплаты','profile'=>'Профиль и адреса','messages'=>'Сообщения','logout'=>'Выйти'],
        'en' => ['dashboard'=>'Dashboard','works'=>'My Works','add_work'=>'+ Add Work','orders'=>'Orders','payouts'=>'Payouts','profile'=>'Profile & Addresses','messages'=>'Messages','logout'=>'Log Out'],
        'fr' => ['dashboard'=>'Tableau de bord','works'=>'Mes œuvres','add_work'=>'+ Ajouter','orders'=>'Commandes','payouts'=>'Paiements','profile'=>'Profil et adresses','messages'=>'Messages','logout'=>'Déconnexion'],
    ];
    $l = $t[$lang] ?? $t['ru'];

    if (arted_is_artist()) {
        $unread    = (int) get_user_meta(get_current_user_id(), 'arted_unread_messages', true);
        $msg_label = $l['messages'] . ($unread > 0 ? ' <span class="arted-badge">' . $unread . '</span>' : '');
        return [
            'artist-dashboard' => $l['dashboard'],
            'artist-works'     => $l['works'],
            'artist-add-work'  => $l['add_work'],
            'artist-orders'    => $l['orders'],
            'artist-payouts'   => $l['payouts'],
            'artist-profile'   => $l['profile'],
            'artist-messages'  => $msg_label,
            'customer-logout'  => $l['logout'],
        ];
    }

    $cl = [
        'ru' => ['dashboard'=>'Мой аккаунт','orders'=>'Заказы','edit-address'=>'Адреса','edit-account'=>'Данные аккаунта','customer-logout'=>'Выйти'],
        'en' => ['dashboard'=>'My Account','orders'=>'Orders','edit-address'=>'Addresses','edit-account'=>'Account Details','customer-logout'=>'Log Out'],
        'fr' => ['dashboard'=>'Mon compte','orders'=>'Commandes','edit-address'=>'Adresses','edit-account'=>'Détails du compte','customer-logout'=>'Déconnexion'],
    ][$lang] ?? ['dashboard'=>'Мой аккаунт','orders'=>'Заказы','edit-address'=>'Адреса','edit-account'=>'Данные аккаунта','customer-logout'=>'Выйти'];
    return array_intersect_key($cl, $items) + ['customer-logout' => $cl['customer-logout']];
}

// ── Контент вкладок ───────────────────────────────────────────────────────
foreach ([
    'artist-dashboard' => 'arted_tab_dashboard',
    'artist-works'     => 'arted_tab_works',
    'artist-add-work'  => 'arted_tab_add_work',
    'artist-orders'    => 'arted_tab_orders',
    'artist-payouts'   => 'arted_tab_payouts',
    'artist-profile'   => 'arted_tab_profile',
    'artist-messages'  => 'arted_tab_messages',
] as $ep => $cb) {
    add_action('woocommerce_account_' . $ep . '_endpoint', $cb);
}

// ── Дашборд ───────────────────────────────────────────────────────────────
function arted_tab_dashboard() {
    $user_id  = get_current_user_id();
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $verified = get_user_meta($user_id, 'arted_artist_verified', true);

    $t = [
        'ru' => [
            'works'     => 'Работ опубликовано',
            'sold'      => 'Продано всего',
            'payout'    => 'Ожидает выплаты',
            'views'     => 'Просмотров / 30 дн',
            'events'    => 'Последние события',
            'no_events' => 'Событий пока нет',
            'add_work'  => '+ Добавить работу',
            'messages'  => 'Сообщения',
            'banner'    => 'Заполните профиль, чтобы ваши работы выглядели профессионально',
            'fill'      => 'Заполнить профиль',
        ],
        'en' => [
            'works'     => 'Published works',
            'sold'      => 'Total sold',
            'payout'    => 'Pending payout',
            'views'     => 'Views / 30 days',
            'events'    => 'Recent activity',
            'no_events' => 'No events yet',
            'add_work'  => '+ Add work',
            'messages'  => 'Messages',
            'banner'    => 'Complete your profile to make your works look professional',
            'fill'      => 'Fill in profile',
        ],
        'fr' => [
            'works'     => 'Œuvres publiées',
            'sold'      => 'Total vendu',
            'payout'    => 'Paiement en attente',
            'views'     => 'Vues / 30 jours',
            'events'    => 'Activité récente',
            'no_events' => 'Aucun événement',
            'add_work'  => '+ Ajouter une œuvre',
            'messages'  => 'Messages',
            'banner'    => 'Complétez votre profil pour que vos œuvres paraissent professionnelles',
            'fill'      => 'Compléter le profil',
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    $works_count = count_user_posts($user_id, 'product');
    $sold_count  = (int) get_user_meta($user_id, 'arted_sold_count',     true);
    $payout      = (int) get_user_meta($user_id, 'arted_payout_pending', true);
    $views       = (int) get_user_meta($user_id, 'arted_views_30d',      true);

    $fields   = ['arted_artist_name', 'arted_artist_city', 'arted_artist_country', 'arted_bio', 'arted_photo_id', 'arted_styles'];
    $filled   = 0;
    foreach ($fields as $f) { if (get_user_meta($user_id, $f, true)) $filled++; }
    $progress = round(($filled / count($fields)) * 100);

    $events = get_user_meta($user_id, 'arted_events', true) ?: [];

    echo '<div class="arted-tab-content">';

    if ($progress < 100 && $verified != 1 && $verified != 2) {
        echo '<div class="arted-profile-banner">';
        echo '<div>';
        echo '<div class="arted-profile-banner-text">' . esc_html($l['banner']) . '</div>';
        echo '<div class="arted-progress-bar"><div class="arted-progress-fill" style="width:' . $progress . '%"></div></div>';
        echo '</div>';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url('artist-profile')) . '" class="arted-btn-secondary">' . esc_html($l['fill']) . '</a>';
        echo '</div>';
    }

    echo '<div class="arted-metrics">';
    echo '<div class="arted-metric"><div class="arted-metric-label">' . esc_html($l['works'])  . '</div><div class="arted-metric-value">' . $works_count . '</div></div>';
    echo '<div class="arted-metric"><div class="arted-metric-label">' . esc_html($l['sold'])   . '</div><div class="arted-metric-value">' . $sold_count . '</div></div>';
    echo '<div class="arted-metric"><div class="arted-metric-label">' . esc_html($l['payout']) . '</div><div class="arted-metric-value' . ($payout > 0 ? ' accent' : '') . '">' . ($payout > 0 ? number_format($payout, 0, '.', ' ') . ' ₽' : '0') . '</div></div>';
    echo '<div class="arted-metric"><div class="arted-metric-label">' . esc_html($l['views'])  . '</div><div class="arted-metric-value">' . $views . '</div></div>';
    echo '</div>';

    echo '<div class="arted-section-title">' . esc_html($l['events']) . '</div>';
    echo '<div class="arted-events">';
    if (empty($events)) {
        echo '<div class="arted-event"><div class="arted-event-text">' . esc_html($l['no_events']) . '</div></div>';
    } else {
        foreach (array_slice(array_reverse($events), 0, 5) as $ev) {
            echo '<div class="arted-event">';
            echo '<div class="arted-event-dot ' . esc_attr($ev['type'] ?? 'blue') . '"></div>';
            echo '<div class="arted-event-text">' . wp_kses_post($ev['text']) . '</div>';
            echo '<div class="arted-event-time">' . esc_html($ev['date'] ?? '') . '</div>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<div class="arted-cab-actions">';
    echo '<a href="' . esc_url(wc_get_account_endpoint_url('artist-add-work')) . '" class="arted-btn-primary">'  . esc_html($l['add_work'])  . '</a>';
    echo '<a href="' . esc_url(wc_get_account_endpoint_url('artist-messages')) . '" class="arted-btn-secondary">' . esc_html($l['messages']) . '</a>';
    echo '</div>';

    echo '</div>';
}

// arted_tab_works() и arted_tab_add_work() определены в arted-works.php
function arted_tab_orders() {
    $user_id = get_current_user_id();
    $lang    = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';

    $t = [
        'ru' => [
            'title'      => 'Заказы',
            'empty'      => 'Заказов пока нет',
            'col_date'   => 'Дата',
            'col_order'  => '№ заказа',
            'col_work'   => 'Работа',
            'col_price'  => 'Ваша цена',
            'col_status' => 'Статус',
            'statuses'   => [
                'pending'    => 'Ожидает оплаты',
                'on-hold'    => 'На удержании',
                'processing' => 'Оплачен',
                'completed'  => 'Выполнен',
                'cancelled'  => 'Отменён',
                'refunded'   => 'Возврат',
                'failed'     => 'Ошибка оплаты',
            ],
        ],
        'en' => [
            'title'      => 'Orders',
            'empty'      => 'No orders yet',
            'col_date'   => 'Date',
            'col_order'  => 'Order',
            'col_work'   => 'Work',
            'col_price'  => 'Your price',
            'col_status' => 'Status',
            'statuses'   => [
                'pending'    => 'Awaiting payment',
                'on-hold'    => 'On hold',
                'processing' => 'Paid',
                'completed'  => 'Completed',
                'cancelled'  => 'Cancelled',
                'refunded'   => 'Refunded',
                'failed'     => 'Payment failed',
            ],
        ],
        'fr' => [
            'title'      => 'Commandes',
            'empty'      => "Aucune commande pour l'instant",
            'col_date'   => 'Date',
            'col_order'  => 'Commande',
            'col_work'   => 'Œuvre',
            'col_price'  => 'Votre prix',
            'col_status' => 'Statut',
            'statuses'   => [
                'pending'    => 'En attente de paiement',
                'on-hold'    => 'En attente',
                'processing' => 'Payée',
                'completed'  => 'Terminée',
                'cancelled'  => 'Annulée',
                'refunded'   => 'Remboursée',
                'failed'     => 'Échec du paiement',
            ],
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    $status_cls = [
        'pending'    => 'pending',
        'on-hold'    => 'pending',
        'processing' => 'published',
        'completed'  => 'published',
        'cancelled'  => 'draft',
        'refunded'   => 'draft',
        'failed'     => 'draft',
    ];

    // ID всех продуктов художника
    $product_ids = get_posts([
        'post_type'      => 'product',
        'author'         => $user_id,
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    echo '<div class="arted-tab-content">';
    echo '<h2 class="arted-works-title">' . esc_html($l['title']) . '</h2>';

    if (empty($product_ids)) {
        echo '<div class="arted-works-empty"><p>' . esc_html($l['empty']) . '</p></div></div>';
        return;
    }

    global $wpdb;
    $ids_in = implode(',', array_map('intval', $product_ids));

    // Строки заказов, содержащих работы этого художника
    $rows = $wpdb->get_results("
        SELECT
            oi.order_id,
            oim_prod.meta_value  AS product_id,
            p.post_date          AS order_date,
            p.post_status        AS order_status
        FROM {$wpdb->prefix}woocommerce_order_items oi
        JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_prod
            ON oim_prod.order_item_id = oi.order_item_id AND oim_prod.meta_key = '_product_id'
        JOIN {$wpdb->posts} p ON p.ID = oi.order_id
        WHERE oi.order_type = 'line_item'
          AND oim_prod.meta_value IN ({$ids_in})
          AND p.post_type IN ('shop_order', 'wc_order')
        ORDER BY p.post_date DESC
        LIMIT 200
    ");

    if (empty($rows)) {
        echo '<div class="arted-works-empty"><p>' . esc_html($l['empty']) . '</p></div></div>';
        return;
    }

    echo '<div class="arted-orders-table-wrap">';
    echo '<table class="arted-orders-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html($l['col_date'])   . '</th>';
    echo '<th>' . esc_html($l['col_order'])  . '</th>';
    echo '<th>' . esc_html($l['col_work'])   . '</th>';
    echo '<th>' . esc_html($l['col_price'])  . '</th>';
    echo '<th>' . esc_html($l['col_status']) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $product_id   = (int) $row->product_id;
        $product      = wc_get_product($product_id);
        $work_name    = $product ? $product->get_name() : '#' . $product_id;
        $artist_price = get_post_meta($product_id, 'arted_artist_price', true);
        if ($artist_price === '') {
            $artist_price = $product ? (float) $product->get_price() : 0;
        }

        $wc_status    = str_replace('wc-', '', $row->order_status);
        $status_label = $l['statuses'][$wc_status] ?? $wc_status;
        $status_color = $status_cls[$wc_status] ?? 'draft';
        $date         = date_i18n('d.m.Y', strtotime($row->order_date));

        echo '<tr>';
        echo '<td class="arted-orders-date">' . esc_html($date) . '</td>';
        echo '<td class="arted-orders-num">#' . (int) $row->order_id . '</td>';
        echo '<td class="arted-orders-work">' . esc_html($work_name) . '</td>';
        echo '<td class="arted-orders-price">' . ($artist_price ? number_format((float)$artist_price, 0, '.', ' ') . ' ₽' : '—') . '</td>';
        echo '<td><span class="arted-work-card-status ' . esc_attr($status_color) . '">' . esc_html($status_label) . '</span></td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    echo '</div>';
}

function arted_tab_payouts() { echo '<div class="arted-tab-content"><p>Выплаты — в разработке</p></div>'; }

function arted_tab_messages() {
    $user_id  = get_current_user_id();
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $messages = get_user_meta($user_id, 'arted_messages', true) ?: [];

    $t = [
        'ru' => ['title' => 'Сообщения', 'empty' => 'Сообщений пока нет', 'from' => 'Галерея'],
        'en' => ['title' => 'Messages',  'empty' => 'No messages yet',     'from' => 'Gallery'],
        'fr' => ['title' => 'Messages',  'empty' => 'Aucun message',        'from' => 'Galerie'],
    ];
    $l = $t[$lang] ?? $t['ru'];

    // Помечаем все прочитанными
    $has_unread = false;
    foreach ($messages as &$msg) {
        if (empty($msg['read'])) { $msg['read'] = true; $has_unread = true; }
    }
    unset($msg);
    if ($has_unread) {
        update_user_meta($user_id, 'arted_messages', $messages);
        update_user_meta($user_id, 'arted_unread_messages', 0);
    }

    echo '<div class="arted-tab-content">';
    echo '<h2 class="arted-works-title">' . esc_html($l['title']) . '</h2>';

    if (empty($messages)) {
        echo '<div class="arted-works-empty"><p>' . esc_html($l['empty']) . '</p></div>';
    } else {
        echo '<div class="arted-messages-list">';
        foreach (array_reverse($messages) as $msg) {
            echo '<div class="arted-message">';
            echo '<div class="arted-message-meta">';
            echo '<span class="arted-message-from">' . esc_html($l['from']) . '</span>';
            echo '<span class="arted-message-date">' . esc_html(date_i18n('d.m.Y H:i', strtotime($msg['date']))) . '</span>';
            echo '</div>';
            echo '<div class="arted-message-text">' . nl2br(esc_html($msg['text'])) . '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';
}

// ── Отправка сообщения со страницы пользователя в WP Admin ───────────────
add_action('edit_user_profile',        'arted_admin_messages_section');
add_action('show_user_profile',        'arted_admin_messages_section');
add_action('edit_user_profile_update', 'arted_admin_messages_save');
add_action('personal_options_update',  'arted_admin_messages_save');

function arted_admin_messages_section($user) {
    if (!current_user_can('manage_options')) return;
    if (!in_array('artist', (array) $user->roles)) return;

    $messages = get_user_meta($user->ID, 'arted_messages', true) ?: [];
    ?>
    <h2>Сообщения художнику</h2>
    <table class="form-table">
        <tr>
            <th><label for="arted_new_message">Новое сообщение</label></th>
            <td>
                <?php wp_nonce_field('arted_send_message_' . $user->ID, 'arted_message_nonce'); ?>
                <textarea id="arted_new_message" name="arted_new_message" rows="4" class="large-text"></textarea>
                <p class="description">Сообщение появится во вкладке «Сообщения» в кабинете художника.</p>
            </td>
        </tr>
        <?php if ($messages): ?>
        <tr>
            <th>История</th>
            <td>
                <?php foreach (array_reverse($messages) as $msg): ?>
                <div style="margin-bottom:12px;padding:10px 14px;background:#f6f7f7;border-left:3px solid #2271b1;border-radius:2px">
                    <div style="font-size:11px;color:#888;margin-bottom:4px"><?= esc_html(date_i18n('d.m.Y H:i', strtotime($msg['date']))) ?> · <?= $msg['read'] ? 'прочитано' : '<b>не прочитано</b>' ?></div>
                    <div><?= nl2br(esc_html($msg['text'])) ?></div>
                </div>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    <?php
}

function arted_admin_messages_save($user_id) {
    if (!current_user_can('manage_options')) return;
    if (!check_admin_referer('arted_send_message_' . $user_id, 'arted_message_nonce')) return;

    $text = trim(sanitize_textarea_field($_POST['arted_new_message'] ?? ''));
    if ($text === '') return;

    $messages = get_user_meta($user_id, 'arted_messages', true) ?: [];
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

// ── Горизонтальная навигация ──────────────────────────────────────────────
add_action('woocommerce_before_account_navigation', function() {
    if (!arted_is_artist()) return;

    $user     = wp_get_current_user();
    $name     = get_user_meta($user->ID, 'arted_artist_name', true) ?: $user->display_name;
    $verified = get_user_meta($user->ID, 'arted_artist_verified', true);
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';

    $badges = [
        'ru' => ['1' => 'Верифицирован', '2' => 'На проверке', '' => 'Новый'],
        'en' => ['1' => 'Verified',       '2' => 'Pending review', '' => 'New'],
        'fr' => ['1' => 'Vérifié',        '2' => 'En attente',     '' => 'Nouveau'],
    ];
    $b     = $badges[$lang] ?? $badges['ru'];
    $btext = $b[(string)$verified] ?? $b[''];
    $bcls  = $verified == 1 ? 'verified' : ($verified == 2 ? 'pending' : '');

    $current = WC()->query->get_current_endpoint();
    $items   = wc_get_account_menu_items();
    unset($items['customer-logout']);

    echo '<div class="arted-cab-header">';
    echo '<div class="arted-cab-top">';
    echo '<span class="arted-cab-name">' . esc_html($name) . '</span>';
    echo '<span class="arted-cab-badge ' . $bcls . '">' . esc_html($btext) . '</span>';
    echo '</div>';
    echo '<nav class="arted-cab-nav">';
    foreach ($items as $key => $label) {
        $active = ($current === $key || ($key === 'artist-dashboard' && empty($current))) ? ' active' : '';
        $extra  = $key === 'artist-add-work' ? ' arted-nav-add' : '';
        echo '<a href="' . esc_url(wc_get_account_endpoint_url($key)) . '" class="' . trim($active . $extra) . '">' . wp_kses_post($label) . '</a>';
    }
    echo '</nav></div>';
});

add_action('woocommerce_before_account_navigation', function() {
    if (arted_is_artist())
        echo '<style>.woocommerce-MyAccount-navigation{display:none!important}</style>';
});

add_action('wp', function() {
    if (arted_is_artist() && is_account_page()) {
        add_filter('body_class', function($c) { $c[] = 'arted-artist-page'; return $c; });
    }
});
