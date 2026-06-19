<?php
// ── Вкладка: Мои работы ───────────────────────────────────────────────────
function arted_tab_works() {
    $user_id = get_current_user_id();
    $lang    = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';

    $t = [
        'ru' => [
            'title'      => 'Мои работы',
            'add'        => '+ Добавить работу',
            'empty'      => 'Работ пока нет. Добавьте первую!',
            'published'  => 'Опубликована',
            'pending'    => 'На модерации',
            'draft'      => 'Черновик',
            'edit'       => 'Редактировать',
            'delete'     => 'Удалить',
            'confirm'    => 'Удалить работу?',
        ],
        'en' => [
            'title'      => 'My Works',
            'add'        => '+ Add Work',
            'empty'      => 'No works yet. Add your first!',
            'published'  => 'Published',
            'pending'    => 'Under review',
            'draft'      => 'Draft',
            'edit'       => 'Edit',
            'delete'     => 'Delete',
            'confirm'    => 'Delete this work?',
        ],
        'fr' => [
            'title'      => 'Mes œuvres',
            'add'        => '+ Ajouter une œuvre',
            'empty'      => "Aucune œuvre pour l'instant. Ajoutez la première!",
            'published'  => 'Publiée',
            'pending'    => 'En modération',
            'draft'      => 'Brouillon',
            'edit'       => 'Modifier',
            'delete'     => 'Supprimer',
            'confirm'    => 'Supprimer cette œuvre?',
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    $works = get_posts([
        'post_type'      => 'product',
        'author'         => $user_id,
        'post_status'    => ['publish', 'pending', 'draft'],
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $add_url = wc_get_account_endpoint_url('artist-add-work');

    echo '<div class="arted-tab-content">';
    echo '<div class="arted-works-header">';
    echo '<h2 class="arted-works-title">' . esc_html($l['title']) . ' <span class="arted-works-count">' . count($works) . '</span></h2>';
    echo '<a href="' . esc_url($add_url) . '" class="arted-btn-primary">' . esc_html($l['add']) . '</a>';
    echo '</div>';

    if (empty($works)) {
        echo '<div class="arted-works-empty">';
        echo '<p>' . esc_html($l['empty']) . '</p>';
        echo '<a href="' . esc_url($add_url) . '" class="arted-btn-primary">' . esc_html($l['add']) . '</a>';
        echo '</div>';
    } else {
        echo '<div class="arted-works-grid">';
        foreach ($works as $work) {
            $product  = wc_get_product($work->ID);
            if (!$product) continue;
            $img_id   = $product->get_image_id();
            $img_url  = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
            $price    = $product->get_price();
            $edit_url = add_query_arg('work_id', $work->ID, $add_url);

            $status = $work->post_status;
            $status_map = [
                'publish' => ['label' => $l['published'], 'cls' => 'published'],
                'pending' => ['label' => $l['pending'],   'cls' => 'pending'],
                'draft'   => ['label' => $l['draft'],     'cls' => 'draft'],
            ];
            $s = $status_map[$status] ?? $status_map['draft'];

            echo '<div class="arted-work-card">';
            echo '<a href="' . esc_url($edit_url) . '" class="arted-work-card-img">';
            if ($img_url) {
                echo '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($product->get_name()) . '">';
            } else {
                echo '<div class="arted-work-card-placeholder">+</div>';
            }
            echo '</a>';
            echo '<div class="arted-work-card-body">';
            echo '<div class="arted-work-card-status ' . $s['cls'] . '">' . esc_html($s['label']) . '</div>';
            echo '<div class="arted-work-card-name">' . esc_html($product->get_name()) . '</div>';
            if ($price) echo '<div class="arted-work-card-price">' . wc_price($price) . '</div>';
            echo '<div class="arted-work-card-actions">';
            echo '<a href="' . esc_url($edit_url) . '" class="arted-btn-secondary arted-btn-sm">' . esc_html($l['edit']) . '</a>';
            echo '<button class="arted-btn-danger arted-btn-sm arted-delete-work" data-id="' . $work->ID . '" data-confirm="' . esc_attr($l['confirm']) . '">' . esc_html($l['delete']) . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '</div>';

    $nonce = wp_create_nonce('arted_delete_work');
    ?>
    <script>
    document.querySelectorAll('.arted-delete-work').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm(this.getAttribute('data-confirm'))) return;
            var id   = this.getAttribute('data-id');
            var card = this.closest('.arted-work-card');
            var fd   = new FormData();
            fd.append('action',  'arted_delete_work');
            fd.append('nonce',   '<?= esc_js($nonce) ?>');
            fd.append('work_id', id);
            fetch('<?= esc_js(admin_url('admin-ajax.php')) ?>', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) { if (data.success) card.remove(); });
        });
    });
    </script>
    <?php
}

// ── Вкладка: Добавить / Редактировать работу ──────────────────────────────
function arted_tab_add_work() {
    $user_id  = get_current_user_id();
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $work_id  = isset($_GET['work_id']) ? (int)$_GET['work_id'] : 0;

    // Проверяем что работа принадлежит художнику
    if ($work_id) {
        $work = get_post($work_id);
        if (!$work || (int)$work->post_author !== $user_id || $work->post_type !== 'product') {
            $work_id = 0;
        }
    }

    $product     = $work_id ? wc_get_product($work_id) : null;
    $is_edit     = (bool)$product;

    $t = [
        'ru' => [
            'add_title'   => 'Новая работа',
            'edit_title'  => 'Редактировать работу',
            'name'        => 'Название работы',
            'name_hint'   => 'Как называется эта работа?',
            'desc'        => 'Описание',
            'desc_hint'   => 'Техника, размер, год, история создания...',
            'price'       => 'Цена (₽)',
            'price_hint'  => 'Цена для покупателя',
            'photo'       => 'Главное фото',
            'photo_hint'  => 'Основное фото работы (обязательно)',
            'gallery'     => 'Дополнительные фото',
            'gallery_hint'=> 'До 8 фото с разных ракурсов',
            'save'        => 'Отправить на модерацию',
            'update'      => 'Сохранить изменения',
            'back'        => '← Мои работы',
            'saved'       => 'Работа сохранена и отправлена на модерацию',
            'updated'     => 'Изменения сохранены',
            'category'    => 'Категория',
        ],
        'en' => [
            'add_title'   => 'New Work',
            'edit_title'  => 'Edit Work',
            'name'        => 'Work title',
            'name_hint'   => 'What is this work called?',
            'desc'        => 'Description',
            'desc_hint'   => 'Technique, size, year, story behind the work...',
            'price'       => 'Price',
            'price_hint'  => 'Price for the buyer',
            'photo'       => 'Main photo',
            'photo_hint'  => 'Primary photo of the work (required)',
            'gallery'     => 'Additional photos',
            'gallery_hint'=> 'Up to 8 photos from different angles',
            'save'        => 'Submit for review',
            'update'      => 'Save changes',
            'back'        => '← My Works',
            'saved'       => 'Work saved and submitted for review',
            'updated'     => 'Changes saved',
            'category'    => 'Category',
        ],
        'fr' => [
            'add_title'   => 'Nouvelle œuvre',
            'edit_title'  => "Modifier l'œuvre",
            'name'        => "Titre de l'œuvre",
            'name_hint'   => "Comment s'appelle cette œuvre?",
            'desc'        => 'Description',
            'desc_hint'   => 'Technique, taille, année, histoire de création...',
            'price'       => 'Prix',
            'price_hint'  => "Prix pour l'acheteur",
            'photo'       => 'Photo principale',
            'photo_hint'  => "Photo principale de l'œuvre (obligatoire)",
            'gallery'     => 'Photos supplémentaires',
            'gallery_hint'=> "Jusqu'à 8 photos sous différents angles",
            'save'        => 'Soumettre pour modération',
            'update'      => 'Enregistrer les modifications',
            'back'        => '← Mes œuvres',
            'saved'       => 'Œuvre enregistrée et soumise pour modération',
            'updated'     => 'Modifications enregistrées',
            'category'    => 'Catégorie',
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    $title     = $product ? $product->get_name() : '';
    $desc      = $product ? $product->get_description() : '';
    $price     = $product ? $product->get_regular_price() : '';
    $img_id    = $product ? $product->get_image_id() : 0;
    $img_url   = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
    $gallery_ids = $product ? $product->get_gallery_image_ids() : [];

    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0]);
    $current_cats = $product ? wp_get_post_terms($work_id, 'product_cat', ['fields' => 'ids']) : [];

    $works_url = wc_get_account_endpoint_url('artist-works');

    echo '<div class="arted-tab-content arted-addwork-wrap">';

    if (!empty($_GET['work_saved'])) {
        $msg = $_GET['work_saved'] === 'updated' ? $l['updated'] : $l['saved'];
        echo '<div class="arted-profile-saved">' . esc_html($msg) . ' ✓</div>';
    }

    echo '<div class="arted-addwork-header">';
    echo '<a href="' . esc_url($works_url) . '" class="arted-back-link">' . esc_html($l['back']) . '</a>';
    echo '<h2 class="arted-addwork-title">' . esc_html($is_edit ? $l['edit_title'] : $l['add_title']) . '</h2>';
    echo '</div>';

    echo '<form class="arted-addwork-form" method="post" enctype="multipart/form-data">';
    echo wp_nonce_field('arted_save_work', 'arted_work_nonce', true, false);
    echo '<input type="hidden" name="arted_work_id" value="' . $work_id . '">';

    // Название
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label">' . esc_html($l['name']) . ' <span style="color:var(--cab-accent)">*</span></label>';
    echo '<input type="text" name="work_title" class="arted-input" value="' . esc_attr($title) . '" placeholder="' . esc_attr($l['name_hint']) . '" required>';
    echo '</div>';

    // Описание
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label">' . esc_html($l['desc']) . '</label>';
    echo '<textarea name="work_desc" class="arted-textarea" rows="5" placeholder="' . esc_attr($l['desc_hint']) . '">' . esc_textarea($desc) . '</textarea>';
    echo '</div>';

    // Цена
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label">' . esc_html($l['price']) . '</label>';
    echo '<input type="number" name="work_price" class="arted-input arted-input-price" value="' . esc_attr($price) . '" placeholder="' . esc_attr($l['price_hint']) . '" min="0" step="1">';
    echo '</div>';

    // Категория
    if (!empty($categories)) {
        echo '<div class="arted-field">';
        echo '<label class="arted-field-label">' . esc_html($l['category']) . '</label>';
        echo '<select name="work_category[]" multiple class="arted-input arted-select">';
        foreach ($categories as $cat) {
            $selected = in_array($cat->term_id, $current_cats) ? ' selected' : '';
            echo '<option value="' . $cat->term_id . '"' . $selected . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    // Главное фото
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label">' . esc_html($l['photo']) . ' <span style="color:var(--cab-accent)">*</span></label>';
    echo '<p class="arted-field-hint">' . esc_html($l['photo_hint']) . '</p>';
    echo '<div class="arted-work-main-photo">';
    echo '<div class="arted-photo-preview" id="arted-work-photo-preview">';
    if ($img_url) {
        echo '<img src="' . esc_url($img_url) . '" alt="">';
    } else {
        echo '<div class="arted-work-photo-placeholder">+</div>';
    }
    echo '</div>';
    echo '<label class="arted-upload-btn" for="work_photo_file">Выбрать фото</label>';
    echo '<input type="file" id="work_photo_file" name="work_photo_file" accept="image/*" style="display:none">';
    if ($img_id) echo '<input type="hidden" name="work_photo_id" value="' . $img_id . '">';
    echo '</div>';
    echo '</div>';

    // Галерея работы
    arted_photo_gallery_field('work_gallery', $l['gallery'], $l['gallery_hint'], $gallery_ids, 8);

    echo '<div class="arted-form-nav">';
    echo '<span></span>';
    echo '<button type="submit" class="arted-btn-primary">' . esc_html($is_edit ? $l['update'] : $l['save']) . '</button>';
    echo '</div>';

    echo '</form></div>';

    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var photoInput = document.getElementById('work_photo_file');
        if (photoInput) {
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var p = document.getElementById('arted-work-photo-preview');
                        p.innerHTML = '<img src="' + e.target.result + '" alt="">';
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
    </script>
    <?php
}

// ── Сохранение работы ─────────────────────────────────────────────────────
add_action('init', 'arted_handle_work_save');
function arted_handle_work_save() {
    if (!is_user_logged_in() || empty($_POST['arted_work_nonce'])) return;
    if (!wp_verify_nonce($_POST['arted_work_nonce'], 'arted_save_work')) return;
    $user = wp_get_current_user();
    if (!in_array('artist', (array) $user->roles)) return;

    $user_id  = get_current_user_id();
    $work_id  = (int)($_POST['arted_work_id'] ?? 0);
    $title    = sanitize_text_field($_POST['work_title'] ?? '');
    $desc     = sanitize_textarea_field($_POST['work_desc'] ?? '');
    $price    = floatval($_POST['work_price'] ?? 0);
    $cats     = array_map('intval', (array)($_POST['work_category'] ?? []));

    if (empty($title)) {
        wp_safe_redirect(add_query_arg('work_error', 'no_title', wc_get_account_endpoint_url('artist-add-work')));
        exit;
    }

    $is_edit = false;
    if ($work_id) {
        $existing = get_post($work_id);
        if ($existing && (int)$existing->post_author === $user_id && $existing->post_type === 'product') {
            $is_edit = true;
        } else {
            $work_id = 0;
        }
    }

    $post_data = [
        'post_title'   => $title,
        'post_content' => $desc,
        'post_type'    => 'product',
        'post_author'  => $user_id,
        'post_status'  => $is_edit ? get_post_status($work_id) : 'pending',
    ];

    if ($is_edit) {
        $post_data['ID'] = $work_id;
        $saved_id = wp_update_post($post_data);
    } else {
        $saved_id = wp_insert_post($post_data);
    }

    if (!$saved_id || is_wp_error($saved_id)) {
        wp_safe_redirect(wc_get_account_endpoint_url('artist-add-work'));
        exit;
    }

    // Автор и город в ACF полях — чтобы Elementor показывал их как у старых товаров
    $artist_name = get_user_meta($user_id, 'arted_artist_name', true);
    $artist_city = get_user_meta($user_id, 'arted_artist_city', true);
    if (function_exists('update_field')) {
        if ($artist_name) update_field('author_name', $artist_name, $saved_id);
        if ($artist_city) update_field('author_city', $artist_city, $saved_id);
    } else {
        if ($artist_name) update_post_meta($saved_id, 'author_name', $artist_name);
        if ($artist_city) update_post_meta($saved_id, 'author_city', $artist_city);
    }

    // Цена
    update_post_meta($saved_id, '_price', $price);
    update_post_meta($saved_id, '_regular_price', $price);
    update_post_meta($saved_id, '_visibility', 'visible');
    update_post_meta($saved_id, '_stock_status', 'instock');

    // Категории
    if (!empty($cats)) {
        wp_set_post_terms($saved_id, $cats, 'product_cat');
    }

    // Главное фото
    if (!empty($_FILES['work_photo_file']['tmp_name'])) {
        $attach_id = arted_upload_and_compress($_FILES['work_photo_file'], $user_id, 1920, 1920);
        if ($attach_id && !is_wp_error($attach_id)) {
            // Удаляем старое если редактирование
            if ($is_edit) {
                $old = get_post_thumbnail_id($saved_id);
                if ($old) wp_delete_attachment($old, true);
            }
            set_post_thumbnail($saved_id, $attach_id);
        }
    } elseif (!empty($_POST['work_photo_id'])) {
        set_post_thumbnail($saved_id, (int)$_POST['work_photo_id']);
    }

    // Галерея (AJAX обрабатывает загрузку, здесь просто сохраняем порядок)
    $gallery_ids_str = $_POST['arted_work_gallery_ids'] ?? '';
    $gallery_ids = array_filter(array_map('intval', explode(',', $gallery_ids_str)));
    update_post_meta($saved_id, '_product_image_gallery', implode(',', $gallery_ids));

    // Событие
    if (function_exists('arted_add_event')) {
        arted_add_event($user_id, 'green', $is_edit ? 'Работа обновлена: ' . $title : 'Добавлена работа: ' . $title);
    }

    $redirect = add_query_arg([
        'work_id'    => $saved_id,
        'work_saved' => $is_edit ? 'updated' : 'new',
    ], wc_get_account_endpoint_url('artist-add-work'));

    wp_safe_redirect($redirect);
    exit;
}

// ── AJAX удаление работы ──────────────────────────────────────────────────
add_action('wp_ajax_arted_delete_work', function() {
    if (!is_user_logged_in()) wp_send_json_error('unauthorized');
    check_ajax_referer('arted_delete_work', 'nonce');

    $work_id = (int)($_POST['work_id'] ?? 0);
    $user_id = get_current_user_id();

    $post = get_post($work_id);
    if (!$post || (int)$post->post_author !== $user_id || $post->post_type !== 'product') {
        wp_send_json_error('forbidden');
    }

    wp_trash_post($work_id);
    wp_send_json_success();
});
