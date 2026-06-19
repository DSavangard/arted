<?php
// WPCode-ID: 3102
// WPCode-Name: arted-email-options
// ── Шаблоны писем RU/EN/FR ────────────────────────────────────────────────
add_action('admin_menu', function() {
    add_menu_page('Письма Gallery', 'Письма Gallery', 'manage_options',
        'arted-emails', 'arted_email_options_page', 'dashicons-email-alt2', 80);
});

function arted_email_options_page() {
    $langs   = ['ru' => 'Русский', 'en' => 'English', 'fr' => 'Français'];
    $current = isset($_GET['lang']) && array_key_exists($_GET['lang'], $langs) ? $_GET['lang'] : 'ru';

    if (!empty($_POST['arted_email_save']) && check_admin_referer('arted_email_save')) {
        $data = [
            'subject' => sanitize_text_field($_POST['email_subject'] ?? ''),
            'body'    => sanitize_textarea_field($_POST['email_body'] ?? ''),
            'cta'     => sanitize_text_field($_POST['email_cta'] ?? ''),
        ];
        update_option('arted_email_welcome_' . $current, $data);
        echo '<div class="notice notice-success"><p>Сохранено.</p></div>';
    }

    $saved = get_option('arted_email_welcome_' . $current, []);
    $page_url = admin_url('admin.php?page=arted-emails');
    ?>
    <div class="wrap">
        <h1>Шаблоны писем</h1>
        <ul class="subsubsub">
            <?php foreach ($langs as $code => $label): ?>
            <li><a href="<?= $page_url ?>&lang=<?= $code ?>" <?= $current === $code ? 'class="current"' : '' ?>><?= $label ?></a><?= $code !== 'fr' ? ' |' : '' ?></li>
            <?php endforeach; ?>
        </ul>
        <form method="post" style="margin-top:20px;">
            <?php wp_nonce_field('arted_email_save'); ?>
            <input type="hidden" name="arted_email_save" value="1">
            <table class="form-table">
                <tr><th>Тема письма</th><td><input type="text" name="email_subject" value="<?= esc_attr($saved['subject'] ?? '') ?>" class="large-text"></td></tr>
                <tr><th>Текст письма</th><td>
                    <textarea name="email_body" rows="10" class="large-text"><?= esc_textarea($saved['body'] ?? '') ?></textarea>
                    <p class="description">Переменные: {name} — имя художника, {url} — ссылка на кабинет</p>
                </td></tr>
                <tr><th>Текст кнопки</th><td><input type="text" name="email_cta" value="<?= esc_attr($saved['cta'] ?? '') ?>" class="regular-text"></td></tr>
            </table>
            <?php submit_button('Сохранить'); ?>
        </form>
    </div>
    <?php
}
