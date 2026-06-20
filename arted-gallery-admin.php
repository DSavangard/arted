<?php
// ── Страница «Галерея» в WP Admin ─────────────────────────────────────────

add_action('admin_menu', function() {
    add_menu_page('Галерея', 'Галерея', 'manage_options', 'arted-gallery',
        'arted_gallery_admin_page', 'dashicons-format-gallery', 26);

    add_submenu_page('arted-gallery', 'Работы',     'Работы',     'manage_options', 'arted-gallery',       'arted_gallery_admin_page');
    add_submenu_page('arted-gallery', 'Художники',  'Художники',  'manage_options', 'arted-artists',       'arted_artists_admin_page');
    add_submenu_page('arted-gallery', 'Выплаты',    'Выплаты',    'manage_options', 'arted-payouts-admin', 'arted_payouts_admin_page');
    add_submenu_page('arted-gallery', 'Настройки',  'Настройки',  'manage_options', 'arted-settings',      'arted_settings_page');
});

// Быстрое изменение статуса прямо из списка
add_action('admin_post_arted_gallery_status', 'arted_gallery_quick_status');

function arted_gallery_quick_status() {
    if (!current_user_can('manage_options')) wp_die('Нет доступа');
    check_admin_referer('arted_gallery_status');

    $post_id    = (int)($_GET['post_id'] ?? 0);
    $new_status = sanitize_key($_GET['status'] ?? '');
    $allowed    = ['publish', 'pending', 'draft', 'trash'];

    if ($post_id && in_array($new_status, $allowed)) {
        if ($new_status === 'trash') {
            wp_trash_post($post_id);
        } else {
            wp_update_post(['ID' => $post_id, 'post_status' => $new_status]);
        }
    }

    $redirect = remove_query_arg(['action','post_id','status','_wpnonce'], wp_get_referer() ?: admin_url('admin.php?page=arted-gallery'));
    wp_redirect($redirect);
    exit;
}

function arted_gallery_admin_page() {
    // ── Параметры фильтрации ──────────────────────────────────────────────
    $filter_status    = sanitize_key($_GET['post_status'] ?? 'any');
    $filter_artist    = (int)($_GET['artist_id'] ?? 0);
    $search           = sanitize_text_field($_GET['s'] ?? '');
    $paged            = max(1, (int)($_GET['paged'] ?? 1));
    $per_page         = 20;

    // ── Запрос ────────────────────────────────────────────────────────────
    $args = [
        'post_type'      => 'product',
        'post_status'    => $filter_status === 'any' ? ['publish','pending','draft'] : [$filter_status],
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ($filter_artist) $args['author'] = $filter_artist;
    if ($search)        $args['s']      = $search;

    $query = new WP_Query($args);

    // ── Счётчики по статусам ──────────────────────────────────────────────
    $count_args = ['post_type' => 'product', 'posts_per_page' => -1, 'fields' => 'ids'];
    if ($filter_artist) $count_args['author'] = $filter_artist;
    if ($search)        $count_args['s']      = $search;

    $counts = [];
    foreach (['publish','pending','draft'] as $st) {
        $c = new WP_Query(array_merge($count_args, ['post_status' => [$st]]));
        $counts[$st] = $c->found_posts;
    }
    $counts['any'] = array_sum($counts);

    // ── Все художники для фильтра ─────────────────────────────────────────
    $artists = get_users(['role' => 'artist', 'orderby' => 'display_name']);

    // ── URL-хелпер ────────────────────────────────────────────────────────
    $base_url = admin_url('admin.php?page=arted-gallery');
    $url = function($extra = []) use ($base_url, $filter_status, $filter_artist, $search) {
        $p = array_filter(array_merge(
            ['post_status' => $filter_status, 'artist_id' => $filter_artist, 's' => $search],
            $extra
        ));
        return $base_url . ($p ? '&' . http_build_query($p) : '');
    };

    $status_labels = [
        'any'     => 'Все',
        'publish' => 'Опубликованы',
        'pending' => 'На модерации',
        'draft'   => 'Черновики',
    ];
    $status_colors = [
        'publish' => '#00a32a',
        'pending' => '#dba617',
        'draft'   => '#888',
        'trash'   => '#d63638',
        'private' => '#888',
    ];

    ?>
    <div class="wrap">
        <h1 style="display:flex;align-items:center;gap:12px">
            Галерея
            <span style="font-size:13px;font-weight:400;color:#888"><?= $counts['any'] ?> работ</span>
        </h1>

        <?php
        // Уведомление после смены статуса
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Статус изменён.</p></div>';
        }
        ?>

        <!-- Фильтры-вкладки по статусу -->
        <ul class="subsubsub" style="margin-bottom:12px">
            <?php foreach ($status_labels as $st => $label):
                $active = $filter_status === $st;
                $cnt    = $counts[$st] ?? 0;
            ?>
            <li>
                <a href="<?= esc_url($url(['post_status' => $st, 'paged' => 1])) ?>"
                   <?= $active ? 'style="font-weight:700"' : '' ?>>
                    <?= esc_html($label) ?>
                    <span class="count">(<?= $cnt ?>)</span>
                </a>
                <?= array_key_last($status_labels) !== $st ? ' |' : '' ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- Поиск + фильтр по художнику -->
        <form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
            <input type="hidden" name="page" value="arted-gallery">
            <input type="hidden" name="post_status" value="<?= esc_attr($filter_status) ?>">
            <input type="text" name="s" value="<?= esc_attr($search) ?>" placeholder="Поиск по названию…" class="regular-text">
            <select name="artist_id">
                <option value="">Все художники</option>
                <?php foreach ($artists as $a): ?>
                <option value="<?= $a->ID ?>" <?= selected($filter_artist, $a->ID, false) ?>>
                    <?= esc_html(get_user_meta($a->ID, 'arted_artist_name', true) ?: $a->display_name) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button">Применить</button>
            <?php if ($search || $filter_artist): ?>
            <a href="<?= esc_url($url(['artist_id' => '', 's' => ''])) ?>" class="button">Сбросить</a>
            <?php endif; ?>
        </form>

        <!-- Таблица работ -->
        <table class="wp-list-table widefat fixed striped" style="table-layout:auto">
            <thead>
                <tr>
                    <th style="width:60px"></th>
                    <th>Название</th>
                    <th style="width:180px">Художник</th>
                    <th style="width:110px">Цена художника</th>
                    <th style="width:110px">Цена для покупателя</th>
                    <th style="width:100px">Статус</th>
                    <th style="width:100px">Дата</th>
                    <th style="width:160px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$query->have_posts()): ?>
                <tr><td colspan="8" style="text-align:center;padding:32px;color:#888">Работ не найдено</td></tr>
            <?php else: while ($query->have_posts()): $query->the_post();
                $pid          = get_the_ID();
                $product      = wc_get_product($pid);
                $post_status  = get_post_status($pid);
                $artist_id    = (int) get_post_field('post_author', $pid);
                $artist       = get_userdata($artist_id);
                $artist_name  = get_user_meta($artist_id, 'arted_artist_name', true) ?: ($artist ? $artist->display_name : '—');
                $artist_price = get_post_meta($pid, 'arted_artist_price', true);
                $buyer_price  = $product ? (float)$product->get_price() : 0;
                $thumb        = get_the_post_thumbnail($pid, [50, 50], ['style' => 'width:50px;height:50px;object-fit:cover;border-radius:4px']);
                $color        = $status_colors[$post_status] ?? '#888';
                $slabel       = $status_labels[$post_status] ?? $post_status;
                $edit_url     = get_edit_post_link($pid);
                $nonce_approve = wp_create_nonce('arted_gallery_status');

                $action_url = function($status) use ($pid, $nonce_approve) {
                    return admin_url('admin-post.php?action=arted_gallery_status&post_id=' . $pid . '&status=' . $status . '&_wpnonce=' . $nonce_approve);
                };
            ?>
                <tr>
                    <td><?= $thumb ?: '<div style="width:50px;height:50px;background:#f0f0f0;border-radius:4px"></div>' ?></td>
                    <td>
                        <a href="<?= esc_url($edit_url) ?>" style="font-weight:600"><?= esc_html(get_the_title() ?: '(без названия)') ?></a>
                        <div style="font-size:11px;color:#888;margin-top:2px">#<?= $pid ?></div>
                    </td>
                    <td>
                        <?php if ($artist): ?>
                        <a href="<?= esc_url($url(['artist_id' => $artist_id])) ?>"><?= esc_html($artist_name) ?></a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= $artist_price !== '' ? number_format((float)$artist_price, 0, '.', ' ') . ' ₽' : '—' ?></td>
                    <td><?= $buyer_price ? number_format($buyer_price, 0, '.', ' ') . ' ₽' : '—' ?></td>
                    <td>
                        <span style="background:<?= $color ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;white-space:nowrap">
                            <?= esc_html($slabel) ?>
                        </span>
                    </td>
                    <td style="color:#888;font-size:12px"><?= get_the_date('d.m.Y') ?></td>
                    <td style="white-space:nowrap">
                        <a href="<?= esc_url($edit_url) ?>" class="button button-small">Редактировать</a>
                        <?php if ($post_status === 'pending'): ?>
                        <a href="<?= esc_url($action_url('publish')) ?>" class="button button-small" style="color:#00a32a;border-color:#00a32a" onclick="return confirm('Опубликовать работу?')">✓</a>
                        <a href="<?= esc_url($action_url('draft')) ?>" class="button button-small" style="color:#d63638;border-color:#d63638" onclick="return confirm('Отклонить работу?')">✕</a>
                        <?php elseif ($post_status === 'publish'): ?>
                        <a href="<?= esc_url($action_url('draft')) ?>" class="button button-small" onclick="return confirm('Снять с публикации?')">↓ Снять</a>
                        <?php elseif ($post_status === 'draft'): ?>
                        <a href="<?= esc_url($action_url('publish')) ?>" class="button button-small" onclick="return confirm('Опубликовать?')">↑ Опубл.</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; wp_reset_postdata(); endif; ?>
            </tbody>
        </table>

        <!-- Пагинация -->
        <?php
        $total_pages = $query->max_num_pages;
        if ($total_pages > 1):
            echo '<div style="margin-top:16px;display:flex;gap:4px;align-items:center">';
            for ($i = 1; $i <= $total_pages; $i++) {
                $active = $i === $paged;
                echo '<a href="' . esc_url($url(['paged' => $i])) . '" class="button' . ($active ? ' button-primary' : '') . '">' . $i . '</a>';
            }
            echo '</div>';
        endif;
        ?>
    </div>
    <?php
}

// ── Страница Художники ────────────────────────────────────────────────────

add_action('admin_post_arted_send_artist_message', 'arted_admin_send_artist_message');

function arted_admin_send_artist_message() {
    if (!current_user_can('manage_options')) wp_die('Нет доступа');
    check_admin_referer('arted_artist_message');

    $artist_id = (int)($_POST['artist_id'] ?? 0);
    $text      = trim(sanitize_textarea_field($_POST['message_text'] ?? ''));

    if ($artist_id && $text && function_exists('arted_artist_add_message')) {
        arted_artist_add_message($artist_id, $text);
    }

    wp_redirect(add_query_arg(['page' => 'arted-artists', 'msg_sent' => 1], admin_url('admin.php')));
    exit;
}

function arted_artists_admin_page() {
    $artists = get_users(['role' => 'artist', 'orderby' => 'registered', 'order' => 'DESC']);

    $verify_labels = [
        '1' => ['Верифицирован', '#00a32a'],
        '2' => ['На проверке',   '#dba617'],
        ''  => ['Новый',         '#888'],
    ];

    // Быстрая верификация
    if (isset($_GET['verify_artist']) && check_admin_referer('arted_verify')) {
        $uid = (int)$_GET['verify_artist'];
        $val = (int)$_GET['verify_val'];
        update_user_meta($uid, 'arted_artist_verified', $val);
        if ($val == 1 && function_exists('arted_artist_add_message')) {
            $artist = get_userdata($uid);
            arted_artist_add_message($uid, '✅ Ваш профиль художника верифицирован галереей Arted.');
        }
        wp_redirect(admin_url('admin.php?page=arted-artists'));
        exit;
    }
    ?>
    <div class="wrap">
        <h1>Художники <span style="font-size:13px;font-weight:400;color:#888"><?= count($artists) ?></span></h1>

        <?php if (!empty($_GET['msg_sent'])): ?>
        <div class="notice notice-success is-dismissible"><p>Сообщение отправлено.</p></div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped" style="table-layout:auto">
            <thead>
                <tr>
                    <th style="width:40px"></th>
                    <th>Художник</th>
                    <th style="width:80px">Работ</th>
                    <th style="width:100px">На модерации</th>
                    <th style="width:120px">Статус</th>
                    <th style="width:110px">Зарегистрирован</th>
                    <th>Написать</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($artists as $artist):
                $name       = get_user_meta($artist->ID, 'arted_artist_name', true) ?: $artist->display_name;
                $verified   = (string)(get_user_meta($artist->ID, 'arted_artist_verified', true) ?? '');
                [$vlabel, $vcolor] = $verify_labels[$verified] ?? $verify_labels[''];
                $works_pub  = count_user_posts($artist->ID, 'product', true);
                $works_pend = (int)(new WP_Query([
                    'post_type' => 'product', 'author' => $artist->ID,
                    'post_status' => ['pending'], 'posts_per_page' => -1, 'fields' => 'ids',
                ]))->found_posts;
                $avatar     = get_avatar($artist->ID, 36, '', '', ['class' => 'rounded']);
                $gallery_url = admin_url('admin.php?page=arted-gallery&artist_id=' . $artist->ID);
                $nonce_v    = wp_create_nonce('arted_verify');
            ?>
                <tr>
                    <td><?= $avatar ?></td>
                    <td>
                        <strong><?= esc_html($name) ?></strong>
                        <div style="font-size:11px;color:#888"><?= esc_html($artist->user_email) ?></div>
                    </td>
                    <td><a href="<?= esc_url($gallery_url) ?>"><?= $works_pub ?></a></td>
                    <td>
                        <?php if ($works_pend): ?>
                        <a href="<?= esc_url($gallery_url . '&post_status=pending') ?>" style="color:#dba617;font-weight:600"><?= $works_pend ?> ожид.</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <span style="background:<?= $vcolor ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px"><?= esc_html($vlabel) ?></span>
                        <?php if ($verified != '1'): ?>
                        <a href="<?= esc_url(admin_url('admin.php?page=arted-artists&verify_artist=' . $artist->ID . '&verify_val=1&_wpnonce=' . $nonce_v)) ?>"
                           style="font-size:11px;margin-left:4px" title="Верифицировать">✓</a>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#888"><?= date_i18n('d.m.Y', strtotime($artist->user_registered)) ?></td>
                    <td>
                        <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="display:flex;gap:6px;align-items:flex-start">
                            <?php wp_nonce_field('arted_artist_message'); ?>
                            <input type="hidden" name="action"    value="arted_send_artist_message">
                            <input type="hidden" name="artist_id" value="<?= $artist->ID ?>">
                            <textarea name="message_text" rows="1" placeholder="Написать художнику…"
                                      style="width:220px;resize:vertical;font-size:12px"
                                      onfocus="this.rows=3"></textarea>
                            <button type="submit" class="button button-small">Отправить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Заглушка — реальная страница в arted-payouts.php
if (!function_exists('arted_payouts_admin_page')) {
    function arted_payouts_admin_page() {
        echo '<div class="wrap"><h1>Выплаты</h1><p>Загружается…</p></div>';
    }
}
