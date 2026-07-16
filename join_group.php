<?php 
session_start();
require_once 'db.php';

// редирект с уведомл через локал сторадж
function redirectWithToast($msg, $isError = false) {
    $errStr = $isError ? 'true' : 'false';
    echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: '$msg', isError: $errStr})); window.location.href = document.referrer;</script>";
    exit;
}

if (!isset($_SESSION['user_id'])) {
    redirectWithToast('Для записи в группу необходимо войти в аккаунт.', true);
}

// если пришел запрос на запись
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $gid = (int)$_POST['group_id'];
    $uid = $_SESSION['user_id'];

    // проверка чтоб два раза не запис
    $chk = $pdo->prepare("SELECT id FROM group_participants WHERE group_id=? AND user_id=?");
    $chk->execute([$gid, $uid]);
    if ($chk->fetch()) {
        redirectWithToast('Вы уже находитесь в списке участников этой группы.', true);
    }

    // сколько мест всего
    $g = $pdo->prepare("SELECT max_seats FROM therapy_groups WHERE id=?");
    $g->execute([$gid]);
    $mSeats = $g->fetchColumn();

    // сколько сейчас чел
    $st = $pdo->prepare("SELECT COUNT(*) FROM group_participants WHERE group_id=? AND status='active'");
    $st->execute([$gid]);
    $occ = $st->fetchColumn();

    // если места нет то в очередь
    $stat = ($occ >= $mSeats) ? 'waitlist' : 'active';

    $pdo->prepare("INSERT INTO group_participants (group_id, user_id, status) VALUES (?, ?, ?)")
        ->execute([$gid, $uid, $stat]);

    if ($stat === 'waitlist') {
        redirectWithToast('Мест не осталось. Вы успешно добавлены в лист ожидания!', false);
    } else {
        redirectWithToast('Вы успешно записаны на воркшоп!', false);
    }
} else {
    header("Location: index.php");
}
?>