<?php
// PHPMailerライブラリを読み込む
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// --- フォームデータの受け取りと基本的なサニタイズ ---
$name             = isset($_POST['your-name']) ? htmlspecialchars(trim($_POST['your-name']), ENT_QUOTES, 'UTF-8') : '';
$name2            = isset($_POST['your-name2']) ? htmlspecialchars(trim($_POST['your-name2']), ENT_QUOTES, 'UTF-8') : '';
$tel              = isset($_POST['tel']) ? htmlspecialchars(trim($_POST['tel']), ENT_QUOTES, 'UTF-8') : '';
$email            = isset($_POST['your-email']) ? htmlspecialchars(trim($_POST['your-email']), ENT_QUOTES, 'UTF-8') : '';
$email2           = isset($_POST['your-email2']) ? htmlspecialchars(trim($_POST['your-email2']), ENT_QUOTES, 'UTF-8') : '';
$newsletter       = isset($_POST['acceptance-newsletter']) && $_POST['acceptance-newsletter'] == '1' ? 'はい' : 'いいえ';
$number           = isset($_POST['number']) ? filter_var($_POST['number'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]) : false;
$child_grade      = isset($_POST['child-grade']) ? htmlspecialchars($_POST['child-grade'], ENT_QUOTES, 'UTF-8') : '';
$area             = isset($_POST['area']) ? htmlspecialchars($_POST['area'], ENT_QUOTES, 'UTF-8') : '';

// --- ▼▼▼ 修正箇所（ここから） ▼▼▼ ---
// $datetime_prefs   = isset($_POST['datetime_preference']) && is_array($_POST['datetime_preference']) ? $_POST['datetime_preference'] : []; // ← 元のコード
// JavasScriptフォームは「配列」ではなく「単一の文字列」で送信するため、文字列(string)として受け取る
$datetime_pref_string = isset($_POST['datetime_preference']) ? htmlspecialchars(trim($_POST['datetime_preference']), ENT_QUOTES, 'UTF-8') : '';
$terms_accepted   = isset($_POST['acceptance-terms']) && $_POST['acceptance-terms'] == '1';

// サニタイズ（希望日時）
// バリデーション(emptyチェック)で $datetime_prefs_sanitized を使うため、変数を準備
$datetime_prefs_sanitized = [];
if (!empty($datetime_pref_string)) {
    // 値が空でなければ、バリデーション通過用に配列に値を入れる
    $datetime_prefs_sanitized[] = $datetime_pref_string;
}
// メール本文用の文字列を作成（元の変数名を再利用）
$datetime_prefs_string = !empty($datetime_pref_string) ? $datetime_pref_string : '未選択';
// --- ▲▲▲ 修正箇所（ここまで） ▲▲▲ ---


// --- バリデーション ---
$errors = [];
if (empty($name)) $errors[] = '氏名を入力してください。';
if (empty($name2)) $errors[] = 'ふりがなを入力してください。';
if (empty($tel)) $errors[] = 'お電話番号を入力してください。';
if (empty($email)) {
    $errors[] = 'メールアドレスを入力してください。';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '有効なメールアドレスを入力してください。';
}
if (empty($email2)) {
    $errors[] = 'メールアドレス確認用を入力してください。';
} elseif ($email !== $email2) {
    $errors[] = 'メールアドレスと確認用メールアドレスが一致しません。';
}
if ($number === false) $errors[] = '大人の参加人数は1～5の間で入力してください。';
if (empty($child_grade)) $errors[] = '第一子の学年を選択してください。';
if (empty($area)) $errors[] = '開催エリアを選択してください。';
// ↓このバリデーションチェックは、上記の修正によって正しく機能するようになります
if (empty($datetime_prefs_sanitized)) $errors[] = 'ご希望の参加日時を少なくとも1つ選択してください。';
if (!$terms_accepted) $errors[] = '個人情報の取り扱いに同意してください。';

// エラーがある場合は処理を中断し、エラーメッセージを表示
if (!empty($errors)) {
    echo "<h1>入力エラー</h1>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
    }
    echo "</ul>";
    echo '<p><a href="javascript:history.back()">前のページに戻る</a></p>';
    exit; // 処理終了
}

// --- メール送信設定 ---
$admin_email = 'pothos02@gmail.com'; // 運営者のアドレス
$site_name   = 'マネー・ラボラトリー ゆたか校'; // 教室名
$smtp_user   = 'pothos02@gmail.com'; // Gmailアドレス
$smtp_pass   = '022603057Hirozfu'; // Gmailのアプリパスワード ★★★ 必ず正しいアプリパスワードを設定 ★★★

// ------------------------------------------
// 1. 運営者への通知メール
// ------------------------------------------
$mail_to_admin = new PHPMailer(true);
try {
    // サーバー設定
    $mail_to_admin->isSMTP();
    $mail_to_admin->Host       = 'smtp.gmail.com';
    $mail_to_admin->SMTPAuth   = true;
    $mail_to_admin->Username   = $smtp_user;
    $mail_to_admin->Password   = $smtp_pass;
    $mail_to_admin->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail_to_admin->Port       = 465;
    $mail_to_admin->CharSet    = 'UTF-8';

    // 送受信者設定
    $mail_to_admin->setFrom($admin_email, mb_encode_mimeheader($site_name)); // 送信元 (教室名をエンコード)
    $mail_to_admin->addAddress($admin_email, '運営者様'); // 送信先
    $mail_to_admin->addReplyTo($email, mb_encode_mimeheader($name)); // 返信先 (氏名をエンコード)

    // メール内容
    $mail_to_admin->isHTML(false); // テキスト形式
    $mail_to_admin->Subject = mb_encode_mimeheader("【{$site_name}】ウェブサイトからセミナー申し込みがありました"); // 件名をエンコード
    $mail_to_admin->Body    = <<<EOT
{$site_name} のウェブサイトからセミナー申し込みがありました。

--------------------------------------------------
氏名: {$name}
ふりがな: {$name2}
電話番号: {$tel}
メールアドレス: {$email}
お知らせ配信登録: {$newsletter}

大人の参加人数: {$number} 人
第一子の学年: {$child_grade}

開催エリア: {$area}
希望参加日時:
 - {$datetime_prefs_string}

個人情報同意: はい
--------------------------------------------------
EOT;

    $mail_to_admin->send();

} catch (Exception $e) {
    // エラー処理（本番環境では詳細なエラーは表示せず、ログに記録する）
    error_log("運営者向けメール送信失敗: {$mail_to_admin->ErrorInfo}"); // エラーログに記録
    echo "メッセージの送信中にエラーが発生しました。しばらくしてからもう一度お試しください。";
    // exit; // 必要に応じてここで処理をDめる
}

// ------------------------------------------
// 2. 申込者への自動応答メール
// ------------------------------------------
$mail_to_user = new PHPMailer(true);
try {
    // サーバー設定は運営者向けと同じ
    $mail_to_user->isSMTP();
    $mail_to_user->Host       = 'smtp.gmail.com';
    $mail_to_user->SMTPAuth   = true;
    $mail_to_user->Username   = $smtp_user;
    $mail_to_user->Password   = $smtp_pass;
    $mail_to_user->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail_to_user->Port       = 465;
    $mail_to_user->CharSet    = 'UTF-8';

    // 送受信者設定
    $mail_to_user->setFrom($admin_email, mb_encode_mimeheader($site_name)); // 送信元
    $mail_to_user->addAddress($email, mb_encode_mimeheader("{$name} 様")); // 送信先

    // メール内容
    $mail_to_user->isHTML(false);
    $mail_to_user->Subject = mb_encode_mimeheader("【{$site_name}】セミナーお申し込みありがとうございます");
    $mail_to_user->Body    = <<<EOT
{$name} 様

この度は、{$site_name} のセミナーにお申し込みいただき、誠にありがとうございます。
以下の内容で承りました。

担当者より追ってご連絡、または確定のご案内をいたしますので、今しばらくお待ちください。

--------------------------------------------------
■お申し込み内容
氏名: {$name}
ふりがな: {$name2}
電話番号: {$tel}
メールアドレス: {$email}

大人の参加人数: {$number} 人
第一子の学年: {$child_grade}

開催エリア: {$area}
希望参加日時:
 - {$datetime_prefs_string}
--------------------------------------------------

※このメールは送信専用です。ご返信いただいても対応できませんのでご了承ください。
※お申し込みが集中した場合、ご希望に添えない場合がございます。

ご不明な点がございましたら、お手数ですが下記までご連絡ください。
{$admin_email}

{$site_name}
（ここに住所や電話番号などの署名を追加）
EOT;

    $mail_to_user->send();

} catch (Exception $e) {
    // エラー処理
    error_log("自動応答メール送信失敗 ({$email}): {$mail_to_user->ErrorInfo}");
    // ユーザーには一般的なエラーメッセージを表示する
    // echo "自動応答メールの送信中にエラーが発生しました。"; // 必要であれば表示
}

// ------------------------------------------
// 3. 送信完了ページへ移動（リダイレクト）
// ------------------------------------------
header('Location: thanks.html'); // 成功したらサンキューページへ
exit;

?>
