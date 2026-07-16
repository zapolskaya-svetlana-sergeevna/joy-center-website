<?php 
session_start();
require_once 'db.php';

// функция чтоб тосты выводить через локалсторадж
function redirectWithToast($url, $msg, $isError = false) {
    $errStr = $isError ? 'true' : 'false';
    echo "<script>localStorage.setItem('flashToast', JSON.stringify({msg: '$msg', isError: $errStr})); window.location.href='$url';</script>";
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// проверка на админа
if (!$user || $user['role'] !== 'admin') { 
header("Location: index.php"); 
exit; 
}

// удаление если нажали кнопку
if (isset($_GET['delete_spec'])) {
    $pdo->prepare("DELETE FROM specialists WHERE id=?")->execute([$_GET['delete_spec']]);
    redirectWithToast('cabinet.php', 'Психолог успешно удален из базы.', false);
}

// сохранение или апдейт
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_specialist'])) {
    $specId = $_POST['spec_id'];
    $fName = trim($_POST['first_name']);
    $lName = trim($_POST['last_name']);
    $spec = trim($_POST['specialization']);
    $exp = (int)$_POST['experience_years'];
    $edu = trim($_POST['education']);
    $desc = trim($_POST['description']);
    $sch = trim($_POST['work_schedule'] ?? '');
    
    // доп поля
    $dirs = trim($_POST['directions'] ?? '');
    $b1t = trim($_POST['block1_title'] ?? '');
    $b1d = trim($_POST['block1_text'] ?? '');
    $b2t = trim($_POST['block2_title'] ?? '');
    $b2d = trim($_POST['block2_text'] ?? '');
    
    $imgPath = $_POST['existing_image'] ?? 'img/default-doc.png';
    // проверка картинки
    if (!empty($_FILES['image']['name'])) {
        $target = "img/" . basename($_FILES['image']['name']);
        if(move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imgPath = $target;
        }
    }

    try {
        if (empty($specId)) {
            // тут создаем нового если айдишника нет
            $email = trim($_POST['new_email']);
            $pass = trim($_POST['new_password']);
            
            if (empty($email) || empty($pass)) {
                redirectWithToast('cabinet.php', 'Ошибка: Укажите email и пароль для нового психолога!', true);
            }
            
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                redirectWithToast('cabinet.php', 'Ошибка: Этот Email уже занят!', true);
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, surname, email, password, role) VALUES (?, ?, ?, ?, 'psychologist')")
            ->execute([$fName, $lName, $email, $hash]);
            
            $uId = $pdo->lastInsertId(); 
            
            $pdo->prepare("INSERT INTO specialists (user_id, first_name, last_name, specialization, experience_years, education, description, photo, work_schedule, directions, block1_title, block1_text, block2_title, block2_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$uId, $fName, $lName, $spec, $exp, $edu, $desc, $imgPath, $sch, $dirs, $b1t, $b1d, $b2t, $b2d]);
                
        } else {
            // обновление старого
            $pdo->prepare("UPDATE specialists SET first_name=?, last_name=?, specialization=?, experience_years=?, education=?, description=?, photo=?, work_schedule=?, directions=?, block1_title=?, block1_text=?, block2_title=?, block2_text=? WHERE id=?")
            ->execute([$fName, $lName, $spec, $exp, $edu, $desc, $imgPath, $sch, $dirs, $b1t, $b1d, $b2t, $b2d, $specId]);
                
            $stmtU = $pdo->prepare("SELECT user_id FROM specialists WHERE id=?");
            $stmtU->execute([$specId]);
            $uId = $stmtU->fetchColumn();
            
            if ($uId) {
                $pdo->prepare("UPDATE users SET name=?, surname=? WHERE id=?")->execute([$fName, $lName, $uId]);
            }
        }
        redirectWithToast('cabinet.php', 'Анкета психолога успешно сохранена!', false);
    } catch (Exception $e) {
        redirectWithToast('cabinet.php', 'Ошибка базы данных: ' . $e->getMessage(), true);
    }
} else { 
    header("Location: cabinet.php"); 
}
?>