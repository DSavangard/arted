document.addEventListener('DOMContentLoaded', function() {
    var lang = (document.cookie.match(/arted_lang=([^;]+)/) || [])[1] || 'ru';
    if (lang === 'ru') return;

    var t = {
        fr: {
            'Фильтры':          'Filtres',
            'Все':              'Tous',
            'Все категории':    'Toutes catégories',
            'Все стили':        'Tous les styles',
            'Все размеры':      'Toutes les tailles',
            'Размер':           'Taille',
            'Стиль':            'Style',
            'Цена':             'Prix',
            'Применить':        'Appliquer',
            'Сбросить':         'Réinitialiser',
            'Показать':         'Afficher',
            'Категория':        'Catégorie',
            'от':               'de',
            'до':               "à",
            'руб':              '₽',
            'Сортировать по':   'Trier par',
            'Новинки':          'Nouveautés',
            'Популярные':       'Populaires',
            'Дешевле':          'Prix croissant',
            'Дороже':           'Prix décroissant',
            'По алфавиту':      'Alphabétique',
        },
        en: {
            'Фильтры':          'Filters',
            'Все':              'All',
            'Все категории':    'All categories',
            'Все стили':        'All styles',
            'Все размеры':      'All sizes',
            'Размер':           'Size',
            'Стиль':            'Style',
            'Цена':             'Price',
            'Применить':        'Apply',
            'Сбросить':         'Reset',
            'Показать':         'Show',
            'Категория':        'Category',
            'от':               'from',
            'до':               'to',
            'руб':              '₽',
            'Сортировать по':   'Sort by',
            'Новинки':          'New',
            'Популярные':       'Popular',
            'Дешевле':          'Price: low to high',
            'Дороже':           'Price: high to low',
            'По алфавиту':      'A-Z',
        }
    };

    var dict = t[lang] || {};

    // Переводим текстовые ноды в указанных контейнерах
    var selectors = [
        '.arted-filters',
        '.e-filter',
        '.jet-filters-area',
        '[class*="filter"]',
        '.woocommerce-ordering',
        'select.orderby',
        '.arted-sort',
    ];

    function translateText(el) {
        el.childNodes.forEach(function(node) {
            if (node.nodeType === 3) {
                var text = node.textContent.trim();
                if (dict[text]) {
                    node.textContent = node.textContent.replace(text, dict[text]);
                }
            } else if (node.nodeType === 1) {
                translateText(node);
            }
        });
    }

    selectors.forEach(function(sel) {
        document.querySelectorAll(sel).forEach(translateText);
    });

    // Переводим select options (сортировка WooCommerce)
    document.querySelectorAll('select.orderby option, select[name="orderby"] option').forEach(function(opt) {
        if (dict[opt.textContent.trim()]) opt.textContent = dict[opt.textContent.trim()];
    });

    // Переводим категории и атрибуты из window.artedTranslations
    if (window.artedTranslations) {
        var cats  = artedTranslations.cats  || {};
        var attrs = artedTranslations.attrs || {};

        document.querySelectorAll('.product-cat, [class*="cat"], .jet-filter-items-list li, .e-filter-item').forEach(function(el) {
            var text = el.textContent.trim().replace(/\s*\(\d+\)$/, '');
            if (cats[text] && cats[text][lang]) {
                el.childNodes.forEach(function(node) {
                    if (node.nodeType === 3 && node.textContent.trim().replace(/\s*\(\d+\)$/, '') === text) {
                        node.textContent = node.textContent.replace(text, cats[text][lang]);
                    }
                });
            }
            if (attrs[text] && attrs[text][lang]) {
                el.childNodes.forEach(function(node) {
                    if (node.nodeType === 3 && node.textContent.trim() === text) {
                        node.textContent = attrs[text][lang];
                    }
                });
            }
        });
    }

    // Анимированный заголовок
    setTimeout(function() {
        var headlines = document.querySelectorAll('.e-animated-headline__dynamic-wrapper, .elementor-headline-dynamic-wrapper');
        if (!headlines.length) return;
        headlines.forEach(function(el) {
            var items = el.querySelectorAll('.e-animated-headline__dynamic-text, .elementor-headline-dynamic-text');
            items.forEach(function(item) {
                var text = item.textContent.trim();
                if (dict[text]) item.textContent = dict[text];
            });
        });
    }, 1200);
});
