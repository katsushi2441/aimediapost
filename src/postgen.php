<?php
// ================================
// Googleニュース → Ollama要約 → X投稿文管理
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
   生成処理
---------------- */
if (isset($_POST["keyword"])) {

    $keyword_raw = trim($_POST["keyword"]);
    $keyword = urlencode($keyword_raw);

    // Google News RSS
    $rss_url = "https://news.google.com/rss/search?q={$keyword}&hl=ja&gl=JP&ceid=JP:ja";
    $rss = @simplexml_load_file($rss_url);

    $titles = array();
    if ($rss && isset($rss->channel->item)) {
        foreach ($rss->channel->item as $item) {
            $titles[] = (string)$item->title;
            if (count($titles) >= 5) break;
        }
    }

    if ($titles) {
        // Ollama prompt
        $prompt  = "以下はニュースの見出しです。\n";
        $prompt .= "X（旧Twitter）に投稿する短い要約文を1つ作ってください。\n";
        $prompt .= "140文字以内、前向き、一般向け。\n\n";
        foreach ($titles as $t) {
            $prompt .= "・{$t}\n";
        }

        $payload = json_encode(array(
            "model"  => "gemma3:12b",
            "prompt" => $prompt,
            "stream" => false
        ));

        $ch = curl_init("https://exbridge.ddns.net/api/generate");
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

            $footer = "\n\n#相互フォロー\n#いいねした人フォローする\n#airadiogenerator で作成";
            $text = trim($json["response"]) . $footer;

            $posts[] = array(
                "id"      => date("YmdHis"),
                "keyword" => $keyword_raw,
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
<title>X投稿文 自動生成</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui;background:#f5f6f8;padding:20px}
.box{background:#fff;padding:15px;border-radius:12px;margin-bottom:12px}
textarea{width:100%;height:120px;font-size:14px}
button{padding:8px 14px;border:none;border-radius:6px;cursor:pointer;margin-right:6px}
.gen{background:#0a66c2;color:#fff}
.del{background:#c62828;color:#fff}
.save{background:#2e7d32;color:#fff}
</style>
</head>
<body>

<div class="box">
<h2>🔍 Googleニュース → X投稿文生成</h2>
<form method="post">
<input type="text" name="keyword" placeholder="検索キーワード" style="width:70%" required>
<button class="gen">生成</button>
</form>
</div>

<?php foreach (array_reverse($posts) as $p): ?>
<div class="box">
<strong><?php echo htmlspecialchars($p["keyword"], ENT_QUOTES, "UTF-8"); ?></strong>
（<?php echo $p["created"]; ?>）

<textarea id="post_<?php echo $p["id"]; ?>"><?php
echo htmlspecialchars($p["text"], ENT_QUOTES, "UTF-8");
?></textarea>

<div style="margin-top:6px">
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

