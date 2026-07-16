<?php 
require_once 'header.php'; 

$q = $_GET['search'] ?? '';
$cat = $_GET['category'] ?? '';
$srt = $_GET['sort'] ?? 'new';
$p = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$lim = 6; 
$off = ($p - 1) * $lim;

// база для запроса
$sqlBase = "FROM products WHERE 1=1";
$params = [];

// если есть категория
if ($cat) {
    $sqlBase .= " AND category = :category";
    $params[':category'] = $cat;
}

// если есть поиск
if ($q) {
    $sqlBase .= " AND title LIKE :search";
    $params[':search'] = "%$q%";
}

// сортировки
$ord = "ORDER BY id DESC"; 
if ($srt == 'price_asc') { $ord = "ORDER BY price ASC"; } 
elseif ($srt == 'price_desc') { $ord = "ORDER BY price DESC"; } 
elseif ($srt == 'old') { $ord = "ORDER BY id ASC"; }

// считаем сколько всего товаров для пагинации
$cSt = $pdo->prepare("SELECT COUNT(*) $sqlBase");
$cSt->execute($params);
$total = $cSt->fetchColumn();
$totalP = ceil($total / $lim);

// сам запрос товаров
$sql = "SELECT * $sqlBase $ord LIMIT $lim OFFSET $off";
$st = $pdo->prepare($sql);
$st->execute($params);
$prods = $st->fetchAll();
?>

<section class="section catalog-main-section">
    <div class="mandala-wrapper-header">
        <img src="img/Group 186.png" alt="Фон" class="rotating-mandala">
    </div>
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">ОНЛАЙН-ПРОДУКТЫ</h2>
            <h3 class="subsection-title">Выберите медитации и курсы</h3>
        </div>
        
        <!-- фильтрц -->
        <div class="filter-panel mb-5">
            <form method="GET" action="catalog.php" id="catalogFilterForm" class="row justify-content-center align-items-center">
                <div class="col-md-4 mb-3">
                    <input type="text" name="search" id="searchInput" class="form-control joy-input" placeholder="Поиск по названию..." value="<?= htmlspecialchars($q) ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <select name="category" class="form-control joy-select" onchange="this.form.submit()">
                        <option value="">Все категории</option>
                        <option value="meditation" <?= $cat == 'meditation' ? 'selected' : '' ?>>Медитации</option>
                        <option value="course" <?= $cat == 'course' ? 'selected' : '' ?>>Курсы</option>
                        <option value="club" <?= $cat == 'club' ? 'selected' : '' ?>>Закрытый клуб</option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <select name="sort" class="form-control joy-select" onchange="this.form.submit()">
                        <option value="new" <?= $srt == 'new' ? 'selected' : '' ?>>Сначала новые</option>
                        <option value="old" <?= $srt == 'old' ? 'selected' : '' ?>>Сначала старые</option>
                        <option value="price_asc" <?= $srt == 'price_asc' ? 'selected' : '' ?>>Сначала дешевые</option>
                        <option value="price_desc" <?= $srt == 'price_desc' ? 'selected' : '' ?>>Сначала дорогие</option>
                    </select>
                </div>
                
                <?php if($q || $cat || $srt != 'new'): ?>
                <div class="col-12 text-center mt-2">
                    <a href="catalog.php" class="text-muted small text-underline">Сбросить фильтры</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- товары -->
        <div class="row" id="catalog-grid">
            <?php if(count($prods) > 0): ?>
                <?php foreach ($prods as $row): ?>
                <div class="col-md-4 col-sm-6 mb-4">
                    <div class="catalog-item">
                        <div class="catalog-img-circle">
                            <img src="<?= htmlspecialchars($row['image']) ?>" alt="<?= htmlspecialchars($row['title']) ?>" onerror="this.src='img/Frame.png'">
                        </div>
                        <h5 class="font-tenor"><?= htmlspecialchars($row['title']) ?></h5>
                        <p class="catalog-item-desc"><?= htmlspecialchars($row['description']) ?></p>
                        <div class="catalog-item-price"><?= $row['price'] ?> BYN</div>
                        <button class="main-button small-btn" onclick="addToCart(<?= $row['id'] ?>, '<?= htmlspecialchars($row['title']) ?>', <?= $row['price'] ?>, '<?= htmlspecialchars($row['image']) ?>')">
                            В корзину
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <h4 class="text-muted">Ничего не найдено :(</h4>
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
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p - 1])) ?>">&laquo;</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalP; $i++): ?>
                        <li class="page-item <?= ($p == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($p >= $totalP) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p + 1])) ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
    // поиск
    let t;
    const sInp = document.getElementById('searchInput');
    if(sInp) {
        sInp.addEventListener('input', function() {
            clearTimeout(t);
            t = setTimeout(() => { document.getElementById('catalogFilterForm').submit(); }, 600);
        });
    }
</script>

<?php require_once 'footer.php'; ?>