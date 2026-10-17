<?php
// Battle of Bots Kazakhstan (USTEM Foundation x KAES) - настройки регистрации
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') { http_response_code(404); exit; }

date_default_timezone_set('Asia/Almaty');

// Куда приходят уведомления о новых заявках
const NOTIFY_EMAIL = 'assylbek@ustemfoundation.org';
// От кого уходят письма (тот же ящик, что у robocon и drone)
const MAIL_FROM = 'noreply@alemtechfest.kz';
// Отправлять тренеру и капитану письмо-подтверждение
const SEND_CONFIRMATION = true;

// Пароль к странице заявок admin.php хранится только в виде хеша.
// Сейчас стоит тот же пароль, что у robocon/admin.php и drone/admin.php.
// Сменить: php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT);" и вставить результат сюда.
const ADMIN_PASSWORD_HASH = '$2y$10$PNN003l/HWv..uOX4xQ2deTtCZBOqG49oZL51y7wWqDK0iB0cwkCS';

// Прием заявок закрывается автоматически: дедлайн подачи работ на квалификацию - 20 ноября.
const REG_DEADLINE = '2026-11-20 23:59:59';

// Файл с заявками. Расширение .php и первая строка с exit не дают скачать его по прямой ссылке.
const DATA_FILE = __DIR__ . '/data/registrations.php';
const DATA_GUARD = "<?php http_response_code(404); exit; ?>\n";

const CSV_HEADER = [
  'ID', 'Дата', 'Язык', 'Команда', 'Организация', 'Город', 'Возрастная группа',
  'Тренер', 'Email тренера', 'Телефон тренера',
  'Капитан', 'Телефон капитана', 'Telegram капитана', 'Участники',
  'Робот', 'Тип робота', 'Стадия', 'Нестандартная конструкция', 'Ссылка на работу',
  'Опыт', 'Откуда узнали', 'Комментарий', 'Согласие с регламентом', 'Согласие на данные', 'Согласие на съемку', 'IP'
];
