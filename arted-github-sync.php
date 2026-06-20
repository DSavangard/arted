<?php
// ── GitHub → WPCode автосинхронизация ────────────────────────────────────

add_action('admin_menu', function() {
    add_management_page(
        'GitHub Sync',
        '↓ GitHub Sync',
        'manage_options',
        'arted-github-sync',
        'arted_github_sync_page'
    );
});

// Карта: файл в GitHub → ID сниппета в WPCode
function arted_github_snippet_map() {
    return [
        'arted-cabinet-nav.php'           => 3105,
        'arted-artist-register-form.php'  => 3100,
        'arted-artist-role.php'           => 3098,
        'arted-artist-on-register.php'    => 3103,
        'arted-email-options.php'         => 3102,
        'arted-settings-page.php'         => 3104,
        'arted-verification.php'          => 3119,
        'arted-profile-form.php'          => 3114,
        'arted-profile-save.php'          => 3115,
        'arted-artist-page.php'           => 3120,
        'arted-cabinet-styles.css'        => 3113,
        'arted-profile-styles.css'        => 3116,
        'arted-artist-page-styles.css'    => 3121,
        'tumbler-registration-style.css'  => 3101,
        'arted-works.php'                 => 3123,
        'arted-product-author.php'        => 3128,
        'arted-author-sync.php'           => 3129,
        'arted-translate-fields.js'       => 3069,
        'arted-github-sync.php'           => 3122,
    ];
}

function arted_github_fetch($filename) {
    $url      = 'https://raw.githubusercontent.com/DSavangard/arted/main/' . $filename;
    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) return ['ok' => false, 'error' => 'FETCH_FAILED: ' . $response->get_error_message()];
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) return ['ok' => false, 'error' => 'FETCH_HTTP_' . $code];
    $body = wp_remote_retrieve_body($response);
    // Убираем BOM и <?php — WPCode добавляет его сам через eval
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body);
    $body = ltrim($body);
    if (stripos($body, '<?php') === 0) {
        $body = substr($body, 5);
    } elseif (stripos($body, '<?') === 0) {
        $body = substr($body, 2);
    }
    $body = ltrim($body, " \t\r\n");
    return ['ok' => true, 'body' => $body];
}

// Коды возврата:
//   FETCH_FAILED / FETCH_HTTP_xxx  — не удалось получить файл с GitHub
//   POST_NOT_FOUND                 — сниппет с таким ID не существует в БД
//   ALREADY_UP_TO_DATE             — содержимое в БД уже совпадает с GitHub (ok=true)
//   DB_WRITE_FAILED                — $wpdb->update() вернул false
//   READBACK_MISMATCH              — записали, но при контрольном чтении БД вернула другой контент
//                                    (WPCode перехватывает запись или хранит не в post_content)
//   UPDATED                        — успешно обновлено (ok=true)
function arted_github_sync_one($filename, $post_id) {
    global $wpdb;

    $fetch = arted_github_fetch($filename);
    if (!$fetch['ok']) return ['ok' => false, 'code' => 'FETCH_FAILED', 'error' => $fetch['error']];

    $new_content = $fetch['body'];

    // Читаем текущее содержимое ДО обновления
    $current = $wpdb->get_var($wpdb->prepare(
        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
        (int) $post_id
    ));

    if ($current === null) {
        return ['ok' => false, 'code' => 'POST_NOT_FOUND', 'error' => 'POST_NOT_FOUND: сниппет #' . $post_id . ' отсутствует в БД'];
    }

    // Контент уже актуален — не нужно обновлять
    if (rtrim($current) === rtrim($new_content)) {
        return [
            'ok'      => true,
            'code'    => 'ALREADY_UP_TO_DATE',
            'preview' => substr($new_content, 0, 50),
            'bytes'   => strlen($new_content),
        ];
    }

    $old_len = strlen($current);
    $new_len = strlen($new_content);

    $rows = $wpdb->update(
        $wpdb->posts,
        [
            'post_content'      => $new_content,
            'post_modified'     => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', true),
        ],
        ['ID' => (int) $post_id],
        ['%s', '%s', '%s'],
        ['%d']
    );

    if ($rows === false) {
        return [
            'ok'    => false,
            'code'  => 'DB_WRITE_FAILED',
            'error' => 'DB_WRITE_FAILED: ' . ($wpdb->last_error ?: 'unknown error'),
        ];
    }

    // Контрольное чтение — проверяем, что в БД действительно то что записали
    $stored = $wpdb->get_var($wpdb->prepare(
        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
        (int) $post_id
    ));

    if (rtrim($stored) !== rtrim($new_content)) {
        $stored_len = strlen($stored ?? '');
        return [
            'ok'    => false,
            'code'  => 'READBACK_MISMATCH',
            'error' => sprintf(
                'READBACK_MISMATCH: отправлено %d байт, в БД %d байт. Первые 80 символов из БД: «%s»',
                $new_len,
                $stored_len,
                substr($stored ?? '', 0, 80)
            ),
        ];
    }

    // Сброс кэша WP
    clean_post_cache($post_id);
    wp_cache_flush();

    // Удаляем WPCode-транзиенты из options (на случай старых версий)
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpcode%' OR option_name LIKE '_transient_timeout_wpcode%'");

    // Стреляем save_post чтобы WPCode пересобрал compiled-кэш.
    // $wpdb->update() обходит хуки — WPCode не узнаёт об изменении без этого.
    $updated_post = get_post($post_id);
    if ($updated_post) {
        do_action('save_post',                         $post_id, $updated_post, true);
        do_action('save_post_' . $updated_post->post_type, $post_id, $updated_post, true);
    }

    // Пробуем вызвать WPCode API напрямую (WPCode 2.x)
    if (function_exists('wpcode') && is_object(wpcode())) {
        $wpcode = wpcode();
        if (isset($wpcode->cache) && method_exists($wpcode->cache, 'delete_all')) {
            $wpcode->cache->delete_all();
        }
        if (isset($wpcode->snippets) && method_exists($wpcode->snippets, 'get_snippets')) {
            // Форсируем перезагрузку списка сниппетов
            do_action('wpcode_cache_cleared');
        }
    }

    // Удаляем файлы кэша с диска (WPCode пишет compiled PHP-файлы)
    $cache_dirs = [
        WP_CONTENT_DIR . '/uploads/wpcode-cache/',
        WP_CONTENT_DIR . '/cache/wpcode/',
        WP_CONTENT_DIR . '/uploads/wpcode/',
    ];
    $files_deleted = 0;
    foreach ($cache_dirs as $dir) {
        if (!is_dir($dir)) continue;
        foreach (glob($dir . '*.php') ?: [] as $f) {
            if (@unlink($f)) $files_deleted++;
        }
    }

    return [
        'ok'           => true,
        'code'         => 'UPDATED',
        'preview'      => substr($new_content, 0, 50),
        'bytes'        => $new_len,
        'delta'        => $new_len - $old_len,
        'cache_purged' => $files_deleted,
    ];
}

function arted_github_sync_page() {
    $map     = arted_github_snippet_map();
    $results = [];

    if (isset($_POST['arted_sync_all']) && check_admin_referer('arted_github_sync')) {
        foreach ($map as $file => $id) {
            $results[$file] = arted_github_sync_one($file, $id);
        }
    }

    if (isset($_POST['arted_sync_one']) && check_admin_referer('arted_github_sync')) {
        $file = sanitize_text_field($_POST['arted_sync_file'] ?? '');
        if ($file && isset($map[$file])) {
            $results[$file] = arted_github_sync_one($file, $map[$file]);
        }
    }

    $first_id    = reset($map);
    $first_post  = get_post($first_id);
    $wpcode_type = $first_post ? $first_post->post_type : 'не найден';

    $errors   = array_filter($results, fn($r) => !$r['ok']);
    $updated  = array_filter($results, fn($r) => $r['ok'] && ($r['code'] ?? '') === 'UPDATED');
    $skipped  = array_filter($results, fn($r) => $r['ok'] && ($r['code'] ?? '') === 'ALREADY_UP_TO_DATE');

    ?>
    <div class="wrap">
        <h1>GitHub → WPCode Sync</h1>
        <p style="color:#666">Репозиторий: <a href="https://github.com/DSavangard/arted" target="_blank">github.com/DSavangard/arted</a> → ветка <code>main</code></p>
        <p style="color:#888;font-size:12px">WPCode post_type: <code><?= esc_html($wpcode_type) ?></code></p>

        <?php if ($results):
            $notice_type = $errors ? 'error' : 'success';
        ?>
        <div class="notice notice-<?= $notice_type ?>" style="padding:12px 15px">
            <?php if ($updated || $skipped): ?>
            <p style="margin:0 0 6px;font-weight:600">
                Обновлено: <?= count($updated) ?> &nbsp;·&nbsp;
                Без изменений: <?= count($skipped) ?> &nbsp;·&nbsp;
                Ошибок: <?= count($errors) ?>
            </p>
            <?php endif; ?>

            <?php foreach ($results as $file => $r):
                $code = $r['code'] ?? ($r['ok'] ? 'UPDATED' : 'ERROR');
                if (!$r['ok']): ?>
                    <p style="margin:4px 0">
                        <code style="background:#d63638;color:#fff;padding:1px 5px;border-radius:3px;font-size:11px"><?= esc_html($code) ?></code>
                        <strong><?= esc_html($file) ?></strong>
                        — <span style="color:#d63638"><?= esc_html($r['error']) ?></span>
                    </p>
                <?php elseif ($code === 'UPDATED'): ?>
                    <p style="margin:4px 0">
                        <code style="background:#00a32a;color:#fff;padding:1px 5px;border-radius:3px;font-size:11px">UPDATED</code>
                        <strong><?= esc_html($file) ?></strong>
                        — <?= esc_html($r['bytes']) ?> байт
                        <?php if (isset($r['delta']) && $r['delta'] !== 0): ?>
                            <span style="color:<?= $r['delta'] > 0 ? '#00a32a' : '#d63638' ?>">
                                (<?= $r['delta'] > 0 ? '+' : '' ?><?= $r['delta'] ?>)
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($r['cache_purged'])): ?>
                            <span style="color:#888;font-size:11px">· кэш: <?= (int)$r['cache_purged'] ?> файл(а) удалено</span>
                        <?php endif; ?>
                        &nbsp;<code style="color:#888;font-size:11px"><?= esc_html($r['preview'] ?? '') ?></code>
                    </p>
                <?php else: ?>
                    <p style="margin:4px 0">
                        <code style="background:#dba617;color:#fff;padding:1px 5px;border-radius:3px;font-size:11px">SKIP</code>
                        <strong><?= esc_html($file) ?></strong>
                        — уже актуален (<?= esc_html($r['bytes'] ?? 0) ?> байт)
                    </p>
                <?php endif;
            endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:20px">
            <?php wp_nonce_field('arted_github_sync'); ?>
            <button name="arted_sync_all" value="1" class="button button-primary button-large">
                ↓ Синхронизировать всё с GitHub
            </button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="max-width:760px">
            <thead>
                <tr>
                    <th>Файл в GitHub</th>
                    <th>WPCode ID</th>
                    <th style="width:80px">Результат</th>
                    <th style="width:110px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($map as $file => $id):
                $post    = get_post($id);
                $exists  = $post && $post->post_type === $wpcode_type;
                $ext     = pathinfo($file, PATHINFO_EXTENSION);
                $type_color = $ext === 'php' ? '#2271b1' : ($ext === 'js' ? '#c67800' : '#00a32a');
                $r       = $results[$file] ?? null;
                $code    = $r ? ($r['code'] ?? ($r['ok'] ? 'UPDATED' : 'ERROR')) : '';
                $badge_colors = [
                    'UPDATED'           => ['bg' => '#00a32a', 'fg' => '#fff'],
                    'ALREADY_UP_TO_DATE'=> ['bg' => '#dba617', 'fg' => '#fff'],
                    'READBACK_MISMATCH' => ['bg' => '#d63638', 'fg' => '#fff'],
                    'DB_WRITE_FAILED'   => ['bg' => '#d63638', 'fg' => '#fff'],
                    'POST_NOT_FOUND'    => ['bg' => '#d63638', 'fg' => '#fff'],
                    'FETCH_FAILED'      => ['bg' => '#d63638', 'fg' => '#fff'],
                ];
                $bc = $badge_colors[$code] ?? ['bg' => '#d63638', 'fg' => '#fff'];
                $short_code = [
                    'ALREADY_UP_TO_DATE' => 'SKIP',
                    'READBACK_MISMATCH'  => 'MISMATCH',
                    'DB_WRITE_FAILED'    => 'DB ERR',
                    'POST_NOT_FOUND'     => 'NOT FOUND',
                    'FETCH_FAILED'       => 'FETCH ERR',
                ][$code] ?? $code;
            ?>
                <tr>
                    <td>
                        <span style="color:<?= $type_color ?>;font-weight:600;font-size:11px;margin-right:6px"><?= strtoupper($ext) ?></span>
                        <a href="https://github.com/DSavangard/arted/blob/main/<?= esc_attr($file) ?>" target="_blank"><?= esc_html($file) ?></a>
                    </td>
                    <td>
                        <?php if ($exists): ?>
                            <a href="<?= admin_url('admin.php?page=wpcode-snippet-manager&snippet_id=' . $id) ?>">#<?= $id ?> — <?= esc_html($post->post_title) ?></a>
                        <?php else: ?>
                            <span style="color:#d63638">#<?= $id ?> не найден</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($code): ?>
                            <code title="<?= $r && !$r['ok'] ? esc_attr($r['error'] ?? '') : '' ?>"
                                  style="background:<?= $bc['bg'] ?>;color:<?= $bc['fg'] ?>;padding:2px 6px;border-radius:3px;font-size:11px;cursor:<?= !$r['ok'] ? 'help' : 'default' ?>">
                                <?= esc_html($short_code) ?>
                            </code>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="margin:0">
                            <?php wp_nonce_field('arted_github_sync'); ?>
                            <input type="hidden" name="arted_sync_file" value="<?= esc_attr($file) ?>">
                            <button name="arted_sync_one" value="1" class="button button-small" <?= !$exists ? 'disabled' : '' ?>>↓ Обновить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <details style="margin-top:16px;color:#888;font-size:12px">
            <summary style="cursor:pointer">Коды ошибок</summary>
            <table style="margin-top:8px;border-collapse:collapse;font-size:12px">
                <tr><td style="padding:3px 12px 3px 0"><code>UPDATED</code></td><td>Файл успешно обновлён и верифицирован readback-чтением</td></tr>
                <tr><td style="padding:3px 12px 3px 0"><code>SKIP</code></td><td>Содержимое в БД уже совпадает с GitHub — обновление не нужно</td></tr>
                <tr><td style="padding:3px 12px 3px 0"><code>MISMATCH</code></td><td>Запись прошла без ошибки, но контрольное чтение вернуло другой контент — WPCode перехватывает запись или хранит код не в post_content</td></tr>
                <tr><td style="padding:3px 12px 3px 0"><code>DB ERR</code></td><td>$wpdb->update() вернул false — ошибка MySQL</td></tr>
                <tr><td style="padding:3px 12px 3px 0"><code>NOT FOUND</code></td><td>Сниппет с указанным ID не существует в БД</td></tr>
                <tr><td style="padding:3px 12px 3px 0"><code>FETCH ERR</code></td><td>Не удалось получить файл с GitHub (сеть или HTTP-ошибка)</td></tr>
            </table>
        </details>

        <p style="margin-top:12px;color:#999;font-size:12px">
            Синхронизация перезаписывает код сниппета содержимым файла из GitHub (ветка main).<br>
            Статус (вкл/выкл) и настройки сниппета не меняются.
        </p>
    </div>
    <?php
}
