<?php
// ── Сохранение формы профиля ──────────────────────────────────────────────
add_action('init', 'arted_handle_profile_save');
function arted_handle_profile_save() {
    if (!is_user_logged_in()) return;
    $user = wp_get_current_user();
    if (!in_array('artist', (array) $user->roles)) return;
    if (empty($_POST['arted_profile_nonce'])) return;
    if (!wp_verify_nonce($_POST['arted_profile_nonce'], 'arted_profile_save')) return;

    $user_id = get_current_user_id();
    $step    = (int)($_POST['arted_profile_step'] ?? 1);
    $enc_key = defined('AUTH_KEY') ? AUTH_KEY : 'arted_key';

    // ── Шаг 1: Личность ──────────────────────────────────────────────────
    if ($step === 1) {
        $fields = ['arted_artist_name', 'arted_artist_city', 'arted_artist_country', 'arted_video'];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) update_user_meta($user_id, $f, sanitize_text_field($_POST[$f]));
        }
        if (!empty($_POST['arted_bio'])) {
            update_user_meta($user_id, 'arted_bio', sanitize_textarea_field($_POST['arted_bio']));
        }
        if (!empty($_POST['arted_artist_name'])) {
            wp_update_user(['ID' => $user_id, 'display_name' => sanitize_text_field($_POST['arted_artist_name'])]);
        }
        if (!empty($_FILES['arted_photo_file']['tmp_name'])) {
            $attach_id = arted_upload_and_compress($_FILES['arted_photo_file'], $user_id, 800, 800);
            if ($attach_id && !is_wp_error($attach_id)) {
                $old_id = get_user_meta($user_id, 'arted_photo_id', true);
                if ($old_id) wp_delete_attachment($old_id, true);
                update_user_meta($user_id, 'arted_photo_id', $attach_id);
            }
        }
    }

    // ── Шаг 2: Творческий мир ────────────────────────────────────────────
    if ($step === 2) {
        if (isset($_POST['arted_styles_json'])) {
            $styles = json_decode(stripslashes($_POST['arted_styles_json']), true) ?: [];
            update_user_meta($user_id, 'arted_styles', array_map('sanitize_text_field', $styles));
        }
        if (isset($_POST['arted_materials_json'])) {
            $materials = json_decode(stripslashes($_POST['arted_materials_json']), true) ?: [];
            update_user_meta($user_id, 'arted_materials', array_map('sanitize_text_field', $materials));
        }
        foreach (['arted_inspiration', 'arted_influences'] as $f) {
            if (isset($_POST[$f])) update_user_meta($user_id, $f, sanitize_textarea_field($_POST[$f]));
        }
    }

    // ── Шаг 3: Мастерская ────────────────────────────────────────────────
    if ($step === 3) {
        if (!empty($_POST['arted_workshop_desc'])) {
            update_user_meta($user_id, 'arted_workshop_desc', sanitize_textarea_field($_POST['arted_workshop_desc']));
        }
        foreach (['workshop', 'personal'] as $gallery) {
            $ids_string = $_POST['arted_' . $gallery . '_ids'] ?? '';
            $ids = array_filter(array_map('intval', explode(',', $ids_string)));
            update_user_meta($user_id, 'arted_' . $gallery . '_photo_ids', $ids);
        }
    }

    // ── Шаг 4: Достижения + Реквизиты ────────────────────────────────────
    if ($step === 4) {
        foreach (['arted_education', 'arted_exhibitions', 'arted_press', 'arted_awards'] as $f) {
            if (isset($_POST[$f])) update_user_meta($user_id, $f, sanitize_textarea_field($_POST[$f]));
        }
        foreach (['arted_bank_account', 'arted_bank_bik', 'arted_bank_name'] as $f) {
            if (isset($_POST[$f])) {
                $val = sanitize_text_field($_POST[$f]);
                update_user_meta($user_id, $f, $val ? arted_encrypt($val, $enc_key) : '');
            }
        }

        // Автоматически отправляем на верификацию после шага 4
        $verified = get_user_meta($user_id, 'arted_artist_verified', true);
        if ($verified != 1 && $verified != 2) {
            update_user_meta($user_id, 'arted_artist_verified', 2);
            update_user_meta($user_id, 'arted_verification_requested', time());
            if (function_exists('arted_verification_send_tg')) {
                arted_verification_send_tg($user_id);
            }
        }
    }

    arted_add_event($user_id, 'blue', 'Профиль обновлён', date_i18n('d.m.Y'));

    if ($step === 4) {
        $url = add_query_arg('profile_saved', '1', wc_get_account_endpoint_url('artist-dashboard'));
    } else {
        $url = add_query_arg([
            'profile_step'  => $step + 1,
            'profile_saved' => '1',
        ], wc_get_account_endpoint_url('artist-profile'));
    }

    wp_safe_redirect($url);
    exit;
}

// ── AJAX загрузка фото галереи ────────────────────────────────────────────
add_action('wp_ajax_arted_upload_gallery_photo', 'arted_ajax_upload_gallery_photo');
function arted_ajax_upload_gallery_photo() {
    if (!is_user_logged_in() || !arted_is_artist()) wp_send_json_error('unauthorized');
    if (!check_ajax_referer('arted_profile_save', 'nonce', false)) wp_send_json_error('nonce');

    $gallery = sanitize_key($_POST['gallery'] ?? '');
    $max     = (int)($_POST['max'] ?? 10);
    $user_id = get_current_user_id();

    if (empty($_FILES['file']['tmp_name'])) wp_send_json_error('no_file');

    $current_ids = get_user_meta($user_id, 'arted_' . $gallery . '_photo_ids', true) ?: [];
    if (count((array)$current_ids) >= $max) wp_send_json_error('max_reached');

    $attach_id = arted_upload_and_compress($_FILES['file'], $user_id, 1920, 1920);
    if (is_wp_error($attach_id)) wp_send_json_error($attach_id->get_error_message());

    $current_ids   = (array)$current_ids;
    $current_ids[] = $attach_id;
    update_user_meta($user_id, 'arted_' . $gallery . '_photo_ids', $current_ids);

    wp_send_json_success([
        'id'  => $attach_id,
        'url' => wp_get_attachment_image_url($attach_id, 'thumbnail'),
    ]);
}

// ── AJAX удаление фото галереи ────────────────────────────────────────────
add_action('wp_ajax_arted_remove_gallery_photo', 'arted_ajax_remove_gallery_photo');
function arted_ajax_remove_gallery_photo() {
    if (!is_user_logged_in() || !arted_is_artist()) wp_send_json_error('unauthorized');
    if (!check_ajax_referer('arted_profile_save', 'nonce', false)) wp_send_json_error('nonce');

    $gallery  = sanitize_key($_POST['gallery'] ?? '');
    $photo_id = (int)($_POST['photo_id'] ?? 0);
    $user_id  = get_current_user_id();

    $ids = (array)(get_user_meta($user_id, 'arted_' . $gallery . '_photo_ids', true) ?: []);
    $ids = array_values(array_filter($ids, fn($id) => (int)$id !== $photo_id));
    update_user_meta($user_id, 'arted_' . $gallery . '_photo_ids', $ids);
    wp_delete_attachment($photo_id, true);

    wp_send_json_success();
}

// ── Загрузка и сжатие изображения ────────────────────────────────────────
function arted_upload_and_compress($file, $user_id, $max_w, $max_h) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file['type'], $allowed)) return new WP_Error('type', 'Недопустимый тип файла');
    if ($file['size'] > 20 * 1024 * 1024) return new WP_Error('size', 'Файл слишком большой (макс. 20MB)');

    $uploaded = wp_handle_upload($file, ['test_form' => false]);
    if (isset($uploaded['error'])) return new WP_Error('upload', $uploaded['error']);

    $compressed = arted_compress_image($uploaded['file'], $uploaded['type'], $max_w, $max_h);
    if ($compressed) {
        @copy($compressed, $uploaded['file']);
        @unlink($compressed);
        $uploaded['type'] = 'image/jpeg';
    }

    $attach_id = wp_insert_attachment([
        'post_mime_type' => $uploaded['type'],
        'post_title'     => sanitize_file_name($file['name']),
        'post_status'    => 'inherit',
        'post_author'    => $user_id,
    ], $uploaded['file']);

    if (is_wp_error($attach_id)) return $attach_id;
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $uploaded['file']));
    return $attach_id;
}

function arted_compress_image($path, $mime, $max_w, $max_h, $quality = 82) {
    if (!function_exists('imagecreatefromjpeg')) return false;

    switch ($mime) {
        case 'image/jpeg': $img = imagecreatefromjpeg($path); break;
        case 'image/png':  $img = imagecreatefrompng($path);  break;
        case 'image/webp': $img = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false; break;
        default: return false;
    }
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);

    if ($w > $max_w || $h > $max_h) {
        $ratio   = min($max_w / $w, $max_h / $h);
        $new_w   = (int)($w * $ratio);
        $new_h   = (int)($h * $ratio);
        $resized = imagecreatetruecolor($new_w, $new_h);
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'arted_') . '.jpg';
    imagejpeg($img, $tmp, $quality);
    imagedestroy($img);
    return $tmp;
}

// ── Добавление события в ленту ────────────────────────────────────────────
function arted_add_event($user_id, $type, $text, $date = '') {
    $events   = get_user_meta($user_id, 'arted_events', true) ?: [];
    $events[] = [
        'type' => $type,
        'text' => $text,
        'date' => $date ?: date_i18n('d.m.Y'),
    ];
    if (count($events) > 20) $events = array_slice($events, -20);
    update_user_meta($user_id, 'arted_events', $events);
}

// ── AJAX для галерей (JS) ─────────────────────────────────────────────────
add_action('wp_footer', 'arted_gallery_js');
function arted_gallery_js() {
    if (!is_account_page() || !arted_is_artist()) return;
    $nonce = wp_create_nonce('arted_profile_save');
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        var nonce   = '<?php echo esc_js($nonce); ?>';

        document.querySelectorAll('[data-gallery]').forEach(function(input) {
            if (input.tagName !== 'INPUT') return;
            input.addEventListener('change', function() {
                var gallery = this.getAttribute('data-gallery');
                var max     = parseInt(this.getAttribute('data-max'));
                var files   = Array.from(this.files);

                files.forEach(function(file) {
                    var fd = new FormData();
                    fd.append('action',  'arted_upload_gallery_photo');
                    fd.append('nonce',   nonce);
                    fd.append('gallery', gallery);
                    fd.append('max',     max);
                    fd.append('file',    file);

                    var container = document.getElementById('arted-gallery-' + gallery);
                    var addBtn    = container.querySelector('.arted-gallery-add');
                    var spinner   = document.createElement('div');
                    spinner.className = 'arted-gallery-spinner';
                    spinner.textContent = '…';
                    container.insertBefore(spinner, addBtn);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            spinner.remove();
                            if (!data.success) { alert(data.data || 'Ошибка загрузки'); return; }
                            var item = document.createElement('div');
                            item.className = 'arted-gallery-item';
                            item.setAttribute('data-id', data.data.id);
                            item.innerHTML = '<img src="' + data.data.url + '"><button type="button" class="arted-gallery-remove" data-gallery="' + gallery + '">×</button>';
                            container.insertBefore(item, addBtn);
                            updateIdsInput(gallery);
                            attachRemove(item.querySelector('.arted-gallery-remove'));
                        })
                        .catch(function() {
                            spinner.remove();
                            alert('Ошибка соединения');
                        });
                });
                this.value = '';
            });
        });

        document.querySelectorAll('.arted-gallery-remove').forEach(attachRemove);

        function attachRemove(btn) {
            btn.addEventListener('click', function() {
                var item    = this.closest('.arted-gallery-item');
                var gallery = this.getAttribute('data-gallery');
                var photoId = item.getAttribute('data-id');

                var fd = new FormData();
                fd.append('action',   'arted_remove_gallery_photo');
                fd.append('nonce',    nonce);
                fd.append('gallery',  gallery);
                fd.append('photo_id', photoId);

                fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            item.remove();
                            updateIdsInput(gallery);
                        }
                    });
            });
        }

        function updateIdsInput(gallery) {
            var container = document.getElementById('arted-gallery-' + gallery);
            var ids = Array.from(container.querySelectorAll('.arted-gallery-item'))
                          .map(function(el) { return el.getAttribute('data-id'); });
            var input = document.getElementById('arted-ids-' + gallery);
            if (input) input.value = ids.join(',');
        }
    });
    </script>
    <?php
}
