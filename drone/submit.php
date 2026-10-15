<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function s($key, $max = 200) {
  $v = $_POST[$key] ?? '';
  if (!is_string($v)) return '';
  $v = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v));
  return mb_substr($v, 0, $max);
}
function arr($key, $allowed) {
  $v = $_POST[$key] ?? [];
  if (!is_array($v)) $v = [$v];
  return array_values(array_intersect($allowed, array_map('strval', $v)));
}
function csvsafe($v) {
  // защита от формул при открытии в Excel
  if ($v !== '' && preg_match('/^[=@\t\r]|^[+\-](?![\d\s(])/u', $v)) return "'" . $v;
  return $v;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'method'], 405);
if (time() > strtotime(REG_DEADLINE)) out(['ok' => false, 'error' => 'closed']);

// антиспам: скрытое поле и минимальное время заполнения
if (s('website') !== '') out(['ok' => true, 'id' => 'ADL-0']);
$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0 && (time() * 1000 - $ts) < 4000) out(['ok' => false, 'error' => 'too_fast']);

$DISC = ['ai_race', 'ai_mission', 'sim_race'];
$EXP  = ['ros', 'px4', 'cv', 'ml', 'sim', 'fpv', 'none'];
$BOOT = ['offline', 'online', 'no'];
$SRC  = ['instagram', 'telegram', 'university', 'friends', 'site', 'other'];

$d = [
  'lang'      => in_array(s('lang', 2), ['ru', 'kz'], true) ? s('lang', 2) : 'ru',
  'team'      => s('team', 80),
  'city'      => s('city', 80),
  'org'       => s('org', 150),
  'size'      => s('size', 1),
  'cap'       => s('cap_name', 120),
  'email'     => s('cap_email', 120),
  'phone'     => s('cap_phone', 40),
  'tg'        => s('cap_tg', 60),
  'members'   => s('members', 2000),
  'disc'      => arr('disciplines', $DISC),
  'exp'       => arr('experience', $EXP),
  'boot'      => s('bootcamp', 10),
  'src'       => s('source', 20),
  'src_other' => s('source_other', 100),
  'comment'   => s('comment', 1000),
  'age16'     => s('age16', 3) === 'yes',
  'parents'   => s('parents', 3) === 'yes',
  'pd'        => s('consent_pd', 3) === 'yes',
  'photo'     => s('consent_photo', 3) === 'yes',
];

$err = [];
foreach (['team', 'city', 'org', 'cap', 'email', 'phone', 'members'] as $k) if ($d[$k] === '') $err[] = $k;
if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'email';
if ($d['phone'] !== '' && strlen(preg_replace('/\D/', '', $d['phone'])) < 10) $err[] = 'phone';
if (!in_array($d['size'], ['2', '3', '4', '5'], true)) $err[] = 'size';
if (!$d['disc']) $err[] = 'disciplines';
if (!$d['exp']) $err[] = 'experience';
if (!in_array($d['boot'], $BOOT, true)) $err[] = 'bootcamp';
if ($d['src'] !== '' && !in_array($d['src'], $SRC, true)) $d['src'] = 'other';
foreach (['age16', 'parents', 'pd', 'photo'] as $k) if (!$d[$k]) $err[] = $k;
if ($err) out(['ok' => false, 'error' => 'validation', 'fields' => array_values(array_unique($err))], 422);

$L = [
  'ai_race' => 'AI Race', 'ai_mission' => 'AI Mission', 'sim_race' => 'Sim Race',
  'ros' => 'ROS/ROS 2', 'px4' => 'PX4/ArduPilot', 'cv' => 'Компьютерное зрение', 'ml' => 'ML/RL',
  'sim' => 'Симуляторы', 'fpv' => 'FPV-пилотирование', 'none' => 'Нет опыта',
  'offline' => 'Очно', 'online' => 'Онлайн-вебинары', 'no' => 'Не нужно',
  'instagram' => 'Instagram', 'telegram' => 'Telegram', 'university' => 'Вуз/работа',
  'friends' => 'Друзья', 'site' => 'Сайт', 'other' => 'Другое',
];
$map = fn($a) => implode(', ', array_map(fn($x) => $L[$x] ?? $x, $a));

$id = 'ADL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
$src = $d['src'] ? ($L[$d['src']] ?? $d['src']) : '';
if ($d['src'] === 'other' && $d['src_other'] !== '') $src .= ': ' . $d['src_other'];

$row = [
  $id, date('Y-m-d H:i'), strtoupper($d['lang']), $d['team'], $d['city'], $d['org'], $d['size'],
  $d['cap'], $d['email'], $d['phone'], $d['tg'], $d['members'],
  $map($d['disc']), $map($d['exp']), $L[$d['boot']] ?? $d['boot'], $src, $d['comment'],
  'да', 'да', 'да', 'да', $_SERVER['REMOTE_ADDR'] ?? '',
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
  if (count($r) >= 22 && end($r) === $ip && strtotime($r[1]) >= $since) $recent++;
}
if ($recent >= 5) { flock($fh, LOCK_UN); fclose($fh); out(['ok' => false, 'error' => 'rate'], 429); }
fseek($fh, 0, SEEK_END);
fputcsv($fh, $row, ',', '"', '');
fflush($fh); flock($fh, LOCK_UN); fclose($fh);

// уведомления
$headers = "From: Alem Drone League <" . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$body = "Новая заявка $id\n\n"
  . "Команда: {$d['team']} ({$d['size']} чел.)\nГород: {$d['city']}\nОрганизация: {$d['org']}\n"
  . "Капитан: {$d['cap']}\nEmail: {$d['email']}\nТелефон: {$d['phone']}\nTelegram: {$d['tg']}\n\n"
  . "Участники:\n{$d['members']}\n\n"
  . "Дисциплины: " . $map($d['disc']) . "\nОпыт: " . $map($d['exp']) . "\n"
  . "Буткемп: " . ($L[$d['boot']] ?? '') . "\nОткуда узнали: $src\nКомментарий: {$d['comment']}\n";
@mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode("ADL: заявка $id - {$d['team']}") . '?=', $body, $headers . "Reply-To: {$d['email']}\r\n");

if (SEND_CONFIRMATION) {
  if ($d['lang'] === 'kz') {
    $subj = "Өтінім қабылданды: AI Drone Championship 2026";
    $txt = "Сәлеметсіз бе, {$d['cap']}!\n\n«{$d['team']}» командасының өтінімі қабылданды. Нөмірі: $id\n\n"
      . "Жақын арада симуляторға қолжетімділік пен нұсқаулықты жібереміз.\n"
      . "Онлайн іріктеу: 16-30 қараша. Жартылай финал: 5-6 желтоқсан, Алматы. Финал: 19-20 желтоқсан, Астана.\n\nAlem Drone League";
  } else {
    $subj = "Заявка принята: AI Drone Championship 2026";
    $txt = "Здравствуйте, {$d['cap']}!\n\nЗаявка команды «{$d['team']}» принята. Номер: $id\n\n"
      . "В ближайшее время пришлем доступ к симулятору и инструкцию.\n"
      . "Онлайн-отбор: 16-30 ноября. Полуфинал: 5-6 декабря, Алматы. Финал: 19-20 декабря, Астана.\n\nAlem Drone League";
  }
  @mail($d['email'], '=?UTF-8?B?' . base64_encode($subj) . '?=', $txt, $headers . "Reply-To: " . NOTIFY_EMAIL . "\r\n");
}

out(['ok' => true, 'id' => $id]);
