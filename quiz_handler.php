<?php 
require_once 'db.php';
header('Content-Type: application/json');

// данные из теста
$tar  = $_POST['target'] ?? '';   
$prob = $_POST['problem'] ?? '';  
$form = $_POST['format'] ?? '';   
$styl = $_POST['style'] ?? '';   
$gen  = $_POST['gender'] ?? '';  

if (!function_exists('joySpecDoesCoupleTherapy')) {
    function joySpecDoesCoupleTherapy($text) {
        $keys = ['семейн', 'парн', 'супруж', 'брак', 'отношен', 'family', 'couple'];
        foreach ($keys as $k) {
            if (mb_strpos($text, $k) !== false) return true;
        }
        return false;
    }
}

// берем всех спецов из базы
$st = $pdo->query("SELECT id, first_name, patronymic, last_name, specialization, description, directions, photo FROM specialists");
$specs = $st->fetchAll(PDO::FETCH_ASSOC);

if (empty($specs)) {
    echo json_encode(['success' => false, 'message' => 'no specialists in db']);
    exit;
}

$scored = [];

foreach ($specs as $s) {
    $score = 0;
    $txt = mb_strtolower($s['specialization'] . ' ' . $s['description'] . ' ' . ($s['directions'] ?? ''));

    $fn = mb_strtolower($s['first_name']);
    $last = mb_substr($fn, -1);
    $isFem = in_array($last, ['а', 'я', 'и']); // по именам

    if ($gen === 'female') {
        if ($isFem) $score += 100; else $score -= 1000; // режем мужиков
    } elseif ($gen === 'male') {
        if (!$isFem) $score += 100; else $score -= 1000; // режем женщин
    }

    if ($tar === 'couples') {
        if (joySpecDoesCoupleTherapy($txt)) $score += 30;
    } elseif ($tar === 'child') {
        if (mb_strpos($txt, 'детск') !== false || mb_strpos($txt, 'ребен') !== false || mb_strpos($txt, 'подростк') !== false) $score += 30;
    }

    if ($prob === 'emotional') {
        foreach(['тревог', 'страх', 'апати', 'депресс', 'кпт', 'панич'] as $k) {
            if (mb_strpos($txt, $k) !== false) $score += 10;
        }
    } elseif ($prob === 'relationship') {
        foreach(['отношени', 'конфликт', 'измен', 'развод', 'партнер'] as $k) {
            if (mb_strpos($txt, $k) !== false) $score += 10;
        }
    } elseif ($prob === 'self') {
        foreach(['самооценк', 'бизнес', 'карьер', 'коуч', 'границ', 'уверенн'] as $k) {
            if (mb_strpos($txt, $k) !== false) $score += 10;
        }
    }

    if ($styl === 'soft') {
        foreach(['бережно', 'эмпати', 'поддержк', 'гештальт', 'приняти'] as $k) {
            if (mb_strpos($txt, $k) !== false) $score += 15;
        }
    } elseif ($styl === 'structured') {
        foreach(['кпт', 'структур', 'анализ', 'логика', 'рациональ'] as $k) {
            if (mb_strpos($txt, $k) !== false) $score += 15;
        }
    }

    $s['score'] = $score;
    $scored[] = $s;
}

// сорт
usort($scored, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// берем 1 в списке
$res = $scored[0];

// отдаем 
echo json_encode([
    'success' => true,
    'id'      => $res['id'],
    'name'    => $res['first_name'] . ' ' . ($res['patronymic'] ?? '') . ' ' . $res['last_name'],
    'role'    => $res['specialization'],
    'img'     => $res['photo']
]);