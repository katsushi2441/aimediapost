<?php
// ================================
// 短文 → Ollama → X引用リツイート文生成
// ================================

$dataFile = __DIR__ . "/posts.json";
if (!file_exists($dataFile)) file_put_contents($dataFile, "[]");

/* ----------------
   編集保存処理
---------------- */
if (isset($_POST["save"]) && isset($_POST["text"])) {
    $id = $_POST["save"];
    $newText = $_POST["text"];

    $posts = json_decode(file_get_contents($dataFile), true);

    foreach ($posts as &$p) {
        if ($p["id"] === $id) {
            $p["text"] = $newText;
            break;
        }
    }
    unset($p);

    file_put_contents(
        $dataFile,
        json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* ----------------
   削除処理
---------------- */
if (isset($_POST["delete"])) {
    $id = $_POST["delete"];
    $posts = json_decode(file_get_contents($dataFile), true);

    $posts = array_values(array_filter($posts, function($p) use ($id) {
        return $p["id"] !== $id;
    }));

    file_put_contents(
        $dataFile,
        json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

/* ----------------
   生成処理（短文 → 引用RT）
---------------- */
if (isset($_POST["source_text"])) {

    $source_text = trim($_POST["source_text"]);

    if ($source_text !== "") {

        $prompt  = "以下はX（旧Twitter）の投稿文です。\n";
        $prompt .= "この投稿に対する「バズりやすい引用リツイート文」を1つ作ってください。\n";
        $prompt .= "・140文字以内\n";
        $prompt .= "・共感 or 問題提起\n";
        $prompt .= "・攻撃的にならない\n\n";
        $prompt .= "【元の投稿】\n";
        $prompt .= $source_text;

        $payload = json_encode(array(
            "model"  => "gemma3:12b",
            "prompt" => $prompt,
            "stream" => false
        ));

        $ch = curl_init("https://exbridge.ddns.net:8012/api/generate");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $res = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($res, true);
        if (isset($json["response"])) {

            $posts = json_decode(file_get_contents($dataFile), true);

            $footer = "\n\n#相互フォロー\n#いいねした人フォローする\n#aimediapost で生成";
            $text = trim($json["response"]) . $footer;

            $posts[] = array(
                "id"      => date("YmdHis"),
                "keyword" => $source_text, // 後方互換用（元ツイ文）
                "text"    => $text,
                "created" => date("Y-m-d H:i:s")
            );

            file_put_contents(
                $dataFile,
                json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }
    }

    header("Location: ".$_SERVER["PHP_SELF"]);
    exit;
}

$posts = json_decode(file_get_contents($dataFile), true);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>投稿短文 生成 - AIMediaPost</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{
    font-family:system-ui;
    background:radial-gradient(1200px 600px at 50% -20%, #1e293b, #020617);
    color:#e5e7eb;
    padding:20px
}
.box{
    background:rgba(15,23,42,0.92);
    padding:16px;
    border-radius:16px;
    margin-bottom:14px;
    border:1px solid rgba(148,163,184,0.15);
    box-shadow:0 10px 30px rgba(0,0,0,0.45)
}
h2{margin:0 0 10px;color:#f8fafc}
textarea{
    width:100%;
    min-height:100px;
    font-size:14px;
    background:#020617;
    color:#e5e7eb;
    border:1px solid rgba(148,163,184,0.25);
    border-radius:10px;
    padding:8px;
}
button{
    padding:8px 14px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    margin-right:6px;
    font-weight:600
}
.gen{
    background:linear-gradient(135deg,#6366f1,#22d3ee);
    color:#020617
}
.save{
    background:linear-gradient(135deg,#16a34a,#22c55e);
    color:#020617
}
.del{
    background:linear-gradient(135deg,#dc2626,#ef4444);
    color:#020617
}
button:hover{opacity:.9}
</style>
</head>
<body>

<div class="box">
<h2>✍ 元ツイ文 → 引用リツイート生成</h2>
<form method="post">
<textarea name="source_text" placeholder="ここに元の投稿文を貼り付けてください" required></textarea>
<button class="gen">引用RT文を生成</button>
</form>
</div>

<?php foreach (array_reverse($posts) as $p): ?>
<div class="box">
<strong>元ツイ：</strong><br>
<div style="font-size:12px;opacity:.85;margin-bottom:6px">
<?php echo nl2br(htmlspecialchars($p["keyword"], ENT_QUOTES, "UTF-8")); ?>
</div>

<textarea id="post_<?php echo $p["id"]; ?>"><?php
echo htmlspecialchars($p["text"], ENT_QUOTES, "UTF-8");
?></textarea>

<div style="margin-top:8px">
<button type="button" onclick="copyText('<?php echo $p["id"]; ?>')">📋 コピー</button>

<form method="post" style="display:inline">
<input type="hidden" name="save" value="<?php echo $p["id"]; ?>">
<input type="hidden" name="text" id="save_text_<?php echo $p["id"]; ?>">
<button class="save" onclick="setSaveText('<?php echo $p["id"]; ?>')">💾 保存</button>
</form>

<form method="post" style="display:inline">
<input type="hidden" name="delete" value="<?php echo $p["id"]; ?>">
<button class="del">削除</button>
</form>
</div>
</div>
<?php endforeach; ?>

<script>
function copyText(id){
    var textarea = document.getElementById("post_" + id);
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    try {
        document.execCommand("copy");
        alert("コピーしました");
    } catch (e) {
        alert("コピーに失敗しました");
    }
}

function setSaveText(id){
    var text = document.getElementById("post_" + id).value;
    document.getElementById("save_text_" + id).value = text;
}
</script>

</body>
</html>

