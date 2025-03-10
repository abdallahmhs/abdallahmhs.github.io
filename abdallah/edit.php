<?php
session_start();
require_once '../db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}

// الحصول على ID المستخدم من الرابط
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("المعرف غير صالح.");
}
$user_id = $_GET['id'];

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // استرداد بيانات المستخدم بناءً على ID
    $sql = "SELECT id, username FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("لم يتم العثور على المستخدم.");
    }

    // معالجة النموذج إذا تم إرساله
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $new_username = trim($_POST['username']);
        $new_password = trim($_POST['password']);

        // التحقق من أن الحقول ليست فارغة
        if (empty($new_username)) {
            $error_message = "يرجى إدخال اسم المستخدم.";
        } else {
            // تحديث اسم المستخدم
            $update_sql = "UPDATE users SET username = :username WHERE id = :id";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':username', $new_username);
            $update_stmt->bindParam(':id', $user_id);

            // تحديث كلمة المرور إذا تم إدخالها
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update_sql .= ", password = :password";
                $update_stmt->bindParam(':password', $hashed_password);
            }

            $update_sql .= " WHERE id = :id";

            if ($update_stmt->execute()) {
                $success_message = "تم تحديث المستخدم بنجاح!";
            } else {
                $error_message = "حدث خطأ أثناء تحديث المستخدم.";
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
    <title>تعديل مستخدم</title>
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
            width: 90%;
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
            font-size: 16px;
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
            font-size: 16px;
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

        /* استجابة للشاشات الصغيرة */
        @media (max-width: 600px) {
            form {
                padding: 15px;
            }

            input[type="text"], input[type="password"], button {
                font-size: 14px;
                padding: 8px;
            }

            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <h2>تعديل مستخدم</h2>
    <form method="POST" action="">
        <label for="username">اسم المستخدم:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        <label for="password">كلمة المرور الجديدة (اختياري):</label>
        <input type="password" id="password" name="password">
        <button type="submit">تحديث المستخدم</button>
    </form>

    <!-- عرض الرسائل -->
    <?php if (isset($success_message)): ?>
        <div class="message success"><?php echo $success_message; ?></div>
    <?php elseif (isset($error_message)): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>
</body>
</html>