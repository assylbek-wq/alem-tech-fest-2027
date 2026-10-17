<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function clean($v, $max) {
  if (!is_string($v)) return '';
  return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v)), 0, $max);
}
function s($key, $max = 200) { return clean($_POST[$key] ?? '', $max); }
function arr($key, $allowed) {
  $v = $_POST[$key] ?? [];
  if (!is_array($v)) $v = [$v];
  return array_values(array_intersect($allowed, array_map('strval', $v)));
}
function lst($key) { $v = $_POST[$key] ?? []; return is_array($v) ? $v : []; }
function csvsafe($v) {
  // защита от формул при открытии в Excel
  $v = (string)$v;
  if ($v !== '' && preg_match('/^[=@\t\r]|^[+\-](?![\d\s(])/u', $v)) return "'" . $v;
  return $v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method'], 405);
if (time() > strtotime(REG_DEADLINE)) out(['ok' => false, 'error' => 'closed']);

// антиспам: скрытое поле и минимальное время заполнения
if (s('website') !== '') out(['ok' => true, 'id' => 'BOB-0']);
$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0 && (time() * 1000 - $ts) < 4000) out(['ok' => false, 'error' => 'too_fast']);

$AGE   = ['junior', 'senior'];
$TYPE  = ['spinner', 'wedge', 'flipper', 'lifter', 'hammer', 'walker', 'other'];
$STAGE = ['idea', 'design', 'building', 'ready'];
$EXP   = ['combat', 'first', 'robocon', 'cad', 'print3d', 'embedded', 'none'];
$SRC   = ['instagram', 'telegram', 'school', 'friends', 'site', 'other'];
$ROLE  = ['pilot' => 'Пилот', 'eng' => 'Инженер-конструктор', 'elec' => 'Электроника', 'repair' => 'Механик пит-зоны', 'other' => 'Другое'];

$d = [
  'lang'      => in_array(s('lang', 2), ['ru', 'kz', 'en'], true) ? s('lang', 2) : 'ru',
  'team'      => s('team', 80),
  'org'       => s('org', 150),
  'city'      => s('city', 80),
  'age'       => s('age_group', 10),
  'k_name'    => s('coach_name', 120),
  'k_email'   => s('coach_email', 120),
  'k_phone'   => s('coach_phone', 40),
  'c_phone'   => s('cap_phone', 40),
  'c_tg'      => s('cap_tg', 60),
  'robot'     => s('robot_name', 80),
  'type'      => s('robot_type', 10),
  'stage'     => s('stage', 10),
  'nonstd'    => s('nonstandard', 3) === 'yes',
  'link'      => s('work_link', 300),
  'exp'       => arr('experience', $EXP),
  'src'       => s('source', 20),
  'src_other' => s('source_other', 100),
  'comment'   => s('comment', 1000),
  'rules'     => s('consent_rules', 3) === 'yes',
  'pd'        => s('consent_pd', 3) === 'yes',
  'photo'     => s('consent_photo', 3) === 'yes',
];

$err = [];

// участники: от 1 до 4, первый - капитан
$mn = lst('m_name'); $ma = lst('m_age'); $mr = lst('m_role');
$members = [];
for ($i = 0; $i < 4; $i++) {
  $m = ['name' => clean($mn[$i] ?? '', 120), 'age' => clean($ma[$i] ?? '', 3), 'role' => clean($mr[$i] ?? '', 10)];
  $empty = $m['name'] === '' && $m['age'] === '';
  if ($i > 0 && $empty) continue;
  $ageOk = ctype_digit($m['age']) && (int)$m['age'] >= 8 && (int)$m['age'] <= 99;
  if ($m['name'] === '' || !$ageOk || !isset($ROLE[$m['role']])) $err[] = 'm' . $i;
  $members[] = $m;
}
$d['members'] = implode("\n", array_map(fn($m, $i) => ($i + 1) . '. ' . $m['name'] . ($i === 0 ? ' (капитан)' : '') . ', ' . $m['age'] . ' лет, ' . ($ROLE[$m['role']] ?? ''), $members, array_keys($members)));

foreach (['team', 'org', 'city', 'k_name', 'k_email', 'k_phone', 'c_phone'] as $k) if ($d[$k] === '') $err[] = $k;
if ($d['k_email'] !== '' && !filter_var($d['k_email'], FILTER_VALIDATE_EMAIL)) $err[] = 'k_email';
foreach (['k_phone', 'c_phone'] as $k) if ($d[$k] !== '' && strlen(preg_replace('/\D/', '', $d[$k])) < 10) $err[] = $k;
if (!in_array($d['age'], $AGE, true)) $err[] = 'age';
if (!in_array($d['type'], $TYPE, true)) $err[] = 'type';
if (!in_array($d['stage'], $STAGE, true)) $err[] = 'stage';
if ($d['link'] !== '' && !preg_match('~^https?://\S+$~i', $d['link'])) $err[] = 'link';
if (!$d['exp']) $err[] = 'experience';
if ($d['src'] !== '' && !in_array($d['src'], $SRC, true)) $d['src'] = 'other';
foreach (['rules', 'pd', 'photo'] as $k) if (!$d[$k]) $err[] = $k;
if ($err) out(['ok' => false, 'error' => 'validation', 'fields' => array_values(array_unique($err))], 422);

$L = [
  'junior' => 'Школьники 12-17', 'senior' => 'Студенты и взрослые 18+',
  'spinner' => 'Спиннер', 'wedge' => 'Клин / толкатель', 'flipper' => 'Флиппер', 'lifter' => 'Подъемник',
  'hammer' => 'Молот / топор', 'walker' => 'Шагающий / нестандартный', 'other' => 'Другое',
  'idea' => 'Только идея', 'design' => 'Проектируем в CAD', 'building' => 'Собираем', 'ready' => 'Робот готов',
  'combat' => 'Бои роботов', 'first' => 'FIRST (FLL / FTC)', 'robocon' => 'Robocon', 'cad' => 'CAD (Fusion 360 и др.)',
  'print3d' => '3D-печать', 'embedded' => 'Микроконтроллеры (Arduino, ESP32)', 'none' => 'Нет опыта',
  'instagram' => 'Instagram', 'telegram' => 'Telegram', 'school' => 'Школа / кружок / вуз', 'friends' => 'Друзья', 'site' => 'Сайт',
];
$map = fn($a) => implode(', ', array_map(fn($x) => $L[$x] ?? $x, $a));

$id = 'BOB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$src = $d['src'] ? ($L[$d['src']] ?? 'Другое') : '';
if ($d['src'] === 'other' && $d['src_other'] !== '') $src .= ': ' . $d['src_other'];
$cap = $members[0];

$row = [
  $id, date('Y-m-d H:i'), strtoupper($d['lang']), $d['team'], $d['org'], $d['city'], $L[$d['age']],
  $d['k_name'], $d['k_email'], $d['k_phone'],
  $cap['name'], $d['c_phone'], $d['c_tg'], $d['members'],
  $d['robot'], $L[$d['type']], $L[$d['stage']], $d['nonstd'] ? 'да' : 'нет', $d['link'],
  $map($d['exp']), $src, $d['comment'], 'да', 'да', 'да', $_SERVER['REMOTE_ADDR'] ?? '',
];
$row = array_map('csvsafe', $row);

// запись с блокировкой файла
$dir = dirname(DATA_FILE);
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$fh = @fopen(DATA_FILE, 'c+');
if (!$fh) out(['ok' => false, 'error' => 'storage'], 500);
flock($fh, LOCK_EX);
if (filesize(DATA_FILE) === 0) { fwrite($fh, DATA_GUARD); fputcsv($fh, CSV_HEADER, ',', '"', ''); }
// не больше 5 заявок с одного IP за 10 минут
$recent = 0; $since = time() - 600; $ip = $_SERVER['REMOTE_ADDR'] ?? '';
rewind($fh); fgets($fh); fgetcsv($fh, 0, ',', '"', '');
while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
  if (count($r) >= 26 && end($r) === $ip && strtotime($r[1]) >= $since) $recent++;
}
if ($recent >= 5) { flock($fh, LOCK_UN); fclose($fh); out(['ok' => false, 'error' => 'rate'], 429); }
fseek($fh, 0, SEEK_END);
fputcsv($fh, $row, ',', '"', '');
fflush($fh); flock($fh, LOCK_UN); fclose($fh);

// уведомление организаторам
$headers = "From: Alem Tech Fest <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$body = "Новая заявка Battle of Bots $id\n\n"
  . "Команда: {$d['team']}\nОрганизация: {$d['org']}, {$d['city']}\nВозрастная группа: {$L[$d['age']]}\n\n"
  . "Тренер: {$d['k_name']}\nEmail: {$d['k_email']}\nТелефон: {$d['k_phone']}\n\n"
  . "Капитан: {$cap['name']}\nТелефон: {$d['c_phone']}\nTelegram: {$d['c_tg']}\n\n"
  . "Участники:\n{$d['members']}\n\n"
  . "Робот: " . ($d['robot'] ?: '-') . "\nТип: {$L[$d['type']]}\nСтадия: {$L[$d['stage']]}\n"
  . "Нестандартная конструкция: " . ($d['nonstd'] ? 'да' : 'нет') . "\nСсылка на работу: " . ($d['link'] ?: '-') . "\n"
  . "Опыт: " . $map($d['exp']) . "\nОткуда узнали: $src\nКомментарий: {$d['comment']}\n";
@mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode("Battle of Bots: заявка $id - {$d['team']} ({$d['city']})") . '?=', $body, $headers . "Reply-To: {$d['k_email']}\r\n");

// подтверждение тренеру
if (SEND_CONFIRMATION) {
  if ($d['lang'] === 'kz') {
    $subj = "Өтінім қабылданды: Battle of Bots Kazakhstan";
    $txt = "Сәлеметсіз бе!\n\n«{$d['team']}» командасының ({$d['org']}, {$d['city']}) өтінімі қабылданды. Нөмірі: $id\n\n"
      . "Санат: Light Weight, роботтың салмағы 1,5 кг-ға дейін.\n"
      . "Іріктеу кезеңі: роботтың жұмысын (жоба, фото немесе бейне) 20 қарашаға дейін жіберіңіз.\n"
      . "Финал 2027 жылғы 18-21 ақпанда Астанада, Alem Tech Fest аясында (KNUS) өтеді.\n\n"
      . "Жаңартылған ереже мен келесі қадамдарды поштаға жібереміз.\n\nAlem Tech Fest · USTEM Foundation · KAES";
  } elseif ($d['lang'] === 'en') {
    $subj = "Registration received: Battle of Bots Kazakhstan";
    $txt = "Hello!\n\nWe have received the registration of team \"{$d['team']}\" ({$d['org']}, {$d['city']}). Reference: $id\n\n"
      . "Class: Light Weight, robot weight up to 1.5 kg.\n"
      . "Qualifying stage: submit your robot work (project, photos or video) by 20 November.\n"
      . "The final takes place on 18-21 February 2027 in Astana at Alem Tech Fest (KNUS).\n\n"
      . "We will email the updated regulations and next steps.\n\nAlem Tech Fest · USTEM Foundation · KAES";
  } else {
    $subj = "Заявка принята: Battle of Bots Kazakhstan";
    $txt = "Здравствуйте!\n\nЗаявка команды «{$d['team']}» ({$d['org']}, {$d['city']}) принята. Номер: $id\n\n"
      . "Категория: Light Weight, вес робота до 1,5 кг.\n"
      . "Квалификационный этап: пришлите работу по роботу (проект, фото или видео) до 20 ноября.\n"
      . "Финал пройдет 18-21 февраля 2027 года в Астане на Alem Tech Fest (KNUS).\n\n"
      . "Обновленный регламент и следующие шаги пришлем на почту.\n\nAlem Tech Fest · USTEM Foundation · KAES";
  }
  @mail($d['k_email'], '=?UTF-8?B?' . base64_encode($subj) . '?=', $txt, $headers . "Reply-To: " . NOTIFY_EMAIL . "\r\n");
}

out(['ok' => true, 'id' => $id]);
