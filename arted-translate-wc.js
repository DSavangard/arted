document.addEventListener('DOMContentLoaded', function() {
    var lang = (document.cookie.match(/arted_lang=([^;]+)/) || [])[1] || 'ru';
    if (lang === 'ru') return;

    var t = {
        fr: {
            'Личный кабинет':               'Mon compte',
            'Мой аккаунт':                  'Mon compte',
            'В корзину':                    'Ajouter au panier',
            'Добавить в корзину':           'Ajouter au panier',
            'Корзина':                      'Panier',
            'Оформить заказ':               'Commander',
            'Продолжить оформление':        'Continuer',
            'Перейти в корзину':            'Voir le panier',
            'Итого':                        'Total',
            'Промежуточный итог':           'Sous-total',
            'Стоимость доставки':           'Livraison',
            'Купить':                       'Acheter',
            'Цена':                         'Prix',
            'Наличие':                      'Disponibilité',
            'В наличии':                    'En stock',
            'Нет в наличии':                'Rupture de stock',
            'Количество':                   'Quantité',
            'Войти':                        'Se connecter',
            'Зарегистрироваться':           "S'inscrire",
            'Имя пользователя или email':   "Nom d'utilisateur ou e-mail",
            'Пароль':                       'Mot de passe',
            'Запомнить меня':               'Se souvenir de moi',
            'Забыли пароль?':               'Mot de passe oublié ?',
            'Адрес электронной почты':      'Adresse e-mail',
            'Имя':                          'Prénom',
            'Фамилия':                      'Nom',
            'Сохранить':                    'Enregistrer',
            'Удалить':                      'Supprimer',
            'Заказы':                       'Commandes',
            'Адреса':                       'Adresses',
            'Данные аккаунта':              'Détails du compte',
            'Нет заказов.':                 'Aucune commande.',
            'Посмотреть товары':            'Voir les produits',
            'Похожие товары':               'Produits similaires',
            'Описание':                     'Description',
            'Доставка':                     'Livraison',
            'Отзывы':                       'Avis',
            'Написать отзыв':               'Écrire un avis',
            'Нет отзывов':                  'Aucun avis',
        },
        en: {
            'Личный кабинет':               'My account',
            'Мой аккаунт':                  'My account',
            'В корзину':                    'Add to cart',
            'Добавить в корзину':           'Add to cart',
            'Корзина':                      'Cart',
            'Оформить заказ':               'Checkout',
            'Продолжить оформление':        'Continue',
            'Перейти в корзину':            'View cart',
            'Итого':                        'Total',
            'Промежуточный итог':           'Subtotal',
            'Стоимость доставки':           'Shipping',
            'Купить':                       'Buy',
            'Цена':                         'Price',
            'Наличие':                      'Availability',
            'В наличии':                    'In stock',
            'Нет в наличии':                'Out of stock',
            'Количество':                   'Quantity',
            'Войти':                        'Log in',
            'Зарегистрироваться':           'Register',
            'Имя пользователя или email':   'Username or email',
            'Пароль':                       'Password',
            'Запомнить меня':               'Remember me',
            'Забыли пароль?':               'Lost your password?',
            'Адрес электронной почты':      'Email address',
            'Имя':                          'First name',
            'Фамилия':                      'Last name',
            'Сохранить':                    'Save',
            'Удалить':                      'Remove',
            'Заказы':                       'Orders',
            'Адреса':                       'Addresses',
            'Данные аккаунта':              'Account details',
            'Нет заказов.':                 'No orders yet.',
            'Посмотреть товары':            'Browse products',
            'Похожие товары':               'Related products',
            'Описание':                     'Description',
            'Доставка':                     'Shipping',
            'Отзывы':                       'Reviews',
            'Написать отзыв':               'Write a review',
            'Нет отзывов':                  'No reviews',
        }
    };

    var dict = t[lang] || {};

    function translateEl(el) {
        if (!el) return;
        el.childNodes.forEach(function(node) {
            if (node.nodeType === 3) {
                var text = node.textContent.trim();
                if (dict[text]) node.textContent = node.textContent.replace(text, dict[text]);
            } else if (node.nodeType === 1 && !['SCRIPT','STYLE','INPUT','TEXTAREA'].includes(node.tagName)) {
                translateEl(node);
            }
        });
    }

    var zones = [
        '.woocommerce-page',
        '.woocommerce',
        '.woocommerce-cart',
        '.woocommerce-checkout',
        '.woocommerce-account',
        '.woocommerce-MyAccount-navigation',
        '.woocommerce-product-details__short-description',
        '.woocommerce-tabs',
        '.woocommerce-message',
        '.woocommerce-info',
        '.cart-contents',
        '.wc-tab',
        '[class*="woocommerce"]',
    ];

    zones.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(translateEl);
    });

    // Переводим автора и город из artedAuthorMap
    if (window.artedAuthorMap) {
        var authors = artedAuthorMap.authors || {};
        var cities  = artedAuthorMap.cities  || {};

        document.querySelectorAll('.arted-product-author, .arted-product-author-name, .arted-product-author-single').forEach(function(el) {
            var text = el.textContent.trim();
            // Ищем по частичному совпадению имени
            Object.keys(authors).forEach(function(nameRu) {
                if (text.indexOf(nameRu) !== -1 && authors[nameRu][lang]) {
                    var link = el.querySelector('a');
                    if (link) link.textContent = link.textContent.replace(nameRu, authors[nameRu][lang]);
                    else el.textContent = el.textContent.replace(nameRu, authors[nameRu][lang]);
                }
            });
            Object.keys(cities).forEach(function(cityRu) {
                if (text.indexOf(cityRu) !== -1 && cities[cityRu][lang]) {
                    el.innerHTML = el.innerHTML.replace(cityRu, cities[cityRu][lang]);
                }
            });
        });
    }
});
