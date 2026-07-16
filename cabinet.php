<?php 
require_once 'db.php';
$hasServiceCol = true; $hasDeletedCol = true; $hasStatusCol = true; 
$hasLinkCol = true; $hasScheduleCol = true; $hasRoomCol = true; $hasNotesCol = true;
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$user = getCurrentUser($pdo);
if (!$user) { header("Location: index.php"); exit; }

// миграции бд
try { $pdo->exec("ALTER TABLE appointments ADD COLUMN service_type VARCHAR(100) DEFAULT 'Очная индивидуальная сессия'"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE appointments ADD COLUMN is_deleted TINYINT(1) DEFAULT 0"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE posts ADD COLUMN status VARCHAR(20) DEFAULT 'published'"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE products ADD COLUMN access_link VARCHAR(500) DEFAULT ''"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE specialists ADD COLUMN work_schedule VARCHAR(255) DEFAULT ''"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE appointments ADD COLUMN room_id VARCHAR(50) DEFAULT ''"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE schedule ADD COLUMN notes VARCHAR(255) DEFAULT ''"); } catch (\Throwable $e) {} 
try { $pdo->exec("ALTER TABLE faq ADD COLUMN status VARCHAR(20) DEFAULT 'published'"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT ''"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(20) DEFAULT 'pending'"); } catch (\Throwable $e) {}
try { $pdo->exec("UPDATE orders SET status = 'completed' WHERE status IS NULL"); } catch (\Throwable $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS therapy_groups (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), description TEXT, event_date DATETIME, max_seats INT, spec_id INT, room_id VARCHAR(50) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_participants (id INT AUTO_INCREMENT PRIMARY KEY, group_id INT, user_id INT, status VARCHAR(20) DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
} catch(Exception $e) {}

// обраб форм
if ($user['role'] === 'psychologist') {
    if (isset($_POST['update_psych_schedule'])) {
        $specId = $_POST['spec_id'];
        $schedule = trim($_POST['work_schedule'] ?? '');
        $pdo->prepare("UPDATE specialists SET work_schedule=? WHERE id=?")->execute([$schedule, $specId]);
        joyCabinetRedirect('График работы успешно обновлен.');
    }
    if (isset($_POST['update_specialist_profile'])) {
        $specId = $_POST['spec_id']; $firstName = trim($_POST['first_name']); 
        $patronymic = trim($_POST['patronymic'] ?? ''); $lastName = trim($_POST['last_name']); 
        $spec = trim($_POST['specialization']); $exp = (int)$_POST['experience_years']; 
        $edu = trim($_POST['education']); $desc = trim($_POST['description']);
        $imagePath = $_POST['existing_image'] ?? 'img/default-doc.png';
        $directions = trim($_POST['directions'] ?? '');
        $b1t = trim($_POST['block1_title'] ?? ''); $b1d = trim($_POST['block1_text'] ?? '');
        $b2t = trim($_POST['block2_title'] ?? ''); $b2d = trim($_POST['block2_text'] ?? '');
        if (!empty($_FILES['image']['name'])) { 
            $target = "img/" . basename($_FILES['image']['name']); 
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target)) $imagePath = $target; 
        }
        $pdo->prepare("UPDATE specialists SET first_name=?, patronymic=?, last_name=?, specialization=?, experience_years=?, education=?, description=?, photo=?, directions=?, block1_title=?, block1_text=?, block2_title=?, block2_text=? WHERE id=?")
    ->execute([$firstName, $patronymic, $lastName, $spec, $exp, $edu, $desc, $imagePath, $directions, $b1t, $b1d, $b2t, $b2d, $specId]);
        joyCabinetRedirect('Профиль успешно сохранен.');
    }
}

if (isset($_POST['add_slot']) || isset($_POST['admin_add_slot'])) {
    $specId = $_POST['spec_id'] ?? $_POST['admin_add_slot_spec'];
    $slotTime = $_POST['slot_time'];
    $check = $pdo->prepare("SELECT id FROM schedule WHERE specialist_id = ? AND slot_datetime = ?"); 
    $check->execute([$specId, $slotTime]);
    if ($check->rowCount() == 0) {
        $pdo->prepare("INSERT INTO schedule (specialist_id, slot_datetime, is_booked) VALUES (?, ?, 0)")->execute([$specId, $slotTime]);
    }
    joyCabinetRedirect('Окно для записи успешно открыто!');
}

if (isset($_POST['notify_waitlist'])) {
    $groupId = (int)$_POST['group_id'];
    $grp = $pdo->query("SELECT title FROM therapy_groups WHERE id = $groupId")->fetch();
    $waitlist = $pdo->query("SELECT u.email, u.name FROM group_participants p JOIN users u ON p.user_id = u.id WHERE p.group_id = $groupId AND p.status = 'waitlist'")->fetchAll();
    
    foreach($waitlist as $w) {
        if (!empty($w['email'])) {
            $msg = "Здравствуйте, {$w['name']}! К сожалению, на групповую терапию «{$grp['title']}» так и не освободилось мест.";
            joySendMail($w['email'], 'Уведомление листа ожидания | J.O.Y.', $msg);
        }
    }
    joyCabinetRedirect('Извинения успешно отправлены списку ожидания.');
}

if (isset($_GET['cancel_group_app'])) {
    $groupId = (int)$_GET['cancel_group_app']; $userId = $user['id'];
    if ($groupId > 0 && $user['role'] === 'client') {
        $stmt = $pdo->prepare("SELECT id, status FROM group_participants WHERE group_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $userId]); $part = $stmt->fetch();
        if ($part) {
            $pdo->prepare("DELETE FROM group_participants WHERE id = ?")->execute([$part['id']]);
            if ($part['status'] === 'active') {
                $stmtWait = $pdo->prepare("SELECT id FROM group_participants WHERE group_id = ? AND status = 'waitlist' ORDER BY created_at ASC LIMIT 1");
                $stmtWait->execute([$groupId]); $nextInLine = $stmtWait->fetch();
                if ($nextInLine) { $pdo->prepare("UPDATE group_participants SET status = 'active' WHERE id = ?")->execute([$nextInLine['id']]); }
            }
            joyCabinetRedirect('Вы успешно отменили запись.');
        }
    }
}

$clientOrdersCount = 0;
if ($user['role'] === 'client') {
    $stmtOrd = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmtOrd->execute([$user['id']]); $clientOrdersCount = $stmtOrd->fetchColumn();
}

function getStatusBadge($status) {
    switch($status) {
        case 'new': return '<span class="badge badge-status-new">Новая</span>';
        case 'confirmed': return '<span class="badge badge-status-confirmed">Подтверждена</span>';
        case 'completed': return '<span class="badge badge-status-completed">Завершена</span>';
        case 'canceled': return '<span class="badge badge-status-canceled">Отменена</span>';
        default: return '<span class="badge bg-light text-muted border">Неизвестно</span>';
    }
}

if (isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkEmail->execute([$email, $user['id']]);
    if ($checkEmail->fetch()) { joyCabinetRedirect('Ошибка: Этот Email уже используется!', true); }
    
    if ($user['role'] === 'admin') {
        $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$_POST['name'], $email, $user['id']]);
    } else {
        $pdo->prepare("UPDATE users SET surname=?, name=?, patronymic=?, email=? WHERE id=?")
            ->execute([$_POST['surname'], $_POST['name'], $_POST['patronymic'], $email, $user['id']]);
    }
    joyCabinetRedirect('Данные профиля успешно обновлены.');
}

if (isset($_POST['change_password'])) {
    $oldPass = $_POST['old_password']; $newPass = $_POST['new_password'];
    if (strlen($newPass) < 6) { joyCabinetRedirect('Ошибка: Новый пароль должен быть не менее 6 символов!', true); }
    if (password_verify($oldPass, $user['password'])) {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $user['id']]);
        joyCabinetRedirect('Пароль успешно изменен.');
    } else { joyCabinetRedirect('Текущий пароль введен неверно!', true); }
}

if (isset($_GET['reset_password']) && $user['role'] === 'admin') {
    $resetId = (int)$_GET['reset_password'];
    $newPass = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8); 
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $resetId]);
    echo "<script>alert('Новый пароль для пользователя ID $resetId: $newPass'); window.location.href='cabinet.php';</script>"; exit;
}

if (isset($_GET['cancel_app']) && $user['role'] === 'client') {
    $appId = $_GET['cancel_app'];
    $pdo->prepare("DELETE FROM schedule WHERE appointment_id = ?")->execute([$appId]);
    $pdo->prepare("UPDATE appointments SET status='canceled', appointment_time = NULL WHERE id = ? AND user_id = ?")->execute([$appId, $user['id']]);
    joyCabinetRedirect('Запись отменена.');
}

if (isset($_POST['change_app_status']) && ($user['role'] === 'admin' || $user['role'] === 'psychologist')) {
    $appId = (int)$_POST['app_id']; $newStatus = $_POST['new_status'] ?? 'confirmed'; $roomId = $_POST['room_id'] ?? '';
    if (isset($_POST['new_time']) && !empty($_POST['new_time'])) {
        $pdo->prepare("UPDATE appointments SET appointment_time=? WHERE id=?")->execute([$_POST['new_time'], $appId]);
        joySyncAppointmentSchedule($pdo, $appId, $_POST['new_time']);
    }
    $pdo->prepare("UPDATE appointments SET status=?, room_id=? WHERE id=?")->execute([$newStatus, $roomId, $appId]);
    if ($newStatus === 'canceled' || $newStatus === 'completed') {
        $pdo->prepare("DELETE FROM schedule WHERE appointment_id = ?")->execute([$appId]);
        if ($newStatus === 'canceled') {
            $pdo->prepare("UPDATE appointments SET appointment_time = NULL WHERE id = ?")->execute([$appId]);
        }
    }
    joyCabinetRedirect('Данные визита обновлены.');
}

if (isset($_GET['reject_app']) && ($user['role'] === 'admin' || $user['role'] === 'psychologist')) {
    $appId = (int)$_GET['reject_app'];
    $allowed = false;
    if ($user['role'] === 'admin') {
        $allowed = true;
    } elseif ($user['role'] === 'psychologist') {
        $stmtSpec = $pdo->prepare("SELECT id FROM specialists WHERE user_id = ?");
        $stmtSpec->execute([$user['id']]);
        if ($specRow = $stmtSpec->fetch()) {
            $chk = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND specialist_id = ?");
            $chk->execute([$appId, $specRow['id']]);
            $allowed = (bool)$chk->fetch();
        }
    }
    if ($allowed) {
        $pdo->prepare("DELETE FROM schedule WHERE appointment_id = ?")->execute([$appId]);
        $pdo->prepare("UPDATE appointments SET status='canceled', appointment_time = NULL WHERE id = ?")->execute([$appId]);
        joyCabinetRedirect('Заявка отклонена.');
    }
}

if (isset($_POST['save_post'])) {
    $title = $_POST['title']; $short_desc = $_POST['short_desc']; $content = $_POST['content']; $id = $_POST['post_id'];
    $imagePath = $_POST['existing_image'] ?? 'img/Frame.png';
    if (!empty($_FILES['image']['name'])) { $target = "img/" . basename($_FILES['image']['name']); if(move_uploaded_file($_FILES['image']['tmp_name'], $target)) $imagePath = $target; }
    if ($user['role'] === 'psychologist') { 
        $getSpec = $pdo->prepare("SELECT id FROM specialists WHERE user_id = ?"); $getSpec->execute([$user['id']]); 
        $author_id = $getSpec->fetchColumn(); $status = 'published';
    } else { $author_id = !empty($_POST['author_id']) ? (int)$_POST['author_id'] : null; $status = 'published'; }
    if ($id) { $pdo->prepare("UPDATE posts SET title=?, short_desc=?, content=?, image=?, author_id=?, status=? WHERE id=?")->execute([$title, $short_desc, $content, $imagePath, $author_id, $status, $id]);
    } else { $pdo->prepare("INSERT INTO posts (title, short_desc, content, image, author_id, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$title, $short_desc, $content, $imagePath, $author_id, $status]); }
    joyCabinetRedirect('Публикация сохранена.');
}

if (isset($_POST['save_group'])) {
    $title = $_POST['title']; $desc = $_POST['description']; $date = $_POST['event_date']; $seats = (int)$_POST['max_seats']; $id = $_POST['group_id']; $roomId = $_POST['room_id'] ?? '';
    if ($user['role'] === 'psychologist') {
        $getSpec = $pdo->prepare("SELECT id FROM specialists WHERE user_id = ?"); $getSpec->execute([$user['id']]); $spec_id = $getSpec->fetchColumn();
    } else { $spec_id = !empty($_POST['spec_id']) ? (int)$_POST['spec_id'] : null; }
    if ($id) { $pdo->prepare("UPDATE therapy_groups SET title=?, description=?, event_date=?, max_seats=?, spec_id=?, room_id=? WHERE id=?")->execute([$title, $desc, $date, $seats, $spec_id, $roomId, $id]);
    } else { $pdo->prepare("INSERT INTO therapy_groups (title, description, event_date, max_seats, spec_id, room_id) VALUES (?, ?, ?, ?, ?, ?)")->execute([$title, $desc, $date, $seats, $spec_id, $roomId]); }
    joyCabinetRedirect('Группа успешно сохранена.');
}

if (isset($_GET['delete_group'])) {
    $groupId = (int)$_GET['delete_group']; $pdo->prepare("DELETE FROM group_participants WHERE group_id=?")->execute([$groupId]); $pdo->prepare("DELETE FROM therapy_groups WHERE id=?")->execute([$groupId]);
    joyCabinetRedirect('Группа удалена.');
}

if (isset($_GET['remove_participant'])) {
    $partId = (int)$_GET['remove_participant']; $stmt = $pdo->prepare("SELECT group_id, status FROM group_participants WHERE id=?");
    $stmt->execute([$partId]); $part = $stmt->fetch();
    if ($part) {
        $pdo->prepare("DELETE FROM group_participants WHERE id=?")->execute([$partId]);
        if ($part['status'] === 'active') {
            $stmtWait = $pdo->prepare("SELECT id FROM group_participants WHERE group_id = ? AND status = 'waitlist' ORDER BY created_at ASC LIMIT 1");
            $stmtWait->execute([$part['group_id']]); $nextInLine = $stmtWait->fetch();
            if ($nextInLine) { $pdo->prepare("UPDATE group_participants SET status = 'active' WHERE id = ?")->execute([$nextInLine['id']]); }
        }
    }
    joyCabinetRedirect('Участник исключен.');
}

if ($user['role'] === 'psychologist') {
    if (isset($_GET['delete_slot'])) {
        $stmtSpec = $pdo->prepare("SELECT id FROM specialists WHERE user_id = ?"); $stmtSpec->execute([$user['id']]);
        if ($specData = $stmtSpec->fetch()) {
            $slotId = $_GET['delete_slot'];
            $checkSlot = $pdo->prepare("SELECT appointment_id FROM schedule WHERE id = ? AND specialist_id = ?"); $checkSlot->execute([$slotId, $specData['id']]);
            if ($slot = $checkSlot->fetch()) {
                if (!empty($slot['appointment_id'])) $pdo->prepare("UPDATE appointments SET status='canceled', appointment_time=NULL WHERE id=?")->execute([$slot['appointment_id']]);
                $pdo->prepare("DELETE FROM schedule WHERE id = ? AND specialist_id = ?")->execute([$slotId, $specData['id']]);
            }
        }
        joyCabinetRedirect('Слот удален.');
    }
    if (isset($_POST['psych_cal_action'])) {
        $stmtSpec = $pdo->prepare("SELECT id FROM specialists WHERE user_id = ?");
        $stmtSpec->execute([$user['id']]);
        $specData = $stmtSpec->fetch();
        if (!$specData) { joyCabinetRedirect('Специалист не найден.', true); }
        $specId = (int)$specData['id'];
        $action = $_POST['action'] ?? '';
        $slotTime = str_replace('T', ' ', trim($_POST['slot_datetime'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $slotTime)) { $slotTime .= ':00'; }
        if (!$slotTime || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $slotTime)) {
            joyCabinetRedirect('Некорректное время.', true);
        }
        $_POST['_cabinet_tab'] = 'psych-appointments';
        $_POST['_cabinet_inner_id'] = 'psychAppTabs';
        $_POST['_cabinet_inner'] = '1';

        if ($action === 'open') {
            $check = $pdo->prepare("SELECT id FROM schedule WHERE specialist_id = ? AND slot_datetime = ?");
            $check->execute([$specId, $slotTime]);
            if ($check->rowCount() == 0) {
                $pdo->prepare("INSERT INTO schedule (specialist_id, slot_datetime, is_booked) VALUES (?, ?, 0)")->execute([$specId, $slotTime]);
            }
            joyCabinetRedirect('Окно открыто.');
        }
        if ($action === 'free') {
            $appStmt = $pdo->prepare("SELECT id, status, guest_name, topic FROM appointments WHERE specialist_id = ? AND appointment_time = ? AND is_deleted = 0");
            $appStmt->execute([$specId, $slotTime]);
            while ($app = $appStmt->fetch()) {
                $isWalkin = ($app['guest_name'] === 'Запись на месте')
                    || (strpos((string)($app['topic'] ?? ''), 'Запись на месте') !== false);
                if ($isWalkin) {
                    $pdo->prepare("UPDATE appointments SET is_deleted=1, appointment_time=NULL, status='canceled' WHERE id=?")->execute([$app['id']]);
                } elseif ($app['status'] === 'completed') {
                    $pdo->prepare("UPDATE appointments SET appointment_time=NULL WHERE id=?")->execute([$app['id']]);
                } else {
                    $pdo->prepare("UPDATE appointments SET status='canceled', appointment_time=NULL WHERE id=?")->execute([$app['id']]);
                }
                $pdo->prepare("DELETE FROM schedule WHERE appointment_id = ?")->execute([$app['id']]);
            }
            $pdo->prepare("DELETE FROM schedule WHERE specialist_id = ? AND slot_datetime = ?")->execute([$specId, $slotTime]);
            joyCabinetRedirect('Ячейка свободна.');
        }
        if ($action === 'walkin') {
            $check = $pdo->prepare("SELECT id FROM schedule WHERE specialist_id = ? AND slot_datetime = ?");
            $check->execute([$specId, $slotTime]);
            $slotId = $check->fetchColumn();
            if (!$slotId) {
                $pdo->prepare("INSERT INTO schedule (specialist_id, slot_datetime, is_booked) VALUES (?, ?, 1)")->execute([$specId, $slotTime]);
                $slotId = $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE schedule SET is_booked = 1 WHERE id = ?")->execute([$slotId]);
            }
            $pdo->prepare("INSERT INTO appointments (specialist_id, guest_name, topic, request_text, contact_method, service_type, appointment_time, status) VALUES (?, 'Запись на месте', '[Очная индивидуальная сессия] Запись на месте', 'Клиент записан лично у специалиста', 'telegram', 'Очная индивидуальная сессия', ?, 'confirmed')")
                ->execute([$specId, $slotTime]);
            $appId = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE schedule SET appointment_id = ? WHERE id = ?")->execute([$appId, $slotId]);
            joyCabinetRedirect('Запись добавлена.');
        }
        if ($action === 'complete') {
            $appStmt = $pdo->prepare("SELECT id FROM appointments WHERE specialist_id = ? AND appointment_time = ? AND status IN ('new','assigned','confirmed') AND is_deleted = 0 LIMIT 1");
            $appStmt->execute([$specId, $slotTime]);
            $appId = (int)$appStmt->fetchColumn();
            if ($appId > 0) {
                $pdo->prepare("UPDATE appointments SET status='completed' WHERE id=?")->execute([$appId]);
                $pdo->prepare("DELETE FROM schedule WHERE appointment_id = ?")->execute([$appId]);
                joyCabinetRedirect('Сессия завершена.');
            }
            joyCabinetRedirect('Запись для завершения не найдена.', true);
        }
        joyCabinetRedirect('Неизвестное действие.', true);
    }
}

if ($user['role'] === 'admin' || $user['role'] === 'psychologist') {
    if (isset($_POST['assign_time'])) {
        $appId = (int)$_POST['app_id'];
        $time = $_POST['time'];
        $pdo->prepare("UPDATE appointments SET appointment_time=?, status='assigned' WHERE id=?")->execute([$time, $appId]);
        joySyncAppointmentSchedule($pdo, $appId, $time);
        joyCabinetRedirect('Время назначено.');
    }
}

if ($user['role'] === 'admin') {
    if (isset($_GET['approve_order'])) { $ordId = (int)$_GET['approve_order']; $pdo->prepare("UPDATE orders SET status='completed' WHERE id=?")->execute([$ordId]);
        joyCabinetRedirect('Доступ открыт!');
    }
    if (isset($_GET['delete_admin_app'])) {
        $appId = (int)$_GET['delete_admin_app']; $pdo->prepare("DELETE FROM schedule WHERE appointment_id=?")->execute([$appId]);
        $pdo->prepare("UPDATE appointments SET is_deleted=1 WHERE id=?")->execute([$appId]);
        joyCabinetRedirect('Заявка скрыта.');
    }
    if (isset($_GET['delete_user'])) { $delId = (int)$_GET['delete_user']; if ($delId !== (int)$user['id']) $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]); joyCabinetRedirect('Пользователь удален.'); }
    if (isset($_GET['delete_order'])) { $ordId = (int)$_GET['delete_order']; $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$ordId]); $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$ordId]);
        joyCabinetRedirect('Заказ удален.');
    }
    if (isset($_GET['delete_admin_slot'])) {
        $slotId = $_GET['delete_admin_slot']; $checkSlot = $pdo->prepare("SELECT appointment_id FROM schedule WHERE id = ?"); $checkSlot->execute([$slotId]);
        if ($slot = $checkSlot->fetch()) {
            if (!empty($slot['appointment_id'])) $pdo->prepare("UPDATE appointments SET status='canceled', appointment_time=NULL WHERE id=?")->execute([$slot['appointment_id']]);
            $pdo->prepare("DELETE FROM schedule WHERE id = ?")->execute([$slotId]);
        }
        joyCabinetRedirect('Слот удален.');
    }
    if (isset($_GET['approve_review'])) { $pdo->prepare("UPDATE reviews SET status = 'approved' WHERE id = ?")->execute([$_GET['approve_review']]); joyCabinetRedirect('Отзыв одобрен.'); }
    if (isset($_GET['reject_review'])) { $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$_GET['reject_review']]); joyCabinetRedirect('Отзыв удален.'); }
    if (isset($_GET['delete_post'])) { $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$_GET['delete_post']]); joyCabinetRedirect('Публикация удалена.'); }
    if (isset($_GET['approve_post'])) { $pdo->prepare("UPDATE posts SET status = 'published' WHERE id=?")->execute([$_GET['approve_post']]); joyCabinetRedirect('Публикация одобрена.'); }
    if (isset($_GET['delete_product'])) { $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$_GET['delete_product']]); joyCabinetRedirect('Продукт удален.'); }
    if (isset($_POST['save_product'])) {
        $title = $_POST['title']; $price = $_POST['price']; $desc = $_POST['description']; $cat = $_POST['category']; $id = $_POST['product_id']; $accessLink = $_POST['access_link'] ?? '';
        $imagePath = $_POST['existing_image'] ?? 'img/Frame.png';
        if (!empty($_FILES['image']['name'])) { $target = "img/" . basename($_FILES['image']['name']); if(move_uploaded_file($_FILES['image']['tmp_name'], $target)) $imagePath = $target; }
        if ($id) { $pdo->prepare("UPDATE products SET title=?, price=?, description=?, image=?, category=?, access_link=? WHERE id=?")->execute([$title, $price, $desc, $imagePath, $cat, $accessLink, $id]);
        } else { $pdo->prepare("INSERT INTO products (title, price, description, image, category, access_link) VALUES (?, ?, ?, ?, ?, ?)")->execute([$title, $price, $desc, $imagePath, $cat, $accessLink]); }
        joyCabinetRedirect('Продукт сохранен.');
    }
    if (isset($_POST['save_system_settings'])) {
        $pdo->prepare("UPDATE settings SET phone=?, email=?, address=?, telegram=?, instagram=?, work_hours=? WHERE id=1")
            ->execute([$_POST['phone'], $_POST['email'], $_POST['address'], $_POST['telegram'], $_POST['instagram'], $_POST['work_hours']]);
        joyCabinetRedirect('Настройки сайта сохранены.');
    }
    if (isset($_POST['save_faq'])) {
        $f_id = $_POST['faq_id']; $q = trim($_POST['question']); $a = trim($_POST['answer']); $st = $_POST['status'] ?? 'published';
        if ($f_id) { $pdo->prepare("UPDATE faq SET question=?, answer=?, status=? WHERE id=?")->execute([$q, $a, $st, $f_id]); } 
        else { $pdo->prepare("INSERT INTO faq (question, answer, status) VALUES (?, ?, ?)")->execute([$q, $a, $st]); }
        joyCabinetRedirect('Вопрос сохранен.');
    }
   if (isset($_GET['delete_faq'])) { 
    $pdo->prepare("DELETE FROM faq WHERE id=?")->execute([$_GET['delete_faq']]); 
    joyCabinetRedirect('Вопрос удален.', false, 'system-settings'); 
}
    if (isset($_POST['add_room'])) { $pdo->prepare("INSERT INTO rooms (name) VALUES (?)")->execute([trim($_POST['room_name'])]); joyCabinetRedirect('Кабинет добавлен.'); }
   if (isset($_GET['delete_room'])) { 
    $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$_GET['delete_room']]); 
    joyCabinetRedirect('Кабинет удален.', false, 'system-settings'); 
}
    if (isset($_POST['add_topic'])) { $pdo->prepare("INSERT INTO topics (title) VALUES (?)")->execute([trim($_POST['topic_title'])]); joyCabinetRedirect('Тема добавлена.'); }
    if (isset($_GET['delete_topic'])) { 
    $pdo->prepare("DELETE FROM topics WHERE id=?")->execute([$_GET['delete_topic']]); 
    joyCabinetRedirect('Тема удалена.', false, 'system-settings'); 
}
}

$avatarLetter = mb_strtoupper(mb_substr($user['name'], 0, 1, 'UTF-8'));
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный Кабинет | J.O.Y.</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Tenor+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="joy.css?v=<?= time() ?>">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
</head>
<body class="cabinet-page">

<header class="joy-topbar">
    <div class="user-info">
        <div class="text-right">
            <p class="user-name"><?= htmlspecialchars($user['name']) ?></p>
            <p class="user-role">
                <?php if($user['role'] === 'admin'): ?>Администратор
                <?php elseif($user['role'] === 'psychologist'): ?>Психолог
                <?php else: ?>Клиент<?php endif; ?>
            </p>
        </div>
        <div class="avatar-mini"><?= $avatarLetter ?></div>
    </div>
</header>

<div class="cabinet-wrapper">
    <aside class="joy-sidebar">
        <div class="sidebar-logo-container">
            <a href="index.php"><img src="img/Frame.svg" alt="J.O.Y."></a>
        </div>
        
        <?php if($user['role'] === 'admin'): ?>
            <a href="#" class="joy-nav-item active" onclick="showTab('admin-appointments', this)"><div class="joy-nav-icon"><i class="fas fa-calendar-check"></i></div><div class="joy-nav-text">Расписание и заявки</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('groups', this)"><div class="joy-nav-icon"><i class="fas fa-users"></i></div><div class="joy-nav-text">Групповая терапия</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('admin-team', this)"><div class="joy-nav-icon"><i class="fas fa-briefcase-medical"></i></div><div class="joy-nav-text">Команда</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('admin-reviews', this)"><div class="joy-nav-icon"><i class="fas fa-star"></i></div><div class="joy-nav-text">Отзывы</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('admin-orders', this)"><div class="joy-nav-icon"><i class="fas fa-shopping-bag"></i></div><div class="joy-nav-text">Заказы</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('admin-products', this)"><div class="joy-nav-icon"><i class="fas fa-box"></i></div><div class="joy-nav-text">Онлайн-продукты</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('posts', this)"><div class="joy-nav-icon"><i class="fas fa-newspaper"></i></div><div class="joy-nav-text">Публикации</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('admin-users', this)"><div class="joy-nav-icon"><i class="fas fa-address-book"></i></div><div class="joy-nav-text">База пользователей</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('system-settings', this)"><div class="joy-nav-icon"><i class="fas fa-info-circle"></i></div><div class="joy-nav-text">Настройки сайта</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('profile', this)"><div class="joy-nav-icon"><i class="fas fa-user-cog"></i></div><div class="joy-nav-text">Мой аккаунт</div></a>
        
        <?php elseif($user['role'] === 'psychologist'): ?>
            <a href="#" class="joy-nav-item active" onclick="showTab('psych-appointments', this)"><div class="joy-nav-icon"><i class="fas fa-user-md"></i></div><div class="joy-nav-text">Клиенты и расписание</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('groups', this)"><div class="joy-nav-icon"><i class="fas fa-users"></i></div><div class="joy-nav-text">Групповая терапия</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('posts', this)"><div class="joy-nav-icon"><i class="fas fa-pen-nib"></i></div><div class="joy-nav-text">Мои статьи</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('psych-profile', this)"><div class="joy-nav-icon"><i class="fas fa-id-card"></i></div><div class="joy-nav-text">Публичный профиль</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('profile', this)"><div class="joy-nav-icon"><i class="fas fa-cog"></i></div><div class="joy-nav-text">Настройки аккаунта</div></a>
        
        <?php else: ?>
            <a href="#" class="joy-nav-item active" onclick="showTab('sessions', this)"><div class="joy-nav-icon"><i class="far fa-calendar-check"></i></div><div class="joy-nav-text">Мои записи</div></a>
            <a href="#" class="joy-nav-item" onclick="showTab('cart', this)"><div class="joy-nav-icon"><i class="fas fa-shopping-basket"></i></div><div class="joy-nav-text">Корзина</div></a>
          <a href="#" class="joy-nav-item" onclick="showTab('my-orders', this)">
    <div class="joy-nav-icon"><i class="fas fa-history"></i></div>
    <div class="joy-nav-text">Мои материалы</div>
</a>
            <a href="#" class="joy-nav-item" onclick="showTab('profile', this)"><div class="joy-nav-icon"><i class="fas fa-cog"></i></div><div class="joy-nav-text">Настройки профиля</div></a>
        <?php endif; ?>
        
        <div class="sidebar-footer-nav">
            <a href="index.php" class="joy-nav-item"><div class="joy-nav-icon"><i class="fas fa-home"></i></div><div class="joy-nav-text">На главную</div></a>
            <a href="auth.php?action=logout" class="joy-nav-item"><div class="joy-nav-icon text-danger"><i class="fas fa-sign-out-alt"></i></div><div class="joy-nav-text text-danger">Выход</div></a>
        </div>
    </aside>

    <main class="joy-content">
        
        <div id="tab-profile" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">Настройки аккаунта</h2>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card-panel h-100">
                        <h5 class="mb-4 font-weight-bold">Личные данные</h5>
                        <form method="POST">
                            <?php if($user['role'] !== 'admin'): ?>
                                <div class="mb-3"><label class="font-weight-bold text-muted small">Фамилия</label><input type="text" name="surname" value="<?= htmlspecialchars($user['surname'] ?? '') ?>" class="form-control joy-input"></div>
                            <?php endif; ?>
                            <div class="mb-3"><label class="font-weight-bold text-muted small">Имя</label><input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" class="form-control joy-input" required></div>
                            <?php if($user['role'] !== 'admin'): ?>
                                <div class="mb-3"><label class="font-weight-bold text-muted small">Отчество</label><input type="text" name="patronymic" value="<?= htmlspecialchars($user['patronymic'] ?? '') ?>" class="form-control joy-input"></div>
                            <?php endif; ?>
                            <div class="mb-4"><label class="font-weight-bold text-muted small">Email (Для входа)</label><input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-control joy-input" required></div>
                            <button type="submit" name="update_profile" class="main-button small-btn">Сохранить</button>
                        </form>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card-panel h-100">
                        <h5 class="mb-4 font-weight-bold">Безопасность</h5>
                        <button type="button" id="togglePasswordBtn" class="btn btn-outline-dark btn-toggle-password" onclick="document.getElementById('passwordFields').style.display='block'; this.style.display='none';"><i class="fas fa-key mr-2"></i> Сменить пароль</button>
                        <div id="passwordFields" class="form-security-block">
                            <form method="POST">
                                <div class="mb-3"><label class="font-weight-bold text-muted small">Текущий пароль</label><input type="password" name="old_password" class="form-control joy-input" required></div>
                                <div class="mb-3"><label class="font-weight-bold text-muted small">Новый пароль</label><input type="password" name="new_password" class="form-control joy-input" minlength="6" required></div>
                                <button type="submit" name="change_password" class="main-button">Сохранить пароль</button>
                                <button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('passwordFields').style.display='none'; document.getElementById('togglePasswordBtn').style.display='inline-block';">Отмена</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if($user['role'] === 'admin' || $user['role'] === 'psychologist'): ?>
        
        <?php 
        $specialistData = null;
        if ($user['role'] === 'psychologist') {
            $stmtSpec = $pdo->prepare("SELECT * FROM specialists WHERE user_id = ?");
            $stmtSpec->execute([$user['id']]); $specialistData = $stmtSpec->fetch();
        }
        ?>

        <div id="tab-groups" class="content-tab" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="tab-title m-0">Групповая терапия</h2>
                <button class="main-button" onclick="editGroup(null)"><i class="fas fa-plus mr-2"></i> Создать группу</button>
            </div>
            
            <div id="groupFormBlock" class="mb-4 card-panel joy-form-block">
                <h4 id="grpFormTitle" class="mb-4">Новая группа</h4>
                <form method="POST">
                    <input type="hidden" name="group_id" id="formGrpId">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Название группы</label><input type="text" name="title" id="formGrpTitle" class="form-control joy-input" required></div>
                        <div class="col-md-3 mb-3"><label class="font-weight-bold text-muted small">Дата и время</label><input type="datetime-local" name="event_date" id="formGrpDate" class="form-control joy-input" required></div>
                        <div class="col-md-3 mb-3"><label class="font-weight-bold text-muted small">Лимит мест</label><input type="number" name="max_seats" id="formGrpSeats" class="form-control joy-input" min="1" required></div>
                    </div>
                    <div class="row">
                        <?php if($user['role'] === 'admin'): ?>
                        <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Ведущий психолог</label>
                            <select name="spec_id" id="formGrpSpec" class="form-control joy-select" required>
                                <option value="" disabled selected>Выберите ведущего...</option>
                                <?php $adminSpecs = $pdo->query("SELECT id, first_name, last_name FROM specialists")->fetchAll(); foreach($adminSpecs as $as): ?><option value="<?= $as['id'] ?>"><?= htmlspecialchars($as['first_name'].' '.$as['last_name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Кабинет / Зал</label>
                            <select name="room_id" id="formGrpRoom" class="form-control joy-select">
                                <option value="">Без кабинета</option>
                                <?php try { $stmtRooms = $pdo->query("SELECT name FROM rooms ORDER BY id ASC"); while($rm = $stmtRooms->fetch()): ?>
                                    <option value="<?= htmlspecialchars($rm['name']) ?>"><?= htmlspecialchars($rm['name']) ?></option>
                                <?php endwhile; } catch (Exception $e) {} ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Описание для клиентов</label><textarea name="description" id="formGrpDesc" class="form-control joy-input" rows="4" required></textarea></div>
                    <button type="submit" name="save_group" class="main-button">Сохранить</button>
                    <button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('groupFormBlock').style.display='none'">Отмена</button>
                </form>
            </div>

            <div>
                <?php 
                $gSql = "SELECT g.*, s.first_name, s.last_name FROM therapy_groups g LEFT JOIN specialists s ON g.spec_id = s.id";
                if ($user['role'] === 'psychologist' && $specialistData) { $gSql .= " WHERE g.spec_id = " . $specialistData['id']; }
                $groups = $pdo->query($gSql . " ORDER BY g.event_date DESC")->fetchAll();
                if(count($groups) == 0) echo "<div class='empty-state'><i class='fas fa-users empty-icon'></i><h4 class='empty-text'>Нет групп</h4></div>";
                foreach($groups as $grp): 
                    $cntActive = $pdo->query("SELECT COUNT(*) FROM group_participants WHERE group_id={$grp['id']} AND status='active'")->fetchColumn();
                    $cntWait = $pdo->query("SELECT COUNT(*) FROM group_participants WHERE group_id={$grp['id']} AND status='waitlist'")->fetchColumn();
                ?>
                <div class="card-panel card-accent-border mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="m-0 font-weight-bold"><?= htmlspecialchars($grp['title']) ?></h4>
                            <div class="d-flex gap-3 mt-2 text-muted small">
                                <span class="mr-3"><i class="far fa-calendar-alt"></i> <?= !empty($grp['event_date']) ? date('d.m.Y H:i', strtotime($grp['event_date'])) : '' ?></span>
                                <span class="mr-3"><i class="fas fa-users"></i> Мест: <?= $cntActive ?> / <?= $grp['max_seats'] ?></span>
                                <?php if(!empty($grp['room_id'])): ?><span class="mr-3"><i class="fas fa-door-open"></i> <?= htmlspecialchars($grp['room_id']) ?></span><?php endif; ?>
                                <?php if($user['role'] === 'admin'): ?><span><i class="fas fa-user-md"></i> <?= htmlspecialchars($grp['first_name'].' '.$grp['last_name']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex">
                            <button class="action-btn btn-edit" onclick='editGroup(<?= json_encode($grp) ?>)' title="Редактировать"><i class="fas fa-pen"></i></button>
                            <a href="?delete_group=<?= $grp['id'] ?>" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить группу и участников?', () => { window.location.href = this.href; })" title="Удалить"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </div>
                    
                    <button class="btn btn-sm btn-list-participants" type="button" data-toggle="collapse" data-target="#parts-<?= $grp['id'] ?>">
                        <strong><i class="fas fa-chevron-down mr-1"></i> Список участников (<?= $cntActive ?>)</strong> 
                    </button>
                    <span class="text-muted small ml-2">В ожидании: <?= $cntWait ?></span>
                    
                    <div class="collapse" id="parts-<?= $grp['id'] ?>">
                        <?php if($cntWait > 0): ?>
                        <div class="mt-3 p-3 bg-white rounded border text-right">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="group_id" value="<?= $grp['id'] ?>">
                                <button type="submit" name="notify_waitlist" class="btn btn-outline-dark btn-sm btn-waitlist-apology" onclick="return confirm('Отправить извинения?')">
                                    <i class="fas fa-envelope"></i> Уведомить лист ожидания (Отсутствие мест)
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <ul class="list-group list-group-flush mt-3 border rounded">
                            <?php 
                            $parts = $pdo->query("SELECT p.id as pid, p.status, u.name, u.surname, u.email FROM group_participants p JOIN users u ON p.user_id = u.id WHERE p.group_id = {$grp['id']} ORDER BY p.created_at ASC")->fetchAll();
                            foreach($parts as $pt): 
                            ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="font-weight-bold"><?= htmlspecialchars($pt['name'].' '.$pt['surname']) ?></span> <span class="text-muted small ml-2">(<?= $pt['email'] ?>)</span>
                                    <?= $pt['status'] == 'waitlist' ? '<span class="badge badge-waitlist ml-2">В ожидании</span>' : '' ?>
                                </div>
                                <a href="?remove_participant=<?= $pt['pid'] ?>" class="action-btn btn-delete btn-small-action" onclick="event.preventDefault(); joyConfirm('Исключить клиента?', () => { window.location.href = this.href; })"><i class="fas fa-times"></i></a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="tab-posts" class="content-tab" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="tab-title m-0">Публикации</h2>
                <button class="main-button" onclick="editPost(null)"><i class="fas fa-plus mr-2"></i> Добавить статью</button>
            </div>
            <div id="postFormBlock" class="mb-4 card-panel joy-form-block">
                <h4 id="postFormTitle" class="mb-4">Новая статья</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="post_id" id="postId">
                    <input type="hidden" name="existing_image" id="postImgOld">
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Заголовок</label><input type="text" name="title" id="postTitle" class="form-control joy-input" required></div>
                    <?php if($user['role'] === 'admin'): ?>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Автор (Психолог)</label>
                        <select name="author_id" id="postAuthor" class="form-control joy-select" required>
                            <option value="">Выберите автора...</option>
                            <?php $adminSpecs = $pdo->query("SELECT id, first_name, last_name FROM specialists")->fetchAll(); foreach($adminSpecs as $as) echo "<option value='{$as['id']}'>".htmlspecialchars($as['first_name'].' '.$as['last_name'])."</option>"; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Краткое описание (Анонс)</label><textarea name="short_desc" id="postShortDesc" class="form-control joy-input" rows="2" required></textarea></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Текст статьи</label><textarea name="content" id="postContent" class="form-control" required></textarea></div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Обложка</label><div class="custom-file"><input type="file" name="image" class="custom-file-input" id="customFilePost"><label class="custom-file-label label-file-custom" for="customFilePost">Выберите файл</label></div></div>
                    <button type="submit" name="save_post" class="main-button">Сохранить</button>
                    <button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('postFormBlock').style.display='none'">Отмена</button>
                </form>
            </div>

            <div class="card-panel p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover m-0 joy-table">
                        <thead class="table-joy-head">
                            <tr>
                                <th class="border-0 px-4 py-3 text-muted font-weight-normal">Обложка</th>
                                <th class="border-0 px-4 py-3 text-muted font-weight-normal">Заголовок</th>
                                <th class="border-0 px-4 py-3 text-muted font-weight-normal">Автор</th>
                                <th class="border-0 px-4 py-3 text-muted font-weight-normal">Статус</th>
                                <th class="border-0 px-4 py-3 text-muted font-weight-normal text-right">Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $pSql = "SELECT p.*, s.first_name, s.last_name FROM posts p LEFT JOIN specialists s ON p.author_id = s.id";
                        if ($user['role'] === 'psychologist' && $specialistData) { $pSql .= " WHERE p.author_id = " . (int)$specialistData['id']; }
                        $pSql .= " ORDER BY p.created_at DESC"; $posts = $pdo->query($pSql)->fetchAll();
                        if(count($posts) == 0) { echo "<tr><td colspan='5' class='text-center py-5 text-muted'>Нет публикаций</td></tr>"; }
                        foreach($posts as $p): 
                            $statusBadge = ($p['status'] == 'published') ? '<span class="badge badge-status-published">На сайте</span>' : '<span class="badge badge-status-pending">Модерация</span>';
                        ?>
                        <tr class="table-joy-row">
                            <td class="align-middle px-4 py-3"><img src="<?= htmlspecialchars($p['image']) ?>" class="table-img-preview" onerror="this.src='img/Frame.png'"></td>
                            <td class="align-middle px-4 font-weight-bold table-cell-title-limit"><?= htmlspecialchars($p['title']) ?></td>
                            <td class="align-middle px-4 text-muted"><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?></td>
                            <td class="align-middle px-4"><?= $statusBadge ?></td>
                            <td class="align-middle px-4 text-right table-actions-cell">
                                <?php if($user['role'] === 'admin'): ?>
                                    <?php if($p['status'] !== 'published'): ?>
                                        <a href="?approve_post=<?= $p['id'] ?>&tab=posts" class="action-btn btn-approve" title="Опубликовать" onclick="event.preventDefault(); joyConfirm('Опубликовать эту статью?', () => { window.location.href = this.href; })"><i class="fas fa-check"></i></a>
                                    <?php endif; ?>
                                    <button class="action-btn btn-edit" onclick='editPost(<?= json_encode($p) ?>)' title="Редактировать"><i class="fas fa-pen"></i></button>
                                    <a href="?delete_post=<?= $p['id'] ?>&tab=posts" class="action-btn btn-delete" title="Удалить" onclick="event.preventDefault(); joyConfirm('Удалить статью?', () => { window.location.href = this.href; })"><i class="fas fa-trash-alt"></i></a>
                                <?php else: ?>
                                    <button class="action-btn btn-edit" onclick='editPost(<?= json_encode($p) ?>)' title="Редактировать"><i class="fas fa-pen"></i></button>
                                    <a href="?psych_delete_post=<?= $p['id'] ?>" class="action-btn btn-delete" title="Удалить" onclick="event.preventDefault(); joyConfirm('Удалить статью?', () => { window.location.href = this.href; })"><i class="fas fa-trash-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>

        <?php if($user['role'] === 'admin'): ?>
        
        <div id="tab-admin-appointments" class="content-tab" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="tab-title m-0">Расписание и заявки</h2>
                <div class="inner-tabs-container m-0" id="adminAppTabs" data-target-prefix="admin-sub">
                    <button class="inner-tab-btn active" onclick="switchInnerTab('adminAppTabs', 0)">Заявки</button>
                    <button class="inner-tab-btn" onclick="switchInnerTab('adminAppTabs', 1)">Окна / Графики</button>
                    <div class="inner-tab-slider"></div>
                </div>
            </div>

            <div id="admin-sub-0" class="inner-content active">
                <div class="card-panel mb-4 p-3 d-flex align-items-center flex-wrap joy-filter-bar">
                    <select class="form-control form-control-sm joy-select filter-select-fixed" id="filterStatus" onchange="applyAdminAppFilters()">
                        <option value="all">Все статусы</option><option value="new">Новые</option><option value="confirmed">Подтверждено</option><option value="completed">Завершено</option><option value="canceled">Отменены</option><option value="callback">Обратная связь</option>
                    </select>
                    <select class="form-control form-control-sm joy-select filter-select-fixed" id="filterAdminService" onchange="applyAdminAppFilters()">
                        <option value="all">Все услуги</option><option value="Очная индивидуальная сессия">Очная сессия</option><option value="Онлайн сессия">Онлайн сессия</option><option value="Парная терапия">Парная терапия</option>
                    </select>
                    <select class="form-control form-control-sm joy-select filter-select-spec" id="filterSpec" onchange="applyAdminAppFilters()">
                        <option value="all">Все психологи</option>
                        <?php $adminSpecs = $pdo->query("SELECT id, first_name, last_name FROM specialists")->fetchAll(); foreach($adminSpecs as $as) echo "<option value='{$as['id']}'>{$as['first_name']} {$as['last_name']}</option>"; ?>
                    </select>
                    <div class="filter-input-search-wrapper">
                        <i class="fas fa-search search-icon-input"></i>
                        <input type="text" id="filterClient" class="form-control form-control-sm joy-input filter-input-client" placeholder="Поиск по клиенту" oninput="applyAdminAppFilters()">
                    </div>
                    <input type="date" id="filterDate" class="form-control form-control-sm joy-input w-auto" onchange="applyAdminAppFilters()">
                    <div class="d-flex align-items-center ml-auto"><button class="btn btn-sm btn-outline-dark btn-round-action" onclick="openClientsModal()"><i class="fas fa-paper-plane mr-2"></i> Рассылка</button></div>
                </div>

                <div id="adminAppsContainer" class="joy-apps-scroll">
                    <?php 
                    $whereSql = $hasDeletedCol ? "WHERE a.is_deleted = 0" : "";
                    $apps = $pdo->query("SELECT a.*, u.surname, u.name, u.email as user_email, s.first_name as spec_name, s.last_name as spec_surname FROM appointments a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN specialists s ON a.specialist_id = s.id $whereSql ORDER BY a.created_at DESC")->fetchAll();
                    if(count($apps) == 0) echo "<div class='empty-state'><i class='fas fa-clipboard-list empty-icon'></i><h4 class='empty-text'>Заявок пока нет</h4></div>";
                    foreach($apps as $app): 
                        $clientName = $app['surname'] ? $app['surname'].' '.$app['name'] : $app['guest_name']; $filterStatus = $app['status']; if (strpos($app['topic'], 'Обратная связь') !== false) $filterStatus = 'callback';
                        $appDisplay = joyParseAppointmentDisplay($app['topic'], $app['service_type'] ?? '');
                        $appDateStr = date('Y-m-d', strtotime(!empty($app['appointment_time']) ? $app['appointment_time'] : $app['created_at']));
                        $rowOpacityClass = ($app['status'] == 'completed' || $app['status'] == 'canceled') ? 'item-opacity-low' : 'card-accent-border';
                    ?>
                    <div class="card-panel admin-app-item mb-3 <?= $rowOpacityClass ?>" 
                         data-status="<?= $filterStatus ?>" data-service="<?= htmlspecialchars($app['service_type'] ?? '') ?>" data-spec="<?= $app['specialist_id'] ?: '0' ?>" data-client="<?= mb_strtolower($clientName) ?>" data-time="<?= strtotime($app['created_at']) ?>" data-appdate="<?= $appDateStr ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                               <h5 class="font-weight-bold mb-1"><?= htmlspecialchars($appDisplay['title']) ?></h5>
                                <?php if(!$appDisplay['isCallback'] && $appDisplay['tag']): ?>
                                <span class="badge mb-3 d-inline-block badge-service-tag"><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($appDisplay['tag']) ?></span>
                                <?php endif; ?>
                                <p class="mb-1 text-muted"><i class="fas fa-user mr-2 text-dark"></i><strong>Клиент:</strong> <?= htmlspecialchars($clientName) ?> (<?= $app['guest_phone'] ?>)</p>
                                <?php if($filterStatus != 'callback'): ?>
                                    <p class="mb-1 text-muted"><i class="fas fa-user-md mr-2 text-dark"></i><strong>Психолог:</strong> <?php if($app['specialist_id']): ?><span class="font-weight-bold text-dark"><?= htmlspecialchars($app['spec_name'].' '.$app['spec_surname']) ?></span><?php else: ?><span class="badge badge-needs-match">Требуется подбор</span><?php endif; ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                                <small class="text-muted d-block mb-3">Создана: <?= date('d.m.Y H:i', strtotime($app['created_at'])) ?></small>
                                <?php if($app['status'] == 'new' && $filterStatus != 'callback'): ?>
                                    <?= getStatusBadge($app['status']) ?>
                                    <?php if(!empty($app['appointment_time'])): ?>
                                        <div class="mt-3 card-action-request-bg">
                                            <p class="font-weight-bold mb-2 small text-muted text-left">Запрос на время:</p>
                                            <h5 class="mb-3 text-left text-dark-joy"><?= date('d.m.Y в H:i', strtotime($app['appointment_time'])) ?></h5>
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="?reject_app=<?= $app['id'] ?>" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); var u=this.href; joyConfirm('Отклонить заявку?', () => { joyNavigateCabinet(u); })">Отклонить</a>
                                                <form method="POST" class="m-0 ml-2"><input type="hidden" name="app_id" value="<?= $app['id'] ?>"><input type="hidden" name="new_status" value="confirmed"><button type="submit" name="change_app_status" class="main-button small-btn">Одобрить</button></form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-3 p-3 rounded text-left card-action-request-bg text-left">
                                            <p class="font-weight-bold mb-2 small text-muted">Клиент в листе ожидания (Без времени)</p>
                                            <form method="POST" class="d-flex align-items-center gap-2">
                                                <input type="hidden" name="app_id" value="<?= $app['id'] ?>"><input type="datetime-local" name="time" class="form-control form-control-sm joy-input w-auto flex-grow-1" required><button type="submit" name="assign_time" class="main-button small-btn ml-2">Назначить</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif($filterStatus != 'callback'): ?>
                                    <div class="p-3 rounded text-left d-inline-block card-action-request-bg box-min-width-300">
                                        <p class="mb-2 text-muted small">Назначенное время:</p>
                                        <h5 class="mb-3 text-dark-joy"><?= !empty($app['appointment_time']) ? date('d.m.Y в H:i', strtotime($app['appointment_time'])) : 'Ожидание' ?></h5>
                                        <form method="POST"><input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                            <div class="d-flex gap-2 mb-2">
                                                <select name="room_id" class="form-control form-control-sm joy-select flex-grow-1 m-0" onchange="this.form.submit()">
                                                    <option value="">Без кабинета</option>
                                                    <?php $stmtRooms = $pdo->query("SELECT name FROM rooms ORDER BY id ASC"); while($rm = $stmtRooms->fetch()): ?>
                                                        <option value="<?= htmlspecialchars($rm['name']) ?>" <?= $app['room_id'] == $rm['name'] ? 'selected' : '' ?>>Каб: <?= htmlspecialchars($rm['name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <select name="new_status" class="form-control form-control-sm joy-select flex-grow-1 m-0 bg-white" onchange="this.form.submit()">
                                                    <option value="confirmed" <?= $app['status']=='confirmed'?'selected':'' ?>>Подтверждена</option>
                                                    <option value="completed" <?= $app['status']=='completed'?'selected':'' ?>>Завершена</option>
                                                    <option value="canceled" <?= $app['status']=='canceled'?'selected':'' ?>>Отменена</option>
                                                </select>
                                            </div>
                                            <input type="hidden" name="change_app_status" value="1">
                                        </form>
                                    </div>
                                <?php else: ?> <?= getStatusBadge('new') ?> <?php endif; ?>
                                <div class="mt-3"><a href="?delete_admin_app=<?= $app['id'] ?>&tab=admin-appointments" class="text-danger small text-underline" onclick="event.preventDefault(); joyConfirm('Удалить заявку?', () => { window.location.href = this.href; })"><i class="fas fa-trash"></i> Удалить</a></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
       <div id="admin-sub-1" class="inner-content">
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card-panel h-100">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <h5 class="m-0 font-weight-bold">Сводка графиков работы</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover m-0 joy-table">
                                <thead class="table-joy-head">
                                    <tr>
                                        <th class="border-0 text-muted font-weight-normal">Психолог</th>
                                        <th class="border-0 text-muted font-weight-normal">Заявленный график</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($adminSpecs as $as): 
                                    $schd = $pdo->query("SELECT work_schedule FROM specialists WHERE id=" . $as['id'])->fetchColumn(); 
                                ?>
                                <tr class="table-joy-row">
                                    <td class="align-middle font-weight-bold py-3"><?= htmlspecialchars($as['first_name'].' '.$as['last_name']) ?></td>
                                    <td class="align-middle text-muted py-3"><?= $schd ? htmlspecialchars($schd) : 'Не указан' ?></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card-panel h-100">
                        <h5 class="mb-4 font-weight-bold text-dark-joy"><i class="fas fa-plus-circle text-muted mr-2"></i>Новое окно</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="text-muted small mb-1 font-weight-bold">Психолог</label>
                                <select name="admin_add_slot_spec" class="form-control joy-select" required>
                                    <option value="" disabled selected>Выберите...</option>
                                    <?php foreach($adminSpecs as $as) echo "<option value='{$as['id']}'>{$as['first_name']} {$as['last_name']}</option>"; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="text-muted small mb-1 font-weight-bold">Дата и время</label>
                                <input type="datetime-local" name="slot_time" class="form-control joy-input" required>
                            </div>
                            <button type="submit" name="admin_add_slot" class="main-button w-100">Открыть окно</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card-panel">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                            <h5 class="m-0 font-weight-bold">Свободные окна в системе</h5>
                        </div>

                        <div class="mb-4 d-flex align-items-center flex-wrap joy-filter-bar">
                            <select class="form-control form-control-sm joy-select filter-select-spec" id="filterAdminSchedSpec" onchange="applyAdminSchedFilters()">
                                <option value="all">Все психологи</option>
                                <?php foreach($adminSpecs as $as) echo "<option value='{$as['id']}'>{$as['first_name']} {$as['last_name']}</option>"; ?>
                            </select>
                            <input type="date" id="filterAdminSchedDate" class="form-control form-control-sm joy-input w-auto" onchange="applyAdminSchedFilters()">
                        </div>

                        <div class="row" id="adminSchedContainer">
                            <?php 
                            $allSlots = $pdo->query("SELECT sch.*, s.first_name, s.last_name FROM schedule sch JOIN specialists s ON sch.specialist_id = s.id WHERE sch.slot_datetime > NOW() ORDER BY sch.slot_datetime ASC")->fetchAll();
                            
                            if(count($allSlots) == 0) {
                                echo "<div class='col-12'><div class='empty-state'><i class='far fa-clock empty-icon'></i><h4 class='empty-text'>Расписание пусто</h4></div></div>";
                            }

                            foreach($allSlots as $slot): 
                                $dateStr = date('Y-m-d', strtotime($slot['slot_datetime'])); 
                                $niceDate = date('d.m.Y', strtotime($slot['slot_datetime'])); 
                                $timeStr = date('H:i', strtotime($slot['slot_datetime']));
                                $bookedOpacity = $slot['is_booked'] ? 'item-opacity-low' : '';
                            ?>
                            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3 admin-sched-item" data-spec="<?= $slot['specialist_id'] ?>" data-date="<?= $dateStr ?>">
                                <div class="p-3 border rounded text-center h-100 d-flex flex-column slot-card-admin-bg <?= $bookedOpacity ?>">
                                    <small class="text-muted d-block mb-1 font-weight-bold"><?= htmlspecialchars($slot['first_name'].' '.$slot['last_name']) ?></small>
                                    <h4 class="mb-1 text-dark-joy"><?= $timeStr ?></h4>
                                    <p class="text-muted small mb-2"><?= $niceDate ?></p>
                                    <div class="mt-auto">
                                        <?php if($slot['is_booked']): ?>
                                            <span class="badge badge-booked-full">Занято</span>
                                        <?php else: ?>
                                            <span class="badge badge-free-slot">Свободно</span>
                                        <?php endif; ?>
                                        <a href="?delete_admin_slot=<?= $slot['id'] ?>&tab=admin-appointments" class="text-danger small text-underline" onclick="event.preventDefault(); joyConfirm('Удалить слот?', () => { window.location.href = this.href; })">Удалить</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <div id="tab-admin-team" class="content-tab" style="display:none;">
            <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="tab-title m-0">Команда</h2><button class="main-button" onclick="editSpecialist(null)"><i class="fas fa-plus mr-2"></i> Добавить психолога</button></div>
            <div id="specialistFormBlock" class="mb-4 card-panel joy-form-block">
                <h4 id="specFormTitle" class="mb-4">Добавление психолога</h4>
                <form method="POST" action="admin_save_spec.php" enctype="multipart/form-data">
                    <input type="hidden" name="spec_id" id="formSpecId"><input type="hidden" name="existing_image" id="formSpecImgOld">
                    <div id="newUserFields" class="row mb-4 p-4 rounded form-security-block-admin">
                        <div class="col-12 mb-3"><h6 class="font-weight-bold m-0"><i class="fas fa-user-plus mr-2 icon-accent-joy"></i>Данные для входа</h6></div>
                        <div class="col-md-6 mb-2"><label class="font-weight-bold text-muted small">Email (Логин)</label><input type="email" name="new_email" id="formSpecEmail" class="form-control joy-input"></div>
                        <div class="col-md-6 mb-2"><label class="font-weight-bold text-muted small">Пароль (мин 6 симв.)</label><input type="text" name="new_password" id="formSpecPass" class="form-control joy-input" minlength="6" placeholder="Например: pass123"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Имя</label><input type="text" name="first_name" id="formSpecFirstName" class="form-control joy-input" required></div>
                        <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Отчество</label><input type="text" name="patronymic" id="formSpecPatronymic" class="form-control joy-input"></div>
                        <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Фамилия</label><input type="text" name="last_name" id="formSpecLastName" class="form-control joy-input" required></div>
                        <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Стаж (лет)</label><input type="number" name="experience_years" id="formSpecExp" class="form-control joy-input" required></div>
                    </div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Специализация</label><input type="text" name="specialization" id="formSpecSpec" class="form-control joy-input" required></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">График работы (Для сводки)</label><input type="text" name="work_schedule" id="formSpecSched" class="form-control joy-input" placeholder="Например: Пн-Ср 10:00 - 18:00"></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Образование</label><textarea name="education" id="formSpecEdu" class="form-control joy-input" rows="2" required></textarea></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Описание</label><textarea name="description" id="formSpecDesc" class="form-control joy-input" rows="4" required></textarea></div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Фото (Обложка)</label><div class="custom-file"><input type="file" name="image" class="custom-file-input" id="customFileSpec"><label class="custom-file-label label-file-custom" for="customFileSpec">Выберите файл</label></div></div>
                    <button type="submit" name="save_specialist" class="main-button">Сохранить</button>
                    <button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('specialistFormBlock').style.display='none'">Отмена</button>
                </form>
            </div>
            <div class="card-panel p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover m-0 joy-table">
                        <thead class="table-joy-head"><tr><th class="border-0 px-4 py-3 text-muted font-weight-normal">Фото</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Психолог</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Специализация</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">График работы</th><th class="border-0 px-4 py-3 text-muted font-weight-normal text-right">Действия</th></tr></thead>
                        <tbody>
                        <?php $allSpecs = $pdo->query("SELECT * FROM specialists ORDER BY id DESC")->fetchAll(); foreach($allSpecs as $s): ?>
                        <tr class="table-joy-row">
                            <td class="align-middle px-4 py-3"><img src="<?= htmlspecialchars($s['photo']) ?>" class="table-avatar-preview" onerror="this.src='img/Frame.png'"></td>
                            <td class="align-middle px-4 font-weight-bold text-dark"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                            <td class="align-middle px-4 text-muted"><?= htmlspecialchars($s['specialization']) ?></td>
                            <td class="align-middle px-4"><span class="badge badge-schedule-tag"><?= $s['work_schedule'] ? htmlspecialchars($s['work_schedule']) : 'Не указан' ?></span></td>
                            <td class="align-middle px-4 text-right table-actions-cell">
                                <button class="action-btn btn-edit" onclick='editSpecialist(<?= json_encode($s) ?>)' title="Редактировать"><i class="fas fa-pen"></i></button>
                                <a href="admin_save_spec.php?delete_spec=<?= $s['id'] ?>" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить специалиста?', () => { window.location.href = this.href; })" title="Удалить"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-admin-reviews" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">Новые отзывы</h2>
            <div>
                <?php 
                $pendingReviews = $pdo->query("SELECT r.*, u.name as client_name, s.first_name, s.last_name FROM reviews r JOIN users u ON r.user_id = u.id JOIN specialists s ON r.specialist_id = s.id WHERE r.status = 'pending' ORDER BY r.created_at DESC")->fetchAll();
                if(count($pendingReviews) == 0) echo "<div class='empty-state'><i class='fas fa-star-half-alt empty-icon'></i><h4 class='empty-text'>Все проверено</h4><p class='text-muted'>Нет новых отзывов.</p></div>";
                foreach($pendingReviews as $rev): 
                ?>
                <div class="card-panel card-accent-border mb-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div><h5 class="m-0 font-weight-bold"><?= htmlspecialchars($rev['client_name']) ?></h5><span class="text-muted small">Психологу: <span class="font-weight-bold text-dark"><?= htmlspecialchars($rev['first_name'].' '.$rev['last_name']) ?></span></span></div>
                        <div class="text-right"><span class="badge badge-rating-large"><i class="fas fa-star mr-1"></i> <?= $rev['rating'] ?></span></div>
                    </div>
                    <div class="mt-3 card-action-request-bg"><p class="review-text-display"><?= nl2br(htmlspecialchars($rev['review_text'])) ?></p></div>
                    <div class="mt-3 d-flex justify-content-end gap-2">
                        <a href="?reject_review=<?= $rev['id'] ?>&tab=admin-reviews" class="btn btn-outline-danger btn-sm btn-round-action-reject-spam" onclick="event.preventDefault(); joyConfirm('Удалить отзыв?', () => { window.location.href = this.href; })">В спам</a>
                        <a href="?approve_review=<?= $rev['id'] ?>&tab=admin-reviews" class="main-button small-btn">Одобрить на сайт</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="tab-admin-orders" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">Заказы (Онлайн-продукты)</h2>
            <div class="mb-4 pb-3 border-bottom d-flex align-items-center flex-wrap joy-filter-bar">
                <input type="date" id="filterOrderDate" class="form-control form-control-sm joy-input w-auto" onchange="sortAdminOrders()">
                <select class="form-control form-control-sm joy-select w-auto" id="sortOrderDate" onchange="sortAdminOrders()">
                    <option value="desc">Сначала новые</option><option value="asc">Сначала старые</option>
                </select>
            </div>
           <div id="adminOrdersContainer">
                <?php
                $orders = $pdo->query("SELECT o.*, u.surname, u.name, u.email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END, o.created_at DESC")->fetchAll();
                if(count($orders) == 0) echo "<div class='empty-state'><i class='fas fa-box-open empty-icon'></i><h4 class='empty-text'>Пока нет заказов</h4></div>";
                foreach($orders as $order):
                    $isCompleted = ($order['status'] === 'completed');
                    $rowOpacityClass = $isCompleted ? 'item-opacity-mid' : 'card-accent-border';
                ?>
                <div class="card-panel mb-4 admin-order-item <?= $rowOpacityClass ?>" data-time="<?= strtotime($order['created_at']) ?>" data-date="<?= date('Y-m-d', strtotime($order['created_at'])) ?>">
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div><h5 class="m-0 font-weight-bold">Заказ #<?= $order['id'] ?></h5><small class="text-muted"><i class="far fa-calendar-alt mr-1"></i> <?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></small></div>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-price-display mr-3"><?= $order['total_price'] ?> BYN</span>
                            <a href="?delete_order=<?= $order['id'] ?>&tab=admin-orders" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить заказ?', () => { window.location.href = this.href; })" title="Удалить заказ"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </div>
                    <p class="mb-3 text-muted"><i class="fas fa-user mr-2 text-dark"></i> <?= htmlspecialchars($order['surname'].' '.$order['name']) ?> <span class="ml-2">(<?= htmlspecialchars($order['email']) ?>)</span></p>
                    <div class="mb-3 card-action-request-bg">
                        <ul class="mb-0 list-unstyled joy-order-items-list">
                            <?php $items = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?"); $items->execute([$order['id']]); while($item = $items->fetch()): ?>
                            <li class="mb-1"><i class="fas fa-check mr-2 icon-accent-joy"></i> <?= htmlspecialchars($item['product_title']) ?> <span class="float-right font-weight-bold text-muted"><?= $item['price'] ?> BYN</span></li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 border-top pt-3">
                        <?php if(!$isCompleted): ?>
                            <span class="badge badge-order-pending"><i class="fas fa-hourglass-half mr-1"></i> Ожидает выдачи доступа</span>
                            <a href="?approve_order=<?= $order['id'] ?>&tab=admin-orders" class="main-button small-btn btn-no-decor" onclick="event.preventDefault(); joyConfirm('Открыть доступ?', () => { window.location.href = this.href; })">Открыть доступ</a>
                        <?php else: ?>
                            <span class="badge badge-order-completed"><i class="fas fa-check-circle mr-1"></i> Доступ открыт</span>
                            <span class="text-muted small">Материалы доступны клиенту</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="tab-admin-products" class="content-tab" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="tab-title m-0">Онлайн-продукты</h2><button class="main-button" onclick="editProduct(null)"><i class="fas fa-plus mr-2"></i> Добавить продукт</button></div>
            <div id="productFormBlock" class="mb-4 card-panel joy-form-block">
                <h4 id="formTitle" class="mb-4">Добавление продукта</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="product_id" id="prodId"><input type="hidden" name="existing_image" id="prodImgOld">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Название</label><input type="text" name="title" id="prodTitle" class="form-control joy-input" required></div>
                        <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Цена (BYN)</label><input type="number" name="price" id="prodPrice" class="form-control joy-input" required></div>
                    </div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Категория</label><select name="category" id="prodCat" class="form-control joy-select"><option value="general">Общее</option><option value="meditation">Медитации</option><option value="course">Курсы</option><option value="club">Клуб</option></select></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Описание</label><textarea name="description" id="prodDesc" class="form-control joy-input" rows="3"></textarea></div>
                    <div class="mb-3"><label class="font-weight-bold text-muted small">Ссылка на материал</label><input type="url" name="access_link" id="prodAccessLink" class="form-control joy-input" placeholder="https://..."></div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Изображение</label><div class="custom-file"><input type="file" name="image" class="custom-file-input" id="customFileProd"><label class="custom-file-label label-file-custom" for="customFileProd">Выберите файл</label></div></div>
                    <button type="submit" name="save_product" class="main-button">Сохранить</button><button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('productFormBlock').style.display='none'">Отмена</button>
                </form>
            </div>
            <div class="card-panel p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover m-0 joy-table">
                        <thead class="table-joy-head"><tr><th class="border-0 px-4 py-3 text-muted font-weight-normal">Фото</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Название</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Доступ</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Цена</th><th class="border-0 px-4 py-3 text-muted font-weight-normal text-right">Действия</th></tr></thead>
                        <tbody>
                        <?php $prods = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(); foreach($prods as $p): ?>
                        <tr class="table-joy-row">
                            <td class="align-middle px-4 py-3"><img src="<?= htmlspecialchars($p['image']) ?>" class="table-img-prod-preview" onerror="this.src='img/Frame.png'"></td>
                            <td class="align-middle px-4 font-weight-bold"><?= htmlspecialchars($p['title']) ?></td>
                            <td class="align-middle px-4"><?php if(!empty($p['access_link'])): ?><span class="badge badge-link-attached">Прикреплена ссылка</span><?php else: ?><span class="badge badge-link-missing">Нет ссылки</span><?php endif; ?></td>
                            <td class="align-middle px-4 font-weight-bold color-accent-joy"><?= $p['price'] ?> BYN</td>
                            <td class="align-middle px-4 text-right table-actions-cell">
                                <button class="action-btn btn-edit" onclick='editProduct(<?= json_encode($p) ?>)' title="Редактировать"><i class="fas fa-pen"></i></button>
                                <a href="?delete_product=<?= $p['id'] ?>" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить этот продукт?', () => { window.location.href = this.href; })" title="Удалить"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-admin-users" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">База пользователей</h2>
            <div class="card-panel p-0 overflow-hidden">
                <div class="p-4 border-bottom bg-white">
                    <div class="d-flex align-items-center flex-wrap joy-filter-bar">
                        <select class="form-control form-control-sm joy-select filter-select-fixed" id="filterUserRole" onchange="applyUserFilters()">
                            <option value="all">Все пользователи</option><option value="client">Только клиенты</option><option value="psychologist">Психологи</option><option value="admin">Администраторы</option>
                        </select>
                        <div class="filter-input-search-wrapper">
                            <i class="fas fa-search search-icon-input"></i>
                            <input type="text" id="filterUserText" class="form-control form-control-sm joy-input filter-input-search-plus" placeholder="Поиск..." oninput="applyUserFilters()">
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover m-0 joy-table">
                        <thead class="table-joy-head"><tr><th class="border-0 px-4 py-3 text-muted font-weight-normal">Имя пользователя</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Контакты</th><th class="border-0 px-4 py-3 text-muted font-weight-normal">Записи</th><th class="border-0 px-4 py-3 text-muted font-weight-normal text-right">Безопасность</th></tr></thead>
                        <tbody id="crmUsersContainer">
                        <?php 
                        $sqlCrm = "SELECT u.*, (SELECT COUNT(*) FROM appointments WHERE user_id = u.id AND is_deleted=0) as app_count, (SELECT guest_phone FROM appointments WHERE user_id = u.id ORDER BY created_at DESC LIMIT 1) as last_phone FROM users u ORDER BY u.id DESC";
                        $usersList = $pdo->query($sqlCrm)->fetchAll(); 
                        foreach($usersList as $u): $phoneStr = $u['last_phone'] ?: $u['phone'];
                        ?>
                        <tr class="crm-user-item table-joy-row" data-role="<?= $u['role'] ?>" data-search="<?= mb_strtolower($u['name'].' '.$u['surname'].' '.$u['email'].' '.$phoneStr) ?>">
                            <td class="align-middle px-4 py-3">
                                <span class="font-weight-bold d-block text-dark"><?= htmlspecialchars($u['name'] . ' ' . $u['surname']) ?></span>
                                <?php if($u['role'] == 'admin'): ?><span class="badge badge-user-role-admin">Админ</span>
                                <?php elseif($u['role'] == 'psychologist'): ?><span class="badge badge-user-role-psych">Психолог</span>
                                <?php else: ?><span class="badge badge-user-role-client">Клиент</span><?php endif; ?>
                            </td>
                            <td class="align-middle px-4 text-muted">
                                <div class="mb-1"><i class="fas fa-envelope icon-contact-fixed"></i> <?= htmlspecialchars($u['email']) ?></div>
                                <div><i class="fas fa-phone icon-contact-fixed"></i> <?= $phoneStr ? htmlspecialchars($phoneStr) : '—' ?></div>
                            </td>
                            <td class="align-middle px-4"><?php if($u['role'] == 'client'): ?><span class="font-weight-bold text-dark-joy"><?= $u['app_count'] ?></span> <span class="text-muted small">сессий</span><?php else: ?>—<?php endif; ?></td>
                            <td class="align-middle px-4 text-right table-actions-cell">
                                <a href="?reset_password=<?= $u['id'] ?>&tab=admin-users" class="action-btn btn-edit" onclick="event.preventDefault(); joyConfirm('Сбросить пароль?', () => { window.location.href = this.href; })" title="Сбросить пароль"><i class="fas fa-key"></i></a>
                                <?php if($u['id'] != $user['id']): ?>
                                    <a href="?delete_user=<?= $u['id'] ?>&tab=admin-users" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить пользователя?', () => { window.location.href = this.href; })" title="Удалить"><i class="fas fa-trash-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="tab-system-settings" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">Настройки сайта</h2>
            <div class="inner-tabs-container mb-4" id="sysTabs" data-target-prefix="sys-sub">
                <button class="inner-tab-btn active" onclick="switchInnerTab('sysTabs', 0)">Контакты</button>
                <button class="inner-tab-btn" onclick="switchInnerTab('sysTabs', 1)">Вопрос-Ответ (FAQ)</button>
                <button class="inner-tab-btn" onclick="switchInnerTab('sysTabs', 2)">Справочники списков</button>
                <div class="inner-tab-slider"></div>
            </div>
            <div id="sys-sub-0" class="inner-content active">
                <div class="card-panel">
                    <?php try { $st = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(); } catch (Exception $e) { $st = []; } ?>
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Телефон</label><input type="text" name="phone" value="<?= htmlspecialchars($st['phone'] ?? '') ?>" class="form-control joy-input" required></div>
                            <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Email</label><input type="email" name="email" value="<?= htmlspecialchars($st['email'] ?? '') ?>" class="form-control joy-input" required></div>
                            <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Адрес</label><input type="text" name="address" value="<?= htmlspecialchars($st['address'] ?? '') ?>" class="form-control joy-input" required></div>
                            <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Часы работы</label><input type="text" name="work_hours" value="<?= htmlspecialchars($st['work_hours'] ?? '') ?>" class="form-control joy-input" required></div>
                            <div class="col-md-6 mb-3"><label class="font-weight-bold text-muted small">Telegram</label><input type="text" name="telegram" value="<?= htmlspecialchars($st['telegram'] ?? '') ?>" class="form-control joy-input"></div>
                            <div class="col-md-6 mb-4"><label class="font-weight-bold text-muted small">Instagram</label><input type="text" name="instagram" value="<?= htmlspecialchars($st['instagram'] ?? '') ?>" class="form-control joy-input"></div>
                        </div>
                        <button type="submit" name="save_system_settings" class="main-button">Сохранить</button>
                    </form>
                </div>
            </div>
            <div id="sys-sub-1" class="inner-content">
                <div class="card-panel p-0 overflow-hidden">
                    <div class="p-4 faq-form-header-bg">
                        <h6 class="font-weight-bold mb-3" id="faqFormTitle">Редактировать вопрос</h6>
                        <form method="POST">
                            <input type="hidden" name="faq_id" id="faqFormId" value="">
                            <div class="row mb-3">
                                <div class="col-md-8"><input type="text" name="question" id="faqFormQ" class="form-control joy-input" placeholder="Вопрос..." required></div>
                                <div class="col-md-4"><select name="status" id="faqFormStatus" class="form-control joy-select"><option value="published">Опубликован</option><option value="pending">Черновик</option></select></div>
                            </div>
                            <textarea name="answer" id="faqFormA" class="form-control joy-input mb-3" rows="3" placeholder="Ответ..." required></textarea>
                            <button type="submit" name="save_faq" class="main-button small-btn">Сохранить</button>
                            <button type="button" class="btn btn-link text-muted ml-2" onclick="document.getElementById('faqFormId').value=''; document.getElementById('faqFormQ').value=''; document.getElementById('faqFormA').value=''; document.getElementById('faqFormTitle').innerText='Добавить новый вопрос';">Очистить</button>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover m-0 joy-table">
                            <thead class="table-joy-head-white"><tr><th class="border-0 px-4 text-muted font-weight-normal">Вопрос-Ответ</th><th class="border-0 px-4 text-muted font-weight-normal text-right">Действия</th></tr></thead>
                            <tbody>
                                <?php try { $faqs = $pdo->query("SELECT * FROM faq ORDER BY CASE WHEN status = 'pending' THEN 0 ELSE 1 END, id DESC")->fetchAll(); foreach($faqs as $f): ?>
                                <tr class="table-joy-row">
                                    <td class="align-middle px-4 py-3">
                                        <div class="d-flex align-items-center mb-1">
                                            <?= $f['status'] == 'pending' ? '<span class="badge badge-status-pending mr-2">Скрыт</span>' : '<span class="badge badge-status-published mr-2">На сайте</span>' ?>
                                            <strong class="text-dark-joy"><?= htmlspecialchars($f['question']) ?></strong>
                                        </div>
                                        <div class="text-muted small pl-1"><?= htmlspecialchars($f['answer']) ?></div>
                                    </td>
                                    <td class="align-middle px-4 text-right table-actions-cell">
                                        <button class="action-btn btn-edit" onclick="editFaq(<?= $f['id'] ?>, '<?= htmlspecialchars(addslashes($f['question'])) ?>', '<?= htmlspecialchars(addslashes($f['answer'])) ?>', '<?= $f['status'] ?>')" title="Редактировать"><i class="fas fa-pen"></i></button>
                                        <a href="?delete_faq=<?= $f['id'] ?>&tab=system-settings" class="action-btn btn-delete" onclick="event.preventDefault(); joyConfirm('Удалить вопрос?', () => { window.location.href = this.href; })" title="Удалить"><i class="fas fa-trash-alt"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; } catch (Exception $e) {} ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="sys-sub-2" class="inner-content">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card-panel h-100">
                            <h5 class="font-weight-bold mb-4">Кабинеты</h5>
                            <form method="POST" class="d-flex mb-4">
                                <input type="text" name="room_name" class="form-control joy-input mr-2 flex-grow-1" placeholder="Название..." required>
                                <button type="submit" name="add_room" class="main-button small-btn btn-auto-width"><i class="fas fa-plus"></i></button>
                            </form>
                            <ul class="list-group">
                                <?php try { $rooms = $pdo->query("SELECT * FROM rooms ORDER BY id DESC")->fetchAll(); foreach($rooms as $r): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="font-weight-bold text-dark-joy"><i class="fas fa-door-open mr-2 text-muted"></i> <?= htmlspecialchars($r['name']) ?></span>
                                    <a href="?delete_room=<?= $r['id'] ?>&tab=system-settings" class="text-danger" onclick="event.preventDefault(); joyConfirm('Удалить?', () => { window.location.href = this.href; })"><i class="fas fa-times-circle"></i></a>
                                </li>
                                <?php endforeach; } catch (Exception $e) {} ?>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card-panel h-100">
                            <h5 class="font-weight-bold mb-4">Темы обращений</h5>
                            <form method="POST" class="d-flex mb-4">
                                <input type="text" name="topic_title" class="form-control joy-input mr-2 flex-grow-1" placeholder="Запрос..." required>
                                <button type="submit" name="add_topic" class="main-button small-btn btn-auto-width"><i class="fas fa-plus"></i></button>
                            </form>
                            <ul class="list-group">
                                <?php try { $topics = $pdo->query("SELECT * FROM topics ORDER BY id DESC")->fetchAll(); foreach($topics as $t): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span class="text-dark-joy"><?= htmlspecialchars($t['title']) ?></span>
                                    <a href="?delete_topic=<?= $t['id'] ?>&tab=system-settings" class="text-danger" onclick="event.preventDefault(); joyConfirm('Удалить?', () => { window.location.href = this.href; })"><i class="fas fa-times-circle"></i></a>
                                </li>
                                <?php endforeach; } catch (Exception $e) {} ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($user['role'] === 'psychologist'): ?>
        <?php 
        $myAppsData = []; $mySlotsData = [];
        if ($specialistData) {
            $rawApps = $pdo->query("SELECT a.id, a.topic, u.name, a.appointment_time, a.guest_name, a.status FROM appointments a LEFT JOIN users u ON a.user_id = u.id WHERE a.specialist_id = {$specialistData['id']} AND a.appointment_time IS NOT NULL AND a.appointment_time != '' AND a.status IN ('new', 'assigned', 'confirmed', 'completed') AND a.is_deleted = 0")->fetchAll();
            foreach($rawApps as $ra) if($ra['appointment_time']) $myAppsData[] = ['id' => (int)$ra['id'], 'time' => $ra['appointment_time'], 'client' => $ra['name'] ?: $ra['guest_name'], 'topic' => $ra['topic'], 'date' => date('Y-m-d', strtotime($ra['appointment_time'])), 'isGroup' => false, 'status' => $ra['status']];
            $rawGrps = $pdo->query("SELECT title, event_date FROM therapy_groups WHERE spec_id = {$specialistData['id']}")->fetchAll();
            foreach($rawGrps as $rg) if($rg['event_date']) $myAppsData[] = ['id' => 0, 'time' => $rg['event_date'], 'client' => 'Группа', 'topic' => $rg['title'], 'date' => date('Y-m-d', strtotime($rg['event_date'])), 'isGroup' => true, 'status' => 'confirmed'];
            $rawSlots = $pdo->query("SELECT id, slot_datetime, notes FROM schedule WHERE specialist_id = {$specialistData['id']} AND is_booked = 0 AND slot_datetime > NOW()")->fetchAll();
            foreach($rawSlots as $rs) $mySlotsData[] = ['id' => (int)$rs['id'], 'time' => $rs['slot_datetime'], 'notes' => $rs['notes']];
        }
        ?>
        <script>const globalPsychApps = <?= json_encode($myAppsData) ?>; const globalPsychSlots = <?= json_encode($mySlotsData) ?>; const psychCalEnabled = true;</script>
        <form method="POST" id="psychCalActionForm" class="d-none">
            <input type="hidden" name="psych_cal_action" value="1">
            <input type="hidden" name="action" id="psychCalActionType" value="">
            <input type="hidden" name="slot_datetime" id="psychCalActionDt" value="">
            <input type="hidden" name="_cabinet_tab" value="psych-appointments">
            <input type="hidden" name="_cabinet_inner_id" value="psychAppTabs">
            <input type="hidden" name="_cabinet_inner" value="1">
        </form>
        <div id="tab-psych-appointments" class="content-tab">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="tab-title m-0">Мои клиенты и расписание</h2>
                <div class="inner-tabs-container m-0" id="psychAppTabs" data-target-prefix="psych-sub">
                    <button class="inner-tab-btn active" onclick="switchInnerTab('psychAppTabs', 0)">Клиенты</button>
                    <button class="inner-tab-btn" onclick="switchInnerTab('psychAppTabs', 1)">Календарь</button>
                    <div class="inner-tab-slider"></div>
                </div>
            </div>
            <div id="psych-sub-0" class="inner-content active">
                <div class="card-panel mb-4 p-3 d-flex align-items-center flex-wrap joy-filter-bar">
                    <select class="form-control form-control-sm joy-select filter-select-fixed" id="filterPsychStatus" onchange="applyPsychAppFilters()">
                        <option value="all">Все статусы</option><option value="new">Новые</option><option value="confirmed">Подтвержденные</option><option value="completed">Архив</option><option value="canceled">Отмененные</option>
                    </select>
                    <select class="form-control form-control-sm joy-select filter-select-fixed" id="filterPsychService" onchange="applyPsychAppFilters()">
                        <option value="all">Все услуги</option><option value="Очная индивидуальная сессия">Очная сессия</option><option value="Онлайн сессия">Онлайн сессия</option><option value="Парная терапия">Парная терапия</option>
                    </select>
                    <div class="filter-input-search-wrapper">
                        <i class="fas fa-search search-icon-input"></i>
                        <input type="text" id="filterPsychClient" class="form-control form-control-sm joy-input filter-input-search-plus" placeholder="Поиск по клиенту" oninput="applyPsychAppFilters()">
                    </div>
                    <input type="date" id="filterPsychDate" class="form-control form-control-sm joy-input w-auto" onchange="applyPsychAppFilters()">
                    <select class="form-control form-control-sm joy-select w-auto ml-auto" id="sortPsychDate" onchange="applyPsychAppFilters()"><option value="desc">Сначала новые</option><option value="asc">Сначала старые</option></select>
                </div>
                <div id="psychAppsContainer" class="joy-apps-scroll">
                    <?php if ($specialistData): 
                        $apps = $pdo->prepare("SELECT a.*, u.surname, u.name, u.email FROM appointments a LEFT JOIN users u ON a.user_id = u.id WHERE a.specialist_id = ? AND a.is_deleted = 0 ORDER BY CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END, a.created_at DESC");
                        $apps->execute([$specialistData['id']]); 
                        $appointmentsList = $apps->fetchAll();

                        if(count($appointmentsList) == 0) echo "<div class='empty-state'><i class='fas fa-user-md empty-icon'></i><h4 class='empty-text'>Нет заявок</h4></div>";

                        foreach($appointmentsList as $app): 
                            $clientName = $app['surname'] ? $app['surname'].' '.$app['name'] : $app['guest_name'];
                            $psychFilterStatus = $app['status'];
                            if (strpos($app['topic'], 'Обратная связь') !== false) $psychFilterStatus = 'callback';
                            $appDisplay = joyParseAppointmentDisplay($app['topic'], $app['service_type'] ?? '');
                            $rowOpacityClass = ($app['status'] == 'completed' || $app['status'] == 'canceled') ? 'item-opacity-low' : 'card-accent-border';
                    ?>
                    <div class="card-panel psych-app-item mb-4 <?= $rowOpacityClass ?>" data-status="<?= $psychFilterStatus === 'callback' ? 'new' : $app['status'] ?>" data-service="<?= htmlspecialchars($app['service_type'] ?? '') ?>" data-client="<?= mb_strtolower($clientName) ?>" data-time="<?= strtotime($app['created_at']) ?>" data-appdate="<?= date('Y-m-d', strtotime(!empty($app['appointment_time']) ? $app['appointment_time'] : $app['created_at'])) ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4 class="font-weight-bold mb-1"><?= htmlspecialchars($appDisplay['title']) ?></h4>
                                <?php if(!$appDisplay['isCallback'] && $appDisplay['tag']): ?>
                                <span class="badge mb-3 d-inline-block badge-service-tag"><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($appDisplay['tag']) ?></span>
                                <?php endif; ?>
                                <p class="mb-1 text-muted"><i class="fas fa-user mr-2 text-dark"></i><strong>Клиент:</strong> <span class="text-dark"><?= htmlspecialchars($clientName) ?></span></p>
                                <div class="mt-3 card-action-request-bg text-muted small text-left"><strong>Запрос:</strong><br><?= nl2br(htmlspecialchars($app['request_text'])) ?></div>
                            </div>
                            <div class="col-md-6 text-md-right mt-3 mt-md-0">
                                <small class="text-muted d-block mb-3">Оформлено: <?= date('d.m.Y H:i', strtotime($app['created_at'])) ?></small>
                                <?php if($app['status'] == 'new' && $psychFilterStatus != 'callback'): ?>
                                    <?= getStatusBadge('new') ?>
                                    <?php if(!empty($app['appointment_time'])): ?>
                                        <div class="mt-3 card-action-request-bg">
                                            <p class="font-weight-bold mb-2 small text-muted text-left">Запрос на время:</p>
                                            <h5 class="mb-3 text-left text-dark-joy"><?= date('d.m.Y в H:i', strtotime($app['appointment_time'])) ?></h5>
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="?reject_app=<?= $app['id'] ?>&tab=admin-appointments" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); var u=this.href; joyConfirm('Отклонить заявку?', () => { joyNavigateCabinet(u); })">Отклонить</a>
                                                <form method="POST" class="m-0 ml-2"><input type="hidden" name="app_id" value="<?= $app['id'] ?>"><input type="hidden" name="new_status" value="confirmed"><button type="submit" name="change_app_status" class="main-button small-btn">Одобрить</button></form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-3 card-action-request-bg">
                                            <form method="POST" class="m-0"><input type="hidden" name="app_id" value="<?= $app['id'] ?>"><input type="hidden" name="new_status" value="confirmed"><button type="submit" name="change_app_status" class="main-button small-btn">Одобрить</button></form>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif($psychFilterStatus != 'callback'): ?>
                                    <div class="p-3 rounded text-left d-inline-block card-action-request-bg box-min-width-300">
                                        <p class="mb-2 text-muted small">Назначенное время:</p>
                                        <h5 class="mb-3 text-dark-joy"><?= !empty($app['appointment_time']) ? date('d.m.Y в H:i', strtotime($app['appointment_time'])) : 'Ожидание' ?></h5>
                                        <form method="POST"><input type="hidden" name="app_id" value="<?= $app['id'] ?>">
                                            <div class="d-flex gap-2 mb-2">
                                                <select name="room_id" class="form-control form-control-sm joy-select flex-grow-1 m-0" onchange="this.form.submit()">
                                                    <option value="">Без кабинета</option>
                                                    <?php $stmtRoomsPsych = $pdo->query("SELECT name FROM rooms ORDER BY id ASC"); while($rm = $stmtRoomsPsych->fetch()): ?>
                                                        <option value="<?= htmlspecialchars($rm['name']) ?>" <?= ($app['room_id'] ?? '') == $rm['name'] ? 'selected' : '' ?>>Каб: <?= htmlspecialchars($rm['name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                                <select name="new_status" class="form-control form-control-sm joy-select flex-grow-1 m-0 bg-white" onchange="this.form.submit()"><option value="confirmed" <?= $app['status']=='confirmed'?'selected':'' ?>>Подтверждена</option><option value="completed" <?= $app['status']=='completed'?'selected':'' ?>>Завершена</option><option value="canceled" <?= $app['status']=='canceled'?'selected':'' ?>>Отменена</option></select>
                                            </div>
                                            <input type="hidden" name="change_app_status" value="1">
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <?= getStatusBadge('new') ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="psych-sub-1" class="inner-content">
                <div class="card-panel mb-4 p-4 d-flex justify-content-between align-items-center flex-wrap joy-basic-schedule-card">
                    <div><h5 class="m-0 font-weight-bold">Мой базовый график</h5><p class="text-muted small m-0">Эта информация видна кураторам.</p></div>
                    <form method="POST" class="d-flex mt-3 mt-md-0 box-min-width-350">
                        <input type="hidden" name="spec_id" value="<?= $specialistData['id'] ?>"><input type="text" name="work_schedule" value="<?= htmlspecialchars($specialistData['work_schedule'] ?? '') ?>" class="form-control joy-input flex-grow-1" placeholder="Пн, Ср, Пт 10:00 - 18:00"><button type="submit" name="update_psych_schedule" class="main-button small-btn ml-2 btn-auto-width" title="Сохранить"><i class="fas fa-save"></i></button>
                    </form>
                </div>
                <div class="calendar-wrapper mb-5">
                    <div class="calendar-left card-panel p-4 m-0 flex-grow-1-5">
                        <div class="calendar-header mb-4"><button type="button" onclick="prevMonth()"><i class="fas fa-chevron-left"></i></button><h4 id="calMonthYear" class="m-0"></h4><button type="button" onclick="nextMonth()"><i class="fas fa-chevron-right"></i></button></div>
                        <div class="calendar-grid" id="calGrid"></div>
                    </div>
                    <div class="calendar-right card-panel p-4 m-0 flex-grow-1 cal-day-view-bg" id="psychDayView">
                        <h5 class="mb-3 font-weight-bold text-center border-bottom pb-3" id="dayViewTitle">Расписание на день</h5>
                        <div class="psych-slot-open-form mb-3 pb-3 border-bottom" id="psychSlotOpenPanel">
                            <form method="POST" id="psychSlotForm" class="psych-slot-toolbar">
                                <input type="hidden" name="spec_id" value="<?= $specialistData['id'] ?>">
                                <input type="hidden" name="add_slot" value="1">
                                <input type="hidden" name="slot_time" id="psychSlotDateTime" value="">
                                <div class="psych-slot-toolbar__field">
                                    <label for="psychCustomSlotTime" class="psych-slot-toolbar__label">Время</label>
                                    <input type="time" id="psychCustomSlotTime" class="form-control form-control-sm joy-input" step="300" value="09:00" required>
                                </div>
                                <button type="submit" class="main-button small-btn psych-slot-toolbar__btn">Открыть окно</button>
                            </form>
                        </div>
                        <div class="daily-timeline daily-timeline-scroll pr-1" id="dailyTimeline"></div>
                    </div>
                </div>
                <div class="card-panel"><h5 class="mb-4 font-weight-bold border-bottom pb-3">Мои открытые окна</h5>
                    <div class="row">
                        <?php if ($specialistData) {
                            $slots = $pdo->prepare("SELECT * FROM schedule WHERE specialist_id = ? AND slot_datetime > NOW() ORDER BY slot_datetime ASC");
                            $slots->execute([$specialistData['id']]); $scheduleList = $slots->fetchAll();
                            if(count($scheduleList) == 0) echo "<div class='col-12'><p class='text-muted small'>Нет открытых окошек.</p></div>";
                            foreach($scheduleList as $slot): 
                                $isBooked = $slot['is_booked'] == 1; $bookedOpacity = $isBooked ? 'item-opacity-mid' : '';
                        ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="p-3 border rounded text-center h-100 d-flex flex-column joy-slot-preview-card <?= $bookedOpacity ?>">
                                <h4 class="mb-1 text-dark-joy"><?= date('H:i', strtotime($slot['slot_datetime'])) ?></h4><p class="text-muted mb-3"><?= date('d.m.Y', strtotime($slot['slot_datetime'])) ?></p>
                                <div class="mt-auto">
                                    <?php if($isBooked): ?><span class="badge badge-booked-full-alt">Занято клиентом</span>
                                    <?php else: ?><span class="badge badge-free-slot">Свободно</span><?php endif; ?>
                                    <a href="cabinet.php?delete_slot=<?= $slot['id'] ?>" class="text-danger small text-underline" onclick="event.preventDefault(); joyConfirm('Удалить слот?', () => { window.location.href = this.href; })">Удалить</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; } ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="tab-psych-profile" class="content-tab" style="display:none;">
            <h2 class="tab-title mb-4">Публичный профиль</h2>
            <div class="card-panel">
                <?php if ($specialistData): ?>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="spec_id" value="<?= $specialistData['id'] ?>"><input type="hidden" name="existing_image" value="<?= htmlspecialchars($specialistData['photo']) ?>">
                    <div class="row border-bottom pb-4 mb-4">
                        <div class="col-md-4 text-center">
                            <img src="<?= htmlspecialchars($specialistData['photo']) ?>" class="profile-edit-avatar-img" onerror="this.src='img/Frame.png'">
                            <div class="custom-file text-left"><input type="file" name="image" class="custom-file-input" id="customFilePsych"><label class="custom-file-label label-file-round" for="customFilePsych">Изменить фото</label></div>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Имя</label><input type="text" name="first_name" value="<?= htmlspecialchars($specialistData['first_name']) ?>" class="form-control joy-input" required></div>
                                <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Отчество</label><input type="text" name="patronymic" value="<?= htmlspecialchars($specialistData['patronymic'] ?? '') ?>" class="form-control joy-input"></div>
                                <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Фамилия</label><input type="text" name="last_name" value="<?= htmlspecialchars($specialistData['last_name']) ?>" class="form-control joy-input" required></div>
                                <div class="col-md-8 mb-3"><label class="font-weight-bold text-muted small">Специализация</label><input type="text" name="specialization" value="<?= htmlspecialchars($specialistData['specialization']) ?>" class="form-control joy-input" required></div>
                                <div class="col-md-4 mb-3"><label class="font-weight-bold text-muted small">Опыт (лет)</label><input type="number" name="experience_years" value="<?= $specialistData['experience_years'] ?>" class="form-control joy-input" required></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Направления работы (через запятую)</label><input type="text" name="directions" value="<?= htmlspecialchars($specialistData['directions'] ?? '') ?>" class="form-control joy-input" placeholder="Тревожность, Самооценка..."></div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Образование</label><textarea name="education" class="form-control joy-input" rows="3" required><?= htmlspecialchars($specialistData['education']) ?></textarea></div>
                    <div class="mb-4"><label class="font-weight-bold text-muted small">Подход и описание</label><textarea name="description" class="form-control joy-input" rows="5" required><?= htmlspecialchars($specialistData['description']) ?></textarea></div>
                    <div class="row p-4 mb-4 mx-0 joy-extra-blocks-container">
                        <div class="col-12 mb-3 px-0"><h6 class="font-weight-bold m-0 text-muted"><i class="fas fa-plus-circle mr-2"></i>Дополнительные блоки (По желанию)</h6></div>
                        <div class="col-md-6 mb-3 pl-0 pr-2">
                            <label class="font-weight-bold text-muted small">Заголовок 1</label><input type="text" name="block1_title" value="<?= htmlspecialchars($specialistData['block1_title'] ?? '') ?>" class="form-control joy-input mb-2">
                            <label class="font-weight-bold text-muted small">Текст 1</label><textarea name="block1_text" class="form-control joy-input" rows="4"><?= htmlspecialchars($specialistData['block1_text'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3 pr-0 pl-2">
                            <label class="font-weight-bold text-muted small">Заголовок 2</label><input type="text" name="block2_title" value="<?= htmlspecialchars($specialistData['block2_title'] ?? '') ?>" class="form-control joy-input mb-2">
                            <label class="font-weight-bold text-muted small">Текст 2</label><textarea name="block2_text" class="form-control joy-input" rows="4"><?= htmlspecialchars($specialistData['block2_text'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" name="update_specialist_profile" class="main-button">Сохранить изменения профиля</button>
                </form>
                <?php else: ?><div class='empty-state'><i class='fas fa-id-card empty-icon'></i><h4 class='empty-text'>Профиль не создан</h4></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($user['role'] === 'client'): ?>

        <div id="tab-sessions" class="content-tab">
            <h2 class="tab-title mb-4">Мои Записи</h2>
            <div class="row">
                <div class="col-lg-12">
                <?php 
                $myApps = $pdo->prepare("SELECT a.*, s.first_name, s.last_name FROM appointments a LEFT JOIN specialists s ON a.specialist_id = s.id WHERE a.user_id=? AND a.is_deleted=0 ORDER BY CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END, a.created_at DESC");
                $myApps->execute([$user['id']]);
                $myGrps = $pdo->prepare("SELECT p.status, g.id as group_id, g.title, g.event_date, g.room_id, s.first_name, s.last_name FROM group_participants p JOIN therapy_groups g ON p.group_id = g.id LEFT JOIN specialists s ON g.spec_id = s.id WHERE p.user_id = ? ORDER BY g.event_date DESC");
                $myGrps->execute([$user['id']]);
                
                if($myApps->rowCount() == 0 && $myGrps->rowCount() == 0) {
                    echo "<div class='empty-state'><i class='far fa-calendar-times empty-icon'></i><h4 class='empty-text'>Нет записей</h4><p class='text-muted mb-4'>У вас пока нет запланированных сессий.</p><a href='specialists.php' class='main-button btn-no-decor'>Выбрать психолога</a></div>";
                }
                while($g = $myGrps->fetch()):
                    $isPast = (!empty($g['event_date']) && strtotime($g['event_date']) < time()); $isWait = ($g['status'] == 'waitlist');
                    $rowOpacityClass = $isPast ? 'item-opacity-mid' : 'card-accent-border';
                ?>
                <div class="card-panel mb-4 <?= $rowOpacityClass ?>">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="m-0 font-weight-bold"><?= htmlspecialchars($g['title']) ?></h4>
                            <div class="mt-2 text-muted small d-flex gap-3">
                                <span class="mr-3"><i class="far fa-calendar-alt"></i> <?= !empty($g['event_date']) ? date('d.m.Y в H:i', strtotime($g['event_date'])) : '' ?></span>
                                <span class="mr-3"><i class="fas fa-user-md"></i> <?= htmlspecialchars($g['first_name'].' '.$g['last_name']) ?></span>
                                <?php if(!empty($g['room_id'])): ?><span><i class="fas fa-door-open"></i> <?= htmlspecialchars($g['room_id']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <?php if($isPast): ?><span class="badge badge-conducted"><i class="fas fa-check-double mr-1"></i> Проведено</span>
                        <?php elseif($isWait): ?><span class="badge badge-waitlist-large"><i class="fas fa-hourglass-half mr-1"></i> Вы в листе ожидания</span><a href="?cancel_group_app=<?= $g['group_id'] ?>" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); joyConfirm('Выйти из ожидания?', () => { window.location.href = this.href; })">Отменить</a>
                        <?php else: ?><span class="badge badge-success-booking"><i class="fas fa-check-circle mr-1"></i> Успешная запись</span><a href="?cancel_group_app=<?= $g['group_id'] ?>" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); joyConfirm('Отменить запись?', () => { window.location.href = this.href; })">Отменить</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php while($a = $myApps->fetch()):
                    $isPast = ($a['status'] == 'completed' || $a['status'] == 'canceled');
                    $rowOpacityClass = $isPast ? 'item-opacity-low' : 'card-accent-border';
                    $clientAppDisplay = joyParseAppointmentDisplay($a['topic'], $a['service_type'] ?? '');
                ?>
                <div class="card-panel mb-4 <?= $rowOpacityClass ?>">
                    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="m-0 font-weight-bold mb-1"><?= htmlspecialchars($clientAppDisplay['title']) ?></h4>
                            <?php if(!$clientAppDisplay['isCallback'] && $clientAppDisplay['tag']): ?>
                            <span class="badge mb-2 d-inline-block badge-service-tag"><i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($clientAppDisplay['tag']) ?></span>
                            <?php endif; ?>
                            <div class="mt-1 text-muted small d-flex gap-3">
                                <?php if(!$clientAppDisplay['isCallback']): ?>
                                    <span class="mr-3"><i class="fas fa-user-md"></i> <?= $a['first_name'] ? htmlspecialchars($a['first_name'].' '.$a['last_name']) : 'Ожидает подбора куратором' ?></span>
                                    <span class="mr-3"><i class="fas fa-door-open"></i> <?= !empty($a['room_id']) ? htmlspecialchars($a['room_id']) : 'Ожидает назначения' ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-history"></i> Создано: <?= date('d.m.Y', strtotime($a['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <?php if($a['status'] == 'assigned' || $a['status'] == 'confirmed'): ?>
                            <span class="badge badge-success-booking-large"><i class="fas fa-check-circle mr-1"></i> Запланировано: <?= !empty($a['appointment_time']) ? date('d.m.Y в H:i', strtotime($a['appointment_time'])) : 'Ожидание' ?></span>
                            <a href="?cancel_app=<?= $a['id'] ?>" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); joyConfirm('Отменить запись?', () => { window.location.href = this.href; })">Отменить</a>
                        <?php elseif($a['status'] == 'completed'): ?><span class="badge badge-conducted-large"><i class="fas fa-check-double mr-1"></i> Сессия проведена</span>
                        <?php elseif($a['status'] == 'canceled'): ?><span class="badge badge-canceled-large"><i class="fas fa-times-circle mr-1"></i> Запись отменена</span>
                        <?php else: ?><span class="badge badge-processing-large"><i class="fas fa-hourglass-half mr-1"></i> Заявка в обработке</span><a href="?cancel_app=<?= $a['id'] ?>" class="btn btn-outline-danger btn-sm btn-round-action-reject" onclick="event.preventDefault(); joyConfirm('Отменить заявку?', () => { window.location.href = this.href; })">Отменить</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
        </div>

<div id="tab-cart" class="content-tab" style="display: none;">
            <h2 class="tab-title mb-4">Корзина</h2>
            <div class="card-panel">
                <div id="cartItemsContainer"></div>
                <div class="mt-4 text-right pt-4 border-top joy-cart-footer" id="cartFooter">
                    <h4 class="mb-4">Итого к оплате: <span id="cartTotal" class="color-accent-joy font-weight-bold font-size-2rem">0</span> BYN</h4>
                    <form action="checkout.php" method="POST" id="orderForm" class="d-inline">
                        <input type="hidden" name="cart_data" id="hiddenCartInput">
                        <button type="button" class="main-button" onclick="submitOrder()"><i class="fas fa-credit-card mr-2"></i> Перейти к оплате</button>
                    </form>
                </div>
            </div>
        </div>

        
        <div id="tab-my-orders" class="content-tab" style="display:none;">
    <h2 class="tab-title mb-4">Мои материалы</h2>
    <div class="row">
        <div class="col-12">
            <?php
            $myOrders = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC");
            $myOrders->execute([$user['id']]);
            
            // пров есть ли
            $allOrders = $myOrders->fetchAll();
            
            if (empty($allOrders)) {
                echo "
                <div class='empty-state'>
                    <i class='fas fa-book-open empty-icon'></i>
                    <h4 class='empty-text'>У вас пока нет материалов</h4>
                    
                    <a href='catalog.php' class='main-button small-btn mt-3' style='display:inline-block; text-decoration:none;'>Перейти в каталог</a>
                </div>";
            } else {
                foreach ($allOrders as $ord) {
                    $isPending = ($ord['status'] == 'pending'); 
                    $rowOpacityClass = $isPending ? 'item-opacity-low' : 'card-accent-border';
                    ?>
                    <div class="card-panel mb-4 <?php echo $rowOpacityClass; ?>">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                            <h4 class="m-0 font-weight-bold">Заказ #<?php echo $ord['id']; ?> <span class="text-muted ml-2 font-size-09rem font-weight-normal"><?php echo date('d.m.Y H:i', strtotime($ord['created_at'])); ?></span></h4>
                            <div class="text-right">
                                <?php if($isPending): ?><span class="badge badge-order-pending-alt">В обработке</span>
                                <?php else: ?><span class="badge badge-order-completed-alt">Доступ открыт</span><?php endif; ?>
                                <span class="badge badge-price-display-alt"><?php echo $ord['total_price']; ?> BYN</span>
                            </div>
                        </div>
                        <ul class="list-unstyled mb-4 text-muted font-size-1rem">
                            <?php 
                            $sqlItems = "SELECT oi.*, p.access_link FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id=?";
                            $stmtItems = $pdo->prepare($sqlItems);
                            $stmtItems->execute([$ord['id']]); 
                            while($i = $stmtItems->fetch()): 
                            ?>
                                <li class='mb-2 d-flex justify-content-between align-items-center p-3 rounded order-item-access-bg'>
                                    <span class="font-weight-bold text-dark-joy"><i class="fas fa-play-circle mr-2 icon-accent-joy"></i> <?php echo htmlspecialchars($i['product_title']); ?></span>
                                    <?php if($isPending): ?><span class="text-muted small"><i class="fas fa-lock mr-1"></i> Доступ закрыт куратором</span>
                                    <?php elseif(!empty($i['access_link'])): ?><a href="<?php echo htmlspecialchars($i['access_link']); ?>" target="_blank" class="main-button small-btn btn-no-decor">Открыть материал</a>
                                    <?php else: ?><span class="badge badge-light border text-muted">Ссылка формируется</span><?php endif; ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                        <div class="text-right"><a href="receipt.php?id=<?php echo $ord['id']; ?>" target="_blank" class="btn btn-outline-dark btn-sm btn-round-action-receipt"><i class="fas fa-file-invoice mr-1"></i> Чек PDF</a></div>
                    </div>
                    <?php 
                } 
            } 
            ?>
        </div>
    </div>
</div>
        
        <?php endif; ?>
    </main>
</div>

<!--модалки) -->
<div class="custom-confirm-overlay" id="joyConfirmModal">
    <div class="custom-confirm-box border-radius-25">
        <i class="fas fa-question-circle custom-confirm-icon"></i>
        <div class="custom-confirm-text" id="joyConfirmText">Вы уверены?</div>
        <div class="custom-confirm-buttons">
            <button class="btn btn-outline-dark btn-round-action-cancel" onclick="closeJoyConfirm()">Отмена</button>
            <button class="main-button btn-auto-width-padding" id="joyConfirmBtn">Да</button>
        </div>
    </div>
</div>

<div id="toast" class="toast-notification"></div>

<!-- рассл -->
<div class="clients-modal-overlay" id="clientsModalOverlay">
    <div class="clients-modal-box">
        <div class="clients-modal-header border-bottom pb-3 mb-3 flex-shrink-0">
            <h4 class="font-weight-bold m-0 font-tenor">Уведомления клиентам</h4>
            <button type="button" class="btn btn-light btn-round-close" onclick="closeClientsModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="notifForm" action="send_notification.php" method="POST" class="notif-form-flex-layout h-100 d-flex flex-column">
            <input type="hidden" name="tab" value="admin-appointments">
            <div class="d-flex mb-3 flex-wrap notif-filters-row flex-shrink-0">
                <div class="filter-input-search-wrapper notif-search-wrap flex-grow-1">
                    <i class="fas fa-search search-icon-input"></i>
                    <input type="text" id="notifClientSearch" class="form-control joy-input filter-input-search-plus" placeholder="Поиск клиента..." oninput="filterNotifClients()">
                </div>
                <select id="notifGroupFilter" class="form-control joy-select flex-grow-1 notif-group-filter" onchange="filterNotifClients()">
                    <option value="all">Все записи</option>
                    <option value="tomorrow">Завтрашние</option>
                    <option value="session">Индивидуальные</option>
                    <option value="waitlist">Очередь на группы</option>
                    <option value="material">Покупатели материалов</option>
                    <?php try { $allGrs = $pdo->query("SELECT id, title FROM therapy_groups ORDER BY event_date DESC")->fetchAll(); foreach($allGrs as $g) echo "<option value='group_{$g['id']}'>Группа: ".htmlspecialchars($g['title'])."</option>"; } catch (Exception $e) {} ?>
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark mb-2 text-left btn-select-visible-clients" onclick="selectAllNotifClients()">Выделить всех видимых</button>
            <div class="clients-modal-body modal-body-scrollable">
                <?php 
                try {
                    $tomorrowStr = date('Y-m-d', strtotime('+1 day'));
                    $actApps = $pdo->query("SELECT a.id, u.name, u.surname, u.email, a.guest_name, a.appointment_time, a.guest_phone FROM appointments a LEFT JOIN users u ON a.user_id=u.id WHERE a.status IN ('confirmed','assigned') AND a.appointment_time IS NOT NULL AND a.appointment_time > NOW() AND a.is_deleted = 0 ORDER BY a.appointment_time ASC")->fetchAll();
                    foreach($actApps as $act): 
                        if (empty($act['email'])) continue;
                        $n = $act['name'] ? trim($act['name'].' '.$act['surname']) : $act['guest_name'];
                        $isTomorrow = (!empty($act['appointment_time']) && date('Y-m-d', strtotime($act['appointment_time'])) === $tomorrowStr) ? 'yes' : 'no';
                ?>
                    <div class="custom-control custom-checkbox notif-client-item mb-2 p-2 rounded client-item-bg-white" data-name="<?= mb_strtolower($n) ?>" data-group="session" data-tomorrow="<?= $isTomorrow ?>">
                        <input type="checkbox" name="clients[]" value="app_<?= $act['id'] ?>" class="custom-control-input" id="client_app_<?= $act['id'] ?>">
                        <label class="custom-control-label cursor-pointer w-100" for="client_app_<?= $act['id'] ?>"><strong class="text-dark-joy"><?= htmlspecialchars($n) ?></strong> <span class="text-muted ml-2">(<?= htmlspecialchars($act['guest_phone'] ?: $act['email']) ?>) — <?= date('d.m H:i', strtotime($act['appointment_time'])) ?></span></label>
                    </div>
                <?php endforeach; 
                    $actGrp = $pdo->query("SELECT p.id as pid, p.group_id, u.name, u.surname, u.email, u.phone, g.title, g.event_date FROM group_participants p JOIN users u ON p.user_id = u.id JOIN therapy_groups g ON p.group_id = g.id WHERE p.status = 'active' AND g.event_date > NOW() ORDER BY g.event_date ASC")->fetchAll();
                    foreach($actGrp as $ag): 
                        if (empty($ag['email'])) continue;
                        $n = trim($ag['name'].' '.$ag['surname']); $isTomorrow = (date('Y-m-d', strtotime($ag['event_date'])) === $tomorrowStr) ? 'yes' : 'no';
                ?>
                    <div class="custom-control custom-checkbox notif-client-item mb-2 p-2 rounded client-item-bg-accent" data-name="<?= mb_strtolower($n) ?>" data-group="group_<?= $ag['group_id'] ?>" data-tomorrow="<?= $isTomorrow ?>">
                        <input type="checkbox" name="clients[]" value="grp_<?= $ag['pid'] ?>" class="custom-control-input" id="client_grp_<?= $ag['pid'] ?>">
                        <label class="custom-control-label cursor-pointer w-100" for="client_grp_<?= $ag['pid'] ?>"><strong class="text-dark-joy"><?= htmlspecialchars($n) ?></strong> <span class="text-accent-joy ml-2">(<?= htmlspecialchars($ag['phone'] ?: $ag['email']) ?>) — Группа <?= date('d.m H:i', strtotime($ag['event_date'])) ?></span></label>
                    </div>
                <?php endforeach;
                    $waitGrp = $pdo->query("SELECT p.id as pid, p.group_id, u.name, u.surname, u.email, u.phone, g.title, g.event_date FROM group_participants p JOIN users u ON p.user_id = u.id JOIN therapy_groups g ON p.group_id = g.id WHERE p.status = 'waitlist' ORDER BY g.event_date ASC, p.created_at ASC")->fetchAll();
                    foreach($waitGrp as $wg):
                        if (empty($wg['email'])) continue;
                        $n = trim($wg['name'].' '.$wg['surname']);
                        $isTomorrow = (!empty($wg['event_date']) && date('Y-m-d', strtotime($wg['event_date'])) === $tomorrowStr) ? 'yes' : 'no';
                ?>
                    <div class="custom-control custom-checkbox notif-client-item mb-2 p-2 rounded client-item-bg-waitlist" data-name="<?= mb_strtolower($n) ?>" data-group="waitlist_group_<?= $wg['group_id'] ?>" data-tomorrow="<?= $isTomorrow ?>">
                        <input type="checkbox" name="clients[]" value="wait_<?= $wg['pid'] ?>" class="custom-control-input" id="client_wait_<?= $wg['pid'] ?>">
                        <label class="custom-control-label cursor-pointer w-100" for="client_wait_<?= $wg['pid'] ?>"><strong class="text-dark-joy"><?= htmlspecialchars($n) ?></strong> <span class="text-muted ml-2">(<?= htmlspecialchars($wg['phone'] ?: $wg['email']) ?>) — <em>Очередь:</em> <?= htmlspecialchars($wg['title']) ?><?= !empty($wg['event_date']) ? ' · '.date('d.m H:i', strtotime($wg['event_date'])) : '' ?></span></label>
                    </div>
                <?php endforeach;
                    $matBuyers = $pdo->query("SELECT DISTINCT u.id as uid, u.name, u.surname, u.email, u.phone FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = 'completed' AND u.email IS NOT NULL AND u.email != '' ORDER BY u.surname, u.name")->fetchAll();
                    foreach($matBuyers as $mb):
                        $n = trim($mb['name'].' '.$mb['surname']);
                ?>
                    <div class="custom-control custom-checkbox notif-client-item mb-2 p-2 rounded client-item-bg-material" data-name="<?= mb_strtolower($n) ?>" data-group="material" data-tomorrow="no">
                        <input type="checkbox" name="clients[]" value="order_<?= $mb['uid'] ?>" class="custom-control-input" id="client_order_<?= $mb['uid'] ?>">
                        <label class="custom-control-label cursor-pointer w-100" for="client_order_<?= $mb['uid'] ?>"><strong class="text-dark-joy"><?= htmlspecialchars($n) ?></strong> <span class="text-muted ml-2">(<?= htmlspecialchars($mb['phone'] ?: $mb['email']) ?>) — <em>Материалы</em></span></label>
                    </div>
                <?php endforeach; } catch (Exception $e) {} ?>
            </div>
            <div class="mt-1 flex-shrink-0">
                <label class="font-weight-bold small text-muted mb-1 d-block">Текст уведомления (шаблон)</label>
                <p class="notif-placeholders-hint mb-2">Подставки: <code>{имя}</code> <code>{дата}</code> <code>{время}</code> <code>{услуга}</code> <code>{кабинет}</code> <code>{специалист}</code> <code>{адрес}</code> <code>{ссылка}</code></p>
                <div class="notif-templates-row mb-2">
                    <button type="button" class="btn btn-sm notif-template-btn" onclick="applyNotifTemplate('reminder')">Напоминание о записи</button>
                    <button type="button" class="btn btn-sm notif-template-btn" onclick="applyNotifTemplate('waitlist')">Извинение (очередь)</button>
                    <button type="button" class="btn btn-sm notif-template-btn" onclick="applyNotifTemplate('material')">Доступ к материалу</button>
                </div>
                <textarea name="message" id="notifMessageText" class="form-control joy-input no-resize" rows="4" required>Здравствуйте, {имя}! Напоминаем о вашей записи в J.O.Y. Center на {дата} в {время}. Услуга: {услуга}. Кабинет: {кабинет}. Ждём вас по адресу: {адрес}.</textarea>
            </div>
            <div class="clients-modal-footer mt-3 d-flex justify-content-end align-items-center border-top pt-3 mb-1 flex-shrink-0">
                <button type="button" class="btn btn-link text-muted mr-3 p-0" onclick="closeClientsModal()">Отмена</button>
                <button type="submit" class="main-button small-btn m-0 btn-auto-width-padding" id="btnSelectClients">Отправить рассылку (0)</button>
            </div>
        </form>
    </div>
</div>

<div class="custom-confirm-overlay" id="orderSuccessModal">
    <div class="custom-confirm-box modal-box-max-500">
        <div class="order-success-icon-container"><i class="fas fa-check order-success-icon"></i></div>
        <h3 class="font-tenor text-dark-joy mb-3">Ваш заказ принят в обработку!</h3>
        <p class="font-lato font-size-105rem text-muted line-height-16 mb-4">
            Доступ будет открыт в течение <b>24 часов</b>.
            <br><br>
            Подтверждение заказа отправлено на вашу почту. Вы сможете открыть приобретенные материалы во вкладке <b>«Мои материалы»</b> сразу после обработки.
        </p>
        <button class="main-button w-100" onclick="document.getElementById('orderSuccessModal').style.display='none'; document.body.style.overflow='';">Отлично, понятно</button>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabName = urlParams.get('tab');

    if (tabName) {
        localStorage.setItem('activeCabinetTab', tabName);
        if (typeof showTab === 'function') {
            showTab(tabName);
        }
    }
});
</script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-ru-RU.min.js"></script>
<script src="joy.js?v=<?= time() ?>"></script>
</body>
</html>