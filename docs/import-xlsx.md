# Імпорт з Google Sheets (XLSX)

[← README](../README.md) | [Архітектура](architecture.md) | [Frontend](frontend-modules.md) | [Backend API](backend-api.md)

---

## Формат Google Sheets

- Підтримується **XLSX** (експорт Google Sheets)
- **Ви можете використовувати звичайні посилання на Google Sheets!**
- Плагін автоматично конвертує будь-яке посилання в правильний XLSX формат

## Як імпортувати

1. В Google Sheets: Файл → Поділитися → Доступ за посиланням ("Будь-хто з посиланням")
2. Скопіюйте **звичайне посилання** на вашу таблицю, наприклад:
   - `https://docs.google.com/spreadsheets/d/ID/edit#gid=0`
   - `https://docs.google.com/spreadsheets/d/ID/`
   - Або навіть експорт URL: `https://docs.google.com/spreadsheets/d/ID/export?format=xlsx&gid=0`
3. Вставте це посилання у поле "Google Sheets URL" в Catalog Master
4. Вкажіть назву аркуша (Sheet1, якщо не змінювали)
5. Налаштуйте відповідність стовпців
6. Натисніть "Імпортувати дані"

## Переваги XLSX

- Зберігаються всі переноси рядків, форматування, українські символи
- Коректно імпортуються числа, текст, спецсимволи
- Всі дані зберігаються у вигляді, максимально наближеному до оригіналу

## Автоматична конвертація

Плагін розпізнає такі формати посилань:
- Звичайні посилання: `https://docs.google.com/spreadsheets/d/ID/edit#gid=0`
- Прямі посилання: `https://docs.google.com/spreadsheets/d/ID/`
- Експорт посилання: `https://docs.google.com/spreadsheets/d/ID/export?format=xlsx&gid=0`
- Застарілі формати з `key=` або `id=`

## Вимоги

- Таблиця має бути доступна для читання ("Будь-хто з посиланням")
- Потрібен PHP ZipArchive, SimpleXML

## Типові помилки

- ❌ Недостатньо прав доступу (закрита таблиця)
- ❌ Пошкоджений XLSX (Google Sheets має бути "живим" документом)
- ❌ Неправильний ID таблиці в посиланні

## Приклади посилань

**Звичайне посилання (рекомендовано):**
```
https://docs.google.com/spreadsheets/d/1DE7W63SlIe7ZyutDh25Z7IdV0Z33hK6ZlSUc6-yXRFo/edit#gid=0
```

**Пряме посилання:**
```
https://docs.google.com/spreadsheets/d/1DE7W63SlIe7ZyutDh25Z7IdV0Z33hK6ZlSUc6-yXRFo/
```

**Експорт посилання (теж працює):**
```
https://docs.google.com/spreadsheets/d/1DE7W63SlIe7ZyutDh25Z7IdV0Z33hK6ZlSUc6-yXRFo/export?format=xlsx&gid=0
```

## Додатково

- Для великих таблиць імпорт може займати до 1-2 хвилин
- Всі дії імпорту логуються (див. "Логи та дебаг")
- Якщо виникають помилки — перевірте логи та правильність посилання

---

**Див. також:** [Архітектура](architecture.md) | [Frontend-модулі](frontend-modules.md) | [Backend API](backend-api.md) 