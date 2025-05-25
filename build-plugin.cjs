#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const archiver = require('archiver');
const { execSync } = require('child_process');

class WordPressPluginBuilder {
    constructor() {
        this.pluginName = 'catalog-master';
        this.version = '1.1.5';
        this.outputDir = './dist';
        this.sourceDir = './';
        
        // Файли та папки для включення в архів
        this.includeFiles = [
            'catalog-master.php',
            'README.md'
        ];
        
        // Опціональні файли (включаються якщо існують)
        this.optionalFiles = [
            'CHANGELOG.md'
        ];
        
        this.includeDirs = [
            'includes',
            'assets/dist'  // Тільки зібрані Vite файли
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
            'vite.config.js',
            'vendor',
            './dist',              // Виключити тільки корневу папку dist
            'docs',
            '.DS_Store',
            'Thumbs.db',
            '.env',
            'ide-helper.php',
            'assets/src',        // Виключити src файли
            'VITE_MIGRATION_PLAN.md',
            'VITE_SETUP_INSTRUCTIONS.md',
            'VITE_MIGRATION_COMPLETE.md',
            'hot'               // Vite hot file
        ];
        
        // Patterns for file extensions
        this.excludeExtensions = ['.log', '.map'];  // Виключити map файли
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

    // Запуск Vite збірки
    runViteBuild() {
        console.log('🔧 Запуск Vite збірки...');
        try {
            execSync('npm run build', { stdio: 'inherit' });
            console.log('✅ Vite збірка завершена успішно\n');
        } catch (error) {
            console.error('❌ Помилка Vite збірки:', error.message);
            process.exit(1);
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
        
        // Перевірити Vite збірку
        if (!fs.existsSync('assets/dist')) {
            console.error('❌ Відсутня папка assets/dist - запустіть npm run build');
            hasErrors = true;
        }
        
        // Перевірити обов'язкові папки
        const requiredDirs = ['includes'];
        requiredDirs.forEach(dir => {
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
        const normalizedPath = filePath.replace(/\\/g, '/'); // Normalize path separators
        
        // Check exclude patterns
        const matchesPattern = this.excludePatterns.some(pattern => {
            if (pattern.includes('*')) {
                // Простий wildcard matcher
                const regex = new RegExp(pattern.replace(/\*/g, '.*'));
                return regex.test(fileName);
            }
            
            // For ./dist only exclude root dist folder, not assets/dist
            if (pattern === './dist') {
                return normalizedPath === 'dist' || normalizedPath.startsWith('dist/');
            }
            
            // For other patterns, check if file path contains pattern
            return normalizedPath.includes(pattern);
        });
        
        // Check file extensions
        const matchesExtension = this.excludeExtensions.includes(fileExtension);
        
        if (matchesPattern || matchesExtension) {
            console.log(`  🚫 Виключено: ${filePath}`);
        }
        
        return matchesPattern || matchesExtension;
    }

    // Рекурсивне додавання файлів до архіву
    addFilesToArchive(archive, dirPath, archivePath = '') {
        if (!fs.existsSync(dirPath)) {
            console.log(`⚠️  Папка не знайдена: ${dirPath}`);
            return;
        }
        
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
                resolve(outputPath);
            });
            
            archive.on('error', (err) => {
                console.error('❌ Помилка створення архіву:', err);
                reject(err);
            });
            
            archive.pipe(output);
            
            // Додати всі файли в корень архіву з префіксом папки плагіна
            console.log('📁 Додавання файлів до архіву:');
            
            // Додати файли
            this.includeFiles.forEach(file => {
                if (fs.existsSync(file)) {
                    archive.file(file, { name: `${this.pluginName}/${file}` });
                    console.log(`  📄 ${this.pluginName}/${file}`);
                }
            });
            
            // Додати опціональні файли
            this.optionalFiles.forEach(file => {
                if (fs.existsSync(file)) {
                    archive.file(file, { name: `${this.pluginName}/${file}` });
                    console.log(`  📄 ${this.pluginName}/${file}` + ' (опціональний)');
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
            
            // Додати опціональні файли
            this.optionalFiles.forEach(file => {
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

    // Головний метод збірки
    async build() {
        try {
            console.log('🔨 ЗБІРКА WORDPRESS ПЛАГІНА CATALOG MASTER v1.1.5\n');
            console.log('=' .repeat(60));
            
            this.init();
            
            // Спочатку запустити Vite збірку
            this.runViteBuild();
            
            this.validateFiles();
            this.getPluginVersion();
            
            console.log(''); // Пустий рядок для красоти
            
            // Створити основний архів з версією
            const mainArchive = await this.createArchive();
            
            // Створити простий архів для швидкого тестування
            await this.createSimpleArchive();
            
            console.log('\n' + '=' .repeat(60));
            console.log('🎉 ЗБІРКА ЗАВЕРШЕНА УСПІШНО!');
            console.log(`📁 Архіви збережено в папці: ${this.outputDir}`);
            console.log('🚀 Плагін готовий до встановлення в WordPress!');
            console.log(`📊 Версія: ${this.version} (Vite Edition)`);
            
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
🔨 WordPress Plugin Builder для Catalog Master v1.1.5

ВИКОРИСТАННЯ:
  node build-plugin.js [опції]

ОПЦІЇ:
  --help, -h     Показати цю довідку
  --version, -v  Показати версію скрипта

ПРИКЛАДИ:
  node build-plugin.js           # Створити архів плагіна
  npm run build:plugin           # Рекомендований спосіб

ПРОЦЕС ЗБІРКИ:
  1. Запуск npm run build (Vite збірка)
  2. Створення архіву з оптимізованими файлами
  3. Виключення src файлів та map файлів
  4. Генерація інструкцій встановлення

ВИХІДНІ ФАЙЛИ:
  ./dist/catalog-master-v{версія}-{дата}.zip  # Основний архів
  ./dist/catalog-master.zip                   # Простий архів для тестування

ВИМОГИ:
  • WordPress >= 5.6
  • PHP >= 7.2
  • ZipArchive, SimpleXML
  • Node.js >= 18 (для розробки)
`);
        return;
    }
    
    if (args.includes('--version') || args.includes('-v')) {
        console.log('WordPress Plugin Builder v1.1.5 (Vite Edition)');
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