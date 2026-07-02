<?php
// ── Автосворачивание окна выбора темы на мобильных через 2.5 сек ─────────
// На мобильных (≤ 768px) окно с выбором темы сворачивается в круглую точку
// через 2.5 сек после открытия или после загрузки страницы.
//
// СЕЛЕКТОР: обновить ARTED_THEME_WIDGET_SELECTOR под реальный класс виджета.
// Найти: открыть DevTools → нажать на окно темы → скопировать класс блока.

define('ARTED_THEME_WIDGET_SELECTOR', '.theme-toggle-mob');

add_action('wp_head', function() { ?>
<style>
/* ── Анимация сворачивания в точку ──────────────────────────────────── */
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
    .arted-theme-collapsed > * {
        display: none !important;
    }
    /* При касании — разворачивается обратно */
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
    .arted-theme-collapsed:focus-within > * {
        display: revert !important;
    }
}
</style>
<script>
(function() {
    if (window.innerWidth > 768) return;

    var SEL   = <?= json_encode(ARTED_THEME_WIDGET_SELECTOR) ?>;
    var DELAY = 2500; // мс

    function collapse(el) {
        el.classList.add('arted-theme-collapsed');

        // Разворачиваем при касании, снова сворачиваем через 2.5 сек
        function onTouch() {
            el.classList.remove('arted-theme-collapsed');
            clearTimeout(el._arted_timer);
            el._arted_timer = setTimeout(function() {
                el.classList.add('arted-theme-collapsed');
            }, DELAY);
        }
        el.addEventListener('touchstart', onTouch, { passive: true });
        el.addEventListener('click', onTouch);
    }

    function init() {
        var els = document.querySelectorAll(SEL);
        els.forEach(function(el) {
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
