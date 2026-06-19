<?php
// Получаем текущий язык
if (!function_exists('arted_get_lang')) {
    function arted_get_lang() {
        if (isset($_COOKIE['arted_lang'])) {
            return $_COOKIE['arted_lang'];
        }
        return 'ru';
    }
}

// Добавляем поля перевода в карточку товара
add_action('woocommerce_product_options_general_product_data', 'arted_add_translation_fields');
function arted_add_translation_fields() {
    global $post;
    echo '<div class="options_group">';
    echo '<p style="padding:10px 12px;font-weight:600;color:#23282d;border-top:1px solid #eee;">🌍 Переводы</p>';
    woocommerce_wp_text_input(array(
        'id'    => '_title_fr',
        'label' => 'Название (FR)',
        'value' => get_post_meta($post->ID, '_title_fr', true),
    ));
    woocommerce_wp_text_input(array(
        'id'    => '_title_en',
        'label' => 'Название (EN)',
        'value' => get_post_meta($post->ID, '_title_en', true),
    ));
    echo '</div><div class="options_group">';
    echo '<p class="form-field"><label style="font-weight:600">Краткое описание (FR)</label>';
    echo '<textarea id="_short_desc_fr" name="_short_desc_fr" rows="3" style="width:100%">' . esc_textarea(get_post_meta($post->ID, '_short_desc_fr', true)) . '</textarea></p>';
    echo '<p class="form-field"><label style="font-weight:600">Краткое описание (EN)</label>';
    echo '<textarea id="_short_desc_en" name="_short_desc_en" rows="3" style="width:100%">' . esc_textarea(get_post_meta($post->ID, '_short_desc_en', true)) . '</textarea></p>';
    echo '</div>';
}

// Полное описание FR и EN
add_action('add_meta_boxes', 'arted_add_description_metaboxes');
function arted_add_description_metaboxes() {
    add_meta_box('arted_desc_fr', '🇫🇷 Описание (FR)', 'arted_render_desc_fr', 'product', 'normal', 'default');
    add_meta_box('arted_desc_en', '🇬🇧 Описание (EN)', 'arted_render_desc_en', 'product', 'normal', 'default');
}

function arted_render_desc_fr($post) {
    $content = get_post_meta($post->ID, '_desc_fr', true);
    wp_editor($content, '_desc_fr', array('textarea_name' => '_desc_fr', 'textarea_rows' => 10, 'media_buttons' => false));
}

function arted_render_desc_en($post) {
    $content = get_post_meta($post->ID, '_desc_en', true);
    wp_editor($content, '_desc_en', array('textarea_name' => '_desc_en', 'textarea_rows' => 10, 'media_buttons' => false));
}

// Сохраняем поля названия и описания
add_action('save_post_product', 'arted_save_translation_fields', 20);
function arted_save_translation_fields($post_id) {
    $text_fields = array('_title_fr', '_title_en', '_short_desc_fr', '_short_desc_en');
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
        }
    }
    foreach (array('_desc_fr', '_desc_en') as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, wp_kses_post($_POST[$field]));
        }
    }
}

// Перевод названия
add_filter('the_title', 'arted_translate_title', 10, 2);
function arted_translate_title($title, $post_id = null) {
    if (!$post_id || !is_singular('product')) return $title;
    $lang = arted_get_lang();
    if ($lang !== 'ru') {
        $translated = get_post_meta($post_id, '_title_' . $lang, true);
        if ($translated) return $translated;
    }
    return $title;
}

// Краткое описание
add_filter('woocommerce_short_description', 'arted_translate_short_desc');
function arted_translate_short_desc($desc) {
    if (!is_singular('product')) return $desc;
    global $post;
    $lang = arted_get_lang();
    if ($lang !== 'ru') {
        $translated = get_post_meta($post->ID, '_short_desc_' . $lang, true);
        if ($translated) return wpautop($translated);
    }
    return $desc;
}

// Полное описание
add_filter('the_content', 'arted_translate_desc');
function arted_translate_desc($content) {
    if (!is_singular('product')) return $content;
    global $post;
    $lang = arted_get_lang();
    if ($lang !== 'ru') {
        $translated = get_post_meta($post->ID, '_desc_' . $lang, true);
        if ($translated) return wpautop(do_shortcode($translated));
    }
    return $content;
}

// Поля перевода категорий
add_action('product_cat_edit_form_fields', function($term) {
    $fr = get_term_meta($term->term_id, 'cat_name_fr', true);
    $en = get_term_meta($term->term_id, 'cat_name_en', true);
    ?>
    <tr class="form-field">
        <th><label>Название (FR)</label></th>
        <td><input type="text" name="cat_name_fr" value="<?php echo esc_attr($fr); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label>Название (EN)</label></th>
        <td><input type="text" name="cat_name_en" value="<?php echo esc_attr($en); ?>"></td>
    </tr>
    <?php
});

add_action('edited_product_cat', function($term_id) {
    foreach (['cat_name_fr', 'cat_name_en'] as $field) {
        if (isset($_POST[$field])) {
            update_term_meta($term_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
});

// Поля перевода значений атрибутов
$attr_taxonomies = ['pa_razmer', 'pa_razmer-detalnyj', 'pa_stil', 'pa_vybor-kadra'];

foreach ($attr_taxonomies as $taxonomy) {
    add_action("{$taxonomy}_edit_form_fields", function($term) {
        $fr = get_term_meta($term->term_id, 'term_name_fr', true);
        $en = get_term_meta($term->term_id, 'term_name_en', true);
        ?>
        <tr class="form-field">
            <th><label>Название (FR)</label></th>
            <td><input type="text" name="term_name_fr" value="<?php echo esc_attr($fr); ?>"></td>
        </tr>
        <tr class="form-field">
            <th><label>Название (EN)</label></th>
            <td><input type="text" name="term_name_en" value="<?php echo esc_attr($en); ?>"></td>
        </tr>
        <?php
    });

    add_action("edited_{$taxonomy}", function($term_id) {
        foreach (['term_name_fr', 'term_name_en'] as $field) {
            if (isset($_POST[$field])) {
                update_term_meta($term_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    });
}

// Передаём переводы категорий и атрибутов в JS
add_action('wp_enqueue_scripts', function() {
    $translations = [];
    $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    foreach ($cats as $cat) {
        $translations['cats'][$cat->name] = [
            'fr' => get_term_meta($cat->term_id, 'cat_name_fr', true),
            'en' => get_term_meta($cat->term_id, 'cat_name_en', true),
        ];
    }
    foreach (['pa_razmer', 'pa_razmer-detalnyj', 'pa_stil', 'pa_vybor-kadra'] as $tax) {
        $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false]);
        foreach ($terms as $term) {
            $translations['attrs'][$term->name] = [
                'fr' => get_term_meta($term->term_id, 'term_name_fr', true),
                'en' => get_term_meta($term->term_id, 'term_name_en', true),
            ];
        }
    }
    wp_add_inline_script('jquery-core',
        'window.artedTranslations = ' . json_encode($translations) . ';',
        'before'
    );
});

// Автор и город — передаём все товары в JS
add_action('wp_footer', function() {
    $product_ids = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
    ]);
    $map = [];
    foreach ($product_ids as $id) {
        $author_ru = get_field('author_name', $id);
        $city_ru   = get_field('author_city', $id);
        if ($author_ru) {
            $map['authors'][$author_ru] = [
                'fr' => get_field('field_6a2ab640da889', $id),
                'en' => get_field('field_6a2ab65eda88a', $id),
            ];
        }
        if ($city_ru) {
            global $wpdb;
            $city_clean = preg_replace('/^г\.\s*/u', '', $city_ru);
            $city_fr = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'author_city_fr' LIMIT 1", $id));
            $city_en = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'author_city_en' LIMIT 1", $id));
            if ($city_fr || !isset($map['cities'][$city_clean])) {
                $map['cities'][$city_clean] = [
                    'fr' => $city_fr ?: '',
                    'en' => $city_en ?: '',
                ];
            }
        }
    }
    echo '<script>window.artedAuthorMap = ' . json_encode($map) . ';</script>';
});

// Перевод строк WordPress/WooCommerce
add_filter('gettext',  'arted_translate_strings', 20, 3);
add_filter('ngettext', 'arted_translate_strings', 20, 3);

function arted_translate_strings($translated, $text, $domain) {
    $lang = isset($_COOKIE['arted_lang']) ? $_COOKIE['arted_lang'] : 'ru';
    if ($lang === 'ru') return $translated;

    $fr = array(
        'Add to cart' => 'Ajouter au panier',
        'Add to Cart' => 'Ajouter au panier',
        'View cart' => 'Voir le panier',
        'Checkout' => 'Commander',
        'Cart' => 'Panier',
        'My account' => 'Mon compte',
        'My Account' => 'Mon compte',
        'Login' => 'Connexion',
        'Log in' => 'Se connecter',
        'Log out' => 'Se déconnecter',
        'Logout' => 'Déconnexion',
        'Register' => "S'inscrire",
        'Username or email address' => "Nom d'utilisateur ou e-mail",
        'Password' => 'Mot de passe',
        'Remember me' => 'Se souvenir de moi',
        'Lost your password?' => 'Mot de passe oublié ?',
        'Email address' => 'Adresse e-mail',
        'First name' => 'Prénom',
        'Last name' => 'Nom',
        'Display name' => 'Nom affiché',
        'Save changes' => 'Enregistrer',
        'Orders' => 'Commandes',
        'Addresses' => 'Adresses',
        'Payment methods' => 'Moyens de paiement',
        'Account details' => 'Détails du compte',
        'Billing address' => 'Adresse de facturation',
        'Shipping address' => 'Adresse de livraison',
        'Add payment method' => 'Ajouter un moyen de paiement',
        'No orders yet.' => 'Aucune commande.',
        'Browse products' => 'Voir les produits',
        'Price' => 'Prix',
        'Quantity' => 'Quantité',
        'Remove' => 'Supprimer',
        'Sale!' => 'Solde !',
        'Related products' => 'Produits similaires',
        'You may also like' => 'Vous aimerez aussi',
        'No products found' => 'Aucun produit trouvé',
        'Search' => 'Rechercher',
        'Filter' => 'Filtrer',
        'Filters' => 'Filtres',
        'Reset filters' => 'Réinitialiser',
        'Category' => 'Catégorie',
        'Size' => 'Taille',
        'Style' => 'Style',
        'From' => 'De',
        'To' => 'À',
        'Privacy policy' => 'Politique de confidentialité',
    );

    if ($lang === 'en') return $text;
    if (isset($fr[$text])) return $fr[$text];
    return $translated;
}
