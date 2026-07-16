<?php 
session_start();
require_once 'db.php';

// функция для тостов в локалсторадж
function redirectWithToast($msg, $isError = false) {
    $err = $isError ? 'true' : 'false';
    echo "<script>
        localStorage.setItem('flashToast', JSON.stringify({msg: '$msg', isError: $err}));
        window.location.href = document.referrer ? document.referrer : 'index.php';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    
    $sid = (int)$_POST['specialist_id'];
    $r = (int)$_POST['rating'];
    $txt = trim($_POST['review_text']);
    $uid = $_SESSION['user_id'];

    // проверк рейта
    if ($r < 1) $r = 1;
    if ($r > 5) $r = 5;

    // проверка была ли сессия 
    $chk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND specialist_id = ? AND status = 'completed'");
    $chk->execute([$uid, $sid]);
    
    if ($chk->fetchColumn() == 0) {
        redirectWithToast('Ошибка: Вы не можете оставить отзыв, так как у вас не было сессий с этим психологом.', true);
    }

    if (!empty($txt)) {
        try {
            // статус пендинг для админ проверки
            $st = $pdo->prepare("INSERT INTO reviews (specialist_id, user_id, rating, review_text, status) VALUES (?, ?, ?, ?, 'pending')");
            $st->execute([$sid, $uid, $r, $txt]);
            redirectWithToast('Спасибо! Ваш отзыв отправлен и появится после проверки.', false);
        } catch (PDOException $e) {
            redirectWithToast('Ошибка при сохранении отзыва.', true);
        }
    } else {
        redirectWithToast('Ошибка: Пожалуйста, напишите текст отзыва.', true);
    }
} else {
    redirectWithToast('Оставлять отзывы могут только авторизованные клиенты.', true);
}
?>