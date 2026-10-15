<?php
// Alem Drone League: AI Drone Championship 2026 - настройки регистрации
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') { http_response_code(404); exit; }

date_default_timezone_set('Asia/Almaty');

// Куда приходят уведомления о новых заявках
const NOTIFY_EMAIL = 'assylbek@ustemfoundation.org';
// От кого уходят письма (ящик лучше создать в Plesk, чтобы письма не попадали в спам)
const MAIL_FROM = 'noreply@alemtechfest.kz';
// Отправлять капитану письмо-подтверждение
const SEND_CONFIRMATION = true;

// Пароль к странице заявок admin.php хранится только в виде хеша.
// Сменить пароль: в Plesk > PHP-консоль или локально выполнить
//   php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT);"
// и вставить результат сюда.
const ADMIN_PASSWORD_HASH = '$2y$10$PNN003l/HWv..uOX4xQ2deTtCZBOqG49oZL51y7wWqDK0iB0cwkCS';

// Прием заявок закрывается автоматически
const REG_DEADLINE = '2026-11-14 23:59:59';

// Файл с заявками. Расширение .php и первая строка с exit не дают скачать его по прямой ссылке.
const DATA_FILE = __DIR__ . '/data/registrations.php';
const DATA_GUARD = "<?php http_response_code(404); exit; ?>\n";

const CSV_HEADER = [
  'ID', 'Дата', 'Язык', 'Команда', 'Город', 'Организация', 'Кол-во участников',
  'Капитан', 'Email', 'Телефон / WhatsApp', 'Telegram', 'Остальные участники',
  'Дисциплины', 'Опыт', 'Буткемп', 'Откуда узнали', 'Комментарий',
  '16+', 'Согласие родителей', 'Согласие на данные', 'Согласие на съемку', 'IP',
  'Дрон', 'Платформа дрона', 'Возраст капитана'
];
