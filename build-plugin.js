#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { execSync } = require('child_process');

class WordPressPluginBuilder {
    constructor() {
        this.pluginName = 'catalog-master';
        this.version = '1.0.0';
        this.outputDir = './dist';
        this.sourceDir = './';
        
        // Файли та папки для включення в архів
        this.includeFiles = [
            'catalog-master.php'
        ];
        
        this.includeDirs = [
            'includes',
            'assets'
        ];
        
        // Файли та папки для виключення
        this.excludePatterns = [
            'node_modules',
            '.git',
            '.gitignore',
            '.gitattributes',
            'build-plugin.js',
            'package.json',
            'package-lock.json',
            'vendor',
            'dist',
            'docs',
            '.DS_Store',
            'Thumbs.db',
            '.env',
            'ide-helper.php'
        ];
        
        // Patterns for file extensions
        this.excludeExtensions = ['.log'];
    }

    // Ініціалізація - створення необхідних папок
    init() {
        console.log('🚀 Ініціалізація збірки плагіна WordPress...\n');
        
        // Створити папку dist якщо не існує
        if (!fs.existsSync(this.outputDir)) {
            fs.mkdirSync(this.outputDir, { recursive: true });
            console.log(`✅ Створено папку ${this.outputDir}`);
        }
    }

    // Перевірка наявності необхідних файлів
    validateFiles() {
        console.log('🔍 Перевірка файлів...');
        
        let hasErrors = false;
        
        // Перевірити головний файл плагіна
        if (!fs.existsSync('catalog-master.php')) {
            console.error('❌ Відсутній головний файл плагіна: catalog-master.php');
            hasErrors = true;
        }
        
        // Перевірити обов'язкові папки
        this.includeDirs.forEach(dir => {
            if (!fs.existsSync(dir)) {
                console.error(`❌ Відсутня папка: ${dir}`);
                hasErrors = true;
            }
        });
        
        if (hasErrors) {
            console.error('\n💥 Помилки валідації. Збірка перервана.');
            process.exit(1);
        }
        
        console.log('✅ Всі необхідні файли знайдено\n');
    }

    // Отримання версії з головного файлу плагіна
    getPluginVersion() {
        try {
            const mainFile = fs.readFileSync('catalog-master.php', 'utf8');
            const versionMatch = mainFile.match(/Version:\s*(.+)/);
            if (versionMatch) {
                this.version = versionMatch[1].trim();
                console.log(`📋 Версія плагіна: ${this.version}`);
            }
        } catch (error) {
            console.warn('⚠️  Не вдалося отримати версію з файлу плагіна');
        }
    }

    // Перевірка чи файл/папка повинні бути виключені
    shouldExclude(filePath) {
        const fileName = path.basename(filePath);
        const fileExtension = path.extname(filePath);
        
        // Check exclude patterns
        const matchesPattern = this.excludePatterns.some(pattern => {
            if (pattern.includes('*')) {
                // Простий wildcard matcher
                const regex = new RegExp(pattern.replace(/\*/g, '.*'));
                return regex.test(fileName);
            }
            return filePath.includes(pattern);
        });
        
        // Check file extensions
        const matchesExtension = this.excludeExtensions.includes(fileExtension);
        
        return matchesPattern || matchesExtension;
    }

    // Рекурсивне додавання файлів до архіву
    addFilesToArchive(archive, dirPath, archivePath = '') {
        const items = fs.readdirSync(dirPath);
        
        items.forEach(item => {
            const fullPath = path.join(dirPath, item);
            const relativePath = archivePath ? path.join(archivePath, item) : item;
            
            if (this.shouldExclude(fullPath)) {
                return;
            }
            
            const stats = fs.statSync(fullPath);
            
            if (stats.isDirectory()) {
                this.addFilesToArchive(archive, fullPath, relativePath);
            } else {
                archive.file(fullPath, { name: relativePath });
                console.log(`  📄 ${relativePath}`);
            }
        });
    }

    // Створення ZIP архіву
    async createArchive() {
        const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');
        const filename = `${this.pluginName}-v${this.version}-${timestamp}.zip`;
        const outputPath = path.join(this.outputDir, filename);
        
        console.log(`📦 Створення архіву: ${filename}\n`);
        
        return new Promise((resolve, reject) => {
            const output = fs.createWriteStream(outputPath);
            const archive = archiver('zip', {
                zlib: { level: 9 } // Максимальне стиснення
            });
            
            output.on('close', () => {
                const sizeKB = (archive.pointer() / 1024).toFixed(2);
                console.log(`\n✅ Архів створено успішно!`);
                console.log(`📁 Файл: ${outputPath}`);
                console.log(`📊 Розмір: ${sizeKB} KB`);
                console.log(`📄 Файлів у архіві: ${archive.pointer() > 0 ? 'так' : 'ні'}`);
                resolve(outputPath);
            });
            
            archive.on('error', (err) => {
                console.error('❌ Помилка створення архіву:', err);
                reject(err);
            });
            
            archive.pipe(output);
            
            // Додати всі файли в корень архіву з префіксом папки плагіна
            console.log('📁 Додавання файлів до архіву:');
            
            // Додати окремі файли
            this.includeFiles.forEach(file => {
                if (fs.existsSync(file)) {
                    archive.file(file, { name: `${this.pluginName}/${file}` });
                    console.log(`  📄 ${this.pluginName}/${file}`);
                }
            });
            
            // Додати папки
            this.includeDirs.forEach(dir => {
                if (fs.existsSync(dir)) {
                    console.log(`  📁 ${this.pluginName}/${dir}/`);
                    this.addFilesToArchive(archive, dir, `${this.pluginName}/${dir}`);
                }
            });
            
            archive.finalize();
        });
    }

    // Створення також копії без версії для швидкого тестування
    async createSimpleArchive() {
        const filename = `${this.pluginName}.zip`;
        const outputPath = path.join(this.outputDir, filename);
        
        console.log(`\n📦 Створення простого архіву: ${filename}`);
        
        return new Promise((resolve, reject) => {
            const output = fs.createWriteStream(outputPath);
            const archive = archiver('zip', {
                zlib: { level: 9 }
            });
            
            output.on('close', () => {
                console.log(`✅ Простий архів створено: ${outputPath}`);
                resolve(outputPath);
            });
            
            archive.on('error', reject);
            archive.pipe(output);
            
            // Додати файли
            this.includeFiles.forEach(file => {
                if (fs.existsSync(file)) {
                    archive.file(file, { name: `${this.pluginName}/${file}` });
                }
            });
            
            this.includeDirs.forEach(dir => {
                if (fs.existsSync(dir)) {
                    this.addFilesToArchive(archive, dir, `${this.pluginName}/${dir}`);
                }
            });
            
            archive.finalize();
        });
    }

    // Створення інсталяційних інструкцій
    createInstallInstructions(archivePath) {
        const instructions = `
📥 ІНСТРУКЦІЇ ДЛЯ ВСТАНОВЛЕННЯ ПЛАГІНА CATALOG MASTER

1️⃣ АВТОМАТИЧНЕ ВСТАНОВЛЕННЯ:
   • Увійдіть в адмін-панель WordPress
   • Перейдіть до "Плагіни" → "Додати новий"
   • Натисніть "Завантажити плагін"
   • Оберіть файл: ${path.basename(archivePath)}
   • Натисніть "Встановити зараз"
   • Активуйте плагін

2️⃣ РУЧНЕ ВСТАНОВЛЕННЯ:
   • Розпакуйте архів ${path.basename(archivePath)}
   • Завантажте папку 'catalog-master' в /wp-content/plugins/
   • В адмін-панелі перейдіть до "Плагіни"
   • Активуйте "Catalog Master"

3️⃣ ПЕРШИЙ ЗАПУСК:
   • Перейдіть до "Catalog Master" в меню адміністратора
   • Створіть новий каталог
   • Налаштуйте підключення до Google Sheets
   • Налаштуйте відповідність стовпців
   • Імпортуйте дані

🔧 ВИМОГИ:
   • WordPress 5.0+
   • PHP 8.0+
   • MySQL 5.6+

📚 ДОКУМЕНТАЦІЯ:
   • Детальні інструкції в файлі README.md
   • Підтримка: створіть issue в репозиторії

🎉 Готово! Плагін готовий до встановлення.
`;

        const instructionsPath = path.join(this.outputDir, 'INSTALL.txt');
        fs.writeFileSync(instructionsPath, instructions.trim());
        console.log(`📋 Створено інструкції: ${instructionsPath}`);
    }

    // Головний метод збірки
    async build() {
        try {
            console.log('🔨 ЗБІРКА WORDPRESS ПЛАГІНА CATALOG MASTER\n');
            console.log('=' .repeat(50));
            
            this.init();
            this.validateFiles();
            this.getPluginVersion();
            
            console.log(''); // Пустий рядок для красоти
            
            // Створити основний архів з версією
            const mainArchive = await this.createArchive();
            
            // Створити простий архів для швидкого тестування
            await this.createSimpleArchive();
            
            // Створити інструкції
            this.createInstallInstructions(mainArchive);
            
            console.log('\n' + '=' .repeat(50));
            console.log('🎉 ЗБІРКА ЗАВЕРШЕНА УСПІШНО!');
            console.log(`📁 Архіви збережено в папці: ${this.outputDir}`);
            console.log('🚀 Плагін готовий до встановлення в WordPress!');
            
        } catch (error) {
            console.error('\n💥 ПОМИЛКА ЗБІРКИ:', error.message);
            process.exit(1);
        }
    }
}

// Перевірка наявності залежностей
function checkDependencies() {
    try {
        require('archiver');
    } catch (error) {
        console.error('❌ Відсутня залежність: archiver');
        console.log('📦 Встановіть залежності: npm install archiver');
        process.exit(1);
    }
}

// Головна функція
async function main() {
    // Перевірити аргументи командного рядка
    const args = process.argv.slice(2);
    
    if (args.includes('--help') || args.includes('-h')) {
        console.log(`
🔨 WordPress Plugin Builder для Catalog Master

ВИКОРИСТАННЯ:
  node build-plugin.js [опції]

ОПЦІЇ:
  --help, -h     Показати цю довідку
  --version, -v  Показати версію скрипта

ПРИКЛАДИ:
  node build-plugin.js           # Створити архів плагіна
  npm run build                  # Якщо додано в package.json

ВИХІДНІ ФАЙЛИ:
  ./dist/catalog-master-v{версія}-{дата}.zip  # Основний архів
  ./dist/catalog-master.zip                   # Простий архів для тестування
  ./dist/INSTALL.txt                          # Інструкції встановлення
`);
        return;
    }
    
    if (args.includes('--version') || args.includes('-v')) {
        console.log('WordPress Plugin Builder v1.0.0');
        return;
    }
    
    checkDependencies();
    
    const builder = new WordPressPluginBuilder();
    await builder.build();
}

// Запуск
if (require.main === module) {
    main().catch(console.error);
}

module.exports = WordPressPluginBuilder; 