<?php
    require_once dirname ( __FILE__ ) . "/../../undefined/DSN.php";
    try {
        $pdo = new PDO ( 'mysql:host=' . $dsn ['host'] . ';dbname=' . $dsn ['dbname'] . ';charset=utf8', $dsn ['user'], $dsn ['pass'], array (
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ) );
    } catch ( PDOException $e ) {
        exit ( 'connection unsuccess' . $e->getMessage () );
    }
    
    echo '
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <link rel="stylesheet" type="text/css" href="style.css">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="lib/animatedtablesorter/tsort.js"></script>
    <script src="lib/animatedtablesorter/setting.js"></script>
    <title>ディフェンドオーダー発生履歴 - svr.aki-memo.net</title>
    </head>
    <body>
    ';
    
    $GLOBALS['count'] = ["A" => 0, "B" => 0, "C" => 0, "D" => 0, "E" => 0, "F" => 0, "G" => 0, "H" => 0];;
    
    function doHolding($groupString, $group){
        if(strpos($groupString, $group) !== false){
            $return[$group] = "■";
            $GLOBALS['count'][$group]++;
        }else{
            $return[$group] = "";
        }
        return $return;
    }
    
    $sql = "SELECT * FROM `djeetadefendorder` ORDER BY `time`";
    $stmt=$pdo->prepare($sql);
    $res=$stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '
    
    <h1>ディフェンドオーダー開催履歴(仮)</h1>
    <p>取り敢えず作りました。<br>botが稼働してからのデータのみです。気が向けば過去のデータも取得するかもしれません。<br>
    表の一番上を押すとソートできます。<br>
    <a href="http://aki-memo.net/defendorderbot" target="_blank">Botについてはこちら</a></p>
    
    <table class="tableSorter">
    <thead>
    <tr><th>時間</th><th>場所</th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th><th>H</th>
    </thead>
    <tbody>
    ';
    
    $allGroup = ["A", "B", "C", "D", "E", "F", "G", "H"];      //foreachとかで回せると楽なので
    $groupStringArr = [
    'A' => "",
    'B' => "",
    'C' => "",
    'D' => "",
    'E' => "",
    'F' => "",
    'G' => "",
    'H' => "",
    ]; //ここをhtmlで出力する
    
    foreach ($result as $value) {
        
        foreach ($allGroup as $key) {
            $groupStringArr = array_merge($groupStringArr, doHolding($value["group"], $key));
        }
        //ここから１列出力
        echo '<tr><td>' . $value['time_str'] .  '</td><td>' . $value['place'] . '</td>';
        foreach ($allGroup as $gs) {
            echo '<td data-sortAs="' . $groupStringArr[$gs] . "-" .  $value['time'] .  '">' . $groupStringArr[$gs] . "</td>";
        }
        echo "</tr>";
        
        $groupStringArr = [ //初期化して終わり
        'A' => "",
        'B' => "",
        'C' => "",
        'D' => "",
        'E' => "",
        'F' => "",
        'G' => "",
        'H' => "",
        ];
    }
    
    echo '</tbody></table>';
    
    echo "<p>";
    foreach ($allGroup as $key) {
        echo $key . "グループ：" . $GLOBALS['count'][$key] . "回<br>";
    }
    echo "</p>";
    
    echo "</body></html>";