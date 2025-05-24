# Архітектура Catalog Master

## Загальна структура

- **assets/src/** — фронтенд (ES6+ модулі, SCSS, Vite)
- **includes/** — бекенд (PHP класи)
- **assets/dist/** — зібрані файли для продакшну
- **dist/** — архіви для встановлення
- **docs/** — документація

## Frontend

- **Vite** — збірка, HMR, оптимізація
- **SCSS** — модульна структура стилів
- **ES6+** — розбиття на класи/модулі:
  - `main.js` — точка входу
  - `js/core/CatalogMaster.js` — головний клас
  - `js/components/ModernTableManager.js` — сучасна таблиця
  - `js/components/ColumnMappingManager.js` — відповідність стовпців
  - `js/components/TabManager.js` — вкладки
  - `js/components/ImportManager.js` — імпорт
  - `js/components/ExportManager.js` — експорт
  - `js/utils/ApiClient.js` — AJAX
  - `js/utils/helpers.js` — утиліти

## Backend

- **class-admin.php** — сторінки, меню, підключення скриптів
- **class-ajax.php** — AJAX endpoints (імпорт, експорт, таблиця, мапінг)
- **class-database.php** — робота з БД (каталоги, товари, мапінг)
- **class-google-sheets.php** — імпорт з Google Sheets (XLSX, fallback CSV)
- **class-logger.php** — логування
- **class-exporter.php** — експорт даних

## Ключові особливості

- **Імпорт тільки XLSX** (Google Sheets → export?format=xlsx)
- **AJAX** — всі дії без перезавантаження сторінки
- **Власна таблиця** — без сторонніх JS бібліотек
- **Логування** — всі критичні дії пишуться в лог
- **SCSS-архітектура** — змінні, компоненти, утиліти, фічі

## Вимоги

- PHP >= 7.2
- ZipArchive, SimpleXML
- WordPress >= 5.6
- Node.js >= 18 (для розробки)

## Збірка

- `npm install`
- `npm run build` — зібрати фронтенд
- `npm run build-plugin` — створити архів для WP

## Безпека

- Валідація/санітизація всіх даних
- Перевірка nonce для AJAX
- Логування всіх помилок

## Підтримка

- Весь код — ES6+, SCSS, PHP OOP
- Вся документація — в папці `docs/` 