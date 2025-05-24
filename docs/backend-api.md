# Backend API (AJAX endpoints)

[← README](../README.md) | [Архітектура](architecture.md) | [Імпорт XLSX](import-xlsx.md) | [Frontend](frontend-modules.md)

---

## Основні endpoints (class-ajax.php)

- **catalog_master_get_catalog_data**
  - Отримати дані для таблиці (AJAX пагінація, пошук, сортування)
  - Параметри: catalog_id, page, page_size, search, sort_column, sort_direction
  - Відповідь: { data, total, page, page_size, total_pages }

- **catalog_master_get_sheets_headers**
  - Отримати заголовки з Google Sheets (XLSX)
  - Параметри: sheet_url, sheet_name
  - Відповідь: { headers }

- **catalog_master_save_column_mapping**
  - Зберегти відповідність стовпців (мапінг)
  - Параметри: catalog_id, mappings[]
  - Відповідь: { message, saved_count }

- **catalog_master_import_data**
  - Імпорт даних з Google Sheets (XLSX)
  - Параметри: catalog_id
  - Відповідь: { success, imported_count, skipped_count, message }

- **catalog_master_update_item**
  - Оновити товар (inline-редагування)
  - Параметри: item_id, data[]
  - Відповідь: { success }

- **catalog_master_delete_item**
  - Видалити товар
  - Параметри: item_id
  - Відповідь: { success }

- **catalog_master_add_item**
  - Додати новий товар
  - Параметри: catalog_id, data[]
  - Відповідь: { success, item_id }

- **catalog_master_test_sheets_connection**
  - Перевірити підключення до Google Sheets
  - Параметри: sheet_url, sheet_name
  - Відповідь: { message, headers, row_count }

## Безпека

- Всі запити перевіряють nonce (`catalog_master_nonce`)
- Всі дані валідуються та санітизуються
- Логування всіх дій (class-logger.php)

## Де дивитися приклади

- `assets/src/js/utils/ApiClient.js` — всі AJAX-запити
- `includes/class-ajax.php` — реалізація endpoint-ів

## Примітка

- Всі відповіді — у форматі JSON
- Всі помилки — через wp_send_json_error (JS ловить і показує повідомлення)

---

**Див. також:** [Архітектура](architecture.md) | [Імпорт XLSX](import-xlsx.md) | [Frontend-модулі](frontend-modules.md) 