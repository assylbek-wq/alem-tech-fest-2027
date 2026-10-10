/**
 * Alem Tech Fest 2027 - заявки с сайта и визовые приглашения.
 *
 * Что делает:
 *  1. Регистрация команд: каждая заявка с сайта становится строкой на листе «Заявки».
 *  2. Визовые приглашения: запрос с сайта (список участников) сохраняется на листе «Visa letters»,
 *     организаторам приходит письмо с PDF-черновиком и ссылками «Подтвердить и отправить» / «Отклонить».
 *     После подтверждения готовое приглашение в PDF уходит заявителю на email.
 *
 * Установка (один раз, 10 минут):
 *  1. Создайте Google Таблицу «Alem Tech Fest 2027». Лучше под аккаунтом hello@ustemfoundation.org:
 *     письма участникам будут уходить от имени этого аккаунта.
 *  2. Расширения -> Apps Script. Вставьте этот файл в Code.gs.
 *  3. Нажмите «+» -> Скрипт, назовите файл assets и вставьте содержимое assets.gs
 *     (логотип, печать и подпись; этот файл присылается отдельно и не публикуется).
 *  4. Развернуть -> Новое развертывание -> Веб-приложение. Выполнять как: «От моего имени».
 *     Доступ: «Все». Разрешите доступ к Таблице, Gmail и Диску.
 *  5. Скопируйте URL веб-приложения (.../exec) и передайте его для подключения к сайту.
 *  После любых правок кода: Развернуть -> Управление развертываниями -> Изменить -> Новая версия.
 */

var NOTIFY_EMAIL = 'hello@ustemfoundation.org';   // кому приходят новые заявки и запросы на приглашения
var SENDER_NAME = 'Alem Tech Fest 2027 · USTEM Foundation';
var REG_SHEET = 'Заявки';
var VISA_SHEET = 'Visa letters';

var REG_COLUMNS = [
  ['createdAt', 'Дата'], ['ref', 'Номер заявки'], ['team', 'Команда'], ['discipline', 'Дисциплина'],
  ['category', 'Категория'], ['students', 'Участников'], ['coaches', 'Тренеров'], ['country', 'Страна'],
  ['city', 'Город'], ['org', 'Организация'], ['lead', 'Руководитель'], ['email', 'Email'],
  ['phone', 'Телефон'], ['visaLetter', 'Нужно приглашение'], ['hotel', 'Нужна помощь с жильем'],
  ['notes', 'Комментарий'], ['lang', 'Язык сайта']
];
var VISA_HEADERS = ['ID', 'Создан', 'Статус', 'Команда', 'Страна', 'Контактное лицо', 'Email', 'Телефон',
  'Прибытие', 'Отъезд', 'Кол-во', 'Участники (JSON)', 'Токен', 'Отправлено', 'Номер заявки на участие'];

/* ------------------------------------------------------------------ */
/* Entry points                                                        */
/* ------------------------------------------------------------------ */

function doPost(e) {
  var lock = LockService.getScriptLock();
  lock.waitLock(20000);
  try {
    var data = JSON.parse((e && e.postData && e.postData.contents) || '{}');
    if (data.type === 'visa') return json_(handleVisaRequest_(data));
    return json_(handleRegistration_(data));
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  } finally {
    lock.releaseLock();
  }
}

function doGet(e) {
  var p = (e && e.parameter) || {};
  if (p.action === 'approve' || p.action === 'reject') return page_(handleDecision_(p));
  return ContentService.createTextOutput('Alem Tech Fest 2027 endpoint is running.');
}

/* ------------------------------------------------------------------ */
/* Team registration                                                   */
/* ------------------------------------------------------------------ */

function handleRegistration_(data) {
  var sheet = sheet_(REG_SHEET, REG_COLUMNS.map(function (c) { return c[1]; }));
  var row = REG_COLUMNS.map(function (c) {
    var v = data[c[0]];
    if (typeof v === 'boolean') return v ? 'да' : '';
    return v == null ? '' : String(v).slice(0, 1000);
  });
  sheet.appendRow(row);
  if (NOTIFY_EMAIL) {
    MailApp.sendEmail({
      to: NOTIFY_EMAIL, name: SENDER_NAME,
      subject: 'Alem Tech Fest 2027: новая заявка ' + (data.ref || '') + ' - ' + (data.team || ''),
      body: REG_COLUMNS.map(function (c, i) { return c[1] + ': ' + row[i]; }).join('\n')
    });
  }
  return { ok: true };
}

/* ------------------------------------------------------------------ */
/* Visa invitation letters                                             */
/* ------------------------------------------------------------------ */

function handleVisaRequest_(d) {
  var people = (d.people || []).filter(function (x) { return x && x.name; }).slice(0, 40).map(function (x) {
    return {
      name: clean_(x.name, 120), dob: clean_(x.dob, 20), citizenship: clean_(x.citizenship, 60),
      passport: clean_(x.passport, 30), role: clean_(x.role, 30)
    };
  });
  if (!people.length) return { ok: false, error: 'no participants' };
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(d.email || ''))) return { ok: false, error: 'bad email' };

  var sheet = sheet_(VISA_SHEET, VISA_HEADERS);
  var id = 'ATF27-V-' + ('000' + sheet.getLastRow()).slice(-4);
  var token = Utilities.getUuid().replace(/-/g, '');
  var req = {
    id: id, created: new Date(), status: 'ожидает подтверждения', team: clean_(d.team, 80),
    country: clean_(d.country, 60), contact: clean_(d.contact, 80), email: clean_(d.email, 120),
    phone: clean_(d.phone, 40), arrival: clean_(d.arrival, 20), departure: clean_(d.departure, 20),
    people: people, ref: clean_(d.ref, 20)
  };
  sheet.appendRow([req.id, req.created, req.status, req.team, req.country, req.contact, req.email, "'" + req.phone,
    "'" + req.arrival, "'" + req.departure, people.length, JSON.stringify(people), token, '', req.ref]);

  var url = ScriptApp.getService().getUrl();
  var approve = url + '?action=approve&id=' + encodeURIComponent(id) + '&t=' + token;
  var reject = url + '?action=reject&id=' + encodeURIComponent(id) + '&t=' + token;
  var draft = letterPdf_(req, true);
  var list = people.map(function (x, i) {
    return (i + 1) + '. ' + x.name + ' | ' + x.dob + ' | ' + x.citizenship + ' | ' + x.passport + ' | ' + x.role;
  }).join('\n');

  MailApp.sendEmail({
    to: NOTIFY_EMAIL, name: SENDER_NAME,
    subject: 'Запрос визового приглашения ' + id + ': ' + req.team + ' (' + req.country + '), ' + people.length + ' чел.',
    htmlBody:
      '<p><b>' + esc_(req.team) + '</b>, ' + esc_(req.country) + '<br>Контакт: ' + esc_(req.contact) + ', ' +
      esc_(req.email) + ', ' + esc_(req.phone) + '<br>Даты: ' + esc_(req.arrival) + ' - ' + esc_(req.departure) + '</p>' +
      '<pre style="font:13px monospace">' + esc_(list) + '</pre>' +
      '<p>Черновик приглашения во вложении. Проверьте данные и выберите:</p>' +
      '<p><a href="' + approve + '" style="background:#0346F5;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold">Подтвердить и отправить заявителю</a>' +
      '&nbsp;&nbsp;<a href="' + reject + '" style="color:#C0261C">Отклонить</a></p>',
    attachments: [draft]
  });
  return { ok: true, id: id };
}

function handleDecision_(p) {
  var sheet = sheet_(VISA_SHEET, VISA_HEADERS);
  var values = sheet.getDataRange().getValues();
  for (var r = 1; r < values.length; r++) {
    if (values[r][0] !== p.id) continue;
    if (values[r][12] !== p.t) return { ok: false, msg: 'Ссылка недействительна.' };
    if (String(values[r][2]).indexOf('отправлено') === 0) return { ok: true, msg: 'Приглашение ' + p.id + ' уже было отправлено ' + values[r][13] + '.' };
    if (p.action === 'reject') {
      sheet.getRange(r + 1, 3).setValue('отклонено');
      return { ok: true, msg: 'Запрос ' + p.id + ' отклонен. Заявителю ничего не отправлено.' };
    }
    var req = {
      id: values[r][0], created: values[r][1], team: values[r][3], country: values[r][4], contact: values[r][5],
      email: values[r][6], phone: values[r][7], arrival: values[r][8], departure: values[r][9],
      people: JSON.parse(values[r][11] || '[]')
    };
    var pdf = letterPdf_(req, false);
    MailApp.sendEmail({
      to: req.email, cc: NOTIFY_EMAIL, replyTo: NOTIFY_EMAIL, name: SENDER_NAME,
      subject: 'Alem Tech Fest 2027 - invitation letter ' + req.id,
      htmlBody: '<p>Dear ' + esc_(req.contact || 'colleagues') + ',</p>' +
        '<p>Please find attached the official invitation letter for team <b>' + esc_(req.team) +
        '</b> (' + req.people.length + ' people) to the Central Asia <i>FIRST</i>® Championship 2027 / Alem Tech Fest 2027, 18–21 February 2027, Astana.</p>' +
        '<p>If you need a visa, present this letter to the Embassy or Consulate of Kazakhstan, or use it for an e-visa application on vmp.gov.kz.</p>' +
        '<p>Best regards,<br>USTEM Foundation<br>hello@ustemfoundation.org · alemtechfest.kz</p>',
      attachments: [pdf]
    });
    var now = new Date();
    sheet.getRange(r + 1, 3).setValue('отправлено');
    sheet.getRange(r + 1, 14).setValue(now);
    return { ok: true, msg: 'Приглашение ' + p.id + ' отправлено на ' + req.email + ' (копия на ' + NOTIFY_EMAIL + ').' };
  }
  return { ok: false, msg: 'Запрос не найден.' };
}

/* ------------------------------------------------------------------ */
/* Letter PDF                                                          */
/* ------------------------------------------------------------------ */

var ROLES_ = { student: 'Participant', coach: 'Coach / mentor', lead: 'Team leader' };

function letterPdf_(req, draft) {
  var A = (typeof ASSETS !== 'undefined') ? ASSETS : {};
  var date = Utilities.formatDate(new Date(), 'Asia/Almaty', 'd MMMM yyyy');
  var rows = req.people.map(function (x, i) {
    return '<tr><td>' + (i + 1) + '</td><td>' + esc_(x.name) + '</td><td>' + esc_(fmt_(x.dob)) + '</td><td>' + esc_(x.citizenship) +
      '</td><td>' + esc_(x.passport) + '</td><td>' + esc_(ROLES_[x.role] || x.role) + '</td></tr>';
  }).join('');
  var period = (req.arrival && req.departure) ? ' for the period from <b>' + esc_(fmt_(req.arrival)) + '</b> to <b>' + esc_(fmt_(req.departure)) + '</b>' : '';
  var html =
    '<html><head><meta charset="utf-8"><style>' +
    '@page{size:A4;margin:16mm 17mm 16mm 22mm}' +
    'body{font-family:"Times New Roman",Times,serif;font-size:11.5pt;line-height:1.3;color:#000}' +
    'table.h{width:100%;border-collapse:collapse}table.h td{vertical-align:top}' +
    '.org{font-size:10.8pt;line-height:1.3}' +
    'p{margin:0 0 9pt;text-align:justify}' +
    'table.l{width:100%;border-collapse:collapse;margin:6pt 0 12pt;font-size:10.5pt}' +
    'table.l th,table.l td{border:1px solid #444;padding:4pt 5pt;text-align:left}' +
    'table.l th{background:#EEF2FB}' +
    '.draft{color:#C0261C;font-weight:bold;font-size:13pt;text-align:center;border:2px solid #C0261C;padding:4pt;margin-bottom:10pt}' +
    '</style></head><body>' +
    (draft ? '<div class="draft">DRAFT - NOT YET APPROVED</div>' : '') +
    '<table class="h"><tr><td>' + (A.logo ? '<img src="' + A.logo + '" style="width:52mm">' : '<b>USTEM Foundation</b>') +
    '</td><td class="org" style="width:82mm"><b>«USTEM Foundation» Public Fund</b><br><i>Kazakhstan, Astana city, Mangilik El avenue,<br>Astana Hub, C4.6<br>Email: hello@ustemfoundation.org</i></td></tr></table>' +
    '<p style="margin-top:14pt">Ref. No: <b>' + esc_(req.id) + '</b><br>Date: ' + date + '</p>' +
    '<p>To: Team <b>«' + esc_(req.team) + '»</b>, ' + esc_(req.country) + (req.contact ? '<br>Attn: ' + esc_(req.contact) : '') + '</p>' +
    '<p><b>Subject: Invitation to the Central Asia <i>FIRST</i>® Championship 2027 / Alem Tech Fest 2027</b></p>' +
    '<p>Dear members of team «' + esc_(req.team) + '»,</p>' +
    '<p>On behalf of the «USTEM Foundation» Public Fund, we are pleased to invite your team to take part in the <b>Central Asia <i>FIRST</i>® Championship 2027</b>, ' +
    'held as part of the <b>Alem Tech Fest 2027</b> international festival of science and technology. The event will take place on <b>18–21 February 2027</b> ' +
    'at the Kazakh National University of Sports (KNUS), 15 Karkaraly Highway, Astana, Republic of Kazakhstan, and is expected to bring together over 500 teams and more than 5,000 participants.</p>' +
    '<p>We confirm that your team is registered for the event and invite the following persons to visit the Republic of Kazakhstan' + period + ':</p>' +
    '<table class="l"><tr><th>No.</th><th>Full name</th><th>Date of birth</th><th>Citizenship</th><th>Passport No.</th><th>Role</th></tr>' + rows + '</table>' +
    '<p>This letter may be presented to the Embassies and Consulates of the Republic of Kazakhstan, or used for an electronic visa application, as the basis for issuing entry visas to the persons listed above.</p>' +
    '<p>We look forward to welcoming you in Astana. Should you have any questions, please contact us at hello@ustemfoundation.org.</p>' +
    '<table class="h" style="margin-top:14pt"><tr><td style="width:60%"><b>Sincerely,</b><br><br><b>Assylbek Murzakhmetov</b><br><b>Head of USTEM Foundation</b></td>' +
    '<td>' + (!draft && A.stamp ? '<img src="' + A.stamp + '" style="width:36mm">' : '') + (!draft && A.signature ? '<img src="' + A.signature + '" style="width:13mm;margin-left:-14mm;vertical-align:top">' : '') + '</td></tr></table>' +
    '</body></html>';
  var blob = HtmlService.createHtmlOutput(html).getBlob().getAs('application/pdf');
  blob.setName((draft ? 'DRAFT_' : '') + 'Invitation_ATF2027_' + req.id + '_' + String(req.team).replace(/[^\w-]+/g, '_') + '.pdf');
  return blob;
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function sheet_(name, headers) {
  var ss = SpreadsheetApp.getActiveSpreadsheet();
  var sh = ss.getSheetByName(name) || ss.insertSheet(name);
  if (sh.getLastRow() === 0) {
    sh.appendRow(headers);
    sh.setFrozenRows(1);
    sh.getRange(1, 1, 1, headers.length).setFontWeight('bold');
  }
  return sh;
}
function clean_(v, max) { return v == null ? '' : String(v).replace(/[\r\n\t]+/g, ' ').trim().slice(0, max || 200); }
function esc_(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
function fmt_(iso) {
  if (Object.prototype.toString.call(iso) === '[object Date]') iso = Utilities.formatDate(iso, Session.getScriptTimeZone(), 'yyyy-MM-dd');
  var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso));
  if (!m) return String(iso);
  var mon = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  return Number(m[3]) + ' ' + mon[Number(m[2]) - 1] + ' ' + m[1];
}
function json_(o) { return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON); }
function page_(r) {
  return HtmlService.createHtmlOutput('<div style="font:16px sans-serif;max-width:560px;margin:60px auto;padding:24px;border-radius:12px;background:' +
    (r.ok ? '#DCF5EA' : '#FDE3E0') + '">' + esc_(r.msg) + '</div>').setTitle('Alem Tech Fest 2027');
}

/* Run once from the editor to test PDF generation without the website. */
function testDraftLetter() {
  var req = { id: 'ATF27-V-TEST', team: 'Test Team', country: 'Uzbekistan', arrival: '2027-02-16', departure: '2027-02-22',
    people: [{ name: 'Ivan Petrov', dob: '2010-05-14', citizenship: 'Uzbekistan', passport: 'AA1234567', role: 'Student' }] };
  var f = DriveApp.createFile(letterPdf_(req, false));
  Logger.log(f.getUrl());
}
