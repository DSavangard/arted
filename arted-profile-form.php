<?php
// ── Контент вкладки Профиль ───────────────────────────────────────────────
function arted_tab_profile() {
    $user_id = get_current_user_id();
    $lang    = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';

    $t = [
        'ru' => [
            'steps'        => ['Личность', 'Творческий мир', 'Мастерская', 'Достижения'],
            'save'         => 'Сохранить',
            'next'         => 'Далее →',
            'prev'         => '← Назад',
            'saved'        => 'Сохранено',
            's1_title'     => 'Личность',
            'name'         => 'Псевдоним / Имя',
            'name_hint'    => 'Публичное имя на карточках работ и вашей странице',
            'photo'        => 'Фото профиля',
            'photo_hint'   => 'Может быть творческим/концептуальным. Необязательно.',
            'photo_change' => 'Изменить фото',
            'photo_upload' => 'Загрузить фото',
            'city'         => 'Город',
            'country'      => 'Страна',
            'bio'          => 'Биография',
            'bio_hint'     => 'Расскажите о себе. Галерея переведёт на EN и FR.',
            'video'        => 'Видео-визитка',
            'video_hint'   => 'Ссылка на YouTube или VK видео',
            's2_title'     => 'Творческий мир',
            'styles'       => 'Любимые стили и направления',
            'materials'    => 'Материалы',
            'inspiration'  => 'Источники вдохновения',
            'inspiration_hint' => 'Что вас вдохновляет?',
            'influences'   => 'Художники которые повлияли на вас',
            's3_title'     => 'Мастерская',
            'workshop_photos'    => 'Фото мастерской / рабочего места',
            'workshop_hint'      => 'До 10 фотографий. Покажите где вы работаете.',
            'workshop_desc'      => 'Описание мастерской',
            'workshop_desc_hint' => 'Где работаете, как устроено пространство',
            'personal_photos'    => 'Личные фото',
            'personal_hint'      => 'Вы в процессе работы, на выставках, в жизни',
            's4_title'     => 'Достижения и реквизиты',
            'education'    => 'Образование',
            'education_hint'   => 'Где учились, у кого',
            'exhibitions'  => 'Выставки',
            'exhibitions_hint' => 'Список значимых выставок',
            'press'        => 'Публикации и пресса',
            'awards'       => 'Награды',
            'optional'     => 'Необязательно — начинающие художники не обязаны заполнять',
            'requisites'   => 'Реквизиты для выплат',
            'req_hint'     => 'Видны только вам и администратору. Хранятся в зашифрованном виде.',
            'bank_account' => 'Номер счёта',
            'bank_bik'     => 'БИК банка',
            'bank_name'    => 'Получатель платежа',
            'bank_name_hint' => 'ФИО или название ИП/юрлица — как в банке',
        ],
        'en' => [
            'steps'        => ['Identity', 'Creative world', 'Studio', 'Achievements'],
            'save'         => 'Save',
            'next'         => 'Next →',
            'prev'         => '← Back',
            'saved'        => 'Saved',
            's1_title'     => 'Identity',
            'name'         => 'Artist name / Pseudonym',
            'name_hint'    => 'Public name shown on artwork cards and your page',
            'photo'        => 'Profile photo',
            'photo_hint'   => 'Can be creative/conceptual. Optional.',
            'photo_change' => 'Change photo',
            'photo_upload' => 'Upload photo',
            'city'         => 'City',
            'country'      => 'Country',
            'bio'          => 'Biography',
            'bio_hint'     => 'Tell us about yourself. Gallery will translate to RU and FR.',
            'video'        => 'Video introduction',
            'video_hint'   => 'Link to YouTube or VK video',
            's2_title'     => 'Creative world',
            'styles'       => 'Favourite styles and directions',
            'materials'    => 'Materials',
            'inspiration'  => 'Sources of inspiration',
            'inspiration_hint' => 'What inspires you?',
            'influences'   => 'Artists who influenced you',
            's3_title'     => 'Studio',
            'workshop_photos'    => 'Studio / workspace photos',
            'workshop_hint'      => 'Up to 10 photos. Show where you work.',
            'workshop_desc'      => 'Studio description',
            'workshop_desc_hint' => 'Where you work, how the space is set up',
            'personal_photos'    => 'Personal photos',
            'personal_hint'      => 'You at work, at exhibitions, in life',
            's4_title'     => 'Achievements & payment details',
            'education'    => 'Education',
            'education_hint'   => 'Where and with whom you studied',
            'exhibitions'  => 'Exhibitions',
            'exhibitions_hint' => 'List of significant exhibitions',
            'press'        => 'Publications & press',
            'awards'       => 'Awards',
            'optional'     => 'Optional — beginners are not required to fill this in',
            'requisites'   => 'Payment details',
            'req_hint'     => 'Visible only to you and the administrator. Stored encrypted.',
            'bank_account' => 'Account number',
            'bank_bik'     => 'Bank BIC',
            'bank_name'    => 'Payment recipient',
            'bank_name_hint' => 'Full name or company name — as registered with your bank',
        ],
        'fr' => [
            'steps'        => ['Identité', 'Monde créatif', 'Atelier', 'Réalisations'],
            'save'         => 'Enregistrer',
            'next'         => 'Suivant →',
            'prev'         => '← Retour',
            'saved'        => 'Enregistré',
            's1_title'     => 'Identité',
            'name'         => "Nom d'artiste / Pseudonyme",
            'name_hint'    => 'Nom public sur les fiches œuvres et votre page',
            'photo'        => 'Photo de profil',
            'photo_hint'   => 'Peut être créative/conceptuelle. Facultatif.',
            'photo_change' => 'Changer la photo',
            'photo_upload' => 'Télécharger une photo',
            'city'         => 'Ville',
            'country'      => 'Pays',
            'bio'          => 'Biographie',
            'bio_hint'     => 'Parlez de vous. La galerie traduira en RU et EN.',
            'video'        => 'Vidéo de présentation',
            'video_hint'   => 'Lien vers une vidéo YouTube ou VK',
            's2_title'     => 'Monde créatif',
            'styles'       => 'Styles et directions préférés',
            'materials'    => 'Matériaux',
            'inspiration'  => "Sources d'inspiration",
            'inspiration_hint' => "Qu'est-ce qui vous inspire?",
            'influences'   => 'Artistes qui vous ont influencé',
            's3_title'     => 'Atelier',
            'workshop_photos'    => "Photos de l'atelier / espace de travail",
            'workshop_hint'      => "Jusqu'à 10 photos. Montrez où vous travaillez.",
            'workshop_desc'      => "Description de l'atelier",
            'workshop_desc_hint' => 'Où vous travaillez, comment est organisé votre espace',
            'personal_photos'    => 'Photos personnelles',
            'personal_hint'      => 'Vous au travail, aux expositions, dans la vie',
            's4_title'     => 'Réalisations et coordonnées bancaires',
            'education'    => 'Formation',
            'education_hint'   => 'Où et avec qui vous avez étudié',
            'exhibitions'  => 'Expositions',
            'exhibitions_hint' => "Liste des expositions importantes",
            'press'        => 'Publications et presse',
            'awards'       => 'Prix et distinctions',
            'optional'     => 'Facultatif — les débutants ne sont pas obligés de remplir',
            'requisites'   => 'Coordonnées bancaires',
            'req_hint'     => "Visibles uniquement par vous et l'administrateur. Stockées chiffrées.",
            'bank_account' => 'Numéro de compte',
            'bank_bik'     => 'Code BIC',
            'bank_name'    => 'Bénéficiaire',
            'bank_name_hint' => "Nom complet ou raison sociale — tel qu'enregistré auprès de votre banque",
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    $name         = get_user_meta($user_id, 'arted_artist_name',      true);
    $city         = get_user_meta($user_id, 'arted_artist_city',      true);
    $country      = get_user_meta($user_id, 'arted_artist_country',   true);
    $bio          = get_user_meta($user_id, 'arted_bio',              true);
    $video        = get_user_meta($user_id, 'arted_video',            true);
    $styles       = get_user_meta($user_id, 'arted_styles',           true) ?: [];
    $materials    = get_user_meta($user_id, 'arted_materials',        true) ?: [];
    $inspiration  = get_user_meta($user_id, 'arted_inspiration',      true);
    $influences   = get_user_meta($user_id, 'arted_influences',       true);
    $workshop_desc= get_user_meta($user_id, 'arted_workshop_desc',    true);
    $education    = get_user_meta($user_id, 'arted_education',        true);
    $exhibitions  = get_user_meta($user_id, 'arted_exhibitions',      true);
    $press        = get_user_meta($user_id, 'arted_press',            true);
    $awards       = get_user_meta($user_id, 'arted_awards',           true);
    $photo_id     = get_user_meta($user_id, 'arted_photo_id',         true);
    $workshop_ids = get_user_meta($user_id, 'arted_workshop_photo_ids', true) ?: [];
    $personal_ids = get_user_meta($user_id, 'arted_personal_photo_ids', true) ?: [];

    $enc_key      = defined('AUTH_KEY') ? AUTH_KEY : 'arted_key';
    $bank_account = arted_decrypt(get_user_meta($user_id, 'arted_bank_account', true), $enc_key);
    $bank_bik     = arted_decrypt(get_user_meta($user_id, 'arted_bank_bik',     true), $enc_key);
    $bank_name    = arted_decrypt(get_user_meta($user_id, 'arted_bank_name',    true), $enc_key);

    $photo_url    = $photo_id ? wp_get_attachment_image_url($photo_id, 'thumbnail') : '';
    $current_step = isset($_GET['profile_step']) ? (int)$_GET['profile_step'] : 1;
    $current_step = max(1, min(4, $current_step));

    $styles_options    = ['Современное', 'Авторское', 'Религиозное', 'Историческое', 'Экзотическое', 'Абстракция', 'Реализм', 'Импрессионизм', 'Минимализм', 'Экспрессионизм'];
    $materials_options = ['Масло', 'Акрил', 'Акварель', 'Гуашь', 'Пастель', 'Графика', 'Бронза', 'Мрамор', 'Керамика', 'Смешанная техника'];

    $profile_url = wc_get_account_endpoint_url('artist-profile');

    echo '<div class="arted-tab-content arted-profile-wrap">';

    if (!empty($_GET['profile_saved'])) {
        echo '<div class="arted-profile-saved">' . esc_html($l['saved']) . ' ✓</div>';
    }

    // Прогресс шагов
    echo '<div class="arted-steps">';
    foreach ($l['steps'] as $i => $step_name) {
        $num      = $i + 1;
        $active   = $num === $current_step ? ' active' : '';
        $done     = $num < $current_step ? ' done' : '';
        $step_url = add_query_arg('profile_step', $num, wc_get_account_endpoint_url('artist-profile'));
        echo '<a href="' . esc_url($step_url) . '" class="arted-step' . $active . $done . '">';
        echo '<div class="arted-step-num">' . ($num < $current_step ? '✓' : $num) . '</div>';
        echo '<div class="arted-step-label">' . esc_html($step_name) . '</div>';
        echo '</a>';
        if ($num < 4) echo '<div class="arted-step-line' . ($num < $current_step ? ' done' : '') . '"></div>';
    }
    echo '</div>';

    echo '<form class="arted-profile-form" method="post" enctype="multipart/form-data">';
    echo wp_nonce_field('arted_profile_save', 'arted_profile_nonce', true, false);
    echo '<input type="hidden" name="arted_profile_step" value="' . $current_step . '">';

    // ── ШАГ 1: Личность ──────────────────────────────────────────────────
    if ($current_step === 1) {
        echo '<div class="arted-form-section">';

        $pcls = 'arted-photo-preview arted-photo-clickable' . ($photo_url ? '' : ' arted-photo-empty');
        echo '<div class="arted-photo-upload">';
        echo '<label class="' . $pcls . '" id="arted-photo-preview" for="arted_photo_file">';
        if ($photo_url) {
            echo '<img src="' . esc_url($photo_url) . '" alt="">';
        }
        echo '<span class="arted-photo-overlay">+</span>';
        echo '</label>';
        echo '<div class="arted-photo-controls">';
        echo '<label class="arted-field-label">' . esc_html($l['photo']) . '</label>';
        echo '<p class="arted-field-hint">' . esc_html($l['photo_hint']) . '</p>';
        echo '<input type="file" id="arted_photo_file" name="arted_photo_file" accept="image/*" style="display:none" data-upload="profile">';
        if ($photo_id) echo '<input type="hidden" name="arted_photo_id" value="' . $photo_id . '">';
        echo '</div></div>';

        arted_profile_field('arted_artist_name', $l['name'], $name, $l['name_hint']);
        arted_profile_field('arted_artist_city', $l['city'], $city);
        arted_profile_field('arted_artist_country', $l['country'], $country);
        arted_profile_textarea('arted_bio', $l['bio'], $bio, $l['bio_hint'], 5);
        arted_profile_field('arted_video', $l['video'], $video, $l['video_hint']);

        echo '</div>';
    }

    // ── ШАГ 2: Творческий мир ────────────────────────────────────────────
    if ($current_step === 2) {
        echo '<div class="arted-form-section">';

        echo '<div class="arted-field">';
        echo '<label class="arted-field-label">' . esc_html($l['styles']) . '</label>';
        echo '<div class="arted-tags">';
        foreach ($styles_options as $opt) {
            $checked = in_array($opt, (array)$styles) ? ' selected' : '';
            echo '<span class="arted-tag' . $checked . '" data-value="' . esc_attr($opt) . '">' . esc_html($opt) . '</span>';
        }
        echo '</div>';
        echo '<input type="hidden" class="arted-tags-input" name="arted_styles_json" value="' . esc_attr(json_encode((array)$styles)) . '">';
        echo '</div>';

        echo '<div class="arted-field">';
        echo '<label class="arted-field-label">' . esc_html($l['materials']) . '</label>';
        echo '<div class="arted-tags">';
        foreach ($materials_options as $opt) {
            $checked = in_array($opt, (array)$materials) ? ' selected' : '';
            echo '<span class="arted-tag' . $checked . '" data-value="' . esc_attr($opt) . '">' . esc_html($opt) . '</span>';
        }
        echo '</div>';
        echo '<input type="hidden" class="arted-tags-input" name="arted_materials_json" value="' . esc_attr(json_encode((array)$materials)) . '">';
        echo '</div>';

        arted_profile_textarea('arted_inspiration', $l['inspiration'], $inspiration, $l['inspiration_hint'], 4);
        arted_profile_textarea('arted_influences',  $l['influences'],  $influences,  '', 4);

        echo '</div>';
    }

    // ── ШАГ 3: Мастерская ────────────────────────────────────────────────
    if ($current_step === 3) {
        echo '<div class="arted-form-section">';
        arted_photo_gallery_field('workshop', $l['workshop_photos'], $l['workshop_hint'], $workshop_ids, 10);
        arted_profile_textarea('arted_workshop_desc', $l['workshop_desc'], $workshop_desc, $l['workshop_desc_hint'], 4);
        arted_photo_gallery_field('personal', $l['personal_photos'], $l['personal_hint'], $personal_ids, 10);
        echo '</div>';
    }

    // ── ШАГ 4: Достижения + Реквизиты ────────────────────────────────────
    if ($current_step === 4) {
        echo '<div class="arted-form-section">';
        echo '<p class="arted-field-hint" style="margin-bottom:20px;">' . esc_html($l['optional']) . '</p>';

        arted_profile_textarea('arted_education',   $l['education'],   $education,   $l['education_hint'],   3);
        arted_profile_textarea('arted_exhibitions', $l['exhibitions'],  $exhibitions, $l['exhibitions_hint'], 4);
        arted_profile_textarea('arted_press',       $l['press'],        $press,       '', 3);
        arted_profile_textarea('arted_awards',      $l['awards'],       $awards,      '', 3);

        echo '<div class="arted-requisites-block">';
        echo '<div class="arted-section-title">' . esc_html($l['requisites']) . '</div>';
        echo '<p class="arted-field-hint">' . esc_html($l['req_hint']) . '</p>';
        arted_profile_field('arted_bank_account', $l['bank_account'], $bank_account, '', 'password');
        arted_profile_field('arted_bank_bik',     $l['bank_bik'],     $bank_bik);
        arted_profile_field('arted_bank_name',    $l['bank_name'],    $bank_name,    $l['bank_name_hint']);
        echo '</div>';

        echo '</div>';
    }

    // Навигация
    echo '<div class="arted-form-nav">';
    if ($current_step > 1) {
        echo '<a href="' . esc_url(add_query_arg('profile_step', $current_step - 1, $profile_url)) . '" class="arted-btn-secondary">' . esc_html($l['prev']) . '</a>';
    } else {
        echo '<span></span>';
    }
    if ($current_step < 4) {
        echo '<button type="submit" class="arted-btn-primary">' . esc_html($l['save']) . ' &amp; ' . esc_html($l['next']) . '</button>';
    } else {
        echo '<button type="submit" class="arted-btn-primary">' . esc_html($l['save']) . '</button>';
    }
    echo '</div>';

    echo '</form></div>';
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.arted-tag').forEach(function(tag) {
            tag.addEventListener('click', function() {
                this.classList.toggle('selected');
                updateTagsInput(this.closest('.arted-field'));
            });
        });

        function updateTagsInput(field) {
            if (!field) return;
            var selected = [];
            field.querySelectorAll('.arted-tag.selected').forEach(function(t) {
                selected.push(t.getAttribute('data-value'));
            });
            var input = field.querySelector('.arted-tags-input');
            if (input) input.value = JSON.stringify(selected);
        }

        var photoInput = document.getElementById('arted_photo_file');
        if (photoInput) {
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        var preview = document.getElementById('arted-photo-preview');
                        preview.innerHTML = '<img src="' + e.target.result + '" alt=""><span class="arted-photo-overlay">+</span>';
                        preview.classList.remove('arted-photo-empty');
                    };
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
    </script>
    <?php
}

// ── Вспомогательные функции полей ────────────────────────────────────────────
function arted_profile_field($name, $label, $value = '', $hint = '', $type = 'text') {
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
    echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="arted-input"' . ($hint ? ' placeholder="' . esc_attr($hint) . '"' : '') . '>';
    echo '</div>';
}

function arted_profile_textarea($name, $label, $value = '', $hint = '', $rows = 4) {
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label" for="' . esc_attr($name) . '">' . esc_html($label) . '</label>';
    echo '<textarea id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" rows="' . $rows . '" class="arted-textarea"' . ($hint ? ' placeholder="' . esc_attr($hint) . '"' : '') . '>' . esc_textarea($value) . '</textarea>';
    echo '</div>';
}

function arted_photo_gallery_field($key, $label, $hint, $ids, $max) {
    $ids = (array)$ids;
    echo '<div class="arted-field">';
    echo '<label class="arted-field-label">' . esc_html($label) . '</label>';
    if ($hint) echo '<p class="arted-field-hint">' . esc_html($hint) . '</p>';
    echo '<div class="arted-gallery" id="arted-gallery-' . esc_attr($key) . '">';
    foreach ($ids as $id) {
        $url = wp_get_attachment_image_url($id, 'thumbnail');
        if (!$url) continue;
        echo '<div class="arted-gallery-item" data-id="' . $id . '">';
        echo '<img src="' . esc_url($url) . '">';
        echo '<button type="button" class="arted-gallery-remove" data-gallery="' . esc_attr($key) . '">×</button>';
        echo '</div>';
    }
    if (count($ids) < $max) {
        echo '<label class="arted-gallery-add" for="arted-upload-' . esc_attr($key) . '">+</label>';
        echo '<input type="file" id="arted-upload-' . esc_attr($key) . '" accept="image/*" multiple style="display:none" data-gallery="' . esc_attr($key) . '" data-max="' . $max . '">';
    }
    echo '</div>';
    echo '<input type="hidden" name="arted_' . esc_attr($key) . '_ids" id="arted-ids-' . esc_attr($key) . '" value="' . esc_attr(implode(',', $ids)) . '">';
    echo '</div>';
}

// ── Шифрование реквизитов ─────────────────────────────────────────────────────
function arted_encrypt($data, $key) {
    if (empty($data)) return '';
    $iv        = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', substr(hash('sha256', $key, true), 0, 32), 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function arted_decrypt($data, $key) {
    if (empty($data)) return '';
    $decoded = base64_decode($data);
    if (!$decoded || strpos($decoded, '::') === false) return '';
    [$iv, $encrypted] = explode('::', $decoded, 2);
    return openssl_decrypt($encrypted, 'AES-256-CBC', substr(hash('sha256', $key, true), 0, 32), 0, $iv);
}
