# Novel ZIP Downloader 🚀
**Lite Novel Downloader | قارئ الروايات الموفر للباقة والجهد**

An efficient, secure, and production-ready PHP utility web application designed to scrape, clean, and package light novel chapters from external sites into beautifully formatted, responsive HTML/CSS offline packages wrapped inside a single ZIP file.

---

## 🌍 نظرة عامة على المشروع (Project Overview)
يقوم هذا النظام بحل مشكلة الإعلانات الكثيفة واستهلاك البيانات أثناء قراءة الروايات عبر الإنترنت. يتيح للمستخدم إدخال رابط الرواية وتحديد نطاق الفصول، ليقوم الخادم بسحب النصوص وتطهيرها وتوليد واجهة قراءة ليلية مريحة ومنظمة تعمل بالكامل دون الحاجة للاتصال بالإنترنت (Offline).

## ✨ Key Features (الميزات التقنية الرئيسية)
* **Advanced SSRF Protection:** Validates input URLs to restrict loopback addresses, local IPs (`127.0.0.1`, `localhost`), and internal subnets.
* **Smart Caching Subsystem:** Implements database checking using PDO prepared statements to deliver instantly cached ZIP files for duplicate requests, optimizing CPU and bandwidth.
* **Dynamic Navigation Builder:** Automatically fixes next/prev buttons by evaluating successfully downloaded chapters to eliminate broken links.
* **Comprehensive XSS Sanitization:** Sanitizes structural texts dynamically with DOMDocument mapping and standard Entity escaping.
* **Concurrent Request Handling:** Generates runtime unique hashes using `uniqid()` and timestamps to eliminate simultaneous generation overlapping.
* **Secure Error Logging:** Silences client-facing system logs and writes structured errors safely into internal server registries.

## 🛠️ Tech Stack & Architecture
* **Backend:** PHP (Native Procedural Layer separated into logical Controller and Templates).
* **Database Management:** MySQL via PDO (PHP Data Objects).
* **DOM Operations:** Core PHP `DOMDocument` for node scraping and markup isolation.
* **Compression:** Native PHP `ZipArchive` utility.
* **UI/UX:** Responsive Vanilla CSS featuring built-in fluid Typography and custom Dark Mode layout.

## 📁 Directory Structure
```text
gama_web/
│
├── assets/
│   ├── css/
│   │   └── style.css          # Main App Interface CSS
│   ├── downloads/            # Production storage for generated ZIP caches
│   ├── errors/               # Target folder for decoupled internal error logs
│   └── tmp/                  # Runtime dynamic caching environments
│
├── condb.php                 # Core PDO Database Singleton Context
├── index.php                 # View Layer Container template
└── process.php               # Heavy Processing Controller Layer
```

## 🚀 Setup & Installation
1. Clone the repository into your local server environment (XAMPP/WampServer).
2. Import the database structure using the query defined in `create_database.sql`.
3. Configure your localized user parameters inside `condb.php`.
4. Ensure the `assets/` internal structure has directory structural permissions allowed (`0755`).

---
Developed with ❤️ by **Abdulrahman Ahmed** (2026)
