# Changelog

## [1.0.2] - 2025-05-24

### 🔧 Виправлення JSON/XML експорту

#### 🎯 **Проблема:** JSON та XML відкривали download URL замість feed URL
**БУЛО:** `https://lwhs.xyz/?catalog_master_export=download&catalog_id=1&format=json` → "Invalid format"  
**СТАЛО:** `https://lwhs.xyz/?catalog_master_export=feed&catalog_id=1&format=json` → працює правильно

#### 🚀 **Рішення:**
- **Smart URL selection** в JavaScript на основі формату
- **CSV/Excel** → відкривають `download_url` (файли для скачування)
- **JSON/XML** → відкривають `feed_url` (веб-фіди)
- **Покращені повідомлення** - показ обох URLs для файлових форматів

#### 💻 **Код змін:**
```javascript
// Визначаємо який URL використовувати
if (format === 'csv' || format === 'excel') {
    urlToOpen = response.data.download_url;  // Файли
} else if (format === 'json' || format === 'xml') {
    urlToOpen = response.data.feed_url;      // Фіди
}
```

#### 📊 **Результат:**
- ✅ **CSV Export** → скачує .csv файл
- ✅ **Excel Export** → скачує .xlsx файл  
- ✅ **JSON Feed** → відкриває JSON фід в браузері
- ✅ **XML Feed** → відкриває XML фід в браузері

---

## [1.0.1] - 2025-05-24

### 🔧 Критичні виправлення експорту

#### 🎯 **Excel експорт - СПРАВЖНІЙ XLSX**
- **Замінено HTML таблицю** на справжній XLSX формат
- **Minimal XLSX implementation** - створення ZIP архіву з XML файлами
- **Fallback до CSV** якщо ZIP не вдається створити
- **Правильний MIME тип**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- **Файл .xlsx** замість .xls

#### 🛡️ **Покращена валідація та безпека**
- **Перевірка порожніх каталогів** - показує помилку замість пустого файлу
- **Empty feed support** - валідні порожні фіди для всіх форматів
- **Memory management** - автоматичне збільшення пам'яті для великих експортів (10000+ записів)
- **Garbage collection** - періодичне очищення пам'яті під час експорту

#### 📡 **Модернізовані cache headers**
- **Замінено застарілі headers** `Expires: Sat, 26 Jul 1997` на сучасні
- **Smart caching**: `Cache-Control: public, max-age=3600` (1 година для фідів)
- **Last-Modified headers** на основі `updated_at` каталогу
- **No-cache для downloads**, cache для feeds

#### 📄 **CSV покращення**
- **BOM тільки для downloads** - виправляє проблеми з веб-фідами
- **UTF-8 charset** в headers для всіх форматів
- **Null safety** - `?? ''` для всіх полів
- **Periodic garbage collection** для великих файлів

#### 📊 **JSON та XML покращення**
- **Smart categories** - тільки непорожні категорії в експорті
- **Level indicators** в JSON (`"level": 1`)
- **Items count** в metadata
- **Type safety** - `intval()`, `floatval()` для числових полів
- **JSON_UNESCAPED_SLASHES** flag для чистішого JSON

### 🚀 **Технічні покращення**

#### 📁 **XLSX Architecture**
```
temp_dir/
├── [Content_Types].xml    # MIME definitions
├── _rels/.rels           # Package relationships  
├── xl/workbook.xml       # Workbook definition
├── xl/_rels/workbook.xml.rels  # Workbook relationships
└── xl/worksheets/sheet1.xml    # Data worksheet
```

#### 🧠 **Memory Management**
- **Автоматичне збільшення memory_limit** до 1024M для великих експортів
- **Execution time extension** до 600 секунд  
- **Garbage collection** після кожного рядка
- **Temporary file cleanup** після експорту

#### 🔒 **Error Handling**
- **Graceful fallbacks** - CSV якщо XLSX не вдається
- **Proper HTTP status codes** для помилок
- **Resource cleanup** навіть при помилках
- **Informative error messages**

### 📚 Оновлена документація
- **CHANGELOG.md** - детальний опис всіх змін
- **README.md** - без змін (все ще актуальний)
- **docs/** - без змін

---

## [1.0.0] - 2025-05-24

### ✨ Нові можливості

#### 🎨 Візуальний інтерфейс відповідності стовпців
- **Real-time оновлення статусу** - миттєві зміни при кожній дії
- **Два окремі блоки** - Google Sheets стовпці vs поля каталогу
- **Кольорові індикатори:**
  - ✅ Зелений - налаштована відповідність
  - ❌ Червоний - відповідність відсутня
  - 🔵 Синій - доступно для налаштування
- **Лічильники** - `Налаштовано: X/Y` та `Всього: Z`
- **Показ зв'язків** - під кожним полем відображається з яким стовпцем Google воно зв'язане

#### 🖼️ Розширені поля категорій
- **`category_image_1`** - URL зображення категорії 1 рівня
- **`category_image_2`** - URL зображення категорії 2 рівня  
- **`category_image_3`** - URL зображення категорії 3 рівня
- **Автоматичне завантаження** зображень категорій з Google Sheets
- **Локальне збереження** в структурованих папках

### 🔧 Покращення

#### Backend
- **База даних:** додано стовпці `category_image_1/2/3` до таблиці `catalog_master_items`
- **Google Sheets обробка:** валідація URL зображень та автоматичне завантаження
- **AJAX обробники:** підтримка нових полів у всіх операціях (add/update)
- **Експорт:** додано category image поля до CSV, Excel, JSON, XML форматів

#### Frontend  
- **JavaScript:** виправлено проблеми з контекстом event handlers
- **Ініціалізація:** правильне завантаження існуючих налаштувань з PHP
- **Статус логіка:** поля показуються налаштованими тільки при повній відповідності
- **Компактний дизайн:** займає 60% менше місця за попередню версію

#### UX/UI
- **Адаптивний дизайн** - працює на мобільних пристроях
- **Sticky headers** - заголовки залишаються видимими при scroll
- **Animation feedback** - плавні переходи при зміні статусу
- **Console logging** - детальні логи для дебагу

### 📚 Оновлена документація
- **README.md** - додано опис нових можливостей
- **docs/USER_GUIDE.md** - детальні інструкції з візуальним інтерфейсом
- **docs/README.md** - оновлена навігація по документації

### 🐛 Виправлені помилки
- **Event handlers context** - виправлено `this.updateCatalogColumnStatus` → `catalogMaster.updateCatalogColumnStatus()`
- **Data initialization** - правильне завантаження існуючих mappings через `wp_localize_script`
- **Status logic** - поля не показуються як налаштовані якщо тільки обрано catalog field без Google column
- **Remove handlers** - корректне оновлення статусу при видаленні mappings

### 🏗️ Технічні зміни
- **Database schema:** нові varchar(500) стовпці для category images
- **JavaScript architecture:** покращена структура з об'єктом `catalogMaster`
- **CSS Grid:** використання сучасних CSS властивостей для layout
- **Real-time updates:** event-driven архітектура для миттєвого відгуку

### 📦 Збірка
- **dist/catalog-master.zip** - готовий архів для встановлення
- **Розмір:** 29 KB
- **Сумісність:** WordPress 5.0+, PHP 8.0+

---

**🎯 Результат:** Повністю функціональний плагін з покращеним інтерфейсом, підтримкою зображень категорій, real-time візуальним feedback, виправленим експортом у справжній Excel формат та коректною роботою JSON/XML фідів. 