<?php
session_start();

require_once 'db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // قراءة المعاملات (action وredirect)
    $action = $_GET['action'] ?? '';
    $redirect = $_GET['redirect'] ?? '';

    if ($action && $redirect) {
        // تسجيل النشاط
        logActivity($pdo, $_SESSION['username'], $action);

        // إعادة التوجيه إلى المشروع المطلوب
        header("Location: $redirect");
        exit();
    } else {
        die("خطأ: غير مصرح لك بتنفيذ هذا الإجراء.");
    }

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