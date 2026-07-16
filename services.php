<?php 
require_once 'db.php';

// бд фикс
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS therapy_groups (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), description TEXT, event_date DATETIME, max_seats INT, spec_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
} catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE therapy_groups ADD COLUMN room_id VARCHAR(50) DEFAULT ''");
} catch(Exception $e) {}

$st = $pdo->query("SELECT * FROM services ORDER BY price ASC");
$srvs = $st->fetchAll();

// берем группы которые еще будут
$grps = $pdo->query("SELECT g.*, s.first_name, s.last_name FROM therapy_groups g LEFT JOIN specialists s ON g.spec_id = s.id WHERE DATE(g.event_date) >= CURRENT_DATE ORDER BY g.event_date ASC")->fetchAll();

$pageTitle = "Консультации | J.O.Y.";
require_once 'header.php';
?>

<section class="section" style="padding-top: 150px; padding-bottom: 100px;">
    <div class="mandala-wrapper-header">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8 text-center">
                <h2 class="section-title mb-3">НАШИ УСЛУГИ</h2>
                <p class="section-text mx-auto">Мы предлагаем различные форматы работы, чтобы терапия была максимально комфортной.</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <?php foreach($srvs as $s): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-price-card h-100 d-flex flex-column shadow-sm">
                        <div class="mb-3">
                            <?php if($s['type'] == 'individual'): ?> <i class="fas fa-user service-icon-accent"></i>
                            <?php elseif($s['type'] == 'family'): ?> <i class="fas fa-user-friends service-icon-accent"></i>
                            <?php elseif($s['type'] == 'online'): ?> <i class="fas fa-laptop service-icon-accent"></i>
                            <?php else: ?> <i class="fas fa-hands-helping service-icon-accent"></i>
                            <?php endif; ?>
                        </div>
                        <h4 class="mb-3 font-tenor"><?= htmlspecialchars($s['title']) ?></h4>
                        <div class="price-box mb-4 mt-auto">
                            <div class="price-value-big"><?= $s['price'] ?> <span class="small">BYN</span></div>
                            <div class="text-muted small">Длительность: <?= $s['duration_min'] ?> минут</div>
                        </div>
                        <button class="main-button small-btn w-100" onclick="openAppointmentForService('<?= htmlspecialchars($s['title']) ?>', event)">Записаться</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- групповые воркш -->
        <?php if(count($grps) > 0): ?>
        <div class="row justify-content-center mt-5 pt-5 border-top">
            <div class="col-lg-10 text-center mb-5"> <h2 class="section-title">ГРУППОВАЯ ТЕРАПИЯ</h2> </div>
            <div class="col-lg-10">
                <?php foreach($grps as $g): 
                    $c = $pdo->prepare("SELECT COUNT(*) FROM group_participants WHERE group_id = ? AND status='active'");
                    $c->execute([$g['id']]);
                    $occ = $c->fetchColumn();
                    $full = $occ >= $g['max_seats'];
                    $perc = ($occ / ((int)$g['max_seats'] ?: 1)) * 100;
                    $sName = trim(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? '')) ?: "Специалист центра";
                ?>
                <div class="group-card-joy mb-5 p-4 shadow-sm" style="background: #fff; border-radius: 20px; border-left: 5px solid #E0C6AD;">
                    <div class="row">
                        <div class="col-12">
                            <h4 class="font-weight-bold text-dark-joy mb-2"><?= htmlspecialchars($g['title']) ?></h4>
                            <div class="text-muted mb-3 small">
                                <i class="far fa-calendar-alt mr-2"></i> <?= date('d.m.Y в H:i', strtotime($g['event_date'])) ?> | 
                                <i class="fas fa-user-md mr-1"></i> <?= htmlspecialchars($sName) ?>
                                <?php if(!empty($g['room_id'])): ?> | Каб: <?= htmlspecialchars($g['room_id']) ?><?php endif; ?>
                            </div>
                            <p class="small mb-4" style="line-height: 1.6;"><?= nl2br(htmlspecialchars($g['description'])) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <div class="flex-grow-1 mr-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small font-weight-bold">Набор группы:</span>
                                        <span class="small <?= $full ? 'text-danger' : 'text-muted' ?>">
                                            <?= $occ ?> из <?= $g['max_seats'] ?> мест
                                        </span>
                                    </div>
                                    <div class="progress joy-progress-container" style="height: 8px; background: #f0f0f0; border-radius: 10px;">
                                        <div class="progress-bar <?= $full ? 'bg-danger' : 'joy-progress-bar' ?>" role="progressbar" 
                                             style="width: <?= $perc ?>%; background-color: #E0C6AD; border-radius: 10px;" 
                                             aria-valuenow="<?= $perc ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>

                                <div class="flex-shrink-0">
                                    <form method="POST" action="join_group.php" class="m-0">
                                        <input type="hidden" name="group_id" value="<?= $g['id'] ?>">
                                        <?php if($full): ?>
                                            <button type="submit" class="btn btn-outline-danger" style="border-radius: 20px; font-size: 0.8rem; padding: 0.6rem 1.2rem;">В лист ожидания</button>
                                        <?php else: ?>
                                            <button type="submit" class="main-button small-btn" style="min-width: 150px; padding: 0.6rem 1.5rem;">Записаться</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- инфа по форматам -->
        <div class="row justify-content-center mt-5 pt-5 border-top">
            <div class="col-lg-12">
                <h3 class="mb-5 text-center font-tenor" style="font-size: 1.8rem;">Как выбрать подходящий формат?</h3>
                <div class="row justify-content-center">
                    
                    <div class="col-md-4 mb-4 px-lg-5 text-center">
                        <h5 class="font-weight-bold color-accent-joy mb-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-check-circle mr-2"></i>Очно в Минске
                        </h5>
                        <p class="text-muted small mx-auto" style="line-height: 1.6; max-width: 280px;">
                            Классический формат для тех, кто ценит личный контакт. В уютном офисе центра создана атмосфера безопасности для глубокой проработки вашего запроса.
                        </p>
                    </div>

                    <div class="col-md-4 mb-4 px-lg-5 text-center">
                        <h5 class="font-weight-bold color-accent-joy mb-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-check-circle mr-2"></i>Онлайн-сессия
                        </h5>
                        <p class="text-muted small mx-auto" style="line-height: 1.6; max-width: 280px;">
                            Удобный выбор, если вы находитесь не в Минске. Сессия проходит в комфортной для вас обстановке, сохраняя полную эффективность очной встречи.
                        </p>
                    </div>

                    <div class="col-md-4 mb-4 px-lg-5 text-center">
                        <h5 class="font-weight-bold color-accent-joy mb-3 d-flex align-items-center justify-content-center">
                            <i class="fas fa-check-circle mr-2"></i>Парная работа
                        </h5>
                        <p class="text-muted small mx-auto" style="line-height: 1.6; max-width: 280px;">
                            Если ваш запрос касается отношений или семейных кризисов — это самый верный выбор. Психолог поможет вам услышать друг друга в безопасном пространстве.
                        </p>
                    </div>

                </div>
            </div>
        </div>
</section>

<?php require_once 'footer.php'; ?>