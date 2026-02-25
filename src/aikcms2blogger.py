#!/usr/bin/env python3
import requests
import json
import hashlib
import datetime
import smtplib
from email.mime.text import MIMEText
from email.header import Header

# =====================================================
# 設定
# =====================================================
TOKEN = "秘密の文字列"

DAILY_API = "https://aiknowledgecms.exbridge.jp/daily_summary.php"
AIK_BASE  = "https://aiknowledgecms.exbridge.jp/aiknowledgecms.php"

SENT_FILE = "sent_daily_summary.json"

FROM = "bm@exbridge.jp"
PASSWORD = "Xbrg20042441"
TO = "katsushi2441.xbrg@blogger.com"
SMTP_HOST = "mail18.heteml.jp"
SMTP_PORT = 465


# =====================================================
# ユーティリティ
# =====================================================
def log(msg):
    print("[daily-blogger]", msg, flush=True)

def make_hash(text):
    return hashlib.md5(text.encode("utf-8")).hexdigest()

def load_sent():
    try:
        with open(SENT_FILE, "r", encoding="utf-8") as f:
            return set(json.load(f))
    except:
        return set()

def save_sent(sent):
    with open(SENT_FILE, "w", encoding="utf-8") as f:
        json.dump(list(sent), f, ensure_ascii=False, indent=2)


# =====================================================
# 前日取得
# =====================================================
def get_target_date():
    return (datetime.date.today() - datetime.timedelta(days=1)).strftime("%Y-%m-%d")


# =====================================================
# Daily Summary API取得
# =====================================================
def get_daily_summary(date):

    log("Requesting daily summary for " + date)

    r = requests.get(
        DAILY_API,
        params={
            "api_get_daily_summary": 1,
            "token": TOKEN,
            "date": date
        },
        timeout=60
    )

    if r.status_code != 200:
        log("HTTP Error: " + str(r.status_code))
        return None

    try:
        return r.json()
    except:
        log("JSON parse error")
        return None


# =====================================================
# Blogger送信
# =====================================================
def send_to_blogger(title, html_content):

    msg = MIMEText(html_content, "html", "utf-8")
    msg["Subject"] = Header(title, "utf-8")
    msg["From"] = FROM
    msg["To"] = TO

    with smtplib.SMTP_SSL(SMTP_HOST, SMTP_PORT, timeout=30) as s:
        s.login(FROM, PASSWORD)
        s.send_message(msg)

    log("Posted: " + title)


# =====================================================
# HTML生成
# =====================================================
def build_html(summary_data, date):

    summary_text = summary_data.get("summary_text", "")
    audio_url    = summary_data.get("audio_url", "")

    target_url = f"https://aiknowledgecms.exbridge.jp/aiknowledgecms.php?base_date={date}"
    trend_url  = "https://aiknowledgecms.exbridge.jp/aitrend.php"
    news_url   = "https://aiknowledgecms.exbridge.jp/newskeyword.php"

    jp_date = datetime.datetime.strptime(date, "%Y-%m-%d").strftime("%Y年%m月%d日")

    html = f"""
<h2>{jp_date} AIニュース総括</h2>

<h3>🧠 AIKnowledgeCMS デイリーサマリー</h3>
<p>{summary_text}</p>
"""

    if audio_url:
        full_audio_url = "https://aiknowledgecms.exbridge.jp/" + audio_url.replace("./","")
        html += f"""
<h3>🎧 音声版</h3>
<p>
<a href="{full_audio_url}" target="_blank">
音声で聞く（AIナレーション）
</a>
</p>
"""

    html += f"""
<hr>

<p>
🔷 <strong>AIKnowledgeCMS</strong><br><br>

📡 {jp_date} トップページ<br>
<a href="{target_url}" target="_blank">
{target_url}
</a>
<br><br>

📈 AIトレンドキーワード辞典<br>
<a href="{trend_url}" target="_blank">
{trend_url}
</a>
<br><br>

📰 AI思考のキーワード＆ニュース<br>
<a href="{news_url}" target="_blank">
{news_url}
</a>

</p>
"""

    return html



# =====================================================
# メイン処理
# =====================================================
def main():

    log("=== START ===")

    target_date = get_target_date()
    sent = load_sent()

    uid = make_hash(target_date)

    if uid in sent:
        log("Already posted. Skipping.")
        return

    summary_data = get_daily_summary(target_date)

    if not summary_data:
        log("No summary data found.")
        return

    title = f"{target_date} AIKnowledgeCMS デイリーニュース総括"
    html = build_html(summary_data, target_date)

    try:
        send_to_blogger(title, html)
        sent.add(uid)
        save_sent(sent)
    except Exception as e:
        log("ERROR: " + str(e))

    log("=== DONE ===")


if __name__ == "__main__":
    main()
