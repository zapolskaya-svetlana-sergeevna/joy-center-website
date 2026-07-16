<?php 
require_once 'db.php';

// берем айдишник из гет
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: specialists.php"); exit; }

// достаем инфу о спеце
$st = $pdo->prepare("SELECT * FROM specialists WHERE id = ?");
$st->execute([$id]);
$spec = $st->fetch();

if (!$spec) { header("Location: specialists.php"); exit; }

// отзывы только одобренные
$stR = $pdo->prepare("SELECT r.*, u.name as client_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.specialist_id = ? AND r.status = 'approved' ORDER BY r.created_at DESC");
$stR->execute([$id]);
$revs = $stR->fetchAll();

// считаем среднюю оценку
$avg = 0;
if (count($revs) > 0) {
    $sum = 0;
    foreach ($revs as $r) $sum += $r['rating'];
    $avg = round($sum / count($revs), 1);
}

// пров может ли юзер писать отзыв 
$canRev = false;
if (isset($_SESSION['user_id'])) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND specialist_id = ? AND status = 'completed'");
    $chk->execute([$_SESSION['user_id'], $id]);
    if ($chk->fetchColumn() > 0) $canRev = true;
}

$pageTitle = htmlspecialchars($spec['first_name'] . ' ' . $spec['last_name']) . " | J.O.Y.";
require_once 'header.php';
?>

<section class="section" style="padding-top: 150px; padding-bottom: 100px;">
    <div class="mandala-wrapper-quiz">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>    

    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <a href="specialists.php" class="back-link" style="text-align: left;"><i class="fas fa-arrow-left"></i> К списку психологов</a>
            </div>
        </div>

        <div class="row">
            <!-- фотка и статы -->
            <div class="col-lg-4 mb-5 mb-lg-0 text-center">
                <div class="profile-photo-frame mb-4">
                    <img src="<?= htmlspecialchars($spec['photo']) ?>" onerror="this.src='img/Frame.png'">
                </div>
                <h2 class="profile-name mb-1"><?= htmlspecialchars($spec['first_name'] . ' ' . ($spec['patronymic'] ?? '') . ' ' . $spec['last_name']) ?></h2>
                <p class="profile-role mb-3"><?= htmlspecialchars($spec['specialization']) ?></p>
                
                <div class="profile-stats-container">
                    <div class="profile-stat-box">
                        <div class="profile-stat-number"><?= $spec['experience_years'] ?></div>
                        <div class="profile-stat-label">Лет опыта</div>
                    </div>
                    <div class="profile-stat-box">
                        <div class="profile-stat-number"><i class="fas fa-star rating-stars-gold"></i> <?= $avg > 0 ? $avg : '—' ?></div>
                        <div class="profile-stat-label">Рейтинг</div>
                    </div>
                </div>

                <!-- слоты для записи -->
                <?php
                $stS = $pdo->prepare("SELECT * FROM schedule WHERE specialist_id = ? AND is_booked = 0 AND slot_datetime > NOW() ORDER BY slot_datetime ASC LIMIT 6");
                $stS->execute([$id]);
                $slots = $stS->fetchAll();
                ?>

                <div class="card-action-request-bg text-left mb-4">
                    <h6 class="font-weight-bold mb-3">Свободное время для записи:</h6>
                    <?php if (count($slots) > 0): ?>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($slots as $s): 
                                $d = date('d.m', strtotime($s['slot_datetime']));
                                $t = date('H:i', strtotime($s['slot_datetime']));
                            ?>
                                <button class="btn btn-outline-dark btn-sm slot-btn-profile" 
                                onclick="openAppointmentForSpec(<?= $spec['id'] ?>, '<?= htmlspecialchars($spec['first_name'].' '.$spec['last_name']) ?>', <?= $s['id'] ?>, '<?= $d ?> в <?= $t ?>', event)">
                                    <?= $d ?> | <?= $t ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted m-0 small">В данный момент нет свободных окон. Оставьте запрос на обратный звонок.</p>
                        <button class="main-button small-btn w-100 mt-3" onclick="document.getElementById('callbackModal').style.display='flex'; event.preventDefault();">Обратная связь</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- табы с инфой -->
            <div class="col-lg-8">
                <ul class="nav nav-tabs profile-tabs-header mb-4" id="profileTab">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#about">Обо мне</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#reviews">Отзывы (<?= count($revs) ?>)</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#articles">Статьи</a></li>
                </ul>

                <div class="tab-content" id="profileTabContent">
                    <!-- инфа о себе -->
                    <div class="tab-pane fade show active" id="about">
                        <?php if(!empty($spec['directions'])): ?>
                            <div class="mb-4">
                                <h4 class="font-tenor mb-3">Направления работы</h4>
                                <div class="directions-list">
                                    <?php foreach(explode(',', $spec['directions']) as $dir): ?>
                                        <?php if(trim($dir) !== ''): ?>
                                        <span class="direction-tag"><?= htmlspecialchars(trim($dir)) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <h4 class="font-tenor mb-3">Образование</h4>
                        <div class="profile-info-card">
                            <p class="m-0 profile-text-content"><?= nl2br(htmlspecialchars($spec['education'])) ?></p>
                        </div>

                        <h4 class="font-tenor mb-3">О подходе</h4>
                        <div class="mb-4 profile-text-content"> <?= nl2br(htmlspecialchars($spec['description'])) ?> </div>

                        <?php if(!empty($spec['block1_title'])): ?>
                            <div class="mb-4">
                                <h4 class="font-tenor mb-3"><?= htmlspecialchars($spec['block1_title']) ?></h4>
                                <div class="profile-text-content"><?= nl2br(htmlspecialchars($spec['block1_text'])) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($spec['block2_title'])): ?>
                            <div class="mb-4">
                                <h4 class="font-tenor mb-3"><?= htmlspecialchars($spec['block2_title']) ?></h4>
                                <div class="profile-text-content"><?= nl2br(htmlspecialchars($spec['block2_text'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- отзывы юзеров -->
                    <div class="tab-pane fade" id="reviews">
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <h4 class="m-0 font-tenor">Отзывы клиентов</h4>
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <?php if($canRev): ?>
                                    <button class="main-button small-btn" onclick="document.getElementById('reviewModal').style.display='flex'">Оставить отзыв</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (count($revs) == 0): ?>
                            <p class="text-muted text-center py-5">Отзывов пока нет.</p>
                        <?php else: ?>
                            <div class="reviews-list">
                                <?php foreach ($revs as $r): ?>
                                    <div class="review-item-card">
                                        <div class="d-flex justify-content-between mb-2">
                                            <h5 class="m-0 font-weight-bold"><?= htmlspecialchars($r['client_name']) ?></h5>
                                            <div class="rating-stars-gold">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $r['rating'] ? '' : 'text-light' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mb-3"><?= date('d.m.Y', strtotime($r['created_at'])) ?></small>
                                        <p class="m-0 profile-text-content"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- статьи психолога -->
                    <div class="tab-pane fade" id="articles">
                        <h4 class="font-tenor mb-4">Публикации</h4>
                        <?php
                        $stP = $pdo->prepare("SELECT * FROM posts WHERE author_id = ? AND (status = 'published' OR status IS NULL) ORDER BY created_at DESC");
                        $stP->execute([$id]);
                        $posts = $stP->fetchAll();
                        ?>
                        <div class="row">
                            <?php foreach($posts as $p): ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm" style="border-radius: 15px;">
                                    <img src="<?= htmlspecialchars($p['image']) ?>" class="card-img-top" style="height: 180px; object-fit: cover;">
                                    <div class="card-body d-flex flex-column p-3">
                                        <h6 class="font-weight-bold font-tenor"><?= htmlspecialchars($p['title']) ?></h6>
                                        <a href="article.php?id=<?= $p['id'] ?>" class="color-accent-joy mt-auto">Читать далее &rarr;</a>
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
</section>

<!-- модалка каб написать отзыв -->
<div class="modal-form" id="reviewModal">
    <form class="form-container" action="submit_review.php" method="POST">
        <span class="close-btn" onclick="document.getElementById('reviewModal').style.display='none'">&times;</span>
        <div class="form-title">ВАШ ОТЗЫВ</div>
        <input type="hidden" name="specialist_id" value="<?= $spec['id'] ?>">
        <div class="mb-3 text-center">
            <label>Оценка:</label>
            <select name="rating" class="form-control joy-select">
                <option value="5">⭐⭐⭐⭐⭐</option>
                <option value="4">⭐⭐⭐⭐</option>
                <option value="3">⭐⭐⭐</option>
                <option value="2">⭐⭐</option>
                <option value="1">⭐</option>
            </select>
        </div>
        <div class="mb-4">
            <label>Текст:</label>
            <textarea class="form-control joy-input" name="review_text" rows="4" required></textarea>
        </div>
        <button type="submit" class="submit-btn">ОТПРАВИТЬ</button>
    </form>
</div>

<?php require_once 'footer.php'; ?>