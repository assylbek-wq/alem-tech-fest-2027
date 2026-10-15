<?php
// ABU Robocon 2027 - национальный отбор Казахстана (финал на Alem Tech Fest) - настройки регистрации
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'config.php') { http_response_code(404); exit; }

date_default_timezone_set('Asia/Almaty');

// Куда приходят уведомления о новых заявках
const NOTIFY_EMAIL = 'assylbek@ustemfoundation.org';
// От кого уходят письма (тот же ящик, что и у Alem Drone League)
const MAIL_FROM = 'noreply@alemtechfest.kz';
// Отправлять инструктору и капитану письмо-подтверждение
const SEND_CONFIRMATION = true;

// Пароль к странице заявок admin.php хранится только в виде хеша.
// Сейчас стоит тот же пароль, что у drone/admin.php.
// Сменить: php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT);" и вставить результат сюда.
const ADMIN_PASSWORD_HASH = '$2y$10$PNN003l/HWv..uOX4xQ2deTtCZBOqG49oZL51y7wWqDK0iB0cwkCS';

// Прием заявок закрывается автоматически. Поменяйте, когда утвердите дедлайн.
const REG_DEADLINE = '2027-01-31 23:59:59';

// Файл с заявками. Расширение .php и первая строка с exit не дают скачать его по прямой ссылке.
const DATA_FILE = __DIR__ . '/data/registrations.php';
const DATA_GUARD = "<?php http_response_code(404); exit; ?>\n";

const CSV_HEADER = [
  'ID', 'Дата', 'Язык', 'Команда', 'Вуз', 'Город', 'Факультет / кафедра',
  'Инструктор', 'Должность инструктора', 'Email инструктора', 'Телефон инструктора',
  'Капитан', 'Email капитана', 'Телефон капитана', 'Telegram капитана',
  'Студенты (основной состав)', 'Pit crew', 'Опыт', 'Статус роботов', 'Нужна помощь',
  'Откуда узнали', 'Комментарий', 'Подтверждение условий', 'Согласие на данные', 'Согласие на съемку', 'IP'
];
