# ⚙️ Налаштування PHP для Catalog Master

## 🎯 Мінімальні вимоги

- **PHP версія:** 8.0 або вище
- **WordPress:** 5.0 або вище
- **MySQL:** 5.6 або вище

## 🔧 Обов'язкові налаштування PHP

### ✅ Критичні параметри:

```ini
# Дозволити відкриття URL (для Google Sheets)
allow_url_fopen = On

# Дозволити завантаження файлів
file_uploads = On

# Логування помилок
log_errors = On

# Часова зона
date.timezone = Europe/Kiev
```

### 🚀 Рекомендовані для продуктивності:

```ini
# Час виконання скрипта (для великих імпортів)
max_execution_time = 300

# Ліміт пам'яті
memory_limit = 512M

# Розмір POST запиту
post_max_size = 64M

# Розмір завантаженого файлу
upload_max_filesize = 32M

# Кількість файлів для завантаження
max_file_uploads = 100

# Рівень звітності про помилки
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
```

### 🔒 Безпека (для продакшн):

```ini
# НЕ показувати помилки користувачам
display_errors = Off

# НЕ показувати версію PHP
expose_php = Off

# Увімкнути тільки для розробки
display_startup_errors = Off
```

### 🚫 Функції які НЕ повинні бути заблоковані:

Переконайтеся, що ці функції **відсутні** в `disable_functions`:

```ini
# Базові функції файлової системи
file_get_contents, fopen, fwrite, fclose, mkdir, rmdir, unlink

# HTTP функції
curl_init, curl_exec, curl_close, curl_setopt

# JSON функції  
json_encode, json_decode

# Функції часу
time, date, strtotime

# Функції масивів та рядків
array_merge, explode, implode, str_replace, preg_match
```

## 🛠️ Способи налаштування

### 1. Через файл `.htaccess`

Створіть або відредагуйте файл `.htaccess` в кореневій папці WordPress:

```apache
# PHP налаштування для Catalog Master
php_value allow_url_fopen On
php_value file_uploads On
php_value log_errors On
php_value max_execution_time 300
php_value memory_limit 512M
php_value post_max_size 64M
php_value upload_max_filesize 32M
php_value max_file_uploads 100

# Тільки для розробки (видаліть на продакшн)
php_value display_errors On
php_value display_startup_errors On
```

### 2. Через файл `wp-config.php`

Додайте в файл `wp-config.php` перед рядком `/* That's all, stop editing! */`:

```php
// PHP налаштування для Catalog Master
ini_set('allow_url_fopen', '1');
ini_set('file_uploads', '1');
ini_set('log_errors', '1');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '512M');
ini_set('post_max_size', '64M');
ini_set('upload_max_filesize', '32M');
ini_set('max_file_uploads', '100');

// Тільки для розробки
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('error_reporting', E_ALL);

// Додаткові налаштування для дебагу
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SAVEQUERIES', true);
```

### 3. Через хостинг панель

#### cPanel:
1. Логін в cPanel
2. **Software** → **Select PHP Version** або **MultiPHP INI Editor**
3. Виберіть домен
4. Змініть параметри або завантажте custom `php.ini`

#### DirectAdmin:
1. Логін в DirectAdmin  
2. **Advanced Features** → **PHP Settings**
3. Виберіть версію PHP та змініть параметри

#### Plesk:
1. Логін в Plesk
2. **Websites & Domains** → ваш домен
3. **PHP Settings**
4. Змініть потрібні параметри

### 4. Через custom `php.ini`

Створіть файл `php.ini` в кореневій папці WordPress:

```ini
; Catalog Master PHP Configuration

; Basic settings
allow_url_fopen = On
file_uploads = On
log_errors = On
max_execution_time = 300
memory_limit = 512M
post_max_size = 64M
upload_max_filesize = 32M
max_file_uploads = 100

; Timezone
date.timezone = "Europe/Kiev"

; Error reporting (adjust for production)
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On

; Security
expose_php = Off

; Session settings
session.cookie_secure = On
session.cookie_httponly = On
session.use_strict_mode = On

; Other useful settings
default_charset = "UTF-8"
mbstring.internal_encoding = "UTF-8"
```

## 🔍 Перевірка налаштувань

### 1. Через WordPress Admin
1. Встановіть оновлений плагін Catalog Master
2. Перейдіть до **Catalog Master → Логи та дебаг**
3. Подивіться секцію **"Конфігурація PHP"**
4. Всі критичні параметри повинні бути зелені

### 2. Через phpinfo()
Створіть тимчасовий файл `test-php.php` в кореневій папці:

```php
<?php
phpinfo();
?>
```

Відкрийте `https://ваш-сайт.com/test-php.php` та знайдіть потрібні параметри.

**❗ Видаліть файл після перевірки з безпеки!**

### 3. Через WP CLI (якщо доступний)
```bash
wp eval "echo 'allow_url_fopen: ' . (ini_get('allow_url_fopen') ? 'On' : 'Off') . PHP_EOL;"
wp eval "echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
wp eval "echo 'max_execution_time: ' . ini_get('max_execution_time') . PHP_EOL;"
```

## ⚠️ Поширені проблеми

### Проблема: "allow_url_fopen is disabled"
**Рішення:**
1. Зверніться до хостинг провайдера
2. Використайте cURL замість file_get_contents (плагін підтримує обидва)
3. Змініть через `.htaccess` або `php.ini`

### Проблема: "Maximum execution time exceeded"
**Рішення:**
```php
// Додайте в wp-config.php
ini_set('max_execution_time', 300);
```

### Проблема: "Fatal error: Allowed memory size exhausted"
**Рішення:**
```php
// Додайте в wp-config.php
ini_set('memory_limit', '512M');
```

### Проблема: "Call to undefined function curl_init()"
**Рішення:**
- Зверніться до хостинг провайдера для увімкнення cURL
- Або переконайтеся що `allow_url_fopen = On`

## 🏢 Налаштування по типах хостингу

### Shared Hosting (спільний хостинг)
- Зазвичай обмежені можливості
- Використовуйте `.htaccess` або `wp-config.php`
- Зверніться в підтримку хостингу

### VPS/Dedicated Servers
- Повний контроль над `php.ini`
- Можна редагувати `/etc/php/8.x/apache2/php.ini`
- Перезапустіть веб-сервер після змін

### Managed WordPress Hosting
- Кожен провайдер має свої особливості
- WP Engine, Kinsta, SiteGround мають спеціальні панелі
- Зверніться в підтримку хостингу

## 📞 Що робити якщо не вдається налаштувати

1. **Зверніться до хостинг провайдера** з таким повідомленням:

> Доброго дня! Я встановлюю WordPress плагін який потребує наступних PHP налаштувань:
> - allow_url_fopen = On
> - memory_limit = 512M (мінімум 256M)
> - max_execution_time = 300
> - file_uploads = On
> 
> Чи можете допомогти налаштувати ці параметри?

2. **Альтернативне рішення:** Використайте тимчасово через код:

```php
// Додайте в functions.php теми
function catalog_master_php_settings() {
    if (current_user_can('manage_options')) {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '512M');
    }
}
add_action('admin_init', 'catalog_master_php_settings');
```

---

**Після налаштування PHP перезавантажте веб-сервер та очистіть кеш!** 