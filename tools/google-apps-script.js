/**
 * Alem Tech Fest 2027 - приём заявок с сайта в Google Таблицу.
 *
 * Установка (один раз, 5 минут):
 * 1. Создайте Google Таблицу, например «Alem Tech Fest 2027 - Заявки».
 * 2. Расширения -> Apps Script. Удалите всё в редакторе и вставьте этот файл целиком. Сохраните.
 * 3. Развернуть -> Новое развертывание -> тип «Веб-приложение».
 *    Выполнять как: «От моего имени». У кого есть доступ: «Все».
 * 4. Нажмите «Развернуть», разрешите доступ к таблице и скопируйте URL веб-приложения
 *    (вида https://script.google.com/macros/s/.../exec). Пришлите его Claude.
 *
 * Необязательно: впишите почту в NOTIFY_EMAIL, чтобы получать письмо о каждой новой заявке.
 */
var NOTIFY_EMAIL = ''; // например 'hello@ustemfoundation.org'
var SHEET_NAME = 'Заявки';

var COLUMNS = [
  ['createdAt', 'Дата'], ['ref', 'Номер заявки'], ['team', 'Команда'], ['discipline', 'Дисциплина'],
  ['category', 'Категория'], ['students', 'Участников'], ['coaches', 'Тренеров'], ['country', 'Страна'],
  ['city', 'Город'], ['org', 'Организация'], ['lead', 'Руководитель'], ['email', 'Email'],
  ['phone', 'Телефон'], ['visaLetter', 'Нужно приглашение'], ['hotel', 'Нужна помощь с жильем'],
  ['notes', 'Комментарий'], ['lang', 'Язык сайта']
];

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var data = JSON.parse(e.postData.contents || '{}');
    var sheet = getSheet_();
    var row = COLUMNS.map(function (c) {
      var v = data[c[0]];
      if (typeof v === 'boolean') return v ? 'да' : '';
      return v == null ? '' : String(v).slice(0, 1000);
    });
    sheet.appendRow(row);
    if (NOTIFY_EMAIL) {
      MailApp.sendEmail(NOTIFY_EMAIL, 'Alem Tech Fest 2027: новая заявка ' + (data.ref || ''),
        COLUMNS.map(function (c, i) { return c[1] + ': ' + row[i]; }).join('\n'));
    }
    return ContentService.createTextOutput(JSON.stringify({ ok: true })).setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ ok: false, error: String(err) })).setMimeType(ContentService.MimeType.JSON);
  } finally {
    lock.releaseLock();
  }
}

function doGet() {
  return ContentService.createTextOutput('Alem Tech Fest 2027 registration endpoint is running.');
}

function getSheet_() {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sheet = ss.getSheetByName(SHEET_NAME) || ss.insertSheet(SHEET_NAME);
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(COLUMNS.map(function (c) { return c[1]; }));
    sheet.setFrozenRows(1);
    sheet.getRange(1, 1, 1, COLUMNS.length).setFontWeight('bold');
  }
  return sheet;
}
