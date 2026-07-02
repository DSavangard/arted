<?php
// ── Кнопка «Регистрация» рядом с заголовком «ВХОД» (мобильная версия) ────
// Добавляет кнопку справа от заголовка ВХОД с якорем к форме регистрации.
// Работает только на странице my-account при незалогиненном пользователе.

add_action('wp_head', function() {
    if (is_user_logged_in() || !is_account_page()) return;

    $lang = function_exists('arted_get_lang') ? arted_get_lang() : 'ru';
    $labels = [
        'ru' => 'Регистрация',
        'en' => 'Register',
        'fr' => "S'inscrire",
    ];
    $label = $labels[$lang] ?? $labels['ru'];
    ?>
    <style>
    /* ── Обёртка заголовка ВХОД + кнопка ────────────────────────── */
    .arted-login-heading-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }
    .arted-login-heading-row h2 {
        margin: 0 !important;
    }
    .arted-register-link {
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #FD2E04 !important;
        border: 1px solid #FD2E04;
        padding: 6px 14px;
        text-decoration: none !important;
        border-radius: 2px;
        transition: background .2s, color .2s;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .arted-register-link:hover {
        background: #FD2E04;
        color: #070707 !important;
    }

    /* ── Якорь — немного выше реального начала формы регистрации ── */
    .arted-register-anchor {
        display: block;
        position: relative;
        top: -80px;
        visibility: hidden;
        pointer-events: none;
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ── 1. Находим заголовок «ВХОД» ──────────────────────────
        var loginForm = document.querySelector('.woocommerce-form-login');
        if (!loginForm) return;

        var loginHeading = null;

        // Вариант A: h2 непосредственно перед формой или в той же колонке
        var prev = loginForm.previousElementSibling;
        while (prev) {
            if (/^H[1-3]$/.test(prev.tagName)) { loginHeading = prev; break; }
            prev = prev.previousElementSibling;
        }

        // Вариант B: h2 в родительском блоке (u-column1)
        if (!loginHeading) {
            var col = loginForm.closest('.u-column1, .col-1, [class*="col"]');
            if (col) loginHeading = col.querySelector('h2, h3');
        }

        // Вариант C: Elementor heading с текстом «ВХОД»
        if (!loginHeading) {
            document.querySelectorAll('.elementor-heading-title, h2, h3').forEach(function(el) {
                var t = el.textContent.trim().toUpperCase();
                if (t === 'ВХОД' || t === 'LOGIN' || t === 'CONNEXION' || t === 'SE CONNECTER') {
                    loginHeading = el;
                }
            });
        }

        if (!loginHeading) return;

        // ── 2. Оборачиваем заголовок в flex-строку ───────────────
        var row = document.createElement('div');
        row.className = 'arted-login-heading-row';
        loginHeading.parentNode.insertBefore(row, loginHeading);
        row.appendChild(loginHeading);

        var btn = document.createElement('a');
        btn.href = '#arted-register';
        btn.className = 'arted-register-link';
        btn.textContent = <?= json_encode($label) ?>;
        row.appendChild(btn);

        // ── 3. Добавляем якорь к форме регистрации ───────────────
        var regForm = document.querySelector('.woocommerce-form-register');
        if (!regForm) {
            // Ищем h2 со словом «Регистрация»
            document.querySelectorAll('h2, h3').forEach(function(el) {
                var t = el.textContent.trim().toUpperCase();
                if (t.includes('РЕГИСТРА') || t.includes('REGISTER') || t.includes('INSCRI')) {
                    regForm = el.parentElement;
                }
            });
        }
        if (!regForm) return;

        var anchor = document.createElement('span');
        anchor.id = 'arted-register';
        anchor.className = 'arted-register-anchor';
        regForm.prepend(anchor);

        // ── 4. Плавный скролл ────────────────────────────────────
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.getElementById('arted-register');
            if (!target) return;
            var top = target.getBoundingClientRect().top + window.scrollY - 80;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });
    </script>
    <?php
}, 20);
