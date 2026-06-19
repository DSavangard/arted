(function() {
    var translations = {
        fr: {
            'КАТАЛОГ':    'CATALOGUE',
            'Каталог':    'Catalogue',
            'О НАС':      'À PROPOS',
            'О нас':      "À propos",
            'КОНТАКТЫ':   'CONTACTS',
            'Контакты':   'Contacts',
            'ХУДОЖНИКИ':  'ARTISTES',
            'Художники':  'Artistes',
            'КОЛЛЕКЦИИ':  'COLLECTIONS',
            'Коллекции':  'Collections',
            'ГАЛЕРЕЯ':    'GALERIE',
            'Галерея':    'Galerie',
            'БЛОГ':       'BLOG',
            'Блог':       'Blog',
            'ГЛАВНАЯ':    'ACCUEIL',
            'Главная':    'Accueil',
            'КОРЗИНА':    'PANIER',
            'Корзина':    'Panier',
            'КАБИНЕТ':    'ESPACE',
            'Кабинет':    'Espace',
            'ВОЙТИ':      'CONNEXION',
            'Войти':      'Connexion',
        },
        en: {
            'КАТАЛОГ':    'CATALOG',
            'Каталог':    'Catalog',
            'О НАС':      'ABOUT',
            'О нас':      'About',
            'КОНТАКТЫ':   'CONTACTS',
            'Контакты':   'Contacts',
            'ХУДОЖНИКИ':  'ARTISTS',
            'Художники':  'Artists',
            'КОЛЛЕКЦИИ':  'COLLECTIONS',
            'Коллекции':  'Collections',
            'ГАЛЕРЕЯ':    'GALLERY',
            'Галерея':    'Gallery',
            'БЛОГ':       'BLOG',
            'Блог':       'Blog',
            'ГЛАВНАЯ':    'HOME',
            'Главная':    'Home',
            'КОРЗИНА':    'CART',
            'Корзина':    'Cart',
            'КАБИНЕТ':    'CABINET',
            'Кабинет':    'Cabinet',
            'ВОЙТИ':      'LOGIN',
            'Войти':      'Login',
        }
    };

    function getLang() {
        var m = document.cookie.match(/arted_lang=([^;]+)/);
        return m ? m[1] : 'ru';
    }

    function translateNode(node, dict) {
        if (!node) return;
        node.childNodes.forEach(function(child) {
            if (child.nodeType === 3) { // text node
                var t = child.textContent.trim();
                if (dict[t]) child.textContent = child.textContent.replace(t, dict[t]);
            } else if (child.nodeType === 1 && !child.querySelector('img')) {
                translateNode(child, dict);
            }
        });
    }

    function translateMenu() {
        var lang = getLang();
        if (lang === 'ru') return;
        var dict = translations[lang];
        if (!dict) return;

        var selectors = [
            '.elementor-nav-menu a',
            '.e-n-menu-nav a',
            'nav.site-navigation a',
            'nav a',
            '.menu-item a',
        ];

        selectors.forEach(function(sel) {
            document.querySelectorAll(sel).forEach(function(link) {
                translateNode(link, dict);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', translateMenu);
    } else {
        translateMenu();
    }

    // Повторяем через небольшую задержку на случай динамической загрузки Elementor
    setTimeout(translateMenu, 800);
})();
