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
    $pattern0 = "/・[0-9]{1,2}\/[0-9]{1,2}.*\([月火水木金土日]{1}\)/um";
    preg_match_all($pattern0, $string, $query_date, PREG_SET_ORDER);
    $dateString = str_replace("・", "" , $query_date[0][0]);
    $dateString = substr($dateString, 0, -5);
    strtotime(date("Y",strtotime("-1 day")) . "/" . $dateString);
    // 次は実施時間とか
    $pattern1 = "/([0-9]{1,2})時頃.+Group +(.*)/um";
    preg_match_all($pattern1, $string, $query, PREG_SET_ORDER);
    $replaceQuery = array();
    foreach ($query as $key => $value) {
        $replaceQuery[$key]["string"] = $value[0];
        $replaceQuery[$key]["time"] =     strtotime(date("Y",strtotime("-1 day")) . "/" . $dateString . " " . $value[1] . ":00");
        $replaceQuery[$key]["time_str"]  = date('Y/m/d H:i', $replaceQuery[$key]["time"]);
        $replaceQuery[$key]["group"] = $value[2];
        $replaceQuery[$key]["group"] = str_replace("/" , "" ,  $replaceQuery[$key]["group"]);
        $replaceQuery[$key]["group"] = str_replace("#グラブル" , "" ,  $replaceQuery[$key]["group"]);
    }
    return $replaceQuery;
}

function getGbfNotice($client){
    // **ツイートを取得して配列化する**
    $statuses = $client->get('search/tweets', ['q' => 'from:granbluefantasy 防衛戦発生予告 #グラブル'])->statuses;
    foreach ($statuses as $key => $value) {
        $tweets_gbf_notice[$key] = array(
        "id" => $value -> id_str,
        "time" => strtotime($value -> created_at),
        "text" => $value -> text
        );
    }
    return $tweets_gbf_notice;
}


function postTweetAll($time, $group, $client){
    // **ALL用のアカウントにツイートさせる処理**
    
    echo time() . " - " . $time . "<br>";
    $difference = $time - time() + 30; //実行時のラグを考慮して30秒足しておく
    echo $difference . "<br>";
    
    $tweetFlag = false;
    
    if ($difference < 0){
        echo "このメッセージはでないはずだよ でたら おしえてね";
    }else if ($difference < 3600){
        $tweet_time =  preg_replace('/^0/','',date("i分後",$difference)); //なぜ時刻の書式に0埋めなし分が無いのか
        $tweetFlag = true;
    }else if ($difference < 4200) {
        $tweet_time = date("g時間後",$difference - (3600 * 9 ));
        $tweetFlag = true;
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
        "【防衛戦発生予告】 約" . $tweet_time. "から1時間以内に発生します。\n対象グループ : " . $tweetGroup . "#グラブル #ディフェンドオーダー";
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
        $sql = "INSERT IGNORE  INTO `djeetadefendorder` (`time`, `time_str`, `group`) values (?,?,?)";
        $stmt=$pdo->prepare($sql);
        $res=$stmt->execute(array($value["time"], $value["time_str"], $value["group"]));
        echo "<pre>" . "time : " . $value["time"] .
        "<br>time_str : " . $value["time_str"] .
        "<br>group : " . $value["group"] . "</pre>";
        sleep(0.5);
    }
}

echo "<hr>";

$time = time();

// データーベースから直近の内容を抜き出す
$sql = "SELECT  `time` ,`time_str` , `group` FROM `djeetadefendorder` WHERE `time` <=  ? AND `time` >=  ?";
$stmt=$pdo->prepare($sql);
$res=$stmt->execute(array($time  + 113610 , $time - 10));

$result = $stmt->fetch(PDO::FETCH_ASSOC);
var_dump($result);

postTweetAll($result['time'], $result['group'], $client);