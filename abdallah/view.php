<?php
session_start();
require_once '../db_config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit();
}

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // استرداد جميع المستخدمين
    $sql = "SELECT id, username FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض المستخدمين</title>
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

        table {
            width: 90%;
            max-width: 800px;
            border-collapse: collapse;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }

        th, td {
            padding: 12px 15px;
            text-align: center;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        a {
            color: #4CAF50;
            text-decoration: none;
            margin-right: 10px;
            font-weight: bold;
        }

        a.delete {
            color: #f44336;
        }

        a:hover {
            text-decoration: underline;
        }

        /* عرض رسالة عند عدم وجود بيانات */
        .no-data {
            text-align: center;
            padding: 20px;
            font-size: 16px;
            color: #777;
        }

        /* استجابة للشاشات الصغيرة */
        @media (max-width: 600px) {
            table {
                font-size: 14px;
            }

            th, td {
                padding: 10px;
            }

            a {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <h2>قائمة المستخدمين</h2>
    <table>
        <tr>
            <th>#</th>
            <th>اسم المستخدم</th>
            <th>الإجراء المطلوب</th>
        </tr>
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td>
                        <!-- زر تعديل -->
                        <a href="edit.php?id=<?php echo $user['id']; ?>">تعديل</a>
                        <!-- زر حذف -->
                        <a href="delete.php?id=<?php echo $user['id']; ?>" class="delete" onclick="return confirm('هل أنت متأكد من أنك تريد حذف هذا المستخدم؟')">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="3" class="no-data">لا توجد بيانات متاحة.</td>
            </tr>
        <?php endif; ?>
    </table>
</body>
</html>