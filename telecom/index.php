<?php
session_start();
require_once '../db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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

require_once 'lib/shared_execute.php';
require_once 'includes/TelecomBase.php';

$telecom = new TelecomBase();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // استقبال البيانات من النموذج
    $title = $_POST['location_name']; // اسم الموقع
    $name = $_POST['username'];
    $password = $_POST['password'];

    // تخزين اسم الموقع في الجلسة
    $_SESSION['location_name'] = $title;

    // تسجيل محاولة تسجيل الدخول
    logActivity($pdo, $_SESSION['username'], "محاولة تسجيل دخول إلى موقع: {$title}");

    // محاولة تسجيل الدخول
    $result = $telecom->login($title, $name, $password);

    // إضافة اسم الموقع إلى النتيجة فقط إذا نجح تسجيل الدخول
    if ($result) {
        $_SESSION['login_result'] = " " . $result; // إضافة اسم الموقع هنا
        logActivity($pdo, $_SESSION['username'], "نجاح تسجيل الدخول إلى موقع: {$title}");
    } else {
        $_SESSION['login_result'] = "فشل تسجيل الدخول";
        logActivity($pdo, $_SESSION['username'], "فشل تسجيل الدخول إلى موقع: {$title}");
    }

    header('Location: /telecom/');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'report') {
	ini_set('max_execution_time', 300);
    // تسجيل محاولة إنشاء التقرير
    logActivity($pdo, $_SESSION['username'], "إنشاء تقرير");
	
    // إنشاء التقرير
    $report_results = $telecom->report();
    $_SESSION['report_results'] = $report_results;
    header('Location: /telecom/');
    exit;
}

include 'public/index.php';
?>