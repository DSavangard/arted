<?php
// ── GitHub → WPCode автосинхронизация ────────────────────────────────────

add_action('admin_menu', function() {
    add_submenu_page(
        'wpcode',
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
        'arted-works.php'                 => 0,
        'arted-github-sync.php'           => 3122,
    ];
}

function arted_github_fetch($filename) {
    $url      = 'https://raw.githubusercontent.com/DSavangard/arted/main/' . $filename;
    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) return ['ok' => false, 'error' => $response->get_error_message()];
    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) return ['ok' => false, 'error' => 'HTTP ' . $code];
    return ['ok' => true, 'body' => wp_remote_retrieve_body($response)];
}

function arted_github_sync_one($filename, $post_id) {
    $result = arted_github_fetch($filename);
    if (!$result['ok']) return $result;

    $updated = wp_update_post([
        'ID'           => (int) $post_id,
        'post_content' => $result['body'],
    ], true);

    if (is_wp_error($updated)) return ['ok' => false, 'error' => $updated->get_error_message()];
    return ['ok' => true];
}

function arted_github_sync_page() {
    $map     = arted_github_snippet_map();
    $results = [];

    // Синхронизировать всё
    if (isset($_POST['arted_sync_all']) && check_admin_referer('arted_github_sync')) {
        foreach ($map as $file => $id) {
            $results[$file] = arted_github_sync_one($file, $id);
        }
    }

    // Синхронизировать один файл
    if (isset($_POST['arted_sync_one']) && check_admin_referer('arted_github_sync')) {
        $file = sanitize_text_field($_POST['arted_sync_file'] ?? '');
        if ($file && isset($map[$file])) {
            $results[$file] = arted_github_sync_one($file, $map[$file]);
        }
    }

    // Debug: определяем реальный post_type WPCode
    $first_id   = reset($map);
    $first_post = get_post($first_id);
    $wpcode_type = $first_post ? $first_post->post_type : 'не найден';

    ?>
    <div class="wrap">
        <h1>GitHub → WPCode Sync</h1>
        <p style="color:#666">Репозиторий: <a href="https://github.com/DSavangard/arted" target="_blank">github.com/DSavangard/arted</a> → ветка <code>main</code></p>
        <p style="color:#888;font-size:12px">WPCode post_type: <code><?= esc_html($wpcode_type) ?></code></p>

        <?php if ($results): ?>
        <div class="notice notice-<?= count(array_filter($results, fn($r) => !$r['ok'])) ? 'warning' : 'success' ?>" style="padding:10px 15px">
            <?php foreach ($results as $file => $r): ?>
                <p><?= $r['ok']
                    ? '✅ <strong>' . esc_html($file) . '</strong> — обновлён'
                    : '❌ <strong>' . esc_html($file) . '</strong> — ' . esc_html($r['error']) ?>
                </p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" style="margin-bottom:20px">
            <?php wp_nonce_field('arted_github_sync'); ?>
            <button name="arted_sync_all" value="1" class="button button-primary button-large">
                ↓ Синхронизировать всё с GitHub
            </button>
        </form>

        <table class="wp-list-table widefat fixed striped" style="max-width:700px">
            <thead>
                <tr>
                    <th>Файл в GitHub</th>
                    <th>WPCode ID</th>
                    <th style="width:120px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($map as $file => $id):
                $post    = get_post($id);
                $exists  = $post && $post->post_type === $wpcode_type;
                $ext     = pathinfo($file, PATHINFO_EXTENSION);
                $type_color = $ext === 'php' ? '#2271b1' : '#00a32a';
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

        <p style="margin-top:20px;color:#999;font-size:12px">
            Синхронизация перезаписывает код сниппета содержимым файла из GitHub (ветка main).<br>
            Статус (вкл/выкл) и настройки сниппета не меняются.
        </p>
    </div>
    <?php
}
