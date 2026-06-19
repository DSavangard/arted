# Arted Gallery — WPCode Snippets

Все сниппеты для arted.gallery (WordPress + Elementor + WooCommerce).

## Структура

| Файл | Тип | Описание |
|------|-----|----------|
| `arted-artist-register-form.php` | PHP | Форма регистрации художника |
| `arted-artist-role.php` | PHP | Роль художника, скрытие админбара |
| `arted-email-options.php` | PHP | Шаблоны писем RU/EN/FR |
| `arted-settings-page.php` | PHP | Настройки галереи (Telegram и др.) |
| `arted-artist-on-register.php` | PHP | Email + Telegram при регистрации |
| `arted-cabinet-nav.php` | PHP | Навигация кабинета художника |
| `arted-cabinet-styles.css` | CSS | Стили кабинета (dark/light тема) |
| `arted-profile-form.php` | PHP | Форма профиля художника (4 шага) |
| `arted-profile-save.php` | PHP | AJAX сохранение профиля + загрузка фото |
| `arted-profile-styles.css` | CSS | Стили формы профиля |
| `arted-verification.php` | PHP | Верификация через Telegram + админку |
| `arted-artist-page.php` | PHP | Публичная страница художника /artist/{slug}/ |
| `arted-artist-page-styles.css` | CSS | Стили публичной страницы |
| `tumbler-registration-style.css` | CSS | Стили переключателя роли на регистрации |
| `functions.php` | PHP | Изменения в child theme functions.php |

## WPCode настройки

Все PHP сниппеты — **Run Everywhere**  
CSS сниппеты — **Site Wide Header**  
Исключение: `tumbler-registration-style.css` — **Site Wide Header**

## Кастомная роль

Роль `artist` создана через User Role Editor.  
Capabilities: `read` + `upload_files`

## Проверка роли

Всегда использовать:
```php
in_array('artist', (array) $user->roles)
```
НЕ использовать `current_user_can('artist')` — не работает для кастомных ролей.
