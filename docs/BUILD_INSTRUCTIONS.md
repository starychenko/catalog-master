# 🔨 Інструкції для збірки плагіна Catalog Master

Цей документ пояснює як використовувати Node.js скрипт для створення ZIP архіву WordPress плагіна.

## 📋 Передумови

- **Node.js** версії 14 або вище
- **npm** (встановлюється разом з Node.js)

### Перевірка версії Node.js:
```bash
node --version
npm --version
```

## 🚀 Швидкий старт

### 1. Встановлення залежностей
```bash
npm install
```

### 2. Створення архіву плагіна
```bash
npm run build
```

або

```bash
node build-plugin.js
```

## 📁 Структура проекту

Переконайтеся, що у вас є наступна структура файлів:

```
catalog-master/
├── catalog-master.php          # ✅ Обов'язковий
├── includes/                   # ✅ Обов'язковий
│   ├── class-database.php
│   ├── class-admin.php
│   ├── class-google-sheets.php
│   ├── class-ajax.php
│   └── class-exporter.php
├── assets/                     # ✅ Обов'язковий
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── README.md                   # ✅ Рекомендований
├── build-plugin.js             # 🔧 Скрипт збірки
├── package.json                # 🔧 NPM конфігурація
└── .gitignore                  # 🔧 Git налаштування
```

## 🎯 Команди збірки

### Основні команди:

```bash
# Повна збірка (очистка + архів)
npm run build

# Тільки створення архіву
node build-plugin.js

# Показати довідку
npm run build:help
# або
node build-plugin.js --help

# Очистити папку dist
npm run clean
```

### Додаткові опції:

```bash
# Показати версію скрипта
node build-plugin.js --version

# Показати повну довідку
node build-plugin.js --help
```

## 📦 Результати збірки

Після виконання збірки в папці `dist/` з'являться файли:

```
dist/
├── catalog-master-v1.0.0-2024-01-15T14-30-00.zip  # Основний архів з версією
├── catalog-master.zip                              # Простий архів для тестування
└── INSTALL.txt                                     # Інструкції встановлення
```

### Типи архівів:

1. **Версійний архів** (`catalog-master-v1.0.0-YYYY-MM-DDTHH-MM-SS.zip`)
   - Містить версію та дату збірки
   - Для офіційних релізів
   - Автоматично визначає версію з `catalog-master.php`

2. **Простий архів** (`catalog-master.zip`)
   - Без версії у назві
   - Для швидкого тестування
   - Завжди перезаписується

## 🔧 Налаштування скрипта

Ви можете налаштувати скрипт редагуючи `build-plugin.js`:

```javascript
class WordPressPluginBuilder {
    constructor() {
        this.pluginName = 'catalog-master';     // Назва плагіна
        this.outputDir = './dist';              // Папка для архівів
        
        // Файли для включення
        this.includeFiles = [
            'catalog-master.php',
            'README.md'
        ];
        
        // Папки для включення
        this.includeDirs = [
            'includes',
            'assets'
        ];
        
        // Файли для виключення
        this.excludePatterns = [
            'node_modules',
            '.git',
            'build-plugin.js',
            // ... інші
        ];
    }
}
```

## 📥 Встановлення в WordPress

### Автоматичне встановлення:
1. В адмін-панелі WordPress → **Плагіни** → **Додати новий**
2. **Завантажити плагін**
3. Оберіть файл `catalog-master-v*.zip`
4. **Встановити зараз** → **Активувати**

### Ручне встановлення:
1. Розпакуйте архів
2. Завантажте папку `catalog-master` в `/wp-content/plugins/`
3. Активуйте плагін в адмін-панелі

## 🐛 Усунення проблем

### Помилка: "Відсутня залежність: archiver"
```bash
npm install archiver
```

### Помилка: "Відсутній головний файл плагіна"
Переконайтеся, що файл `catalog-master.php` існує в кореневій папці.

### Помилка: "Відсутня папка: includes"
Переконайтеся, що папка `includes` з усіма PHP класами існує.

### Архів порожній або малий за розміром
Перевірте права доступу до файлів та папок:
```bash
# На Linux/Mac
chmod -R 755 ./
```

### Node.js не встановлений
Завантажте з [nodejs.org](https://nodejs.org/) або встановіть через пакетний менеджер:

```bash
# Windows (через Chocolatey)
choco install nodejs

# macOS (через Homebrew)
brew install node

# Ubuntu/Debian
sudo apt install nodejs npm
```

## 📝 Логи збірки

Скрипт виводить детальні логи процесу збірки:

```
🔨 ЗБІРКА WORDPRESS ПЛАГІНА CATALOG MASTER

==================================================
🚀 Ініціалізація збірки плагіна WordPress...

✅ Створено папку ./dist
🔍 Перевірка файлів...
✅ Всі необхідні файли знайдено

📋 Версія плагіна: 1.0.0

📦 Створення архіву: catalog-master-v1.0.0-2024-01-15T14-30-00.zip

📁 Додавання файлів до архіву:
  📄 catalog-master/catalog-master.php
  📄 catalog-master/README.md
  📁 catalog-master/includes/
  📄 catalog-master/includes/class-database.php
  📄 catalog-master/includes/class-admin.php
  📄 catalog-master/includes/class-google-sheets.php
  📄 catalog-master/includes/class-ajax.php
  📄 catalog-master/includes/class-exporter.php
  📁 catalog-master/assets/
  📄 catalog-master/assets/css/admin.css
  📄 catalog-master/assets/js/admin.js

✅ Архів створено успішно!
📁 Файл: ./dist/catalog-master-v1.0.0-2024-01-15T14-30-00.zip
📊 Розмір: 125.34 KB
📄 Файлів у архіві: так

📦 Створення простого архіву: catalog-master.zip
✅ Простий архів створено: ./dist/catalog-master.zip
📋 Створено інструкції: ./dist/INSTALL.txt

==================================================
🎉 ЗБІРКА ЗАВЕРШЕНА УСПІШНО!
📁 Архіви збережено в папці: ./dist
🚀 Плагін готовий до встановлення в WordPress!
```

## 🔄 Автоматизація

Для автоматизації збірки можна додати скрипт в CI/CD:

```yaml
# .github/workflows/build.yml
name: Build Plugin
on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - run: npm install
      - run: npm run build
      - uses: actions/upload-artifact@v3
        with:
          name: plugin-archive
          path: dist/*.zip
```

## 📞 Підтримка

Якщо виникли проблеми:
1. Перевірте версії Node.js та npm
2. Переконайтеся в правильності структури файлів
3. Перевірте права доступу до файлів
4. Створіть issue в репозиторії

---

**Версія інструкцій:** 1.0.0  
**Останнє оновлення:** 2024-01-15 