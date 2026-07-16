<?php 
require_once 'db.php';
// берем айди или 1 по дефолту
$id = $_GET['id'] ?? 1;

// костыль для бд если колонки нет
try { 
    $pdo->exec("ALTER TABLE posts ADD COLUMN status VARCHAR(20) DEFAULT 'published'"); 
} catch (Exception $e) {}

// запрос статьи вместе с данными автора
$st = $pdo->prepare("SELECT p.*, s.first_name, s.last_name, s.specialization, s.photo as spec_photo, s.id as spec_id FROM posts p LEFT JOIN specialists s ON p.author_id = s.id WHERE p.id = ?");
$st->execute([$id]);
$art = $st->fetch();

if (!$art) die("Статья не найдена");
$user = getCurrentUser($pdo);

// добавление коммента если юзер залогинен
if (isset($_POST['add_comment']) && $user) {
    $st = $pdo->prepare("INSERT INTO comments (article_id, user_id, text, parent_id) VALUES (?, ?, ?, ?)");
    $st->execute([$id, $user['id'], $_POST['text'], $_POST['parent_id'] ?: NULL]);
    header("Location: article.php?id=$id"); 
    exit;
}

// удаление коммента
if (isset($_GET['del_comment']) && $user) {
    $cId = (int)$_GET['del_comment'];
    $chk = $pdo->prepare("SELECT user_id FROM comments WHERE id=?");
    $chk->execute([$cId]);
    $c = $chk->fetch();
    
    // удаляем если свой или админ
    if ($c && ($c['user_id'] == $user['id'] || $user['role'] == 'admin')) {
        $pdo->prepare("DELETE FROM comments WHERE id=? OR parent_id=?")->execute([$cId, $cId]);
    }
    header("Location: article.php?id=$id"); 
    exit;
}
?>
<?php require_once 'header.php'; ?>

<section class="section article-detail-page" style="padding-top: 150px; padding-bottom: 100px;">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <a href="publications.php" class="article-back-link"> &larr; Вернуться к публикациям </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="article-detail-title text-center mb-5">
                <?= htmlspecialchars($art['title']) ?>
            </h1>
            
            <div class="article-text-body">
                <?= $art['content'] ?>
            </div>

            <!-- блок про автора -->
            <?php if($art['spec_id']): ?>
            <div class="article-author-card shadow-sm">
                <img src="<?= htmlspecialchars($art['spec_photo']) ?>" alt="Автор" class="author-img-round" onerror="this.src='img/Frame.png'">
                <div>
                    <h5 class="m-0 font-tenor">Автор: <?= htmlspecialchars($art['first_name'] . ' ' . $art['last_name']) ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($art['specialization']) ?></p>
                    <a href="profile.php?id=<?= $art['spec_id'] ?>" class="btn btn-sm btn-outline-dark" style="border-radius: 20px; font-size: 0.8rem;">Профиль психолога</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <hr style="margin: 4rem 0; border-color: #E0C6AD; opacity: 0.3;">
    
    <!-- комменты тут -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h3 class="comments-section-title">Комментарии</h3>
            
            <?php if($user): ?>
            <form method="POST" class="mb-5">
                <textarea name="text" class="form-control comment-textarea-joy" rows="3" placeholder="Ваши мысли по поводу прочитанного..." required></textarea>
                <input type="hidden" name="parent_id" value="">
                <button type="submit" name="add_comment" class="main-button small-btn">Отправить</button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning mb-5" style="border-radius: 15px; font-family: 'Lato', sans-serif;">
                Чтобы оставить комментарий, пожалуйста, <a href="#" class="font-weight-bold color-accent-joy" onclick="openAuthModal(event)">войдите в аккаунт</a>.
            </div>
            <?php endif; ?>
            
            <div class="comments-list">
                <?php
                $coms = $pdo->prepare("SELECT c.*, u.name, u.role FROM comments c JOIN users u ON c.user_id = u.id WHERE article_id=? AND parent_id IS NULL ORDER BY created_at DESC");
                $coms->execute([$id]);
                while($c = $coms->fetch()):
                ?>
                <div class="comment-item">
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <div>
                            <strong class="text-dark-joy"><?= htmlspecialchars($c['name']) ?></strong>
                            <?php if($c['role']=='admin'): ?><span class="comment-badge-joy">Admin</span><?php endif; ?>
                            <?php if($c['role']=='psychologist'): ?><span class="comment-badge-joy" style="background:#85776a;">Психолог</span><?php endif; ?>
                        </div>
                        <span class="text-muted small"><?= date('d.m.Y', strtotime($c['created_at'])) ?></span>
                    </div>
                    <p class="mb-2 text-muted small"><?= htmlspecialchars($c['text']) ?></p>
                    
                    <div class="d-flex align-items-center">
                        <?php if($user && $user['role'] == 'admin'): ?>
                        <button class="btn btn-link btn-sm p-0 text-muted mr-3" onclick="document.getElementById('reply-<?=$c['id']?>').style.display='block'">Ответить</button>
                        <?php endif; ?>
                        
                        <?php if($user && ($user['id'] == $c['user_id'] || $user['role'] == 'admin')): ?>
                        <a href="?id=<?=$id?>&del_comment=<?=$c['id']?>" class="text-danger small" onclick="return confirm('Удалить?')"><i class="fas fa-trash-alt"></i></a>
                        <?php endif; ?>
                    </div>

                    <?php if($user && $user['role'] == 'admin'): ?>
                    <form method="POST" id="reply-<?=$c['id']?>" style="display:none;" class="mt-3 ml-4">
                        <textarea name="text" class="form-control mb-2" placeholder="Ответ админа" style="border-radius: 10px;" required></textarea>
                        <input type="hidden" name="parent_id" value="<?=$c['id']?>">
                        <button type="submit" name="add_comment" class="btn btn-sm btn-dark" style="border-radius: 15px;">Отправить</button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- вложенные ответы -->
                    <?php
                    $reps = $pdo->prepare("SELECT c.*, u.name, u.role FROM comments c JOIN users u ON c.user_id = u.id WHERE parent_id=? ORDER BY created_at ASC");
                    $reps->execute([$c['id']]);
                    while($r = $reps->fetch()):
                    ?>
                    <div class="comment-reply-wrapper">
                        <div class="d-flex justify-content-between mb-1">
                            <strong class="text-dark-joy"><?= htmlspecialchars($r['name']) ?></strong>
                            <?php if($user && ($user['id'] == $r['user_id'] || $user['role'] == 'admin')): ?>
                            <a href="?id=<?=$id?>&del_comment=<?=$r['id']?>" class="text-danger small" onclick="return confirm('Удалить?')"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                        <p class="m-0 small text-muted"><?= htmlspecialchars($r['text']) ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>
</section>

<?php require_once 'footer.php'; ?>