#!/usr/bin/env node

/**
 * Скрипт підготовки середовища розробки для Catalog Master (CommonJS)
 * 
 * Використання: node scripts/setup-dev.cjs
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const os = require('os');

// Кольори для консолі
const colors = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

class DevSetup {
    constructor() {
        this.isWindows = os.platform() === 'win32';
        this.projectRoot = path.resolve(__dirname, '..');
        this.errors = [];
        this.warnings = [];
    }

    log(message, color = 'reset') {
        console.log(`${colors[color]}${message}${colors.reset}`);
    }

    error(message) {
        this.log(`❌ ${message}`, 'red');
        this.errors.push(message);
    }

    warning(message) {
        this.log(`⚠️  ${message}`, 'yellow');
        this.warnings.push(message);
    }

    success(message) {
        this.log(`✅ ${message}`, 'green');
    }

    info(message) {
        this.log(`ℹ️  ${message}`, 'blue');
    }

    async run() {
        this.log('\n🚀 Налаштування середовища розробки Catalog Master\n', 'cyan');

        try {
            await this.checkPrerequisites();
            await this.setupDirectories();
            await this.createConfigFiles();
            await this.finalChecks();
            
            this.showSummary();
        } catch (error) {
            this.error(`Критична помилка: ${error.message}`);
            process.exit(1);
        }
    }

    async checkPrerequisites() {
        this.log('🔍 Перевірка залежностей...', 'bright');

        // Перевірка Node.js
        try {
            const nodeVersion = execSync('node --version', { encoding: 'utf8' }).trim();
            this.success(`Node.js: ${nodeVersion}`);
        } catch (error) {
            this.error('Node.js не знайдено');
        }

        // Перевірка npm
        try {
            const npmVersion = execSync('npm --version', { encoding: 'utf8' }).trim();
            this.success(`npm: ${npmVersion}`);
        } catch (error) {
            this.error('npm не знайдено');
        }

        // Перевірка основних файлів
        const files = ['package.json', 'catalog-master.php'];
        files.forEach(file => {
            if (fs.existsSync(path.join(this.projectRoot, file))) {
                this.success(`Файл знайдено: ${file}`);
            } else {
                this.error(`Файл відсутній: ${file}`);
            }
        });
    }

    async setupDirectories() {
        this.log('\n📁 Створення директорій...', 'bright');

        const dirs = [
            'logs',
            'temp',
            'tests/tmp',
            'tests/logs',
            'docs/dev'
        ];

        dirs.forEach(dir => {
            const fullPath = path.join(this.projectRoot, dir);
            if (!fs.existsSync(fullPath)) {
                fs.mkdirSync(fullPath, { recursive: true });
                this.success(`Створено: ${dir}`);
            } else {
                this.info(`Існує: ${dir}`);
            }
        });
    }

    async createConfigFiles() {
        this.log('\n⚙️  Створення конфігураційних файлів...', 'bright');

        // .env файл
        this.createEnvFile();

        // WordPress конфігурація
        this.createWordPressConfig();
    }

    createEnvFile() {
        const envContent = `# Catalog Master Development Environment
NODE_ENV=development
CATALOG_MASTER_DEBUG=true

# WordPress Database
DB_NAME=wordpress_dev
DB_USER=root
DB_PASSWORD=
DB_HOST=localhost

# Development URLs
DEV_URL=http://localhost/wordpress
VITE_DEV_SERVER=http://localhost:5173

# Paths
XAMPP_PATH=C:\\xampp
PHP_PATH=C:\\xampp\\php\\php.exe
WP_PATH=C:\\xampp\\htdocs\\wordpress
`;

        const envPath = path.join(this.projectRoot, '.env.example');
        if (!fs.existsSync(envPath)) {
            fs.writeFileSync(envPath, envContent);
            this.success('Створено .env.example');
        } else {
            this.info('.env.example вже існує');
        }
    }

    createWordPressConfig() {
        const configContent = `<?php
/**
 * WordPress конфігурація для розробки Catalog Master
 */

// Налаштування бази даних
define('DB_NAME', 'wordpress_dev');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Налаштування дебагу
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('SAVEQUERIES', true);

// Catalog Master дебаг
define('CATALOG_MASTER_DEBUG', true);

// Збільшення лімітів
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Префікс таблиць
$table_prefix = 'wp_';

// Абсолютний шлях
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Завантаження WordPress
require_once ABSPATH . 'wp-settings.php';
`;

        const docsDir = path.join(this.projectRoot, 'docs');
        if (!fs.existsSync(docsDir)) {
            fs.mkdirSync(docsDir, { recursive: true });
        }

        const configPath = path.join(docsDir, 'wp-config-dev.php');
        if (!fs.existsSync(configPath)) {
            fs.writeFileSync(configPath, configContent);
            this.success('Створено docs/wp-config-dev.php');
        } else {
            this.info('docs/wp-config-dev.php вже існує');
        }
    }

    async finalChecks() {
        this.log('\n🔍 Фінальні перевірки...', 'bright');

        // Перевірка npm залежностей
        if (fs.existsSync(path.join(this.projectRoot, 'node_modules'))) {
            this.success('npm залежності встановлені');
        } else {
            this.warning('npm залежності не встановлені. Запустіть: npm install');
        }

        // Перевірка Vite конфігурації
        if (fs.existsSync(path.join(this.projectRoot, 'vite.config.js'))) {
            this.success('Vite конфігурація знайдена');
        } else {
            this.warning('vite.config.js не знайдено');
        }
    }

    showSummary() {
        this.log('\n📋 Підсумок налаштування:', 'bright');

        if (this.errors.length === 0) {
            this.success('✅ Середовище розробки успішно налаштовано!');
        } else {
            this.error(`❌ Знайдено ${this.errors.length} помилок:`);
            this.errors.forEach(error => this.log(`   • ${error}`, 'red'));
        }

        if (this.warnings.length > 0) {
            this.warning(`⚠️  Знайдено ${this.warnings.length} попереджень:`);
            this.warnings.forEach(warning => this.log(`   • ${warning}`, 'yellow'));
        }

        this.log('\n🚀 Наступні кроки:', 'cyan');
        this.log('1. Встановіть залежності: npm install');
        this.log('2. Запустіть XAMPP (Apache + MySQL)');
        this.log('3. Створіть базу даних wordpress_dev');
        this.log('4. Скопіюйте docs/wp-config-dev.php у WordPress');
        this.log('5. Запустіть розробку: npm run dev\n');
    }
}

// Запуск скрипта
if (require.main === module) {
    const setup = new DevSetup();
    setup.run().catch(console.error);
}

module.exports = DevSetup; 