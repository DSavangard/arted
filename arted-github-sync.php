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
    // GitHub API — без CDN-кэша, всегда возвращает актуальную версию
    $url      = 'https://api.github.com/repos/DSavangard/arted/contents/' . rawurlencode($filename) . '?ref=main';
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => [
            'Accept'     => 'application/vnd.github.v3.raw',
            'User-Agent' => 'arted-sync/1.0',
        ],
    ]);
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

function arted_wpcode_resave($post_id, $code) {
    global $wpdb;
    $methods = [];

    // 1. Удаляем WPCode transients из wp_options
    $deleted = (int) $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_wpcode%'
            OR option_name LIKE '_transient_timeout_wpcode%'
            OR option_name LIKE '_site_transient_wpcode%'
            OR option_name LIKE '_site_transient_timeout_wpcode%'"
    );
    $methods[] = "transients:{$deleted}";

    // 2. file_cache — пробуем все возможные ключи
    if (function_exists('wpcode') && is_object(wpcode()) && isset(wpcode()->file_cache)) {
        $fc = wpcode()->file_cache;
        if (method_exists($fc, 'delete')) {
            foreach (['active', 'snippets', 'all', 'php', 'enabled', 'wpcode', $post_id] as $key) {
                $fc->delete($key);
            }
            $methods[] = 'file_cache::delete';
        }
    }

    // 3. WordPress object cache (включая Redis/Memcached)
    clean_post_cache($post_id);
    wp_cache_flush();
    $methods[] = 'wp_cache_flush';

    // 4. OPcache
    if (function_exists('opcache_reset')) {
        @opcache_reset();
        $methods[] = 'opcache_reset';
    }

    // 5. LiteSpeed Cache
    do_action('litespeed_purge_all');

    return [
        'ok'      => true,
        'methods' => implode(', ', $methods),
    ];
}

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

    // Контент уже актуален — resave вызывается один раз снаружи после всех синков
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

    // Шаг 1: пишем код напрямую в БД (wp_update_post срезает PHP через kses)
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
        return ['ok' => false, 'code' => 'DB_WRITE_FAILED', 'error' => 'DB_WRITE_FAILED: ' . ($wpdb->last_error ?: 'unknown')];
    }

    // Контрольное чтение
    $stored = $wpdb->get_var($wpdb->prepare(
        "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
        (int) $post_id
    ));

    if (rtrim($stored) !== rtrim($new_content)) {
        return [
            'ok'    => false,
            'code'  => 'READBACK_MISMATCH',
            'error' => sprintf(
                'READBACK_MISMATCH: отправлено %d байт, в БД %d байт. Первые 80 символов из БД: «%s»',
                $new_len,
                strlen($stored ?? ''),
                substr($stored ?? '', 0, 80)
            ),
        ];
    }

    clean_post_cache($post_id);

    // Шаг 2: симулируем нажатие Save в WPCode UI через внутренний HTTP-запрос.
    // GET страницы → извлекаем nonce → POST с тем же content.
    // Это единственный способ пройти через полный WPCode-пайплайн сохранения.
    $resave_info = arted_wpcode_resave($post_id, $new_content);

    // OPcache
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    return [
        'ok'           => true,
        'code'         => 'UPDATED',
        'preview'      => substr($new_content, 0, 50),
        'bytes'        => $new_len,
        'delta'        => $new_len - $old_len,
        'resave'       => $resave_info,
    ];
}

function arted_inspect_snippet($post_id) {
    global $wpdb;
    $post_id = (int) $post_id;
    $out = [];

    $post = get_post($post_id);
    if (!$post) return ['error' => 'Post #' . $post_id . ' not found'];

    $out['post_type']    = $post->post_type;
    $out['post_status']  = $post->post_status;
    $out['post_title']   = $post->post_title;
    $out['post_content_len']    = strlen($post->post_content);
    $out['post_content_preview'] = substr($post->post_content, 0, 200);

    // Все post_meta для этого сниппета
    $meta_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key",
        $post_id
    ));
    $meta = [];
    foreach ($meta_rows as $m) {
        $val = $m->meta_value;
        $meta[$m->meta_key] = strlen($val) > 200 ? substr($val, 0, 200) . '…[' . strlen($val) . ' bytes]' : $val;
    }
    $out['post_meta'] = $meta;

    // WPCode API
    $out['wpcode_function_exists'] = function_exists('wpcode');
    if (function_exists('wpcode') && is_object(wpcode())) {
        $wpcode = wpcode();
        $out['wpcode_classes'] = array_filter(get_object_vars($wpcode), fn($v) => is_object($v));
        $out['wpcode_classes'] = array_map('get_class', $out['wpcode_classes']);

        // Методы file_cache — ключевой объект
        if (isset($wpcode->file_cache)) {
            $out['file_cache_methods'] = get_class_methods($wpcode->file_cache);
        }

        // Пробуем получить сниппет через API
        if (isset($wpcode->snippets) && method_exists($wpcode->snippets, 'get_snippet')) {
            $snippet = $wpcode->snippets->get_snippet($post_id);
            if ($snippet) {
                $out['wpcode_snippet_class'] = get_class($snippet);
                $out['wpcode_snippet_methods'] = array_values(array_filter(
                    get_class_methods($snippet),
                    fn($m) => strpos($m, 'get_') === 0 || strpos($m, 'set_') === 0
                ));
                if (method_exists($snippet, 'get_code')) {
                    $code = $snippet->get_code();
                    $out['wpcode_snippet_get_code_len']     = strlen($code ?? '');
                    $out['wpcode_snippet_get_code_preview'] = substr($code ?? '', 0, 200);
                }
            }
        }
    }

    // Кастомные таблицы WPCode
    $tables = $wpdb->get_col("SHOW TABLES LIKE '%wpcode%'");
    $out['wpcode_tables'] = $tables;

    // Все обработчики save_post_wpcode — видим что именно WPCode вызывает при сохранении
    $out['save_post_wpcode_hooks'] = [];
    if (!empty($GLOBALS['wp_filter']['save_post_wpcode'])) {
        foreach ($GLOBALS['wp_filter']['save_post_wpcode'] as $priority => $callbacks) {
            foreach ($callbacks as $cb) {
                $fn = $cb['function'];
                if (is_array($fn)) {
                    $label = (is_object($fn[0]) ? get_class($fn[0]) : $fn[0]) . '::' . $fn[1];
                } elseif (is_string($fn)) {
                    $label = $fn;
                } else {
                    $label = 'Closure';
                }
                $out['save_post_wpcode_hooks'][$priority][] = $label;
            }
        }
    }

    // Что file_cache хранит для этого сниппета прямо сейчас
    if (function_exists('wpcode') && is_object(wpcode()) && isset(wpcode()->file_cache)) {
        $fc  = wpcode()->file_cache;
        $out['file_cache_get_result'] = method_exists($fc, 'get') ? $fc->get($post_id) : 'no get() method';
        $out['file_cache_get_string'] = method_exists($fc, 'get') ? $fc->get((string)$post_id) : 'no get() method';
    }

    return $out;
}

function arted_github_sync_page() {
    $map     = arted_github_snippet_map();
    $results = [];
    $inspect = null;

    $resave_result = null;

    if (isset($_POST['arted_sync_all']) && check_admin_referer('arted_github_sync')) {
        foreach ($map as $file => $id) {
            $results[$file] = arted_github_sync_one($file, $id);
        }
        $resave_result = arted_wpcode_resave(0, '');
    }

    if (isset($_POST['arted_sync_one']) && check_admin_referer('arted_github_sync')) {
        $file = sanitize_text_field($_POST['arted_sync_file'] ?? '');
        if ($file && isset($map[$file])) {
            $results[$file] = arted_github_sync_one($file, $map[$file]);
            $resave_result  = arted_wpcode_resave($map[$file], '');
        }
    }

    if (isset($_POST['arted_rebuild_cache']) && check_admin_referer('arted_github_sync')) {
        $resave_result = arted_wpcode_resave(0, '');
    }

    if (isset($_POST['arted_inspect']) && check_admin_referer('arted_github_sync')) {
        $inspect_id = (int)($_POST['arted_inspect_id'] ?? 0);
        if ($inspect_id) {
            $inspect = ['id' => $inspect_id, 'data' => arted_inspect_snippet($inspect_id)];
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
                        <?php if (!empty($r['resave'])): $rs = $r['resave']; ?>
                            · cache: <?= $rs['ok'] ? '✅' : '⚠' ?>
                            <span style="color:#888;font-size:11px"><?= esc_html($rs['methods'] ?? '') ?></span>
                        <?php endif; ?>
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

        <?php if ($resave_result): ?>
        <div style="background:#f6f7f7;border:1px solid #c3c4c7;padding:8px 12px;margin-bottom:16px;border-radius:3px;font-size:12px">
            Кэш WPCode: <?= $resave_result['ok'] ? '✅' : '⚠️' ?>
            <span style="color:#666"><?= esc_html($resave_result['methods'] ?? '') ?></span>
        </div>
        <?php endif; ?>

        <?php if ($inspect): ?>
        <div style="background:#f0f0f0;border:1px solid #ccc;padding:16px;margin-bottom:20px;border-radius:4px">
            <h3 style="margin:0 0 12px">🔍 Диагностика сниппета #<?= (int)$inspect['id'] ?></h3>
            <table style="border-collapse:collapse;font-size:12px;width:100%">
            <?php foreach ($inspect['data'] as $key => $val): ?>
                <tr>
                    <td style="padding:4px 12px 4px 0;font-weight:600;white-space:nowrap;vertical-align:top;color:#555"><?= esc_html($key) ?></td>
                    <td style="padding:4px 0;word-break:break-all">
                        <?php if (is_array($val)): ?>
                            <pre style="margin:0;font-size:11px;background:#fff;padding:6px;border-radius:3px;overflow:auto;max-height:200px"><?= esc_html(print_r($val, true)) ?></pre>
                        <?php else: ?>
                            <code style="font-size:11px"><?= esc_html((string)$val) ?></code>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:20px;display:flex;gap:8px;align-items:center">
            <?php wp_nonce_field('arted_github_sync'); ?>
            <button name="arted_sync_all" value="1" class="button button-primary button-large">
                ↓ Синхронизировать всё с GitHub
            </button>
            <button name="arted_rebuild_cache" value="1" class="button button-large">
                ♻ Сбросить кэш WPCode
            </button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="max-width:860px">
            <thead>
                <tr>
                    <th>Файл в GitHub</th>
                    <th>WPCode ID</th>
                    <th style="width:80px">Результат</th>
                    <th style="width:170px"></th>
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
                    <td style="white-space:nowrap">
                        <form method="post" style="margin:0;display:inline">
                            <?php wp_nonce_field('arted_github_sync'); ?>
                            <input type="hidden" name="arted_sync_file" value="<?= esc_attr($file) ?>">
                            <button name="arted_sync_one" value="1" class="button button-small" <?= !$exists ? 'disabled' : '' ?>>↓ Обновить</button>
                        </form>
                        <?php if ($exists): ?>
                        <form method="post" style="margin:0;display:inline;margin-left:4px">
                            <?php wp_nonce_field('arted_github_sync'); ?>
                            <input type="hidden" name="arted_inspect_id" value="<?= $id ?>">
                            <button name="arted_inspect" value="1" class="button button-small" style="color:#666">🔍</button>
                        </form>
                        <?php endif; ?>
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
