<?php 
session_start();
require_once 'db.php';

// проверяем что админ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $u = getCurrentUser($pdo);
    if ($u['role'] !== 'admin') die("Доступ запрещен");

    $tpl = trim($_POST['message']);
    $cls = $_POST['clients'] ?? []; 
    $s = getSettings($pdo);

    if (empty($cls) || empty($tpl)) {
        echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: 'Ошибка: выберите клиентов!', isError: true})); window.location.href = 'cabinet.php';</script>";
        exit;
    }

$cnt = 0;
    foreach ($cls as $v) {
        $em = null; $name = ''; $date = ''; $time = ''; $room = ''; $spec = ''; $topic = '';
        $addr = $s['address'] ?? 'Минск, Победителей 11';

        if (strpos($v, 'app_') === 0) {
            $aid = str_replace('app_', '', $v);
            $st = $pdo->prepare("SELECT a.*, u.email, s.first_name, s.last_name FROM appointments a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN specialists s ON a.specialist_id = s.id WHERE a.id = ?");
            $st->execute([$aid]);
            $d = $st->fetch();
            if($d) {
                $em = $d['email']; $name = $d['guest_name'] ?: 'Клиент'; 
                $date = !empty($d['appointment_time']) ? date('d.m.Y', strtotime($d['appointment_time'])) : '—';
                $time = !empty($d['appointment_time']) ? date('H:i', strtotime($d['appointment_time'])) : '—';
                $room = $d['room_id'] ?: 'Кабинет уточняется';
                $spec = $d['first_name'] ? $d['first_name'].' '.$d['last_name'] : 'Психолог центра';
                $topic = $d['service_type'] ?: 'Индивидуальная сессия';
            }
        } elseif (strpos($v, 'grp_') === 0 || strpos($v, 'wait_') === 0) {
            $pid = str_replace(['grp_', 'wait_'], '', $v);
            $st = $pdo->prepare("SELECT p.*, u.name as guest_name, u.email, g.title, g.event_date, s.first_name, s.last_name FROM group_participants p JOIN users u ON p.user_id = u.id JOIN therapy_groups g ON p.group_id = g.id LEFT JOIN specialists s ON g.spec_id = s.id WHERE p.id = ?");
            $st->execute([$pid]);
            $d = $st->fetch();
            if($d) {
                $em = $d['email']; $name = $d['guest_name']; 
                $date = !empty($d['event_date']) ? date('d.m.Y', strtotime($d['event_date'])) : '—';
                $time = !empty($d['event_date']) ? date('H:i', strtotime($d['event_date'])) : '—';
                $room = 'Групповой зал'; $spec = ($d['first_name'] ?? '').' '.($d['last_name'] ?? '');
                $topic = 'Групповая терапия: ' . $d['title'];
            }
        } elseif (strpos($v, 'order_') === 0) {
            $uid = str_replace('order_', '', $v);
            $st = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $st->execute([$uid]);
            $d = $st->fetch();
            if($d) {
                $em = $d['email']; $name = $d['name'];
                $topic = 'Онлайн-материалы';
            }
        }

        if (!empty($em)) {
            $m = str_replace(['{имя}', '{дата}', '{время}', '{кабинет}', '{специалист}', '{услуга}', '{адрес}'], [$name, $date, $time, $room, $spec, $topic, $addr], $tpl);
            if (joySendMail($em, 'Уведомление от J.O.Y. Center', $m)) {
                $cnt++;
            }
        }
    }

    echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: 'Успешно отправлено: $cnt', isError: false})); window.location.href = 'cabinet.php';</script>";
    exit;
}
?>