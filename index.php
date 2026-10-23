<?php
include 'process.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lite Novel Downloader | قارئ الروايات الموفر للباقة</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <main>
        <div class="container">
            <div class="card">
                <h1>Novel ZIP Downloader</h1>
                <p class="subtitle">حمل روايتك المفضلة بصيغة ونصوص خفيفة للقراءة أوفلاين</p>

                <?php echo $status_message; ?>

                <form action="#" method="POST">

                    <div class="form-group">
                        <label for="novel_url">رابط الرواية الأصلي:</label>
                        <input type="url" id="novel_url" name="novel_url" placeholder="https://example.com" required>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label for="start_ch">من فصل:</label>
                            <input type="number" id="start_ch" name="start_ch" value="1" min="1" required>
                        </div>

                        <div class="form-group">
                            <label for="end_ch">إلى فصل:</label>
                            <input type="number" id="end_ch" name="end_ch" value="20" min="1" required>
                        </div>
                    </div>

                    <button type="submit"> تحميل الرواية</button>

                </form>

                <p class="footer-note">ملاحظة: سيتم توليد ملف يحتوي على فهرس وفصول مريحة للعين بدون إعلانات ثقيلة.</p>
            </div>
        </div>
    </main>
    <footer>
        <p>&copy; 2026 جميع الحقوق محفوظة. تم التطوير بواسطة عبد الرحمن أحمد</p>
    </footer>

</body>

</html>