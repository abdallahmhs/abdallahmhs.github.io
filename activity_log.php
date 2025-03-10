<?php
session_start();
// التأكد من أن المستخدم مسجل دخول من النظام الرئيسي
if (!isset($_SESSION['username'])) {
    header('Location: /login.php'); // توجيه المستخدم إلى تسجيل الدخول الرئيسي
    exit;
}

// تضمين ملف Configuration الخاص بقاعدة البيانات
require 'db_config.php';

try {
    // إنشاء اتصال بقاعدة البيانات باستخدام PDO
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // استعلام لجلب السجلات من جدول activity_log
    $stmt = $pdo->query("SELECT * FROM activity_log ORDER BY timestamp DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>سجل تحركات المستخدمين</title>
</head>
<body>
    <h2>سجل تحركات المستخدمين</h2>
    <table border="1">
        <tr>
            <th>المستخدم</th>
            <th>العملية</th>
            <th>التاريخ</th>
        </tr>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= htmlspecialchars($log['username']) ?></td>
                <td><?= htmlspecialchars($log['action']) ?></td>
                <td><?= htmlspecialchars($log['timestamp']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>