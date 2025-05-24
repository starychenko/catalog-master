# ⚡ Батч-система імпорту для великих каталогів

**Catalog Master v1.1.0** впроваджує революційну батч-систему, яка дозволяє імпортувати **необмежену кількість товарів** без timeout помилок.

---

## 🎯 **Огляд системи**

### **Основна ідея**
Замість завантаження всього каталогу за одну операцію, система розбиває імпорт на **маленькі пакети (батчі)** по 25 товарів.

### **Переваги батч-підходу**
- 🚫 **Немає timeout помилок** - кожен батч швидкий
- 📊 **Реальний прогрес** - ви бачите точний лічильник
- 🔄 **Відновлення після збоїв** - можна продовжити
- 💾 **Оптимізація памʼяті** - ефективне використання ресурсів

---

## 🔧 **Технічна архітектура**

### **Frontend (JavaScript)**
```javascript
// Головний цикл батч-імпорту
async processBatch(offset, originalButtonText, buttonElement) {
    const response = await this.api.importData(
        this.currentCatalogId,
        offset,              // Початкова позиція
        this.batchSize,      // 25 товарів
        isFirstBatch         // Чи це перший батч?
    );
    
    // Оновлюємо прогрес
    this.updateProgressIndicator(response.message);
    
    // Продовжуємо наступний батч або завершуємо
    if (response.is_complete) {
        this.completeImport();
    } else {
        this.processBatch(response.next_offset, ...);
    }
}
```

### **Backend (PHP)**
```php
// Основний AJAX endpoint
public function import_data() {
    $offset = intval($_POST['offset']);
    $batch_size = intval($_POST['batch_size']) ?: 25;
    $is_first_batch = boolval($_POST['is_first_batch']);
    
    if ($is_first_batch) {
        // Завантажуємо дані з Google Sheets та кешуємо
        $import_result = CatalogMaster_GoogleSheets::import_from_url(...);
        set_transient($cache_key, $import_result, HOUR_IN_SECONDS);
        CatalogMaster_Database::clear_catalog_items($catalog_id);
    } else {
        // Отримуємо дані з кешу
        $cached_data = get_transient($cache_key);
    }
    
    // Обробляємо поточний батч
    $current_chunk = array_slice($all_data, $offset, $batch_size);
    $result = CatalogMaster_GoogleSheets::process_data_chunk_for_import(...);
    
    // Зберігаємо в базу даних
    CatalogMaster_Database::insert_catalog_items($catalog_id, $result['items_for_db']);
}
```

---

## 📊 **Детальний потік даних**

### **1. Ініціалізація (перший батч)**
```
1. Завантаження Google Sheets → XLSX парсинг
2. Кешування в WordPress Transients (1 година)
3. Очищення старих даних каталогу
4. Ініціалізація кешу зображень категорій
5. Повернення загальної кількості товарів
```

### **2. Обробка батчу (2-N батчі)**
```
1. Отримання даних з кешу
2. Вибірка 25 товарів (array_slice)
3. Мапінг стовпців → структура каталогу
4. Завантаження та обробка зображень
5. Збереження в базу даних
6. Оновлення кешу зображень
```

### **3. Завершення імпорту**
```
1. Очищення всіх transient кешів
2. Фінальне повідомлення
3. Переключення на вкладку даних
4. Оновлення таблиці
```

---

## 🗄️ **Система кешування**

### **Transient ключі**
```php
$transient_data_key = 'cm_import_data_' . $catalog_id;       // Дані Google Sheets
$transient_total_key = 'cm_import_total_' . $catalog_id;     // Загальна кількість
$transient_img_cache_key = 'cm_import_img_cache_' . $catalog_id; // Кеш зображень
```

### **Час життя кешу**
- **HOUR_IN_SECONDS** (3600 секунд) - достатньо для імпорту
- **Автоматичне очищення** після завершення
- **Fallback при збоях** - повідомлення про сесію

---

## 📈 **Прогрес-бар та UI**

### **Інформація що відображається**
```javascript
// Текст прогресу
`Обробка: ${this.totalItemsProcessed} / ${this.totalItemsToProcess}. ${response.message}`

// Візуальний прогрес-бар
percentage = (this.totalItemsProcessed / this.totalItemsToProcess) * 100;
progressFill.style.width = `${percentage}%`;
```

### **Типові повідомлення**
- `"Ініціалізація імпорту..."` - початковий стан
- `"Пакет оброблено: 25 записів."` - під час обробки  
- `"Імпорт завершено!"` - фінальний стан

---

## ⚙️ **Налаштування продуктивності**

### **Константи конфігурації**
```php
// У класі CatalogMaster_Ajax
const IMPORT_BATCH_SIZE = 25;

// У wp-config.php (опціонально)
define('CATALOG_MASTER_BATCH_SIZE', 50);        // Більший батч
define('CATALOG_MASTER_CACHE_TIME', 7200);      // Довший кеш
```

### **Рекомендовані налаштування PHP**
```ini
; Для великих каталогів (>5,000 товарів)
max_execution_time = 300        ; 5 хвилин
memory_limit = 512M             ; 512MB RAM
upload_max_filesize = 50M       ; Великі зображення
post_max_size = 50M             ; POST дані
max_input_vars = 5000           ; Багато полів форми
```

---

## 🔍 **Моніторинг та логування**

### **Логи системи**
```php
CatalogMaster_Logger::info("Import: First batch for catalog {$catalog_id}");
CatalogMaster_Logger::info("Import: Cleared items for catalog {$catalog_id}. Total items: {$total}");
CatalogMaster_Logger::info('📊 Data chunk processing completed', [
    'processed_rows' => $processed_rows,
    'items_for_db_count' => count($items_for_db),
    'errors_in_chunk' => $errors_count
]);
```

### **Відслідковування в консолі браузера**
```javascript
console.log('🌐 AJAX Request: catalog_master_import_data', data);
console.log('📊 Loaded N items (N total)');
console.log('📥 AJAX Result:', result);
```

---

## 🚨 **Обробка помилок та відновлення**

### **Типи помилок та реакція**
```javascript
// Network помилки
catch (error) {
    console.error('❌ Import error:', error);
    this.updateProgressIndicator(`Помилка: ${error.message}`, false, true);
    this.cleanupImport(buttonElement, originalButtonText);
}

// Сесійні помилки
if ($cached_data === false) {
    wp_send_json_error('Помилка сесії імпорту. Спробуйте знову.');
}
```

### **Відновлення після збоїв**
1. **Кеш зберігається 1 годину** - можна перезапустити
2. **Offset відслідковується** - продовження з потрібного місця
3. **Cleanup на помилках** - коректне завершення UI

---

## 📊 **Продуктивність по розмірах каталогів**

| Товарів | Батчів | Приблизний час | Пікове споживання RAM |
|---------|--------|----------------|----------------------|
| 100     | 4      | 15 секунд      | 32MB                |
| 500     | 20     | 1-2 хвилини    | 64MB                |
| 1,000   | 40     | 3-5 хвилин     | 96MB                |
| 5,000   | 200    | 15-25 хвилин   | 128MB               |
| 10,000  | 400    | 30-50 хвилин   | 192MB               |

**Примітка:** Час залежить від кількості зображень та швидкості мережі.

---

## 🎯 **Найкращі практики**

### **Для користувачів**
1. ✅ **Не закривайте браузер** під час імпорту
2. ✅ **Спостерігайте за прогресом** - він реальний
3. ✅ **При помилках** - просто перезапустіть імпорт
4. ✅ **Великі каталоги** - плануйте 20-60 хвилин

### **Для розробників**
1. ✅ **Моніторьте логи** - `CatalogMaster_Logger`
2. ✅ **Тестуйте на різних розмірах** каталогів
3. ✅ **Перевіряйте налаштування PHP** для продакшну
4. ✅ **Використовуйте константи** для fine-tuning

---

## 🔮 **Майбутні покращення**

### **Можливі оптимізації**
- 🔄 **Паралельна обробка зображень**
- 📊 **Адаптивний розмір батчу** залежно від продуктивності
- 💾 **Compression кешу** для економії памʼяті
- 🔧 **Background processing** через WP Cron

### **Додаткові функції**
- 📧 **Email уведомлення** про завершення імпорту
- 📱 **Push notifications** для прогресу
- 🎯 **Селективний імпорт** - тільки нові/оновлені товари
- 🔄 **Автоматична синхронізація** з Google Sheets

---

> 💡 **Батч-система Catalog Master v1.1.0** - це **enterprise-рівня** рішення для роботи з каталогами будь-якого розміру! 