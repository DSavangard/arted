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

function arted_tab_works()    { echo '<div class="arted-tab-content"><p>Мои работы — в разработке</p></div>'; }
function arted_tab_add_work() { echo '<div class="arted-tab-content"><p>Добавить работу — в разработке</p></div>'; }
function arted_tab_orders()   { echo '<div class="arted-tab-content"><p>Заказы — в разработке</p></div>'; }
function arted_tab_payouts()  { echo '<div class="arted-tab-content"><p>Выплаты — в разработке</p></div>'; }
function arted_tab_messages() { echo '<div class="arted-tab-content"><p>Сообщения — в разработке</p></div>'; }

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
