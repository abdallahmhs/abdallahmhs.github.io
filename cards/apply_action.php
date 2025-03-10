<?php
require '../db_config.php'; // تأكد من الاتصال بقاعدة البيانات
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح لك بتنفيذ هذا الإجراء.']);
    exit;
}

// إنشاء اتصال بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage()]);
    exit;
}

require 'routeros_api.class.php';

// قراءة إعدادات MikroTik من ملف
$routers = [];
if (file_exists('mikrotik_routers.json')) {
    $routers = json_decode(file_get_contents('mikrotik_routers.json'), true);
}

$selectedRouter = $_SESSION['selected_router'] ?? 0;
$router = $routers[$selectedRouter] ?? null;

if (!$router) {
    echo json_encode(['success' => false, 'message' => 'الراوتر غير موجود.']);
    exit;
}

// الاتصال بـ MikroTik
$API = new RouterosAPI();
$API->debug = false;

if (!$API->connect($router['host'], $router['user'], $router['pass'])) {
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بـ MikroTik.']);
    exit;
}

// قراءة البيانات المرسلة
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'إجراء غير معروف';
$username = $_SESSION['username'];

// دالة لتسجيل النشاطات
function logActivity($pdo, $username, $action) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (username, action) VALUES (:username, :action)");
        $logStmt->execute([':username' => $username, ':action' => $action]);
    } catch (Exception $e) {
        // تسجيل الخطأ إذا فشل الإدراج
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

// معالجة الإجراءات
$response = ['success' => false, 'message' => ''];
try {
    switch ($action) {
        case 'add-users-from-file':
            $users = $data['users'] ?? [];
            if (!empty($users)) {
                foreach ($users as $user) {
                    $API->comm('/ip/hotspot/user/add', [
                        'name' => $user['name'],
                        'password' => $user['password'],
                        'profile' => $user['profile'] ?? '1.5M',
                        'disabled' => $user['disabled'] ?? 'no',
                        'limit-bytes-total' => $user['limit-bytes-total'] ?? '300M',
                        'limit-uptime' => $user['limit-uptime'] ?? '2h',
                        'comment' => $user['comment'] ?? 'jan/01/2025#12:12:12%true'
                    ]);
                }
                logActivity($pdo, $username, "إضافة مستخدمين على الراوتر {$router['name']}");
                $response = ['success' => true, 'message' => 'تمت إضافة المستخدمين بنجاح.'];
            } else {
                $response = ['message' => 'لا يوجد مستخدمين لإضافتهم.'];
            }
            break;

        case 'enable':
        case 'disable':
        case 'delete':
        case 'reset-counter':
            $userIds = $data['userIds'] ?? [];
            if (empty($userIds)) {
                $response = ['message' => 'الرجاء تحديد مستخدمين أولاً.'];
            } else {
                foreach ($userIds as $userId) {
                    switch ($action) {
                        case 'enable':
                            $API->write('/ip/hotspot/user/set', false);
                            $API->write('=.id=' . $userId, false);
                            $API->write('=disabled=no', true);
                            break;
                        case 'disable':
                            $API->write('/ip/hotspot/user/set', false);
                            $API->write('=.id=' . $userId, false);
                            $API->write('=disabled=yes', true);
                            break;
                        case 'delete':
                            $API->write('/ip/hotspot/user/remove', false);
                            $API->write('=.id=' . $userId, true);
                            break;
                        case 'reset-counter':
                            $API->write('/ip/hotspot/user/reset-counters', false);
                            $API->write('=.id=' . $userId, true);
                            break;
                    }
                }
                $API->read();
                logActivity($pdo, $username, "تنفيذ الإجراء '$action' على الراوتر {$router['name']}");
                $response = ['success' => true, 'message' => "تم تنفيذ الإجراء '$action' بنجاح."];
            }
            break;

        case 'edit-users':
            $userIds = $data['userIds'] ?? [];
            if (empty($userIds)) {
                $response = ['message' => 'الرجاء تحديد مستخدمين أولاً.'];
            } else {
                foreach ($userIds as $userId) {
                    $params = ['.id' => $userId];
                    $fields = ['name', 'password', 'profile', 'comment', 'limitUptime', 'limitBytesTotal', 'macAddress'];
                    foreach ($fields as $field) {
                        if (isset($data[$field])) {
                            $value = $data[$field];
                            if ($value === '0') {
                                if ($field === 'limitUptime') {
                                    $params['limit-uptime'] = '';
                                } elseif ($field === 'limitBytesTotal') {
                                    $params['limit-bytes-total'] = '';
                                } elseif ($field === 'macAddress') {
                                    $params['mac-address'] = '';
                                } else {
                                    $params[str_replace('limitUptime', 'limit-uptime', str_replace('limitBytesTotal', 'limit-bytes-total', str_replace('macAddress', 'mac-address', $field)))] = '';
                                }
                            } elseif (!empty($value)) {
                                if ($field === 'limitUptime') {
                                    $params['limit-uptime'] = $value;
                                } elseif ($field === 'limitBytesTotal') {
                                    $params['limit-bytes-total'] = $value;
                                } elseif ($field === 'macAddress') {
                                    $params['mac-address'] = $value;
                                } else {
                                    $params[str_replace('limitUptime', 'limit-uptime', str_replace('limitBytesTotal', 'limit-bytes-total', str_replace('macAddress', 'mac-address', $field)))] = $value;
                                }
                            }
                        }
                    }
                    $API->comm('/ip/hotspot/user/set', $params);
                }
                logActivity($pdo, $username, "تعديل المستخدمين على الراوتر {$router['name']}");
                $response = ['success' => true, 'message' => 'تم تعديل المستخدمين بنجاح.'];
            }
            break;

        default:
            $response = ['message' => 'الإجراء غير معروف.'];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()];
}

$API->disconnect();
echo json_encode($response);
?>