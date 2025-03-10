<?php
session_start();
require_once '../db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// التحقق من وجود ID المستخدم
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("المعرف غير صالح.");
}
$user_id = $_GET['id'];

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // استرداد اسم المستخدم قبل الحذف
    $sql = "SELECT username FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("لم يتم العثور على المستخدم.");
    }

    // حذف المستخدم
    $delete_sql = "DELETE FROM users WHERE id = :id";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->bindParam(':id', $user_id);

    if ($delete_stmt->execute()) {
        echo "<script>alert('تم حذف المستخدم " . htmlspecialchars($user['username']) . " بنجاح!'); window.location.href='view_users.php';</script>";
    } else {
        echo "<script>alert('حدث خطأ أثناء حذف المستخدم.'); window.location.href='view_users.php';</script>";
    }

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>