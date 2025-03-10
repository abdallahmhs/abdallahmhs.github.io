<?php
session_start();

// التأكد من أن المستخدم مسجل دخول
if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit;
}

require '../db_config.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}

function logActivity($pdo, $username, $action) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (username, action) VALUES (:username, :action)");
        $logStmt->execute([':username' => $username, ':action' => $action]);
    } catch (Exception $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

require 'routeros_api.class.php';

$routers = [];
if (file_exists('mikrotik_routers.json')) {
    $routers = json_decode(file_get_contents('mikrotik_routers.json'), true);
    if (!is_array($routers) || empty($routers)) {
        echo "<p class='error-message'>ملف الإعدادات غير صالح.</p>";
        exit;
    }
} else {
    echo "<p class='error-message'>ملف الإعدادات غير موجود.</p>";
    exit;
}

$selectedRouter = $_SESSION['selected_router'] ?? 0;
$router = $routers[$selectedRouter] ?? null;

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getDeviceType($leases, $macAddress) {
    foreach ($leases as $lease) {
        if ($lease['mac-address'] === $macAddress) {
            return isset($lease['host-name']) ? $lease['host-name'] : 'غير معروف';
        }
    }
    return 'غير معروف';
}

function parseComment($comment) {
    $data = [];
    if (preg_match('/([^!]+)!([^%]+)%([^^]+)\^(.*?)\*(.*?)&(.*?)/', $comment, $matches)) {
        $data['opened_at'] = trim($matches[2]);
        $data['current_close_time'] = trim($matches[4]);
        $data['remaining_data'] = formatBytes(trim($matches[5]));
    }
    return $data;
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بحث كروت</title>
    <!-- ربط Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            direction: rtl;
            text-align: right;
        }
        .search-form {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .search-form input[type="text"] {
            width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .search-form button {
            margin-left: 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .search-form button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th,
        td {
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .error-message {
            color: red;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }
        .success-message {
            color: green;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center">بحث عن كارت في السيرفر</h1>
        <!-- نموذج البحث -->
        <form method="POST" action="" class="search-form">
            <input type="text" id="searchTerm" name="searchTerm" placeholder="أدخل اسم المستخدم أو عنوان MAC" required>
            <button type="submit" class="btn btn-primary">بحث</button>
        </form>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $searchTerm = isset($_POST['searchTerm']) ? $_POST['searchTerm'] : '';
            if (empty($searchTerm)) {
                echo "<p class='error-message'>يرجى إدخال اسم المستخدم أو عنوان MAC.</p>";
            } elseif ($router) {
                $API = new RouterosAPI();
                if ($API->connect($router['host'], $router['user'], $router['pass'])) {
                    $users = $API->comm('/ip/hotspot/user/print');
                    $leases = $API->comm('/ip/dhcp-server/lease/print');

                    $results = [];
                    foreach ($users as $user) {
                        if (stripos($user['name'], $searchTerm) !== false || stripos($user['mac-address'] ?? '', $searchTerm) !== false) {
                            $macAddress = isset($user['mac-address']) ? $user['mac-address'] : '';
                            $uptime = isset($user['uptime']) ? $user['uptime'] : 'غير محدد';
                            $commentData = parseComment($user['comment'] ?? '');

                            $results[] = [
                                'name' => $user['name'],
                                'mac_address' => $macAddress,
                                'uptime' => $uptime,
                                'download' => isset($user['bytes-out']) ? formatBytes($user['bytes-out']) : '0 B',
                                'upload' => isset($user['bytes-in']) ? formatBytes($user['bytes-in']) : '0 B',
                                'device_type' => getDeviceType($leases, $macAddress),
                                'opened_at' => $commentData['opened_at'] ?? 'غير محدد',
                                'current_close_time' => $commentData['current_close_time'] ?? 'غير محدد',
                                'remaining_data' => $commentData['remaining_data'] ?? 'غير محدد',
                            ];
                        }
                    }

                    if (!empty($results)) {
                        echo '<table class="table table-striped">';
                        echo '<tr><th>اسم المستخدم</th><th>عنوان MAC</th><th>مدة الاستخدام</th><th>تحميل</th><th>رفع</th><th>نوع الجهاز</th><th>وقت الاستخدام</th><th>تاريخ الاستخدام</th><th>الكمية المتبقية</th></tr>';
                        foreach ($results as $result) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($result['name']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['mac_address']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['uptime']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['download']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['upload']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['device_type']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['opened_at']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['current_close_time']) . '</td>';
                            echo '<td>' . htmlspecialchars($result['remaining_data']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } else {
                        echo "<p class='error-message'>لم يتم العثور على أي نتائج للبحث عن: $searchTerm</p>";
                    }

                    $API->disconnect();
                } else {
                    echo "<p class='error-message'>فشل الاتصال بجهاز MikroTik.</p>";
                }
            } else {
                echo "<p class='error-message'>الراوتر غير محدد.</p>";
            }
        }
        ?>
                <div style="text-align: center;">
            <a href="dashboard.php" class="btn btn-primary">العودة إلى لوحة التحكم</a>
        </div>
    </div>
    <!-- ربط Bootstrap JS (اختياري) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>