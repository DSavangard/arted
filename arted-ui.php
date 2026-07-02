<?php
// ── Фронтенд UI: мигание тёмной темы + автосворачивание виджета темы ──────

// ── Предотвращение белого мигания при переходе между страницами ───────────
// Причина: браузер рендерит страницу с дефолтным светлым фоном до того,
// как Elementor добавит класс dark-theme на body.
add_action('wp_head', function() {
    echo '<style id="arted-critical-bg">
html{background:#070707}
html body{background:#070707}
</style>';
}, -99);

// ── Автосворачивание окна выбора темы на мобильных через 2.5 сек ─────────
// Виджет с классом .theme-toggle-mob (в контейнере .dot-mobile) сворачивается
// в 14×14px точку через 2.5 сек, разворачивается при касании.

define('ARTED_THEME_WIDGET_SELECTOR', '.theme-toggle-mob');

add_action('wp_head', function() { ?>
<style>
@media (max-width: 768px) {
    .arted-theme-collapsed {
        width:  14px !important;
        height: 14px !important;
        min-width:  14px !important;
        min-height: 14px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        padding: 0 !important;
        opacity: 0.5 !important;
        transition: width .4s ease, height .4s ease, opacity .4s ease, border-radius .4s ease !important;
        cursor: pointer !important;
    }
    .arted-theme-collapsed > * { display: none !important; }
    .arted-theme-collapsed:active,
    .arted-theme-collapsed:focus-within {
        width:  auto !important;
        height: auto !important;
        min-width:  0 !important;
        border-radius: 4px !important;
        opacity: 1 !important;
        padding: revert !important;
    }
    .arted-theme-collapsed:active > *,
    .arted-theme-collapsed:focus-within > * { display: revert !important; }
}
</style>
<script>
(function() {
    if (window.innerWidth > 768) return;

    var SEL   = <?= json_encode(ARTED_THEME_WIDGET_SELECTOR) ?>;
    var DELAY = 2500;

    function collapse(el) {
        el.classList.add('arted-theme-collapsed');

        // Клик вешаем на родителя: после сворачивания до 14x14
        // прозрачная Elementor-обёртка перехватывает тапы раньше самого виджета
        var clickTarget = el.closest('.elementor-widget-html') || el.parentElement || el;

        if (clickTarget._arted_expand_init) return;
        clickTarget._arted_expand_init = true;

        function onTouch() {
            if (!el.classList.contains('arted-theme-collapsed')) return;
            el.classList.remove('arted-theme-collapsed');
            clearTimeout(el._arted_timer);
            el._arted_timer = setTimeout(function() {
                el.classList.add('arted-theme-collapsed');
            }, DELAY);
        }
        clickTarget.addEventListener('touchstart', onTouch, { passive: true });
        clickTarget.addEventListener('click', onTouch);
    }

    function init() {
        document.querySelectorAll(SEL).forEach(function(el) {
            if (el._arted_collapse_init) return;
            el._arted_collapse_init = true;
            el._arted_timer = setTimeout(function() { collapse(el); }, DELAY);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Повтор для виджетов, загружаемых динамически Elementor
    setTimeout(init, 1000);
    setTimeout(init, 3000);
})();
</script>
<?php }, 20);
