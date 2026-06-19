(function() {
    function getLang() {
        return document.cookie.split('; ').reduce((acc, c) => {
            const [k, v] = c.split('=');
            return k === 'arted_lang' ? v : acc;
        }, 'ru');
    }

    function translateAll() {
        const lang = getLang();
        if (lang === 'ru') return;

        const t = window.artedTranslations;
        const m = window.artedAuthorMap;

        // Категории в фильтрах
        if (t && t.cats) {
            document.querySelectorAll('.custom-select-option').forEach(el => {
                if (el.dataset.translated) return;
                const text = el.textContent.trim();
                if (t.cats[text] && t.cats[text][lang]) {
                    el.textContent = t.cats[text][lang];
                    el.dataset.translated = '1';
                }
            });
            document.querySelectorAll('.product-cat a, .woocommerce-breadcrumb a, .woocommerce-breadcrumb span').forEach(el => {
                if (el.dataset.translated) return;
                const text = el.textContent.trim();
                if (t.cats[text] && t.cats[text][lang]) {
                    el.textContent = t.cats[text][lang];
                    el.dataset.translated = '1';
                }
            });
        }

        // Атрибуты
        if (t && t.attrs) {
            document.querySelectorAll('.woocommerce-product-attributes td, .widget_layered_nav a').forEach(el => {
                if (el.dataset.translated) return;
                const text = el.textContent.trim();
                if (t.attrs[text] && t.attrs[text][lang]) {
                    el.textContent = t.attrs[text][lang];
                    el.dataset.translated = '1';
                }
            });
        }

        // Автор в сетке
        if (m && m.authors) {
            document.querySelectorAll('.product-author-name').forEach(el => {
                if (el.dataset.translated) return;
                const ru = el.textContent.trim();
                const key = Object.keys(m.authors).find(k => k.toLowerCase() === ru.toLowerCase());
                if (key && m.authors[key][lang]) {
                    el.textContent = m.authors[key][lang];
                    el.dataset.translated = '1';
                }
            });
        }

        // Город в сетке
        if (m && m.cities) {
            document.querySelectorAll('.product-author-city').forEach(el => {
                if (el.dataset.translated) return;
                const ru = el.textContent.trim();
                const clean = ru.replace(/^г\.\s*/, '').trim();
                const key = Object.keys(m.cities).find(k => k.toLowerCase() === clean.toLowerCase());
                if (key && m.cities[key][lang]) {
                    el.textContent = m.cities[key][lang];
                    el.dataset.translated = '1';
                }
            });
        }

        // Автор и город на single product (Elementor heading)
        if (m) {
            document.querySelectorAll('.single-product .elementor-heading-title').forEach(el => {
                if (el.dataset.translated) return;
                const ru = el.textContent.trim();

                if (m.authors) {
                    const key = Object.keys(m.authors).find(k => k.toLowerCase() === ru.toLowerCase());
                    if (key && m.authors[key][lang]) {
                        el.textContent = m.authors[key][lang];
                        el.dataset.translated = '1';
                        return;
                    }
                }

                if (m.cities) {
                    const clean = ru.replace(/^г\.\s*/, '').trim();
                    const key = Object.keys(m.cities).find(k => k.toLowerCase() === clean.toLowerCase());
                    if (key && m.cities[key][lang]) {
                        el.textContent = 'г. ' + m.cities[key][lang];
                        el.dataset.translated = '1';
                    }
                }
            });
        }
    }

    setTimeout(translateAll, 300);
    setTimeout(translateAll, 1000);
    setTimeout(translateAll, 2500);
})();
