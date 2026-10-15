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
if (s('website') !== '') out(['ok' => true, 'id' => 'ABU-0']);
$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0 && (time() * 1000 - $ts) < 4000) out(['ok' => false, 'error' => 'too_fast']);

$EXP    = ['robocon', 'ftc', 'robomaster', 'ros', 'cad', 'embedded', 'cv', 'none'];
$ROBOTS = ['idea', 'design', 'building', 'ready'];
$HELP   = ['parts', 'field', 'mentor', 'stay'];
$SRC    = ['instagram', 'telegram', 'university', 'friends', 'site', 'other'];
$COURSE = ['1', '2', '3', '4', '5', 'college'];
$ROLE   = ['lead' => 'Капитан / оператор', 'mech' => 'Механика и CAD', 'elec' => 'Электроника', 'code' => 'Программирование и автономия', 'other' => 'Другое'];

$d = [
  'lang'      => in_array(s('lang', 2), ['ru', 'kz', 'en'], true) ? s('lang', 2) : 'ru',
  'team'      => s('team', 80),
  'uni'       => s('uni', 150),
  'city'      => s('city', 80),
  'dept'      => s('dept', 150),
  'i_name'    => s('inst_name', 120),
  'i_pos'     => s('inst_pos', 120),
  'i_email'   => s('inst_email', 120),
  'i_phone'   => s('inst_phone', 40),
  'c_phone'   => s('cap_phone', 40),
  'c_tg'      => s('cap_tg', 60),
  'exp'       => arr('experience', $EXP),
  'robots'    => s('robots', 10),
  'help'      => arr('help', $HELP),
  'src'       => s('source', 20),
  'src_other' => s('source_other', 100),
  'comment'   => s('comment', 1000),
  'elig'      => s('consent_elig', 3) === 'yes',
  'pd'        => s('consent_pd', 3) === 'yes',
  'photo'     => s('consent_photo', 3) === 'yes',
];

$err = [];

// основной состав: ровно 3 студента, первый - капитан
$sn = lst('s_name'); $sc = lst('s_course'); $sm = lst('s_major'); $sr = lst('s_role'); $se = lst('s_email');
$students = [];
for ($i = 0; $i < 3; $i++) {
  $m = ['name' => clean($sn[$i] ?? '', 120), 'course' => clean($sc[$i] ?? '', 10), 'major' => clean($sm[$i] ?? '', 120),
        'role' => clean($sr[$i] ?? '', 10), 'email' => clean($se[$i] ?? '', 120)];
  if ($m['name'] === '' || !in_array($m['course'], $COURSE, true) || $m['major'] === '' || !isset($ROLE[$m['role']])) $err[] = 's' . $i;
  if ($i === 0 && !filter_var($m['email'], FILTER_VALIDATE_EMAIL)) $err[] = 's0';
  $students[] = $m;
}
$courseLbl = fn($c) => $c === 'college' ? 'колледж' : $c . ' курс';
$d['students'] = implode("\n", array_map(fn($m, $i) => ($i + 1) . '. ' . $m['name'] . ($i === 0 ? ' (капитан)' : '') . ', ' . $courseLbl($m['course']) . ', ' . $m['major'] . ', ' . ($ROLE[$m['role']] ?? '') . ($m['email'] !== '' ? ', ' . $m['email'] : ''), $students, array_keys($students)));

// pit crew: до 3 человек, необязательно
$pn = lst('pit_name'); $pc = lst('pit_course');
$pit = [];
for ($i = 0; $i < 3; $i++) {
  $n = clean($pn[$i] ?? '', 120); $c = clean($pc[$i] ?? '', 10);
  if ($n === '') continue;
  $pit[] = $n . (in_array($c, $COURSE, true) ? ', ' . $courseLbl($c) : '');
}
$d['pit'] = implode("\n", $pit);

foreach (['team', 'uni', 'city', 'i_name', 'i_pos', 'i_email', 'i_phone', 'c_phone'] as $k) if ($d[$k] === '') $err[] = $k;
if ($d['i_email'] !== '' && !filter_var($d['i_email'], FILTER_VALIDATE_EMAIL)) $err[] = 'i_email';
foreach (['i_phone', 'c_phone'] as $k) if ($d[$k] !== '' && strlen(preg_replace('/\D/', '', $d[$k])) < 10) $err[] = $k;
if (!$d['exp']) $err[] = 'experience';
if (!in_array($d['robots'], $ROBOTS, true)) $err[] = 'robots';
if ($d['src'] !== '' && !in_array($d['src'], $SRC, true)) $d['src'] = 'other';
foreach (['elig', 'pd', 'photo'] as $k) if (!$d[$k]) $err[] = $k;
if ($err) out(['ok' => false, 'error' => 'validation', 'fields' => array_values(array_unique($err))], 422);

$L = [
  'robocon' => 'ABU Robocon / национальные Robocon', 'ftc' => 'FIRST (FTC/FRC)', 'robomaster' => 'RoboMaster', 'ros' => 'ROS/ROS 2',
  'cad' => 'CAD и мехобработка', 'embedded' => 'Микроконтроллеры', 'cv' => 'Компьютерное зрение', 'none' => 'Нет опыта',
  'idea' => 'Только начинаем', 'design' => 'Проектируем', 'building' => 'Собираем', 'ready' => 'Есть рабочие прототипы',
  'parts' => 'Комплектующие', 'field' => 'Элементы поля для тренировок', 'mentor' => 'Консультации наставника', 'stay' => 'Проживание в Астане',
  'instagram' => 'Instagram', 'telegram' => 'Telegram', 'university' => 'Вуз', 'friends' => 'Друзья', 'site' => 'Сайт', 'other' => 'Другое',
];
$map = fn($a) => implode(', ', array_map(fn($x) => $L[$x] ?? $x, $a));

$id = 'ABU-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$src = $d['src'] ? ($L[$d['src']] ?? $d['src']) : '';
if ($d['src'] === 'other' && $d['src_other'] !== '') $src .= ': ' . $d['src_other'];
$cap = $students[0];

$row = [
  $id, date('Y-m-d H:i'), strtoupper($d['lang']), $d['team'], $d['uni'], $d['city'], $d['dept'],
  $d['i_name'], $d['i_pos'], $d['i_email'], $d['i_phone'],
  $cap['name'], $cap['email'], $d['c_phone'], $d['c_tg'],
  $d['students'], $d['pit'], $map($d['exp']), $L[$d['robots']] ?? $d['robots'], $map($d['help']),
  $src, $d['comment'], 'да', 'да', 'да', $_SERVER['REMOTE_ADDR'] ?? '',
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
$body = "Новая заявка ABU Robocon $id\n\n"
  . "Команда: {$d['team']}\nВуз: {$d['uni']}, {$d['city']}\nФакультет: {$d['dept']}\n\n"
  . "Инструктор: {$d['i_name']}, {$d['i_pos']}\nEmail: {$d['i_email']}\nТелефон: {$d['i_phone']}\n\n"
  . "Капитан: {$cap['name']}\nEmail: {$cap['email']}\nТелефон: {$d['c_phone']}\nTelegram: {$d['c_tg']}\n\n"
  . "Основной состав:\n{$d['students']}\n\nPit crew:\n" . ($d['pit'] ?: '-') . "\n\n"
  . "Опыт: " . $map($d['exp']) . "\nРоботы: " . ($L[$d['robots']] ?? '') . "\nНужна помощь: " . ($map($d['help']) ?: '-') . "\n"
  . "Откуда узнали: $src\nКомментарий: {$d['comment']}\n";
@mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode("ABU Robocon: заявка $id - {$d['team']} ({$d['uni']})") . '?=', $body, $headers . "Reply-To: {$d['i_email']}\r\n");

// подтверждение инструктору и капитану
if (SEND_CONFIRMATION) {
  if ($d['lang'] === 'kz') {
    $subj = "Өтінім қабылданды: ABU Robocon 2027 ұлттық іріктеуі";
    $txt = "Сәлеметсіз бе!\n\n«{$d['team']}» командасының ({$d['uni']}) өтінімі қабылданды. Нөмірі: $id\n\n"
      . "Ұлттық іріктеудің финалы 2027 жылғы 18-21 ақпанда Астанада, Alem Tech Fest аясында (ҚҰСУ / KNUS) өтеді. Жеңімпаз команда Қазақстан атынан ABU Robocon 2027 халықаралық жарысына (Соло, Индонезия, 29 тамыз 2027) қатысады.\n\n"
      . "Ойын ережесі: The Pursuit of Mustika Nusantara, https://aburobocon2027.id/rulebook/\n"
      . "Іріктеу кезеңдері мен мерзімдерін жақын арада поштаға жібереміз.\n\nAlem Tech Fest · USTEM Foundation · ҚР ҒЖБМ · Хабар агенттігі";
  } elseif ($d['lang'] === 'en') {
    $subj = "Registration received: ABU Robocon 2027 Kazakhstan national selection";
    $txt = "Hello!\n\nWe have received the registration of team \"{$d['team']}\" ({$d['uni']}). Reference: $id\n\n"
      . "The national selection final takes place on 18-21 February 2027 in Astana at Alem Tech Fest (KNUS). The winning team represents Kazakhstan at ABU Robocon 2027 in Solo, Indonesia (29 August 2027).\n\n"
      . "Game rules: The Pursuit of Mustika Nusantara, https://aburobocon2027.id/rulebook/\n"
      . "We will email the selection stages and deadlines shortly.\n\nAlem Tech Fest · USTEM Foundation · Ministry of Science and Higher Education of Kazakhstan · Khabar Agency";
  } else {
    $subj = "Заявка принята: национальный отбор ABU Robocon 2027";
    $txt = "Здравствуйте!\n\nЗаявка команды «{$d['team']}» ({$d['uni']}) принята. Номер: $id\n\n"
      . "Финал национального отбора пройдет 18-21 февраля 2027 года в Астане на Alem Tech Fest (KNUS). Команда-победитель представит Казахстан на ABU Robocon 2027 в Соло, Индонезия (29 августа 2027).\n\n"
      . "Правила игры: The Pursuit of Mustika Nusantara, https://aburobocon2027.id/rulebook/\n"
      . "Этапы отбора и сроки пришлем на почту в ближайшее время.\n\nAlem Tech Fest · USTEM Foundation · МНВО РК · Агентство «Хабар»";
  }
  $to = $d['i_email'] . ($cap['email'] && strcasecmp($cap['email'], $d['i_email']) !== 0 ? ', ' . $cap['email'] : '');
  @mail($to, '=?UTF-8?B?' . base64_encode($subj) . '?=', $txt, $headers . "Reply-To: " . NOTIFY_EMAIL . "\r\n");
}

out(['ok' => true, 'id' => $id]);
