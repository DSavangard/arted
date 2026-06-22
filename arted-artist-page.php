<?php
// ── 1. Rewrite rule: /artist/{slug}/ ─────────────────────────────────────
add_action('init', function() {
    add_rewrite_rule('^artist/([^/]+)/?$', 'index.php?arted_artist=$matches[1]', 'top');
});

add_filter('query_vars', function($vars) {
    $vars[] = 'arted_artist';
    return $vars;
});

// ── 2. Перехват шаблона ───────────────────────────────────────────────────
add_action('template_redirect', function() {
    $slug = get_query_var('arted_artist');
    if (!$slug) return;

    $user = get_user_by('slug', $slug);
    if (!$user || !in_array('artist', (array) $user->roles)) {
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        get_template_part('404');
        exit;
    }

    arted_render_artist_page($user);
    exit;
});

// ── 3. Рендер страницы художника ──────────────────────────────────────────
function arted_render_artist_page($user) {
    $user_id  = $user->ID;
    // Убираем WooCommerce контент ДО get_header()
    add_filter('woocommerce_is_shop', '__return_false');
    add_filter('woocommerce_is_product_category', '__return_false');
    remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
    remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);
    remove_all_actions('woocommerce_before_main_content');
    remove_all_actions('woocommerce_after_main_content');
    remove_all_actions('woocommerce_sidebar');
    $lang     = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $verified = get_user_meta($user_id, 'arted_artist_verified', true);

    // Данные художника
    $name        = get_user_meta($user_id, 'arted_artist_name',    true) ?: $user->display_name;
    $city        = get_user_meta($user_id, 'arted_artist_country', true)
                   ? get_user_meta($user_id, 'arted_artist_city', true) . ', ' . get_user_meta($user_id, 'arted_artist_country', true)
                   : get_user_meta($user_id, 'arted_artist_city', true);
    $bio         = get_user_meta($user_id, 'arted_bio',            true);
    $video       = get_user_meta($user_id, 'arted_video',          true);
    $styles      = (array)(get_user_meta($user_id, 'arted_styles',    true) ?: []);
    $materials   = (array)(get_user_meta($user_id, 'arted_materials', true) ?: []);
    $photo_id    = get_user_meta($user_id, 'arted_photo_id',        true);
    $photo_url   = $photo_id ? wp_get_attachment_image_url($photo_id, 'medium') : '';
    $workshop_ids = (array)(get_user_meta($user_id, 'arted_workshop_photo_ids', true) ?: []);
    $education   = get_user_meta($user_id, 'arted_education',      true);
    $exhibitions = get_user_meta($user_id, 'arted_exhibitions',    true);
    $awards      = get_user_meta($user_id, 'arted_awards',         true);

    // Работы художника (WooCommerce товары)
    $works_query = new WP_Query([
        'post_type'      => 'product',
        'author'         => $user_id,
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    $works = $works_query->posts;

    $t = [
        'ru' => [
            'verified'     => 'Верифицированный художник',
            'works'        => 'Работы',
            'no_works'     => 'Работ пока нет',
            'bio'          => 'О художнике',
            'styles'       => 'Стили',
            'materials'    => 'Материалы',
            'workshop'     => 'Мастерская',
            'achievements' => 'Достижения',
            'education'    => 'Образование',
            'exhibitions'  => 'Выставки',
            'awards'       => 'Награды',
            'contact'      => 'Написать художнику',
            'buy'          => 'Купить',
        ],
        'en' => [
            'verified'     => 'Verified artist',
            'works'        => 'Works',
            'no_works'     => 'No works yet',
            'bio'          => 'About the artist',
            'styles'       => 'Styles',
            'materials'    => 'Materials',
            'workshop'     => 'Studio',
            'achievements' => 'Achievements',
            'education'    => 'Education',
            'exhibitions'  => 'Exhibitions',
            'awards'       => 'Awards',
            'contact'      => 'Contact artist',
            'buy'          => 'Buy',
        ],
        'fr' => [
            'verified'     => 'Artiste vérifié',
            'works'        => 'Œuvres',
            'no_works'     => "Aucune œuvre pour l'instant",
            'bio'          => "À propos de l'artiste",
            'styles'       => 'Styles',
            'materials'    => 'Matériaux',
            'workshop'     => 'Atelier',
            'achievements' => 'Réalisations',
            'education'    => 'Formation',
            'exhibitions'  => 'Expositions',
            'awards'       => 'Prix',
            'contact'      => "Contacter l'artiste",
            'buy'          => 'Acheter',
        ],
    ];
    $l = $t[$lang] ?? $t['ru'];

    // Заголовок страницы для SEO
    add_filter('pre_get_document_title', function() use ($name) {
        return esc_html($name) . ' — arted.gallery';
    });

    get_header();
    remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
    remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
    ?>
    <div class="arted-artist-page-wrap">

        <?php // ── Шапка художника ── ?>
        <div class="arted-ap-hero">
            <div class="arted-ap-hero-inner">
                <div class="arted-ap-photo">
                    <?php if ($photo_url): ?>
                        <img src="<?= esc_url($photo_url) ?>" alt="<?= esc_attr($name) ?>">
                    <?php else: ?>
                        <div class="arted-ap-photo-placeholder"><?= mb_substr($name, 0, 1) ?></div>
                    <?php endif; ?>
                </div>
                <div class="arted-ap-info">
                    <div class="arted-ap-name-row">
                        <h1 class="arted-ap-name"><?= esc_html($name) ?></h1>
                        <?php if ($verified == 1): ?>
                        <span class="arted-ap-verified">✓ <?= esc_html($l['verified']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($city): ?>
                    <div class="arted-ap-city"><?= esc_html($city) ?></div>
                    <?php endif; ?>
                    <?php if ($styles): ?>
                    <div class="arted-ap-tags">
                        <?php foreach ($styles as $s): ?>
                        <span class="arted-ap-tag"><?= esc_html($s) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <a href="#arted-contact" class="arted-btn-primary arted-ap-contact-btn">
                        <?= esc_html($l['contact']) ?>
                    </a>
                </div>
            </div>
        </div>

        <div class="arted-ap-body">

            <?php // ── Биография ── ?>
            <?php if ($bio): ?>
            <section class="arted-ap-section">
                <h2 class="arted-ap-section-title"><?= esc_html($l['bio']) ?></h2>
                <div class="arted-ap-bio"><?= nl2br(esc_html($bio)) ?></div>
                <?php if ($materials): ?>
                <div class="arted-ap-tags" style="margin-top:16px;">
                    <span class="arted-ap-tag-label"><?= esc_html($l['materials']) ?>:</span>
                    <?php foreach ($materials as $m): ?>
                    <span class="arted-ap-tag"><?= esc_html($m) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php // ── Видео ── ?>
            <?php if ($video): ?>
            <section class="arted-ap-section">
                <div class="arted-ap-video">
                    <?php
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video, $m)) {
                        echo '<iframe src="https://www.youtube.com/embed/' . esc_attr($m[1]) . '" frameborder="0" allowfullscreen></iframe>';
                    } elseif (preg_match('/vk\.com\/video(-?\d+_\d+)/', $video, $m)) {
                        $parts = explode('_', $m[1]);
                        echo '<iframe src="https://vk.com/video_ext.php?oid=' . $parts[0] . '&id=' . $parts[1] . '" frameborder="0" allowfullscreen></iframe>';
                    }
                    ?>
                </div>
            </section>
            <?php endif; ?>

            <?php // ── Работы ── ?>
            <section class="arted-ap-section">
                <h2 class="arted-ap-section-title"><?= esc_html($l['works']) ?></h2>
                <?php if (empty($works)): ?>
                <p class="arted-ap-empty"><?= esc_html($l['no_works']) ?></p>
                <?php else: ?>
                <div class="arted-ap-works">
                    <?php foreach ($works as $post):
                        $product = wc_get_product($post->ID);
                        if (!$product) continue;
                        $img   = wp_get_attachment_image_url($product->get_image_id(), 'medium');
                        $price = $product->get_price_html();
                    ?>
                    <a href="<?= esc_url($product->get_permalink()) ?>" class="arted-ap-work">
                        <div class="arted-ap-work-img">
                            <?php if ($img): ?>
                            <img src="<?= esc_url($img) ?>" alt="<?= esc_attr($product->get_name()) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="arted-ap-work-info">
                            <div class="arted-ap-work-name"><?= esc_html($product->get_name()) ?></div>
                            <div class="arted-ap-work-price"><?= $price ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <?php // ── Мастерская ── ?>
            <?php if (!empty($workshop_ids)): ?>
            <section class="arted-ap-section">
                <h2 class="arted-ap-section-title"><?= esc_html($l['workshop']) ?></h2>
                <div class="arted-ap-workshop">
                    <?php foreach ($workshop_ids as $wid):
                        $wurl = wp_get_attachment_image_url($wid, 'medium');
                        if (!$wurl) continue;
                    ?>
                    <div class="arted-ap-workshop-img">
                        <img src="<?= esc_url($wurl) ?>" alt="">
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php // ── Достижения ── ?>
            <?php if ($education || $exhibitions || $awards): ?>
            <section class="arted-ap-section">
                <h2 class="arted-ap-section-title"><?= esc_html($l['achievements']) ?></h2>
                <div class="arted-ap-achievements">
                    <?php if ($education): ?>
                    <div class="arted-ap-achievement">
                        <div class="arted-ap-achievement-label"><?= esc_html($l['education']) ?></div>
                        <div class="arted-ap-achievement-text"><?= nl2br(esc_html($education)) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($exhibitions): ?>
                    <div class="arted-ap-achievement">
                        <div class="arted-ap-achievement-label"><?= esc_html($l['exhibitions']) ?></div>
                        <div class="arted-ap-achievement-text"><?= nl2br(esc_html($exhibitions)) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($awards): ?>
                    <div class="arted-ap-achievement">
                        <div class="arted-ap-achievement-label"><?= esc_html($l['awards']) ?></div>
                        <div class="arted-ap-achievement-text"><?= nl2br(esc_html($awards)) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php // ── Форма контакта ── ?>
            <section class="arted-ap-section" id="arted-contact">
                <h2 class="arted-ap-section-title"><?= esc_html($l['contact']) ?></h2>
                <form class="arted-ap-contact-form" data-artist="<?= (int)$user_id ?>">
                    <?= wp_nonce_field('arted_contact_artist', 'arted_contact_nonce', true, false) ?>
                    <input type="hidden" name="arted_artist_id" value="<?= (int)$user_id ?>">
                    <div class="arted-ap-form-row">
                        <input type="text"  name="contact_name"  class="arted-input" placeholder="<?= $lang === 'ru' ? 'Ваше имя' : ($lang === 'fr' ? 'Votre nom' : 'Your name') ?>" required>
                        <input type="email" name="contact_email" class="arted-input" placeholder="Email" required>
                    </div>
                    <textarea name="contact_message" class="arted-textarea" rows="4" placeholder="<?= $lang === 'ru' ? 'Сообщение...' : ($lang === 'fr' ? 'Message...' : 'Message...') ?>" required></textarea>
                    <button type="submit" class="arted-btn-primary"><?= esc_html($l['contact']) ?></button>
                    <div class="arted-ap-contact-success" style="display:none;color:#2d6a2d;margin-top:12px;">
                        <?= $lang === 'ru' ? 'Сообщение отправлено!' : ($lang === 'fr' ? 'Message envoyé!' : 'Message sent!') ?>
                    </div>
                </form>
            </section>

        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.querySelector('.arted-ap-contact-form');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            var fd = new FormData(form);
            fd.append('action', 'arted_contact_artist');
            fetch('<?= esc_js(admin_url('admin-ajax.php')) ?>', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        form.reset();
                        form.querySelector('.arted-ap-contact-success').style.display = 'block';
                    }
                    btn.disabled = false;
                });
        });
    });
    </script>
    <?php
    get_footer();
}
