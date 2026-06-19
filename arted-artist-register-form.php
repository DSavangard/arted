<?php
// WPCode-ID: 3100
// WPCode-Name: arted-artist-register-form
// ── Переключатель роли на форме регистрации ───────────────────────────────
add_action('woocommerce_register_form', 'arted_register_role_switcher', 5);
function arted_register_role_switcher() {
    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $labels = [
        'ru' => ['buyer' => 'Покупатель', 'artist' => 'Художник', 'name' => 'Имя художника / псевдоним', 'city' => 'Город', 'contact' => 'Контакт (Instagram, сайт или телефон)'],
        'en' => ['buyer' => 'Buyer', 'artist' => 'Artist', 'name' => 'Artist name / pseudonym', 'city' => 'City', 'contact' => 'Contact (Instagram, website or phone)'],
        'fr' => ['buyer' => 'Acheteur', 'artist' => 'Artiste', 'name' => "Nom d'artiste / pseudonyme", 'city' => 'Ville', 'contact' => 'Contact (Instagram, site ou téléphone)'],
    ];
    $l = $labels[$lang] ?? $labels['ru'];
    ?>
    <div class="arted-role-switcher">
        <div class="arted-role-toggle">
            <button type="button" class="arted-role-btn active" data-role="buyer"><?= esc_html($l['buyer']) ?></button>
            <button type="button" class="arted-role-btn" data-role="artist"><?= esc_html($l['artist']) ?></button>
        </div>
        <input type="hidden" name="arted_user_role" id="arted_user_role" value="buyer">
    </div>
    <div id="arted-artist-fields" style="display:none;">
        <p class="woocommerce-form-row">
            <label><?= esc_html($l['name']) ?> <span class="required">*</span></label>
            <input type="text" name="arted_artist_name" class="woocommerce-Input" value="<?= esc_attr($_POST['arted_artist_name'] ?? '') ?>">
        </p>
        <p class="woocommerce-form-row">
            <label><?= esc_html($l['city']) ?></label>
            <input type="text" name="arted_artist_city" class="woocommerce-Input" value="<?= esc_attr($_POST['arted_artist_city'] ?? '') ?>">
        </p>
        <p class="woocommerce-form-row">
            <label><?= esc_html($l['contact']) ?></label>
            <input type="text" name="arted_artist_contact" class="woocommerce-Input" value="<?= esc_attr($_POST['arted_artist_contact'] ?? '') ?>">
        </p>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var roleInput  = document.getElementById('arted_user_role');
        var fields     = document.getElementById('arted-artist-fields');
        var btns       = document.querySelectorAll('.arted-role-btn');
        btns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                btns.forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                var role = this.getAttribute('data-role');
                roleInput.value = role;
                fields.style.display = role === 'artist' ? 'block' : 'none';
            });
        });
    });
    </script>
    <?php
}

// ── Валидация полей художника ─────────────────────────────────────────────
add_action('woocommerce_register_post', 'arted_validate_artist_fields', 10, 3);
function arted_validate_artist_fields($username, $email, $errors) {
    if (empty($_POST['arted_user_role']) || $_POST['arted_user_role'] !== 'artist') return;
    if (empty($_POST['arted_artist_name'])) {
        $errors->add('arted_artist_name', 'Укажите имя художника или псевдоним.');
    }
}

// ── Сохранение данных и установка роли ───────────────────────────────────
add_action('woocommerce_created_customer', 'arted_artist_save_data', 10, 3);
function arted_artist_save_data($customer_id, $new_customer_data, $password_generated) {
    if (empty($_POST['arted_user_role']) || $_POST['arted_user_role'] !== 'artist') return;

    set_transient('arted_make_artist_' . $customer_id, 1, 600);

    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    if (!empty($_COOKIE['arted_lang'])) $lang = sanitize_key($_COOKIE['arted_lang']);

    update_user_meta($customer_id, 'arted_artist_name',    sanitize_text_field($_POST['arted_artist_name']    ?? ''));
    update_user_meta($customer_id, 'arted_artist_city',    sanitize_text_field($_POST['arted_artist_city']    ?? ''));
    update_user_meta($customer_id, 'arted_artist_contact', sanitize_text_field($_POST['arted_artist_contact'] ?? ''));
    update_user_meta($customer_id, 'arted_is_artist',      1);
    update_user_meta($customer_id, 'arted_artist_verified', 0);
    update_user_meta($customer_id, 'arted_registration_date', current_time('mysql'));
    update_user_meta($customer_id, 'arted_lang', $lang);
}

// ── Установка роли после логина (через transient) ─────────────────────────
add_action('template_redirect', function() {
    if (!is_user_logged_in()) return;
    $user_id = get_current_user_id();
    if (get_transient('arted_make_artist_' . $user_id)) {
        $user = new WP_User($user_id);
        $user->set_role('artist');
        delete_transient('arted_make_artist_' . $user_id);
    }
});

// ── Резервная установка роли ──────────────────────────────────────────────
add_action('user_register', function($user_id) {
    if (empty($_POST['arted_user_role']) || $_POST['arted_user_role'] !== 'artist') return;
    $user = new WP_User($user_id);
    $user->set_role('artist');
}, 999);
