<?php 
session_start();
require_once 'db.php';

// добавляем колонки если их нет
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL");
} catch (\Throwable $e) {}

// функция для уведомлений
function redirectWithToast($url, $msg, $isError = false) {
    $errStr = $isError ? 'true' : 'false';
    $msgJson = json_encode($msg, JSON_UNESCAPED_UNICODE);
    echo "<script>
        localStorage.setItem('flashToast', JSON.stringify({msg: $msgJson, isError: $errStr}));
        window.location.href='$url';
    </script>";
    exit;
}

$act = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // рега нового юзера
    if (isset($_POST['register'])) {
        $sn = trim($_POST['surname']); 
        $n = trim($_POST['name']);
        $pt = trim($_POST['patronymic']); 
        $em = trim($_POST['email']);
        $p_raw = $_POST['password'];
        
        if (strlen($p_raw) < 6) { 
            redirectWithToast('index.php', 'Ошибка: Пароль от 6 символов!', true); 
        }
        
        $p_hash = password_hash($p_raw, PASSWORD_DEFAULT);
        try {
            $st = $pdo->prepare("INSERT INTO users (surname, name, patronymic, email, password) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$sn, $n, $pt, $em, $p_hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            redirectWithToast('cabinet.php', 'Регистрация успешна!', false);
        } catch (PDOException $e) { 
            redirectWithToast('index.php', 'Email уже занят', true); 
        }
    } 
    
    // вход в акк
    elseif (isset($_POST['login'])) {
        $em = $_POST['email']; 
        $pass = $_POST['password'];
        $st = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $st->execute([$em]);
        $u = $st->fetch();
        
        if ($u && password_verify($pass, $u['password'])) {
            $_SESSION['user_id'] = $u['id'];
            // смотрим куда кидать юзера по роли
            $tab = ($u['role'] === 'admin') ? 'admin-appointments' : (($u['role'] === 'psychologist') ? 'psych-appointments' : 'sessions');
            echo "<script>localStorage.setItem('activeCabinetTab', '$tab'); window.location.href='cabinet.php';</script>";
            exit;
        }
        redirectWithToast('index.php', 'Неверный email или пароль.', true);
    }
    
    // если забыли пароль
    elseif (isset($_POST['forgot_password'])) {
        $em = trim($_POST['email'] ?? '');
        $st = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $st->execute([$em]);
        $u = $st->fetch();

        if ($u) {
            $tok = bin2hex(random_bytes(32));
            $exp = date('Y-m-d H:i:s', time() + 3600); 
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")->execute([$tok, $exp, $u['id']]);

            // делаем ссылку для сброса
            $prot = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $h = $_SERVER['HTTP_HOST'];
            $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $link = $prot . "://" . $h . $uri . "/reset_password.php?token=" . $tok;

            $msg = "Здравствуйте, " . htmlspecialchars($u['name']) . "!\n\nДля установки нового пароля перейдите по ссылке:\n" . $link;
            
            if (joySendMail($em, 'Восстановление пароля | J.O.Y.', $msg)) {
                redirectWithToast('index.php', 'Ссылка отправлена на email.');
            } else {
                redirectWithToast('index.php', 'Ошибка отправки почты.', true);
            }
        } else {
            redirectWithToast('index.php', 'Email не найден.', true);
        }
    }
}

// выход
if ($act === 'logout') { session_destroy(); header("Location: index.php"); exit; }