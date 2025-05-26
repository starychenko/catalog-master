#!/usr/bin/env node

/**
 * Скрипт запуску розробки
 */

const { spawn } = require('child_process');
const os = require('os');

console.log('🚀 Запуск середовища розробки Catalog Master...\n');

// Запуск Vite dev server
console.log('⚡ Запускаю Vite dev server...');
const vite = spawn('npm', ['run', 'dev'], {
    stdio: 'inherit',
    shell: true
});

vite.on('close', (code) => {
    console.log(`\n📱 Vite dev server завершився з кодом ${code}`);
});

// Відкриття браузера через 3 секунди (Windows)
if (os.platform() === 'win32') {
    setTimeout(() => {
        console.log('🌐 Відкриваю браузер...');
        spawn('start', ['http://localhost:5173'], { shell: true });
    }, 3000);
}

console.log('\n✅ Середовище розробки запущено!');
console.log('📱 Vite dev server: http://localhost:5173');
console.log('🌐 WordPress: http://localhost/wordpress');
console.log('📋 Натисніть Ctrl+C для зупинки\n'); 