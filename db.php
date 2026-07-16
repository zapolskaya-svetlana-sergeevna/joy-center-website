<?php 
date_default_timezone_set('Europe/Minsk');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// коннект к базе
$h = 'sql305.infinityfree.com'; 
$db = 'if0_42064541_joy_db';
$u = 'if0_42064541'; 
$p = 'barashka666'; 

$dsn = "mysql:host=$h;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $u, $p, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\PDOException $e) { 
    die("Ошибка подключения к БД: " . $e->getMessage()); 
}

// оформ письма
function joyBuildEmailHtml($txt) {
    return "
    <div style='background-color: #fdfbf9; padding: 30px; font-family: Arial, sans-serif;'>
        <div style='max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 20px; border: 2px solid #E0C6AD; overflow: hidden;'>
            <div style='background: #E0C6AD; padding: 20px; text-align: center;'> <h1 style='color: white; margin: 0;'>J.O.Y. Center</h1> </div>
            <div style='padding: 30px; color: #3D3935; line-height: 1.6; font-size: 16px;'> " . nl2br($txt) . " </div>
            <div style='background: #fff8f3; padding: 20px; text-align: center; border-top: 1px solid #E0C6AD;'> <p style='margin: 0; font-size: 14px; font-weight: bold;'>Центр психологии J.O.Y.</p> </div>
        </div>
    </div>";
}

// отправка почты через пхпмайлер
function joySendMail($to, $sub, $cnt) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: J.O.Y. Center <zapolskaaveta@gmail.com>' . "\r\n";

    if (strpos($cnt, '<div') === false) { 
        $message = joyBuildEmailHtml($cnt); 
    } else { 
        $message = $cnt; 
    }
    return mail($to, $sub, $message, $headers);
}


function joyCabinetRedirect($msg = null, $isError = false) {
    if ($msg !== null) {
        $err = $isError ? 'true' : 'false';
        $msgJson = json_encode($msg, JSON_UNESCAPED_UNICODE);
        echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: $msgJson, isError: $err}));</script>";
    }
    $referer = $_SERVER['HTTP_REFERER'] ?? 'cabinet.php';

    $url_components = parse_url($referer);
    parse_str($url_components['query'] ?? '', $params);
    $tab = $_POST['_cabinet_tab'] ?? $params['tab'] ?? 'profile';

    echo "<script>
        localStorage.setItem('activeCabinetTab', '$tab');
        window.location.href = 'cabinet.php?tab=$tab';
    </script>";
    exit;
}

// хелперы разные
function getCurrentUser($pdo) { 
    if (isset($_SESSION['user_id'])) { 
        $st = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
        $st->execute([$_SESSION['user_id']]); 
        return $st->fetch(); 
    } 
    return null; 
}

function getSettings($pdo) { 
    try { 
        $st = $pdo->query("SELECT * FROM settings LIMIT 1"); 
        return $st->fetch() ?: ['phone'=>'','email'=>'','address'=>'','telegram'=>'','instagram'=>'','work_hours'=>'']; 
    } catch (Exception $e) { 
        return ['phone'=>'','email'=>'','address'=>'','telegram'=>'','instagram'=>'','work_hours'=>'']; 
    } 
}

function joyViberUrl($p) { 
    $d = preg_replace('/\D+/', '', (string)$p); 
    return $d ? 'viber://chat?number=' . $d : '#'; 
}

function joyTelegramUrl($t) { 
    $h = ltrim(trim((string)$t), '@'); 
    return $h ? 'https://t.me/' . rawurlencode($h) : '#'; 
}

function joyParseAppointmentDisplay($top, $serv) { 
    $isCall = (strpos((string)$top, 'Обратная связь') !== false); 
    $lab = trim(preg_replace('/^\[.*?\]\s*/', '', (string)$top)); 
    if ($isCall) return ['title' => 'Обратная связь', 'tag' => '', 'isCallback' => true]; 
    return ['title' => $serv ?: 'Индивидуальная сессия', 'tag' => $lab ?: 'Без темы', 'isCallback' => false]; 
}

// синхрон расписания если поменяли дату
function joySyncAppointmentSchedule($pdo, $aid, $dt) { 
    $st = $pdo->prepare("SELECT specialist_id FROM appointments WHERE id = ?"); 
    $st->execute([$aid]); 
    $sid = (int)$st->fetchColumn(); 
    if ($sid <= 0) return; 
    
    $stime = str_replace('T', ' ', trim($dt)); 
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $stime)) $stime .= ':00'; 
    
    $pdo->prepare("UPDATE schedule SET is_booked = 0, appointment_id = NULL WHERE appointment_id = ?")->execute([$aid]); 
    $chk = $pdo->prepare("SELECT id FROM schedule WHERE specialist_id = ? AND slot_datetime = ?"); 
    $chk->execute([$sid, $stime]); 
    $exId = $chk->fetchColumn(); 
    
    if ($exId) { $pdo->prepare("UPDATE schedule SET is_booked = 1, appointment_id = ? WHERE id = ?")->execute([$aid, $exId]); } 
    else { $pdo->prepare("INSERT INTO schedule (specialist_id, slot_datetime, is_booked, appointment_id) VALUES (?, ?, 1, ?)")->execute([$sid, $stime, $aid]); } 
}