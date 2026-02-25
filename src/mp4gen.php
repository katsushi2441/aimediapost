<?php
// =========================================
// audio2mp4.php
// 画像 + mp3/wav URL + ラジオ台本 → mp4 生成（スクロール対応）
// + 音声音声ファイル事前アップロード（専用ボタン）
// PHP5互換 / 機能削除なし
// =========================================

date_default_timezone_set("Asia/Tokyo");

define("API_ENDPOINT", "http://exbridge.ddns.net:8002/audio_to_mp4");

$musicDir = __DIR__ . "/musics";
$musicUrlBase = "https://exbridge.jp/aidexx/musics";

if (!is_dir($musicDir)) {
    mkdir($musicDir, 0755, true);
}

$msg = "";
$result = null;
$audio_url = "";
$script_text = "";

/* =========================
   音声アップロード専用処理
========================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["upload_audio"])
) {
    if (
        isset($_FILES["audio_file"])
        && isset($_FILES["audio_file"]["tmp_name"])
        && $_FILES["audio_file"]["tmp_name"] !== ""
    ) {
        $org_name = isset($_FILES["audio_file"]["name"]) ? $_FILES["audio_file"]["name"] : "";
        $tmp_path = $_FILES["audio_file"]["tmp_name"];

        $ext = strtolower(trim(pathinfo($org_name, PATHINFO_EXTENSION)));

        $mime = "";
        if (function_exists("mime_content_type")) {
            $mime = @mime_content_type($tmp_path);
        }

        $mime_to_ext = "";
        if ($mime !== "") {
            $m = strtolower(trim($mime));
            if ($m === "audio/mpeg" || $m === "audio/mp3" || $m === "audio/x-mp3" || $m === "audio/mpeg3") {
                $mime_to_ext = "mp3";
            }
        }

        if ($ext === "mpeg") $ext = "mp3";

        $is_mp3 = false;
        $is_wav = false;

        if ($ext === "mp3") $is_mp3 = true;
        if ($ext === "wav") $is_wav = true;

        if (!$is_mp3 && !$is_wav && $mime_to_ext === "mp3") {
            $ext = "mp3";
            $is_mp3 = true;
        }

        if ($is_mp3 || $is_wav) {
            $saveName = "music." . $ext;
            $savePath = $musicDir . "/" . $saveName;

            if (move_uploaded_file($tmp_path, $savePath)) {
                $audio_url = $musicUrlBase . "/" . $saveName;
                $msg = "✅ 楽曲/音声アップロード完了";
            } else {
                $msg = "❌ 楽曲/音声ファイルの保存に失敗しました";
            }
        } else {
            $msg = "❌ mp3 または wav のみ対応しています"
                 . "<br>ファイル名: " . htmlspecialchars($org_name, ENT_QUOTES, "UTF-8")
                 . "<br>拡張子判定: " . htmlspecialchars($ext, ENT_QUOTES, "UTF-8")
                 . "<br>MIME判定: " . htmlspecialchars($mime, ENT_QUOTES, "UTF-8");
        }
    } else {
        $msg = "❌ 楽曲/音声ファイルを選択してください";
    }
}

/* =========================
   MP4生成処理
========================= */
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && !isset($_POST["upload_audio"])
) {
    if (
        isset($_FILES["image"])
        && isset($_POST["audio_url"])
        && isset($_POST["script_text"])
        && isset($_FILES["image"]["tmp_name"])
        && $_FILES["image"]["tmp_name"] !== ""
        && trim($_POST["audio_url"]) !== ""
    ) {

        $audio_url   = trim($_POST["audio_url"]);
        $script_text = trim($_POST["script_text"]);

        $ch = curl_init(API_ENDPOINT);

        $post = array(
            "audio_url"   => $audio_url,
            "script_text" => $script_text,
            "image" => new CURLFile(
                $_FILES["image"]["tmp_name"],
                mime_content_type($_FILES["image"]["tmp_name"]),
                $_FILES["image"]["name"]
            )
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            $msg = "❌ API通信失敗: " . htmlspecialchars($err, ENT_QUOTES, "UTF-8");
        } else {
            $json = json_decode($res, true);
            if (is_array($json) && isset($json["ok"]) && $json["ok"]) {
                $result = $json;
                $msg = "✅ MP4生成完了";
            } else {
                $msg = "❌ 生成失敗<br><pre>" .
                    htmlspecialchars($res, ENT_QUOTES, "UTF-8") .
                    "</pre>";
            }
        }

    } else {
        $msg = "❌ 画像・音声URL・台本をすべて指定してください";
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>音声＋台本 → MP4</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body {
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
    background: radial-gradient(1200px 600px at 50% -20%, #1e293b, #020617);
    color: #e5e7eb;
    padding:16px;
}
.wrap { max-width:720px; margin:0 auto; }

.card {
    background: rgba(15, 23, 42, 0.92);
    border: 1px solid rgba(148, 163, 184, 0.15);
    border-radius:18px;
    padding:18px;
    margin-bottom:16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.45);
}

h1, h2 {
    font-size:18px;
    margin:0 0 12px 0;
    color:#f8fafc;
}

label {
    font-size:12px;
    color:#cbd5f5;
    display:block;
    margin-bottom:4px;
}

input[type=text],
input[type=file],
textarea {
    width:100%;
    padding:8px;
    background:#020617;
    color:#e5e7eb;
    border:1px solid rgba(148,163,184,0.25);
    border-radius:10px;
}

textarea { min-height:160px; }

button {
    width:100%;
    margin-top:14px;
    padding:12px;
    border:0;
    border-radius:12px;
    background: linear-gradient(135deg,#6366f1,#22d3ee);
    color:#020617;
    font-size:14px;
    font-weight:600;
    cursor:pointer;
}

video {
    width:100%;
    margin-top:10px;
    border-radius:14px;
    background:#000;
}

a {
    color:#38bdf8;
    word-break:break-all;
}

.note {
    font-size:12px;
    color:#94a3b8;
    margin-top:8px;
}
</style>
</head>
<body>

<div class="wrap">

<div class="card">
<h1>🎵 楽曲/音声ファイルアップロード</h1>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="upload_audio" value="1">

<label>楽曲/音声ファイル（mp3 / wav）</label>
<input type="file" name="audio_file" accept=".mp3,.wav" required>

<button type="submit">楽曲/音声をアップロード</button>
</form>
</div>

<div class="card">
<h1>🎬 楽曲/音声＋テキスト字幕 → MP4</h1>

<?php if ($msg !== ""): ?>
<div><?php echo $msg; ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">

<label>① 背景画像</label>
<input type="file" name="image" accept="image/*" required>

<label style="margin-top:12px;">② 楽曲/音声URL</label>
<input type="text" name="audio_url" value="<?php echo htmlspecialchars($audio_url, ENT_QUOTES, "UTF-8"); ?>" required>

<label style="margin-top:12px;">③ テキスト字幕（スクロール表示）</label>
<textarea name="script_text"><?php
$default_text = "AI思考の社会と音楽\n\n"
              ."Youtube、Tiktok、XなどのSNSで\nコンテンツを配信しています。\n\n"
              ."AIMediaPostで生成されています。\n\n";

echo htmlspecialchars(
    isset($script_text) && trim($script_text) !== "" ? $script_text : $default_text,
    ENT_QUOTES,
    "UTF-8"
);
?></textarea>

<button type="submit">
<?php echo $result ? "字幕を修正して再生成" : "MP4を生成"; ?>
</button>
</form>

<div class="note">
・先に楽曲/音声をアップロードしてください<br>
・字幕を修正してすぐ再生成できます
</div>
</div>

<?php if ($result): ?>
<div class="card">
<h2>生成結果</h2>

<video controls>
    <source src="<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>">
</video>

<a href="<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>" target="_blank">
<?php echo htmlspecialchars($result["mp4_url"], ENT_QUOTES, "UTF-8"); ?>
</a>
</div>
<?php endif; ?>

</div>

</body>
</html>

