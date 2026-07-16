<?php 
require_once 'db.php';

// провер бд 
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL");
} catch (\Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL");
} catch (\Throwable $e) {}

$tok = trim($_GET['token'] ?? $_POST['token'] ?? '');
$err = '';
$ok = false;

// если форму отправ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $tok = trim($_POST['token'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    if (strlen($pass) < 6) {
        $err = 'Пароль должен быть не менее 6 символов.';
    } elseif ($pass !== $pass2) {
        $err = 'Пароли не совпадают.';
    } elseif ($tok === '') {
        $err = 'Недействительная ссылка.';
    } else {
        // провер жив ли токен
        $st = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $st->execute([$tok]);
        $uid = $st->fetchColumn();
        
        if (!$uid) {
            $err = 'Ссылка устарела. Запросите заново.';
        } else {
            $h = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")->execute([$h, $uid]);
            $ok = true;
        }
    }
}

$isValid = false;
if (!$ok && $tok !== '') {
    $st = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $st->execute([$tok]);
    $isValid = (bool)$st->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Новый пароль | J.O.Y.</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="joy.css?v=<?= time() ?>">
</head>
<body class="auth-reset-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card-panel p-4">
                <h3 class="text-center mb-4" style="font-family:'Tenor Sans';">Новый пароль</h3>
                <?php if ($ok): ?>
                    <p class="text-center mb-4">Пароль изменён. Войдите в кабинет.</p>
                    <a href="index.php" class="main-button w-100 d-block text-center" onclick="localStorage.setItem('openAuth','login');">Войти</a>
                <?php elseif ($tok === '' || !$isValid): ?>
                    <p class="text-danger text-center mb-4"><?= $err ?: 'Ссылка не работает.' ?></p>
                    <a href="index.php" class="main-button w-100 d-block text-center" onclick="localStorage.setItem('openAuth','forgot');">Запросить снова</a>
                <?php else: ?>
                    <?php if ($err): ?><p class="text-danger small mb-3"><?= $err ?></p><?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($tok) ?>">
                        <div class="mb-3">
                            <input type="password" name="password" class="form-control joy-input" placeholder="Пароль (от 6 симв)" required>
                        </div>
                        <div class="mb-4">
                            <input type="password" name="password_confirm" class="form-control joy-input" placeholder="Повторите пароль" required>
                        </div>
                        <button type="submit" name="reset_password" class="main-button w-100">Сохранить</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>