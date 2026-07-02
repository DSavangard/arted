<?php
// ── Предотвращение мигания белого фона при переходе между страницами ───────
// Причина: браузер рендерит страницу с дефолтным светлым фоном до того,
// как WordPress/Elementor добавит класс dark-theme на body.
// Решение: выводим фоновый цвет в первом тэге <style> в <head> (priority -99)

add_action('wp_head', function() {
    echo '<style id="arted-critical-bg">
html{background:#070707}
html body{background:#070707}
</style>';
}, -99);
