<?php
require __DIR__ . '/config.php';
header('X-Robots-Tag: noindex, nofollow');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('adl_admin');
session_start();

$error = '';
if (isset($_POST['password'])) {
  usleep(400000);
  if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) { session_regenerate_id(true); $_SESSION['ok'] = true; header('Location: admin.php'); exit; }
  $error = 'Неверный пароль';
}
if (isset($_GET['logout'])) { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
$authed = !empty($_SESSION['ok']);

function rows() {
  if (!is_file(DATA_FILE)) return [];
  $fh = fopen(DATA_FILE, 'r'); fgets($fh); $out = [];
  while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) $out[] = $r;
  fclose($fh); return $out;
}

if ($authed && isset($_GET['csv'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="adl_registrations_' . date('Y-m-d') . '.csv"');
  $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
  foreach (rows() as $r) fputcsv($o, $r, ';', '"', '');
  exit;
}
$h = fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Заявки · Alem Drone League</title>
<style>
body{margin:0;font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f3f5f9;color:#0b1730}
.wrap{max-width:1400px;margin:0 auto;padding:24px 16px}
h1{font-size:22px;margin:0 0 4px}.muted{color:#5b6680}
.card{background:#fff;border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.bar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:16px 0}
a.btn,button{background:#0164d9;color:#fff;border:0;border-radius:10px;padding:10px 16px;font-weight:600;text-decoration:none;cursor:pointer}
a.ghost{background:#e6ebf5;color:#0b1730}
input[type=password]{padding:10px 12px;border:1px solid #c8d0e0;border-radius:10px;font-size:15px;width:240px}
.tbl{overflow:auto;max-height:70vh;border:1px solid #e1e6f0;border-radius:10px}
table{border-collapse:collapse;font-size:13px;min-width:1600px}
th,td{padding:8px 10px;border-bottom:1px solid #eef1f6;text-align:left;vertical-align:top;white-space:pre-wrap}
th{position:sticky;top:0;background:#f7f9fc;font-weight:700}
.err{color:#c62828;margin-top:8px}
</style></head><body><div class="wrap">
<?php if (!$authed): ?>
  <div class="card" style="max-width:380px;margin:10vh auto">
    <h1>Заявки AI Drone Championship</h1><p class="muted">Введите пароль администратора</p>
    <form method="post"><input type="password" name="password" autofocus required> <button>Войти</button></form>
    <?php if ($error): ?><div class="err"><?= $h($error) ?></div><?php endif; ?>
  </div>
<?php else: $rows = rows(); $head = array_shift($rows) ?: CSV_HEADER; $rows = array_reverse($rows); ?>
  <h1>Заявки AI Drone Championship 2026</h1>
  <div class="muted">Всего заявок: <b><?= count($rows) ?></b> · прием до <?= $h(date('d.m.Y', strtotime(REG_DEADLINE))) ?></div>
  <div class="bar"><a class="btn" href="?csv=1">Скачать CSV для Excel</a><a class="btn ghost" href="?logout=1">Выйти</a></div>
  <div class="card tbl"><table><thead><tr><?php foreach ($head as $c): ?><th><?= $h($c) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= $h(ltrim($c, "'")) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($head) ?>" class="muted">Пока заявок нет</td></tr><?php endif; ?>
  </tbody></table></div>
<?php endif; ?>
</div></body></html>
