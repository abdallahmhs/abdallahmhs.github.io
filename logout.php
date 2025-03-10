<?php
session_start();
require_once 'db_config.php';

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];

        // تسجيل نشاط تسجيل الخروج في activity_log
        logActivity($pdo, $username, "logged_out");
    }

    // إنهاء الجلسة
    session_destroy();

    // إعادة التوجيه إلى صفحة تسجيل الدخول
    header("Location: login.php");
    exit();

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

// دالة لتسجيل النشاطات
function logActivity($pdo, $username, $action) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (username, action) VALUES (:username, :action)");
        $logStmt->execute([':username' => $username, ':action' => $action]);
    } catch (Exception $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}
?>