<?php 
require_once 'header.php'; 

// настройки пагинации
$p = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$lim = 6; 
$off = ($p - 1) * $lim;

// костыль для базы если колонки нет
try { $pdo->exec("ALTER TABLE posts ADD COLUMN status VARCHAR(20) DEFAULT 'published'"); } catch (Exception $e) {}

// считаем сколько всего статей 
$cSt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published' OR status IS NULL");
$total = $cSt->fetchColumn();
$totalP = ceil($total / $lim);

// тянем сами статьи
$st = $pdo->prepare("SELECT * FROM posts WHERE status = 'published' OR status IS NULL ORDER BY created_at DESC LIMIT $lim OFFSET $off");
$st->execute();
$arts = $st->fetchAll();
?>

<section class="section" style="padding-top: 150px; padding-bottom: 100px; background: white;">
    <div class="mandala-wrapper-header">
        <img src="img/Group 186.png" class="rotating-mandala">
    </div>
    <div class="container">
        <div class="row justify-content-center mb-5">
            <div class="col-lg-10 text-center">
                <h2 class="section-title">ПРОСТРАНСТВО ЗНАНИЙ</h2>
                <h3 class="subsection-title">Наши публикации</h3>
            </div>
        </div>
        
        <div class="row publications-grid" id="publicationsList">
            <?php if(count($arts) > 0): ?>
                <?php foreach ($arts as $a): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 publication-card">
                        <img src="<?= htmlspecialchars($a['image']) ?>" class="card-img-top publication-card__img" onerror="this.src='img/Frame.png'">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title publication-card__title"><?= htmlspecialchars($a['title']) ?></h5>
                            <p class="card-text text-muted publication-card__text"><?= htmlspecialchars($a['short_desc']) ?></p>
                            <a href="article.php?id=<?= $a['id'] ?>" class="btn-link stretched-link" style="color: #E0C6AD; font-weight: bold; text-decoration: none;">Читать далее &rarr;</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <h4 class="text-muted">Публикаций пока нет.</h4>
                </div>
            <?php endif; ?>
        </div>

        <!-- пагинация страниц -->
        <?php if ($totalP > 1): ?>
        <div class="row mt-5">
            <div class="col-12">
                <nav>
                    <ul class="pagination justify-content-center joy-pagination">
                        <li class="page-item <?= ($p <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $p - 1 ?>">&laquo;</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalP; $i++): ?>
                        <li class="page-item <?= ($p == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($p >= $totalP) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $p + 1 ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'footer.php'; ?>