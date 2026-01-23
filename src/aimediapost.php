<?php
// =====================================================
// AIMediaPost FINAL UI STABLE (FULL RESTORE)
// 機能削除なし／構造変更なし
// バグ修正：保存時に必ず全件マージ
// =====================================================
date_default_timezone_set("Asia/Tokyo");

/* =========================
   paths
========================= */
$dataFile = __DIR__ . "/posts.json";
$bgmDir   = __DIR__ . "/bgm";
$imgDir   = __DIR__ . "/images";

/* =========================
   init
========================= */
if (!file_exists($dataFile)) file_put_contents($dataFile, "[]");
if (!is_dir($bgmDir)) mkdir($bgmDir, 0755, true);
if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);

function load_posts() {
    $j = json_decode(@file_get_contents(__DIR__."/posts.json"), true);
    return is_array($j) ? $j : array();
}
function save_posts($p) {
    file_put_contents(
        __DIR__."/posts.json",
        json_encode(array_values($p), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
    );
}

/* =====================================================
   BGM upload / delete
===================================================== */
if (isset($_FILES["bgm"]) && $_FILES["bgm"]["tmp_name"] !== "") {
    move_uploaded_file($_FILES["bgm"]["tmp_name"], $bgmDir."/".basename($_FILES["bgm"]["name"]));
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}
if (isset($_GET["delete_bgm"])) {
    $f = basename($_GET["delete_bgm"]);
    if (is_file($bgmDir."/".$f)) unlink($bgmDir."/".$f);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* =====================================================
   Image upload（投稿に紐づけ）
===================================================== */
if (
    isset($_POST["upload_image"])
    && isset($_FILES["image"]["tmp_name"])
    && $_FILES["image"]["tmp_name"] !== ""
) {
    $all = load_posts();
    $id = $_POST["upload_image"];
    $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
    $fn  = "img_".$id.".".$ext;
    move_uploaded_file($_FILES["image"]["tmp_name"], $imgDir."/".$fn);

    foreach ($all as $k=>$p) {
        if ($p["id"] === $id) {
            $all[$k]["image_url"] = "images/".$fn;
        }
    }
    save_posts($all);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* =====================================================
   字幕付きMP4生成（既存画像＋投稿文）
===================================================== */
if (isset($_POST["make_mp4_sub"])) {
    $all = load_posts();
    $id = $_POST["make_mp4_sub"];
    $audio_url = "";
    $image_url = "";

    foreach ($all as $p) {
        if ($p["id"] === $id) {
            if (!empty($p["mix_url"])) {
                $audio_url = $p["mix_url"];
            } elseif (!empty($p["audio_url"])) {
                $audio_url = $p["audio_url"];
            }
            if (!empty($p["image_url"])) {
                $image_url = $p["image_url"];
            }
            break;
        }
    }

    if (
        $audio_url !== ""
        && $image_url !== ""
        && isset($_POST["script_text"])
        && trim($_POST["script_text"]) !== ""
    ) {
        $ch = curl_init("http://exbridge.ddns.net:8002/audio_to_mp4");
        $image_path = __DIR__ . "/" . $image_url;
        $post = array(
            "audio_url"   => $audio_url,
            "script_text" => $_POST["script_text"],
            "image" => new CURLFile(
                $image_path,
                mime_content_type($image_path),
                basename($image_path)
            )
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        if (is_array($json) && isset($json["mp4_url"])) {
            foreach ($all as $k=>$p) {
                if ($p["id"] === $id) {
                    $all[$k]["mp4_url"] = $json["mp4_url"];
                }
            }
            save_posts($all);
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* =====================================================
   JSON API（TTS / MIX）
===================================================== */
if (
    $_SERVER["REQUEST_METHOD"]==="POST"
    && isset($_SERVER["CONTENT_TYPE"])
    && strpos($_SERVER["CONTENT_TYPE"],"application/json")!==false
) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!is_array($data)) $data=array();

    // ---- TTS ----
    if (isset($data["post_id"]) && isset($data["text"]) && !isset($data["mode"])) {
        $ch=curl_init("http://exbridge.ddns.net:8002/tts_sample");
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_HTTPHEADER,array("Content-Type: application/json"));
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));
        $res=curl_exec($ch);
        curl_close($ch);
        $j=json_decode($res,true);

        if (isset($j["audio_url"])) {
            $all = load_posts();
            foreach($all as $k=>$p){
                if ($p["id"]===$data["post_id"]) {
                    $all[$k]["text"]=$data["text"];
                    $all[$k]["audio_url"]=$j["audio_url"];
                }
            }
            save_posts($all);
        }
        header("Content-Type: application/json");
        echo $res;
        exit;
    }

    // ---- MIX ----
    if (isset($data["mode"]) && $data["mode"]==="mix") {
        $all = load_posts();
        $radio = "";

        foreach ($all as $p) {
            if ($p["id"] === $data["post_id"]) {
                if (!empty($p["audio_url"])) $radio = $p["audio_url"];
                break;
            }
        }
        if ($radio === "") {
            header("Content-Type: application/json");
            echo json_encode(array("error"=>"audio_url not found"));
            exit;
        }

        $bgm_file = (isset($data["bgm_file"]) && $data["bgm_file"]!=="") ? $data["bgm_file"] : "";
        $bgm_vol   = isset($data["bgm_volume"])?(int)$data["bgm_volume"]:30;
        $bgm_start = isset($data["bgm_start"])?(float)$data["bgm_start"]:0;

        foreach ($all as $k=>$p) {
            if ($p["id"] === $data["post_id"]) {
                $all[$k]["bgm_file"]   = $bgm_file;
                $all[$k]["bgm_volume"] = $bgm_vol;
                $all[$k]["bgm_start"]  = $bgm_start;
            }
        }
        save_posts($all);

        $payload = array(
            "radio_url"   => $radio,
            "bgm_url"     => "https://exbridge.jp/aidexx/bgm/".rawurlencode($bgm_file),
            "bgm_volume"  => $bgm_vol,
            "bgm_start"   => $bgm_start,
            "source_file" => basename(parse_url($radio, PHP_URL_PATH))
        );

        $ch=curl_init("http://exbridge.ddns.net:8002/mix");
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_HTTPHEADER,array("Content-Type: application/json"));
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($payload));
        $res=curl_exec($ch);
        curl_close($ch);
        $j=json_decode($res,true);

        if (isset($j["file"])) {
            $all = load_posts();
            foreach($all as $k=>$p){
                if ($p["id"]===$data["post_id"]) {
                    $all[$k]["mix_url"]   = "https://exbridge.ddns.net/aidexx/mixed/".$j["file"];
                    $all[$k]["bgm_file"]  = $bgm_file;
                    $all[$k]["bgm_volume"]= $bgm_vol;
                    $all[$k]["bgm_start"] = $bgm_start;
                }
            }
            save_posts($all);
        }
        header("Content-Type: application/json");
        echo $res;
        exit;
    }
}

/* =====================================================
   投稿生成
===================================================== */
if (isset($_POST["keyword"])) {
    $kw=$_POST["keyword"];
    $rss=@simplexml_load_file(
        "https://news.google.com/rss/search?q=".urlencode($kw)."&hl=ja&gl=JP&ceid=JP:ja"
    );
    $titles=array();
    if($rss)foreach($rss->channel->item as $i){
        $titles[]=(string)$i->title;
        if(count($titles)>=5)break;
    }
    if($titles){
        $prompt="以下はニュース見出しです。X用短文を140字以内で。\n";
        foreach($titles as $t)$prompt.="・".$t."\n";
        $ch=curl_init("https://exbridge.ddns.net/api/generate");
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_HTTPHEADER,array("Content-Type: application/json"));
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode(array(
            "model"=>"gemma3:12b",
            "prompt"=>$prompt,
            "stream"=>false
        )));
        $res=curl_exec($ch);
        curl_close($ch);
        $j=json_decode($res,true);
        if(isset($j["response"])){
            $posts=load_posts();
            array_unshift($posts,array(
                "id"=>date("YmdHis"),
                "text"=>$j["response"],
                "created"=>date("Y-m-d H:i:s"),
                "audio_url"=>"",
                "mix_url"=>"",
                "image_url"=>"",
                "mp4_url"=>"",
                "bgm_file"=>"",
                "bgm_volume"=>30,
                "bgm_start"=>0
            ));
            save_posts($posts);
        }
    }
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* =====================================================
   編集 / 削除
===================================================== */
if (isset($_POST["edit"])) {
    $posts=load_posts();
    foreach($posts as $i=>$p){
        if($p["id"]===$_POST["edit"]){
            array_unshift($posts,$p);
            unset($posts[$i+1]);
            $posts=array_values($posts);
            break;
        }
    }
    save_posts($posts);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}
if (isset($_POST["delete"])) {
    $posts=load_posts(); $n=array();
    foreach($posts as $p) if($p["id"]!==$_POST["delete"]) $n[]=$p;
    save_posts($n);
    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* =====================================================
   view data（表示専用・非破壊補完）
===================================================== */
$posts=load_posts();
$current=isset($posts[0])?$posts[0]:null;
$raw_current=$current;
$others=array_slice($posts,1);

if ($current) {
    if (!isset($current["bgm_volume"])) $current["bgm_volume"]=30;
    if (!isset($current["bgm_start"]))  $current["bgm_start"]=0;
}
$bgms=array();
$dh=opendir($bgmDir);
while(($f=readdir($dh))!==false){ if($f!="."&&$f!="..")$bgms[]=$f; }
closedir($dh);
sort($bgms);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>AIMediaPost</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:system-ui;background:#f5f6f8}
.container{max-width:760px;margin:0 auto;padding:12px}
.card{background:#fff;border-radius:14px;padding:14px;margin-bottom:14px}

textarea,input,select,button{width:100%}
textarea{min-height:120px}
button{margin-top:8px;padding:10px;border:none;border-radius:8px;background:#2563eb;color:#fff}
button.danger{background:#c62828}

.row{display:flex;gap:8px;align-items:center}
.row button{width:auto}

.stack{display:block}
.stack > *{margin-top:8px}

audio,video,img{width:100%}

.list-item{padding:10px;border-top:1px solid #eee}
.list-actions{display:flex;gap:8px;margin-bottom:6px}

.filename{font-size:14px;word-break:break-all}
#mix_status{margin-top:6px;font-size:14px;color:#2563eb}
</style>
</head>
<body>
<div class="container">

<div class="card">
<form method="post" class="row">
<input name="keyword" placeholder="検索キーワード">
<button>生成</button>
</form>
</div>

<?php if($current): ?>
<div class="card">
<h2>投稿文</h2>

<textarea id="text"><?php echo htmlspecialchars($current["text"]); ?></textarea>
<button onclick="tts('<?php echo $current["id"]; ?>')">🎤 音声化</button>

<?php if(!empty($current["audio_url"])): ?>
<audio controls src="<?php echo $current["audio_url"]; ?>"></audio>
<?php endif; ?>

<h3>BGM（MIX）</h3>
<select id="bgm">
<?php foreach($bgms as $b): ?>
<option value="<?php echo htmlspecialchars($b); ?>"<?php if(isset($raw_current["bgm_file"]) && $raw_current["bgm_file"]===$b) echo " selected"; ?>>
<?php echo htmlspecialchars($b); ?>
</option>
<?php endforeach; ?>
</select>

<input id="bgm_volume" type="range" min="0" max="100" value="<?php echo (int)$current["bgm_volume"]; ?>">
<input id="bgm_start" type="number" min="0" value="<?php echo htmlspecialchars($current["bgm_start"]); ?>">

<div id="mix_status">
<?php if(isset($raw_current["bgm_file"]) && $raw_current["bgm_file"]!==""): ?>
🎵 使用中BGM：<b><?php echo htmlspecialchars($raw_current["bgm_file"]); ?></b>
／ 開始：<b><?php echo htmlspecialchars($current["bgm_start"]); ?>秒</b>
／ 音量：<b><?php echo htmlspecialchars($current["bgm_volume"]); ?></b>
<?php endif; ?>
</div>

<button onclick="mix('<?php echo $current["id"]; ?>')">🎵 MIX</button>

<?php if(!empty($current["mix_url"])): ?>
<audio controls src="<?php echo $current["mix_url"]; ?>"></audio>
<?php endif; ?>

<h3>画像アップ</h3>
<?php if(!empty($current["image_url"])): ?>
<img src="<?php echo htmlspecialchars($current["image_url"]); ?>">
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="upload_image" value="<?php echo $current["id"]; ?>">
<input type="file" name="image" accept="image/*" required>
<button>画像アップ</button>
</form>

<h3>🎬 字幕付き MP4</h3>
<form method="post">
<input type="hidden" name="make_mp4_sub" value="<?php echo $current["id"]; ?>">
<textarea name="script_text"><?php echo htmlspecialchars($current["text"]); ?></textarea>
<button>MP4生成</button>
</form>

<?php if(!empty($current["mp4_url"])): ?>
<video controls src="<?php echo htmlspecialchars($current["mp4_url"]); ?>"></video>
<?php endif; ?>

<form method="post">
<input type="hidden" name="delete" value="<?php echo $current["id"]; ?>">
<button class="danger">削除</button>
</form>
</div>
<?php endif; ?>

<div class="card">
<h2>BGM マネージャー</h2>

<form method="post" enctype="multipart/form-data" class="stack">
<input type="file" name="bgm" accept="audio/*" required>
<button>アップロード</button>
</form>

<?php foreach($bgms as $b): ?>
<div class="list-item">
<div class="filename"><?php echo htmlspecialchars($b); ?></div>
<audio controls src="bgm/<?php echo rawurlencode($b); ?>"></audio>
<a href="?delete_bgm=<?php echo rawurlencode($b); ?>">削除</a>
</div>
<?php endforeach; ?>
</div>

<div class="card">
<h2>投稿一覧</h2>

<?php foreach($others as $p): ?>
<div class="list-item">
<div class="list-actions">
<form method="post">
<input type="hidden" name="edit" value="<?php echo $p["id"]; ?>">
<button>編集</button>
</form>
<form method="post">
<input type="hidden" name="delete" value="<?php echo $p["id"]; ?>">
<button class="danger">削除</button>
</form>
</div>
<div class="filename">
<?php echo htmlspecialchars(isset($p["text"])?$p["text"]:""); ?>
</div>
</div>
<?php endforeach; ?>
</div>

</div>

<script>
function tts(id){
 fetch("",{
   method:"POST",
   headers:{"Content-Type":"application/json"},
   body:JSON.stringify({
     post_id:id,
     text:document.getElementById("text").value
   })
 }).then(function(){location.reload();});
}
function mix(id){
 var bgm=document.getElementById("bgm").value;
 var vol=document.getElementById("bgm_volume").value;
 var st=document.getElementById("bgm_start").value;

 document.getElementById("mix_status").innerHTML =
   (bgm?("🎵 使用中BGM：<b>"+bgm+"</b>"):"")
   +(st!==""?(" ／ 開始：<b>"+st+"秒</b>"):"")
   +(vol!==""?(" ／ 音量：<b>"+vol+"</b>"):"");

 fetch("",{
   method:"POST",
   headers:{"Content-Type":"application/json"},
   body:JSON.stringify({
     mode:"mix",
     post_id:id,
     bgm_file:bgm,
     bgm_volume:vol,
     bgm_start:st
   })
 }).then(function(){location.reload();});
}
</script>
</body>
</html>

