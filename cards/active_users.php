<?php
session_start();

// التأكد من أن المستخدم مسجل دخول من النظام الرئيسي
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php'); // توجيه المستخدم إلى تسجيل الدخول الرئيسي
    exit;
}

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
require '../db_config.php';
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

require 'routeros_api.class.php';

// قراءة إعدادات MikroTik من ملف
$routers = [];
if (file_exists('mikrotik_routers.json')) {
    $routers = json_decode(file_get_contents('mikrotik_routers.json'), true);
}

// تحديد الراوتر المختار
$selectedRouter = $_SESSION['selected_router'] ?? 0;
$router = $routers[$selectedRouter] ?? null;
$routerName = $router['name'] ?? 'غير محدد';
$users = [];

if ($router) {
    $API = new RouterosAPI();
    if ($API->connect($router['host'], $router['user'], $router['pass'])) {
        // جلب المستخدمين المتصلين بالهوتسبوت
        $activeUsers = $API->comm('/ip/hotspot/active/print');
        
        if (!empty($activeUsers)) {
            foreach ($activeUsers as $user) {
                $hosts = $API->comm('/ip/dhcp-server/lease/print', [
                    '?active-address' => $user['address']
                ]);
                $hostInfo = !empty($hosts) ? $hosts[0]['host-name'] : 'غير موجود';
                
                $users[] = [
                    'id' => $user['.id'],
                    'name' => $user['user'],
                    'ip' => $user['address'],
                    'macAddress' => $user['mac-address'],
                    'comment' => $user['comment'] ?? 'لا يوجد تعليق',
                    'uptime' => $user['uptime'] ?? 'غير متوفر',
                    'host' => $hostInfo
                ];
            }
        }
        $API->disconnect();
    }
}

// حذف المستخدم
if (isset($_GET['delete_id']) && $router) {
    $deleteId = $_GET['delete_id'];
    $API = new RouterosAPI();
    if ($API->connect($router['host'], $router['user'], $router['pass'])) {
        // حذف المستخدم
        $API->comm('/ip/hotspot/active/remove', ['.id' => $deleteId]);

        // تسجيل النشاط في قاعدة البيانات
        logActivity($pdo, $_SESSION['username'], "حذف مستخدم نشط على الراوتر {$routerName}");

        $API->disconnect();
    }

    // إعادة التوجيه إلى نفس الصفحة بعد الحذف
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المستخدمين النشطين</title>
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; text-align: right; background-color: #f4f4f4; margin: 0; padding: 20px; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; color: #4CAF50; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: right; border: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 4px; cursor: pointer; display: inline-block; }
        .btn-delete {
            color: white;
            background-color: red;
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-delete:hover { background-color: darkred; }
        .btn-back { background-color: #4CAF50; color: white; margin-top: 20px; }
        .btn-back:hover { background-color: #45a049; }
        .no-users { text-align: center; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <h1>المتصلين النشطين   - <?php echo $routerName; ?></h1>
        <?php if (!empty($users)): ?>
        <table>
            <thead>
                <tr>
                    <th>اسم المستخدم</th>
                    <th>عنوان IP</th>
                    <th>عنوان MAC</th>
                    <th>التعليق</th>
                    <th>مدة الاتصال</th>
                    <th>اسم الجهاز</th>
                    <th>مسح</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['name']; ?></td>
                    <td><?php echo $user['ip']; ?></td>
                    <td><?php echo $user['macAddress']; ?></td>
                    <td><?php echo $user['comment']; ?></td>
                    <td><?php echo $user['uptime']; ?></td>
                    <td><?php echo $user['host']; ?></td>
                    <td><a href="?delete_id=<?php echo $user['id']; ?>" class="btn btn-delete" onclick="return confirm('هل أنت متأكد أنك تريد حذف هذا المستخدم؟')">حذف</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p class="no-users">لا يوجد مستخدمين متصلين حالياً.</p>
        <?php endif; ?>
        <div style="text-align: center;">
            <a href="dashboard.php" class="btn btn-back">العودة إلى لوحة التحكم</a>
        </div>
    </div>
</body>
</html>
