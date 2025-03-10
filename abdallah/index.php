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

    // معالجة النموذج إذا تم إرساله
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        // التحقق من أن الحقول ليست فارغة
        if (empty($username) || empty($password)) {
            $error_message = "يرجى ملء جميع الحقول.";
        } else {
            // تشفير كلمة المرور
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            // التحقق مما إذا كان اسم المستخدم موجودًا بالفعل
            $check_sql = "SELECT COUNT(*) FROM users WHERE username = :username";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            $count = $check_stmt->fetchColumn();

            if ($count > 0) {
                $error_message = "اسم المستخدم موجود بالفعل!";
            } else {
                // إدراج المستخدم الجديد
                $sql = "INSERT INTO users (username, password) VALUES (:username, :password)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);

                if ($stmt->execute()) {
                    $success_message = "تم إنشاء المستخدم بنجاح!";
                } else {
                    $error_message = "حدث خطأ أثناء إنشاء المستخدم.";
                }
            }
        }
    }

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء مستخدم جديد</title>
    <style>
        /* تنسيق عام */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f9f9f9;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        form {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            width: 100%;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background-color: #45a049;
        }

        .message {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* زر التحويل */
        .redirect-button {
            margin-top: 20px; /* فاصل بين الزر والنماذج */
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%; /* يجعل الزر يتمدد عبر العرض الكامل */
            max-width: 300px; /* يحدد عرض الزر الأقصى */
            text-align: center; /* تمركز النص داخل الزر */
        }

        .redirect-button:hover {
            background-color: #0056b3;
        }

        /* حاوية الزر للتوسيط */
        .button-container {
            display: flex;
            justify-content: center; /* تمركز أفقي */
            width: 100%; /* يجعل الحاوية تغطي العرض الكامل */
            margin-top: 20px; /* فاصل بين الزر والعناصر السابقة */
        }

        /* استجابة للشاشات الصغيرة */
        @media (max-width: 600px) {
            form {
                padding: 15px;
            }

            button, .redirect-button {
                font-size: 14px;
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
    <h2>إنشاء مستخدم جديد</h2>
    <form method="POST" action="">
        <label for="username">اسم المستخدم:</label>
        <input type="text" id="username" name="username" required>
        <label for="password">كلمة المرور:</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">إنشاء المستخدم</button>
    </form>

    <!-- حاوية زر الذهاب إلى اليوزرات -->
    <div class="button-container">
        <a href="view.php" class="redirect-button">الذهاب إلى اليوزرات</a>
    </div>

    <!-- عرض الرسائل -->
    <?php if (isset($success_message)): ?>
        <div class="message success"><?php echo $success_message; ?></div>
    <?php elseif (isset($error_message)): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>
</body>
</html>