<?php 
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = isset($_POST['specialist_id']) ? (int)$_POST['specialist_id'] : null;
    $slid = !empty($_POST['slot_id']) ? (int)$_POST['slot_id'] : null;

    $srv = trim($_POST['service_type'] ?? 'Очная индивидуальная сессия');
    $r_top = trim($_POST['topic'] ?? 'Общий вопрос');

    // если обратная связь то меняем тему
    if (strpos($r_top, 'Обратная связь') !== false) {
        $top = 'Обратная связь';
        $srv = 'Обратная связь';
    } else {
        $top = "[$srv] " . $r_top;
    }
   
    $txt = trim($_POST['request'] ?? '');
    $con = 'telegram'; // по дефолту тг
    $ph = trim($_POST['phone'] ?? '');
    $nm = trim($_POST['name'] ?? 'Аноним');
    
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    try {
        $pdo->beginTransaction();
        $sql = "INSERT INTO appointments (user_id, specialist_id, guest_name, guest_phone, topic, request_text, contact_method, service_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $st = $pdo->prepare($sql);
        $st->execute([$uid, $sid, $nm, $ph, $top, $txt, $con, $srv]);
        $new_id = $pdo->lastInsertId();

        // если юзер выбрал время то броним слот
        if ($slid) {
            $check = $pdo->prepare("SELECT slot_datetime FROM schedule WHERE id = ? AND is_booked = 0");
            $check->execute([$slid]);
            $sData = $check->fetch();

            if ($sData) {
                $upd1 = $pdo->prepare("UPDATE schedule SET is_booked = 1, appointment_id = ? WHERE id = ?");
                $upd1->execute([$new_id, $slid]);
                $upd2 = $pdo->prepare("UPDATE appointments SET appointment_time = ? WHERE id = ?");
                $upd2->execute([$sData['slot_datetime'], $new_id]);
            }
        }
        $pdo->commit();
        
        // обратно откуда пришли с флагом гуд
        $ref = $_SERVER['HTTP_REFERER'] ?? 'index.php';
        $ref .= (parse_url($ref, PHP_URL_QUERY) ? '&' : '?') . 'appoint_success=1';
        header("Location: $ref");
        exit;
              
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("ошибка сохранения заявки: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
?>