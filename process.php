<?php
set_time_limit(0); // منع انتهاء وقت التنفيذ للملفات الكبيرة
require_once 'condb.php';

$status_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. استقبال البيانات وتطهيرها الأولي
    $novel_url = filter_input(INPUT_POST, 'novel_url', FILTER_SANITIZE_URL);
    $start_ch  = filter_input(INPUT_POST, 'start_ch', FILTER_VALIDATE_INT);
    $end_ch    = filter_input(INPUT_POST, 'end_ch', FILTER_VALIDATE_INT);

    // 2. التحقق الأمني من الرابط لمنع ثغرات SSRF
    $is_url_valid = false;
    if ($novel_url) {
        $parsed_url = parse_url($novel_url);
        if (isset($parsed_url['scheme']) && in_array($parsed_url['scheme'], ['http', 'https'])) {
            $host = strtolower($parsed_url['host'] ?? '');
            // منع الروابط المحلية والداخلية تماماً لصد الـ SSRF
            if ($host !== 'localhost' && $host !== '127.0.0.1' && $host !== '::1' && !str_starts_with($host, '192.168.')) {
                $is_url_valid = true;
            }
        }
    }

    if (!$is_url_valid || !$start_ch || !$end_ch || $start_ch > $end_ch || $start_ch < 1) {
        $status_message = "<div style='color: #ff4a4a; margin-bottom: 15px;'>البيانات المدخلة غير صالحة. يرجى التحقق من الرابط أو أرقام الفصول والتأكد أنه رابط خارجي آمن.</div>";
    } else {
        try {
            // إزالة الشرطة المائلة من نهاية الرابط إن وجدت لتوحيد الروابط في قاعدة البيانات
            $novel_url = rtrim($novel_url, '/');

            $stmt = $db->prepare("SELECT id, zip_file_path, total_chapters FROM cached_novels WHERE source_url = :url");
            $stmt->execute(['url' => $novel_url]);
            $cached_novel = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cached_novel) {
                // الكاش متوفر
                $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $log_stmt = $db->prepare("INSERT INTO download_logs (novel_id, user_ip) VALUES (:novel_id, :user_ip)");
                $log_stmt->execute([
                    'novel_id' => $cached_novel['id'],
                    'user_ip'  => $user_ip
                ]);

                $status_message = "
    <div style='background-color: #1e3a3a; border: 1px solid #00adb5; padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px;'>
        <p style='color: #00adb5; margin: 0 0 10px 0; font-weight: bold;'>✨ تم تجهيز فصول الرواية بنجاح (مستخرجة من الكاش)!</p>
        <a href='" . htmlspecialchars($cached_novel['zip_file_path'], ENT_QUOTES, 'UTF-8') . "' download style='display: inline-block; background-color: #00adb5; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 5px; transition: 0.3s;'>📥 اضغط هنا لتحميل ملف ZIP</a>
    </div>";
            } else {
                // الكاش غير متوفر، سنبدأ السحب وعمل الحزمة لأول مرة

                // استخدام uniqid لمنع تداخل المجلدات عند الضغط المتزامن لـ أكثر من مستخدم
                $folder_name = "novel_" . time() . "_" . uniqid();
                $folder_path = "assets/tmp/" . $folder_name;

                if (!is_dir($folder_path)) {
                    mkdir($folder_path, 0755, true); // استخدام تصريح 755 أكثر أماناً من 777
                }

                $downloaded_chapters = []; // مصفوفة لتتبع الفصول التي نجح تحميلها فعلياً لإصلاح أزرار التنقل

                for ($ch = $start_ch; $ch <= $end_ch; $ch++) {

                    $current_ch_url = $novel_url . "/chapter-" . $ch;

                    $ch_curl = curl_init();
                    curl_setopt($ch_curl, CURLOPT_URL, $current_ch_url);
                    curl_setopt($ch_curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_curl, CURLOPT_FOLLOWLOCATION, true);

                    // تحسين أمني: تفعيل التحقق من شهادة الأمان (تأكد من تحديث سيرفرك بـ cacert.pem)
                    curl_setopt($ch_curl, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch_curl, CURLOPT_SSL_VERIFYHOST, 2);

                    curl_setopt($ch_curl, CURLOPT_TIMEOUT, 30); // تحديد مهلة زمنية للطلب حتى لا يعلق السكريبت
                    curl_setopt($ch_curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

                    $html_content = curl_exec($ch_curl);

                    if (curl_errno($ch_curl) || curl_getinfo($ch_curl, CURLINFO_HTTP_CODE) !== 200) {
                        curl_close($ch_curl);
                        continue; // تخطي الفصل في حال حدوث خطأ بالشبكة أو 404
                    }
                    curl_close($ch_curl);

                    // فلترة واستخراج النص
                    $clean_content = "";
                    $dom = new DOMDocument();
                    // استخدام كتم الأخطاء @ متبوعاً بـ الفلترة لترميز النصوص بشكل صحيح ومنع التلاعب بـ الخصائص البنيوية
                    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html_content);
                    $paragraphs = $dom->getElementsByTagName('p');

                    foreach ($paragraphs as $p) {
                        // تطهير النصوص لمنع ثغرات الـ XSS المخزنة في صفحات الـ HTML المرتجعة للمستخدم
                        $txt = trim($p->nodeValue);
                        if (!empty($txt)) {
                            $clean_content .= "<p>" . htmlspecialchars($txt, ENT_QUOTES, 'UTF-8') . "</p>\n";
                        }
                    }

                    // إذا كان الفصل فارغاً تماماً من المحتوى، نتخطى حفظه
                    if (empty($clean_content)) {
                        continue;
                    }

                    // نقوم بحفظ رقم الفصل الذي نجح كـ مفتاح مؤقتاً وسنقوم بتحديث روابط التنقل لاحقاً أو كتابة الملف مباشرة
                    $downloaded_chapters[$ch] = $clean_content;

                    // ممارسات جيدة: إراحة السيرفر المستهدف لتجنب الحظر (Rate Limit) بنصف ثانية
                    usleep(500000);
                }

                $success_count = count($downloaded_chapters);

                if ($success_count > 0) {

                    // كتابة ملفات الفصول الآن بعد معرفة الفصول الناجحة لحل مشكلة أزرار التنقل (Next/Prev) المكسورة
                    $ch_numbers = array_keys($downloaded_chapters);

                    foreach ($downloaded_chapters as $ch => $clean_content) {

                        // إيجاد الفصل السابق والتالي الحقيقيين من المصفوفة الناجحة
                        $current_index = array_search($ch, $ch_numbers);
                        $prev_ch = ($current_index > 0) ? $ch_numbers[$current_index - 1] : null;
                        $next_ch = ($current_index < $success_count - 1) ? $ch_numbers[$current_index + 1] : null;

                        // بناء الأزرار ديناميكياً بناءً على الملفات الموجودة فعلاً
                        $navigation_buttons = "<div class='navigation'>";
                        if ($prev_ch !== null) {
                            $navigation_buttons .= "<a href='ch_" . $prev_ch . ".html' class='btn'>⏮ الفصل السابق</a> ";
                        }
                        $navigation_buttons .= "<a href='read.html' class='btn btn-index'>↩ الفهرس</a> ";
                        if ($next_ch !== null) {
                            $navigation_buttons .= "<a href='ch_" . $next_ch . ".html' class='btn'>الفصل التالي ⏭</a>";
                        }
                        $navigation_buttons .= "</div>";

                        $final_clean_html = "
<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>الفصل رقم " . $ch . "</title>
    <link rel='stylesheet' href='style.css'>
</head>
<body>
    <div class='chapter-container'>
        <h1 class='chapter-title'>الفصل رقم " . $ch . "</h1>
        <div class='chapter-text'>
            " . $clean_content . "
        </div>
        " . $navigation_buttons . "
    </div>
</body>
</html>";

                        $file_name = "ch_" . $ch . ".html";
                        $full_file_path = $folder_path . "/" . $file_name;
                        file_put_contents($full_file_path, $final_clean_html);
                    }

                    // توليد ملف الفهرس الرئيسي read.html
                    $index_file_path = $folder_path . "/read.html";
                    $index_html = "
<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>فهرس الرواية | القراءة أوفلاين</title>
    <style>
        body { font-family: sans-serif; background-color: #161410; color: #fdfbf7; padding: 20px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; background-color: #1c1a16; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.4); }
        h1 { color: #00adb5; font-size: 24px; margin-bottom: 5px; }
        p { color: #888; font-size: 14px; margin-bottom: 25px; }
        ul { list-style: none; padding: 0; }
        li { margin: 12px 0; }
        a { display: block; background-color: #25231f; color: #fff; padding: 14px; text-decoration: none; border-radius: 4px; border: 1px solid #333; font-weight: bold; transition: 0.3s; }
        a:hover { background-color: #00adb5; border-color: #00adb5; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>قائمة فصول الرواية</h1>
        <p>اختر الفصل وابدأ القراءة بدون إنترنت تماماً</p>
        <ul>";

                    foreach ($ch_numbers as $i) {
                        $index_html .= "<li><a href='ch_" . $i . ".html'>الفصل رقم " . $i . "</a></li>";
                    }

                    $index_html .= "
        </ul>
    </div>
</body>
</html>";
                    file_put_contents($index_file_path, $index_html);

                    // توليد ملف ستايل الفصول style.css
                    $css_file_path = $folder_path . "/style.css";
                    $css_code = "
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #161410; color: #fdfbf7; padding: 20px; line-height: 1.9; margin: 0; }
.chapter-container { max-width: 750px; margin: 40px auto; background-color: #1c1a16; padding: 35px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
.chapter-title { color: #00adb5; text-align: center; border-bottom: 1px solid #333; padding-bottom: 15px; margin-bottom: 30px; font-size: 26px; }
.chapter-text p { margin-bottom: 1.8rem; font-size: 19px; text-align: justify; word-spacing: 1px; }
.navigation { text-align: center; margin-top: 40px; border-top: 1px solid #333; padding-top: 20px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
.btn { display: inline-block; background-color: #00adb5; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; transition: 0.3s; border: none; cursor: pointer; font-size: 15px; }
.btn:hover { background-color: #008c95; }
.btn-index { background-color: #25231f; color: #00adb5; border: 1px solid #333; }
.btn-index:hover { background-color: #333; color: #fff; }
@media (max-width: 480px) {
body { padding: 10px; }
.chapter-container { padding: 20px 15px; margin: 10px auto; }
.chapter-title { font-size: 22px; }
.chapter-text p { font-size: 17px; }
.btn { padding: 10px 16px; font-size: 14px; width: 100%; text-align: center; }
.navigation { flex-direction: column; gap: 8px; }
}";
                    file_put_contents($css_file_path, $css_code);
                    // ضغط الحزمة بالكامل إلى ملف ZIP وحذف المجلد المؤقت
                    $final_zip_path = "assets/downloads/" . $folder_name . ".zip";
                    // التأكد من وجود مجلد التحميل النهائي
                    if (!is_dir("assets/downloads/")) {
                        mkdir("assets/downloads/", 0755, true);
                    }
                    $zip = new ZipArchive();
                    if ($zip->open($final_zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        $files = scandir($folder_path);
                        foreach ($files as $file) {
                            if ($file !== '.' && $file !== '..') {
                                $zip->addFile($folder_path . '/' . $file, $file);
                            }
                        }
                        $zip->close();
                        // تنظيف وحذف الملفات المؤقتة
                        foreach ($files as $file) {
                            if ($file !== '.' && $file !== '..') {
                                unlink($folder_path . '/' . $file);
                            }
                        }
                        rmdir($folder_path);
                        // إدراج السجل الجديد بالكاش لتسريع الطلبات القادمة للرواية نفسها
                        $insert_stmt = $db->prepare("INSERT INTO cached_novels (novel_title, source_url, total_chapters, zip_file_path) VALUES (:title, :url, :total, :path)");
                        $insert_stmt->execute([
                            'title' => 'رواية جديدة السحب',
                            'url'   => $novel_url,
                            'total' => $success_count,
                            'path'  => $final_zip_path
                        ]);
                        $status_message = "

🎉 مبروك! تم سحب الرواية وتجهيزها لأول مرة بنجاح.
<a href='" . htmlspecialchars($final_zip_path, ENT_QUOTES, 'UTF-8') . "' download style='display: inline-block; background-color: #00adb5; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 5px; transition: 0.3s;'>📥 اضغط هنا لتحميل ملف ZIP الجديد
";
                    } else {
                        $status_message = "فشل إنشاء ملف الـ ZIP المضغوط على السيرفر.";
                    }
                } else {
                    if (is_dir($folder_path)) {
                        rmdir($folder_path);
                    }
                    $status_message = "فشل تحميل الفصول المطلوبة. يرجى التحقق من نمط روابط الموقع أو المحاولة لاحقاً.";
                }
            }
        } catch (PDOException $e) {
            // تسجيل الخطأ بشكل آمن في مجلد مخصص للأخطاء
            if (!is_dir("assets/errors")) {
                mkdir("assets/errors", 0755, true);
            }
            $errorMsg = "[" . date('Y-m-d H:i:s') . "] Query Error: " . $e->getMessage() . PHP_EOL;
            file_put_contents("assets/errors/errors.txt", $errorMsg, FILE_APPEND);
            $status_message = "عذراً، حدث خطأ داخلي أثناء معالجة الطلب. تم تسجيل الخطأ لدى الإدارة.";
        }
    }
}
