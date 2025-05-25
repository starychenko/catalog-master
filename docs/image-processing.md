# 🖼️ Професійна система обробки зображень

**Catalog Master v1.1.5** включає повноцінну систему автоматичного завантаження, обробки та оптимізації зображень із Google Sheets.

---

## 🎯 **Огляд системи**

### **Що робить система**
1. 🔗 **Автоматично завантажує** зображення з URL у Google Sheets
2. 🎨 **Обробляє та оптимізує** - масштабування, конвертація в JPG
3. 📁 **Організовує в структуру папок** на сервері
4. 🔄 **Кешує категорії** - уникає дублювання зображень
5. 📊 **Логує всі операції** для моніторингу

### **Підтримувані типи зображень**
- 🖼️ **Вхідні формати:** JPG, PNG, GIF, WebP, BMP
- 📤 **Вихідний формат:** JPG (90% якість)
- 📐 **Цільовий розмір:** 1000x1000px (з кропом)

---

## 📁 **Структура папок на сервері**

```
/wp-content/uploads/catalog-master-images/
└── catalog-{ID}/                    ← ID каталогу
    ├── products/                    ← Зображення товарів
    │   ├── product_123.jpg          ← product_id.jpg
    │   ├── product_456.jpg
    │   └── product_789.jpg
    └── categories/                  ← Зображення категорій
        ├── electronics.jpg          ← category_id_1.jpg
        ├── phones.jpg               ← category_id_2.jpg  
        └── laptops.jpg              ← category_id_3.jpg
```

### **Правила найменування файлів**
- **Товари:** `{product_id}.jpg` - унікальний ID товару
- **Категорії:** `{category_id_1}.jpg` - ID категорії першого рівня
- **Fallback:** `product_{row_number}.jpg` якщо немає ID

---

## 🔧 **Технічна реалізація**

### **Головна функція обробки**
```php
private static function download_and_process_image(
    $image_url,           // URL зображення з Google Sheets
    $catalog_id,          // ID каталогу
    $filename_base,       // Базове ім'я файлу (product_id або category_id)
    $type = 'product',    // 'product' або 'category'
    $target_width = 1000, // Цільова ширина
    $target_height = 1000 // Цільова висота
) {
    // 1. Валідація URL
    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        return '';
    }
    
    // 2. Створення структури папок
    $upload_dir = wp_upload_dir();
    $sub_dir = ($type === 'product') ? 'products/' : 'categories/';
    $target_dir = $upload_dir['basedir'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $sub_dir;
    
    // 3. Завантаження в тимчасовий файл
    $temp_file = download_url($image_url, 300); // 5 хвилин timeout
    
    // 4. Обробка через WordPress Image Editor
    $image_editor = wp_get_image_editor($temp_file);
    $image_editor->set_quality(90);
    $image_editor->resize($target_width, $target_height, true); // true = crop
    
    // 5. Збереження як JPG
    $final_filename = sanitize_file_name($filename_base) . '.jpg';
    $saved = $image_editor->save($target_dir . $final_filename, 'image/jpeg');
    
    // 6. Повернення URL
    return $upload_dir['baseurl'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $sub_dir . $final_filename;
}
```

### **Розумне кешування категорій**
```php
// У функції process_data_chunk_for_import
if ($image_type === 'category') {
    // Перевіряємо кеш
    if (isset($updated_image_cache[$original_value])) {
        $item[$catalog_column] = $updated_image_cache[$original_value];
    } else {
        // Завантажуємо та кешуємо
        $local_image_url = self::download_and_process_image(...);
        if (!empty($local_image_url)) {
            $updated_image_cache[$original_value] = $local_image_url;
        }
        $item[$catalog_column] = $local_image_url;
    }
}
```

---

## 📊 **Мапінг стовпців зображень**

### **Стовпці що оброблюються як зображення**
```php
// Розпізнання стовпців зображень
if ($catalog_column === 'product_image_url' || strpos($catalog_column, 'category_image_') === 0) {
    
    // Обробка зображення
}
```

### **Підтримувані поля**
- `product_image_url` → `/products/product_id.jpg`
- `category_image_1` → `/categories/category_id_1.jpg`
- `category_image_2` → `/categories/category_id_2.jpg`  
- `category_image_3` → `/categories/category_id_3.jpg`

---

## 🎨 **Параметри обробки зображень**

### **За замовчуванням**
```php
$target_width = 1000;    // 1000px ширина
$target_height = 1000;   // 1000px висота
$quality = 90;           // 90% якість JPG
$crop = true;            // Обрізка зі збереженням пропорцій
```

### **Налаштування через константи**
```php
// У wp-config.php
define('CATALOG_MASTER_IMAGE_WIDTH', 1200);     // Кастомна ширина
define('CATALOG_MASTER_IMAGE_HEIGHT', 1200);    // Кастомна висота
define('CATALOG_MASTER_IMAGE_QUALITY', 85);     // Кастомна якість
```

### **Хуки для fine-tuning**
```php
// Фільтри для розробників
add_filter('catalog_master_image_target_size', function($size, $type) {
    if ($type === 'category') {
        return ['width' => 800, 'height' => 800]; // Менші категорії
    }
    return $size;
}, 10, 2);

add_filter('catalog_master_image_process_quality', function($quality, $type) {
    return $type === 'product' ? 95 : 85; // Вища якість для товарів
}, 10, 2);
```

---

## 🔄 **Система кешування зображень**

### **Принцип роботи**
1. **Зображення категорій** часто повторюються для багатьох товарів
2. **Кеш зберігає** відповідність: `URL_з_Google_Sheets → URL_на_сервері`
3. **При повторному URL** - просто використовується готове зображення
4. **Економія:** трафіку, часу обробки, дискового простору

### **Структура кешу**
```php
$category_image_cache = [
    'https://example.com/category1.jpg' => '/wp-content/uploads/.../electronics.jpg',
    'https://example.com/category2.jpg' => '/wp-content/uploads/.../phones.jpg',
    // ... інші кешовані URL
];
```

### **Управління кешем**
```php
// Початок імпорту - порожній кеш
set_transient($cache_key, array(), HOUR_IN_SECONDS);

// Під час батчу - оновлення кешу
set_transient($cache_key, $updated_cache, HOUR_IN_SECONDS);

// Завершення імпорту - очищення кешу
delete_transient($cache_key);
```

---

## 🚨 **Обробка помилок**

### **Типи помилок та реакція**
```php
// Невалідний URL
if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
    CatalogMaster_Logger::warning("Invalid image URL: {$image_url}");
    return '';
}

// Помилка завантаження
if (is_wp_error($temp_file)) {
    CatalogMaster_Logger::error("Download failed: " . $temp_file->get_error_message());
    return '';
}

// Помилка обробки
if (is_wp_error($image_editor)) {
    CatalogMaster_Logger::error("Image editor failed: " . $image_editor->get_error_message());
    return '';
}

// Помилка збереження
if (!$saved || is_wp_error($saved)) {
    CatalogMaster_Logger::error("Save failed for: {$image_url}");
    return '';
}
```

### **Fallback стратегія**
- ❌ **Не вдалося завантажити** → порожнє поле, продовжуємо імпорт
- ❌ **Не вдалося обробити** → використовуємо оригінальний URL
- ✅ **Graceful degradation** - імпорт не зупиняється через зображення

---

## 📈 **Продуктивність та оптимізація**

### **Статистика обробки**
```php
// Логування результатів
CatalogMaster_Logger::info("Image processed and saved", [
    'final_url' => $final_url,
    'original_url' => $image_url,
    'file_size_before' => filesize($temp_file),
    'file_size_after' => filesize($final_path),
    'processing_time' => $end_time - $start_time
]);
```

### **Оптимізації для великих каталогів**
1. **Timeout збільшено** до 300 секунд (5 хвилин)
2. **Паралельна обробка** в межах батчу
3. **Кешування категорій** - економія до 80% операцій
4. **Compression JPG** - економія до 60% дискового простору

### **Рекомендації по розмірах**
| Тип товару | Розмір зображення | Якість | Приблизний розмір файлу |
|------------|------------------|--------|------------------------|
| Електроніка | 1000x1000 | 90% | 80-150KB |
| Одяг | 800x800 | 85% | 60-120KB |
| Категорії | 600x600 | 80% | 40-80KB |

---

## 🛠️ **Діагностика та відлагодження**

### **Логи обробки зображень**
```php
// У CatalogMaster_Logger
CatalogMaster_Logger::info("🖼️ Starting image processing", [
    'url' => $image_url,
    'type' => $type,
    'filename' => $filename_base
]);

CatalogMaster_Logger::info("✅ Image processed successfully", [
    'local_url' => $local_url,
    'file_size' => filesize($local_path)
]);
```

### **Перевірка структури папок**
```bash
# SSH команди для діагностики
ls -la /wp-content/uploads/catalog-master-images/
du -sh /wp-content/uploads/catalog-master-images/catalog-*/
find /wp-content/uploads/catalog-master-images/ -name "*.jpg" | wc -l
```

### **Права доступу**
```bash
# Правильні права для папок та файлів
chmod 755 /wp-content/uploads/catalog-master-images/
chmod 755 /wp-content/uploads/catalog-master-images/catalog-*/
chmod 755 /wp-content/uploads/catalog-master-images/catalog-*/products/
chmod 755 /wp-content/uploads/catalog-master-images/catalog-*/categories/
chmod 644 /wp-content/uploads/catalog-master-images/catalog-*/*/*.jpg
```

---

## 🔧 **Налаштування WordPress**

### **Необхідні розширення PHP**
```ini
extension=gd          ; Обробка зображень
extension=imagick     ; Альтернативний редактор (опціонально)
extension=exif        ; Метадані зображень
```

### **Налаштування upload**
```ini
upload_max_filesize = 50M    ; Для великих зображень
post_max_size = 50M          ; POST дані
memory_limit = 256M          ; Обробка зображень вимагає памʼяті
```

### **WordPress константи**
```php
// У wp-config.php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

---

## 🎯 **Найкращі практики**

### **Для користувачів**
1. ✅ **Використовуйте якісні зображення** - мінімум 800x800px
2. ✅ **Перевіряйте URL** - вони мають бути доступними публічно
3. ✅ **Не використовуйте дуже великі файли** - понад 10MB
4. ✅ **Унікальні імена категорій** - для кращого кешування

### **Для розробників**
1. ✅ **Моніторьте логи** обробки зображень
2. ✅ **Тестуйте різні формати** вхідних зображень
3. ✅ **Налаштовуйте якість** під потреби проекту
4. ✅ **Очищайте старі зображення** при зміні каталогів

### **Оптимізація продуктивності**
```php
// Кастомна функція очищення старих зображень
function cleanup_old_catalog_images($catalog_id) {
    $catalog_dir = wp_upload_dir()['basedir'] . '/catalog-master-images/catalog-' . $catalog_id;
    if (file_exists($catalog_dir)) {
        // Recursive delete
        array_map('unlink', glob("$catalog_dir/*/*.jpg"));
        rmdir($catalog_dir . '/products');
        rmdir($catalog_dir . '/categories');
        rmdir($catalog_dir);
    }
}
```

---

## 🔮 **Майбутні покращення**

### **Планові функції**
- 🖼️ **WebP підтримка** - сучасний формат зображень
- 🎨 **Multiple sizes** - thumbnails, medium, large
- 🔄 **Lazy loading** - оптимізація завантаження
- 📱 **Responsive images** - адаптивні розміри

### **Advanced функції**
- 🤖 **AI оптимізація** - автоматичне кадрування та покращення
- 🏷️ **Auto-tagging** - розпізнавання об'єктів на зображеннях
- 🎭 **Watermarks** - автоматичні водяні знаки
- 📊 **Analytics** - статистика використання зображень

---

## 🐛 **Типові проблеми та рішення**

### **❌ Проблема: Зображення категорій не завантажуються локально**

**Симптоми:**
- Зображення товарів працюють (локальні URL в експорті)
- Зображення категорій НЕ працюють (залишаються зовнішні URL)
- В логах видно fallback імена файлів: `category1_123.jpg` замість `electronics.jpg`

**Причина:**
```php
// ПРОБЛЕМНИЙ порядок мапінгу:
category_image_1 → Electronics Image    // Обробляється ПЕРШИМ
category_id_1    → Category ID          // Обробляється ДРУГИМ

// В момент обробки зображення, category_id_1 ще порожній!
```

**✅ Вирішення (виправлено у v1.1.0):**
Система тепер отримує `category_id` безпосередньо з сирих даних рядка, незалежно від порядку стовпців в мапінгу.

**Діагностичні логи:**
```php
🖼️ Processing category image: {
    "catalog_column": "category_image_1",
    "category_id_key": "category_id_1", 
    "category_id_raw": "electronics",     // ← Тепер правильно знаходить
    "filename_base": "electronics",       // ← Правильне ім'я файлу
    "original_url": "https://example.com/img.jpg"
}
```

### **❌ Проблема: Дублювання завантажень зображень**

**Симптоми:**
- Одне й те ж зображення категорії завантажується кілька разів
- Повільний імпорт великих каталогів

**Причина:**
Кеш зображень категорій не працює правильно.

**✅ Рішення:**
```php
// Перевірка в логах:
✅ Using cached category image: {
    "original_url": "https://example.com/electronics.jpg",
    "cached_url": "/wp-content/uploads/.../electronics.jpg"
}

// Успішне кешування:
🎨 Downloaded and cached category image: {
    "original_url": "https://example.com/electronics.jpg", 
    "local_url": "/wp-content/uploads/.../electronics.jpg",
    "filename_base": "electronics"
}
```

### **❌ Проблема: Хибні спрацьовування на стовпцях зображень (виправлено у v1.1.2)**

**Симптоми:**
- Стовпці з назвами `image_description`, `image_width`, `image_size` обробляються як зображення
- Повільний імпорт через спроби завантажити текстові поля як URL зображень
- Помилки в логах про недійсні URL зображень

**Причина (до v1.1.2):**
```php
// ❌ ПРОБЛЕМНА логіка - занадто широка перевірка:
if (strpos($catalog_column, 'image_url') !== false || strpos($catalog_column, 'image_') === 0)

// Спрацьовувала на:
- image_description  ← НЕ зображення!
- image_width       ← НЕ зображення!
- some_image_url_field ← НЕ зображення!
```

**✅ Вирішення (v1.1.2+):**
```php
// ✅ ТОЧНА логіка - тільки справжні стовпці зображень:
if ($catalog_column === 'product_image_url' || strpos($catalog_column, 'category_image_') === 0)

// Спрацьовує ТІЛЬКИ на:
- product_image_url     ✅ 
- category_image_1      ✅
- category_image_2      ✅  
- category_image_3      ✅
```

**Результат:**
- 🚀 **Швидший імпорт** - система не намагається завантажувати текстові поля
- 🎯 **Точна обробка** - тільки справжні зображення обробляються
- 📊 **Чистіші логи** - немає помилок про недійсні URL

### **✅ Нова функція: Підтримка category_name для зображень (v1.1.3+)**

**Тепер система підтримує гнучкий мапінг зображень категорій:**

**Пріоритет імен файлів:**
1. **`category_id_1`** (якщо є) → `electronics.jpg`
2. **`category_name_1`** (якщо немає ID) → `elektronika.jpg` (транслітерація)
3. **Fallback** → `category1_123.jpg`

**Приклади транслітерації:**
```php
"Електроніка"           → "elektronika.jpg"
"Смартфони і планшети"  → "smartfony_i_planshety.jpg"  
"Home & Garden"         → "home_garden.jpg"
"Авто-мото"             → "avto_moto.jpg"
"Beauty & Health"       → "beauty_health.jpg"
```

**Підтримувані мови:**
- 🇺🇦 **Українська** - повна підтримка (і, ї, є, ґ)
- 🇷🇺 **Російська** - повна підтримка 
- 🇬🇧 **English** - пряма підтримка
- 🌍 **Інші** - безпечне перетворення

**Діагностичні логи:**
```php
🖼️ Processing category image: {
    "category_id_key": "category_id_1",
    "category_name_key": "category_name_1", 
    "category_id_raw": "",                    // ← Порожній
    "category_name_raw": "Електроніка",       // ← Використовується
    "filename_base": "elektronika",           // ← Транслітеровано
    "original_url": "https://example.com/img.jpg"
}
```

---

> 🎨 **Система обробки зображень Catalog Master v1.1.5** забезпечує **професійну якість** та **оптимальну продуктивність** для каталогів будь-якого розміру! 