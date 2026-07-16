<?php 
// дата и время
function getJoyDate() {
    $ms = [1=>'Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'];
    return date('d') . ' ' . $ms[date('n')] . ' ' . date('Y');
}

// мелкий календарь для админки
function drawMiniCalendar() {
    $d = date('d');
    return "<div style='font-size:0.8rem; text-align:center; margin-top:10px; color:#888;'>
            Сегодня: <span style='color:#E0C6AD; font-weight:bold;'>".getJoyDate()."</span>
            </div>";
}

// список фоток для выбора
function getImagesFromDir() {
    $dir = scandir(__DIR__ . '/img');
    $res = [];
    foreach($dir as $f) {
        if($f!='.' && $f!='..') $res[] = $f;
    }
    return array_slice($res, 0, 5);
}
?>