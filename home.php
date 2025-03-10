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

    // تسجيل دخول المستخدم إلى الصفحة الرئيسية
    logActivity($pdo, $_SESSION['username'], "دخول إلى الصفحة الرئيسية");

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

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الصفحة الرئيسية</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column; /* لترتيب العناصر عموديًا */
            align-items: center; /* لمركز العناصر أفقيًا */
            justify-content: center; /* لمركز العناصر عموديًا */
            height: 100vh;
            margin: 0;
        }
        .home-container {
            text-align: center;
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 100px;
        }
        .btn {
            margin: 5px 0; /* إضافة مسافة بين الأزرار */
        }
        @media (max-width: 576px) {
            .logo {
                max-width: 80px; /* تقليل حجم الشعار على الشاشات الصغيرة */
            }
        }
    </style>
</head>
<body>
    <!-- إضافة الشعار -->
    <div class="logo-container">
        <img src="logo.png" alt="شعار الموقع" class="logo">
    </div>

    <!-- الحاوية الرئيسية -->
    <div class="home-container">
        <h2>مرحبًا، <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>اختر المسار الذي تريد:</p>
        <!-- رابط إلى telecom مع تسجيل النشاط -->
        <a href="log_action.php?action=accessed_telecom&redirect=telecom/index.php" class="btn btn-success w-100">التيليكوم</a>
        <!-- رابط إلى cards مع تسجيل النشاط -->
        <a href="log_action.php?action=accessed_cards&redirect=cards/index.php" class="btn btn-primary w-100">الكروت</a>
        <!-- زر تسجيل الخروج -->
        <a href="logout.php" class="btn btn-danger w-100 mt-3">تسجيل الخروج</a>
    </div>
</body>
</html>