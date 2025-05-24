# Frontend-модулі Catalog Master

## Структура assets/src/js

- **core/CatalogMaster.js** — головний клас, ініціалізація всіх компонентів
- **components/ModernTableManager.js** — сучасна таблиця (AJAX, пагінація, пошук, sticky header)
- **components/ColumnMappingManager.js** — відповідність стовпців (мапінг Google Sheets → каталог)
- **components/TabManager.js** — керування вкладками
- **components/ImportManager.js** — імпорт даних (Google Sheets XLSX)
- **components/ExportManager.js** — експорт (CSV, Excel, JSON, XML)
- **utils/ApiClient.js** — AJAX-запити до бекенду
- **utils/helpers.js** — утиліти (повідомлення, debounce, тощо)

## SCSS-архітектура (assets/src/styles)

- **variables/** — змінні (кольори, spacing, типографіка)
- **base/** — базові стилі (reset, типографіка)
- **layout/** — layout, grid
- **components/** — кнопки, форми, картки, вкладки, модальні
- **features/** — специфічні фічі (таблиця, мапінг, імпорт, експорт)
- **utilities/** — утиліти, responsive

## Вхідна точка

- **main.js** — імпортує всі модулі, ініціалізує CatalogMaster, підключає SCSS

## Збірка

- **Vite** — швидка збірка, HMR, оптимізація
- **npm run build** — зібрати для продакшну

## Особливості

- Весь код — ES6+ (класи, модулі)
- Всі стилі — SCSS (модульна структура)
- Немає сторонніх JS-бібліотек для таблиць — все власна реалізація
- Підтримка WordPress-дизайну (кольори, кнопки)

## Для розробників

- Додавайте нові компоненти у відповідні папки
- Дотримуйтесь модульної структури
- Всі зміни SCSS — через змінні та модулі 