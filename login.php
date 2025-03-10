<?php
session_start();
require_once 'db_config.php';

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = $_POST['username'];
        $password = $_POST['password'];

        // استعلام لجلب بيانات المستخدم
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;

            // تسجيل نشاط تسجيل الدخول في activity_log
            logActivity($pdo, $username, "logged_in");

            header("Location: home.php");
            exit();
        } else {
            $error = "اسم المستخدم أو كلمة المرور غير صحيحة.";
        }
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


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo-container {
            text-align: center; /* لمركز الشعار فقط */
            margin-bottom: 20px; /* مسافة بين الشعار والعنوان */
        }
        .logo {
            max-width: 100px; /* ضبط حجم الشعار */
        }
        .login-container h2 {
            margin-bottom: 20px;
        }
        .form-label {
            font-size: 16px;
        }
        .btn-primary {
            font-size: 18px;
            padding: 10px;
        }
        @media (max-width: 576px) {
            .login-container {
                padding: 15px;
            }
            .btn-primary {
                font-size: 16px;
            }
            .logo {
                max-width: 80px; /* تقليل حجم الشعار على الشاشات الصغيرة */
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- إضافة حاوية خاصة للشعار -->
        <div class="logo-container">
            <img src="logo.png" alt="شعار الموقع" class="logo">
        </div>
        <h2 class="text-center">تسجيل الدخول</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">اسم المستخدم:</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور:</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">تسجيل الدخول</button>
        </form>
    </div>
</body>
</html>