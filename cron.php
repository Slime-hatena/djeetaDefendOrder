<?php

// cowitter ロード処理
require dirname ( __FILE__ ) . "/../../undefined/djeetaDefendOrder.php";
require __DIR__ . '/vendor/autoload.php';
use mpyw\Co\Co;
use mpyw\Co\CURLException;
use mpyw\Cowitter\Client;
use mpyw\Cowitter\HttpException;
$client = new Client([$consumer_key, $consumer_secret,
$TwitterUsers[0]['access_token'], $TwitterUsers[0]['access_token_secret']]);
$client = $client->withOptions([CURLOPT_CAINFO => __DIR__ . '/vendor/cacert.pem']);
// PDOロード処理
require_once dirname ( __FILE__ ) . "/../../undefined/DSN.php";
try {
    $pdo = new PDO ( 'mysql:host=' . $dsn ['host'] . ';dbname=' . $dsn ['dbname'] . ';charset=utf8', $dsn ['user'], $dsn ['pass'], array (
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ) );
} catch ( PDOException $e ) {
    exit ( 'connection unsuccess' . $e->getMessage () );
}
// ロード処理ここまで


function checkString($string)
{
    // **正規表現であれやこれやして取り出す処理**
    // 日付を割り出す
    $pattern0 = "/【グランブルーファンタジー】【DEFEND ORDER】\n.*([0-9]{1,2}\/[0-9]{1,2}).* ([0-9]{1,2}:[0-9]{1,2})より、.*「(.*)」.*\n参加対象はグループ(.*)です。/um";
    preg_match_all($pattern0, $string, $query_date, PREG_SET_ORDER);

    var_dump($query_date);

    foreach ($query_date as $key => $value) {
        $replaceQuery[$key]["string"] = $value[0];
        $replaceQuery[$key]["time"] =     strtotime(date("Y",strtotime("-1 day")) . "/" . $value[1] . " " . $value[2]);
        $replaceQuery[$key]["time_str"]  = date('Y/m/d H:i', $replaceQuery[$key]["time"]);
        $replaceQuery[$key]["place"] = $value[3];
        $replaceQuery[$key]["group"] = $value[4];
        $replaceQuery[$key]["group"] = str_replace("/" , "" ,  $replaceQuery[$key]["group"]);
        $replaceQuery[$key]["group"] = str_replace("#グラブル" , "" ,  $replaceQuery[$key]["group"]);
    }
    var_dump ($replaceQuery);
    echo "<hr>";
    return $replaceQuery;
}

function getGbfNotice($client){
    // **ツイートを取得して配列化する**
    $statuses = $client->get('search/tweets', ['q' => 'from:granbluefantasy 【グランブルーファンタジー】【DEFEND ORDER】 にて防衛戦が発生します。 #グラブル '])->statuses;
    foreach ($statuses as $key => $value) {
        $tweets_gbf_notice[$key] = array(
        "id" => $value -> id_str,
        "time" => strtotime($value -> created_at),
        "text" => $value -> text
        );
    }

    return $tweets_gbf_notice;
}


function postTweetAll($time, $time_str, $place, $group, $client){
    // **ALL用のアカウントにツイートさせる処理**
    
    echo time() . " - " . $time . "<br>";
    $difference = $time - time();
    echo $difference . "<br>";
    
    $tweetFlag = false;
    
    if ($difference < 1800){
                $tweet_time =  preg_replace('/^0/','',date("i分後",$difference)); //なぜ時刻の書式に0埋めなし分が無いのか
        $tweetFlag = true;}
        else{        
        echo "このメッセージはでないはずだよ でたら おしえてね";
    }
    if($tweetFlag){
        // グループを記号で区切る
        $groupArr = str_split($group);
        $tweetGroup = "";
        foreach ($groupArr as $value) {
            $tweetGroup = $tweetGroup . $value . "/";
        }
        $tweetGroup = substr($tweetGroup, 0, -1);
        
        $tweetString =
        "【防衛戦発生予告】[" . $time_str . "]\n約" . $tweet_time. "「" . $place . "」にて発生します。\n対象グループ : " . $tweetGroup . " #グラブル #ディフェンドオーダー";
        echo "<pre>" . $tweetString . "</pre>";
        $client->post('statuses/update', ['status' =>$tweetString]);
        echo "Allアカウント : ツイート<br>";
        
        
        //各グループアカウントでツイート
        foreach ($groupArr as $value) {
           postTweetGroup($value ,$tweetString);
            echo $value . "アカウント : ツイート<br>";
        }
    }
}

function postTweetGroup($group, $tweetString){
    require dirname ( __FILE__ ) . "/../../undefined/djeetaDefendOrder.php";
    //ユーザーを変えるので新しくロード
    $client = new Client([$consumer_key, $consumer_secret,
    $TwitterUsers[$group]['access_token'], $TwitterUsers[$group]['access_token_secret']]);
    $client = $client->withOptions([CURLOPT_CAINFO => __DIR__ . '/vendor/cacert.pem']);
    $client->post('statuses/update', ['status' =>$tweetString]);
}

// 取得してチェックさせる
$tweets_gbf_notice = getGbfNotice($client); //ここクライアントを送ってあげないと取得できないので
foreach ($tweets_gbf_notice as $key => $value) {
    $gbf_notice_group[$key] = checkString($value["text"]);
}

// データベースに入れる処理
foreach ($gbf_notice_group as $key => $value_n) {

    foreach ($value_n as $key => $value) {
        $sql = "INSERT IGNORE  INTO `djeetadefendorder` (`time`, `time_str`, `group`, `place`) values (?,?,?,?)";
        $stmt=$pdo->prepare($sql);
        $res=$stmt->execute(array($value["time"], $value["time_str"], $value["group"] ,$value["place"]));
        echo "<pre>" . "time : " . $value["time"] .
        "<br>time_str : " . $value["time_str"] .
        "<br>group : " . $value["group"] .  
        "<br>place : " . $value['place'] . 
        "</pre>";
        sleep(0.5);
    }

}

echo "<hr>";

$time = time();

// データーベースから直近の内容を抜き出す
$sql = "SELECT  `time` ,`time_str` , `group`, `place`  FROM `djeetadefendorder` WHERE `time` <=  ? AND `time` >=  ?";
$stmt=$pdo->prepare($sql);
$res=$stmt->execute(array($time  + 113610 , $time - 10));

$result = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($result);

postTweetAll($result['time'], $result['time_str'], $result['place'] ,$result['group'], $client);