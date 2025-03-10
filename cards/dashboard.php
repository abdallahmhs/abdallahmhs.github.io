<?php
// dashboard.php
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

// قراءة إعدادات MikroTik من ملف
$routers = [];
if (file_exists('mikrotik_routers.json')) {
    $routers = json_decode(file_get_contents('mikrotik_routers.json'), true);
}

// تحديد السيرفر المختار
$selectedRouter = $_GET['router'] ?? $_SESSION['selected_router'] ?? 0;
$router = $routers[$selectedRouter] ?? null;

// اسم السيرفر المحدد
$routerName = $router['name'] ?? 'غير محدد';

if ($router) {
    $_SESSION['selected_router'] = $selectedRouter;

    require 'routeros_api.class.php';
    $API = new RouterosAPI();

    if ($API->connect($router['host'], $router['user'], $router['pass'])) {
        // تسجيل نشاط دخول المستخدم إلى السيرفر
        logActivity($pdo, $_SESSION['username'], "دخول إلى السيرفر {$routerName}");

// استرداد بيانات اليوزرات
$users = $API->comm('/ip/hotspot/user/print');
$userStats = [];

foreach ($users as $user) {
    // تحديد الحرف الأول من اسم المستخدم
    $firstChar = substr($user['name'], 0, 1);

    // التحقق مما إذا كان الحرف الأول رقمًا
    if (is_numeric($firstChar)) {
        if (!isset($userStats[$firstChar])) {
            $userStats[$firstChar] = [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'users' => []
            ];
        }
    } else {
        if (!isset($userStats['other'])) {
            $userStats['other'] = [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'users' => []
            ];
        }
        $firstChar = 'other';
    }

    // زيادة العدد الإجمالي لليوزرات
    $userStats[$firstChar]['total']++;

    // فحص النشاط بناءً على bytes-out و mac-address
    if (
        (isset($user['bytes-out']) && $user['bytes-out'] > 0) || 
        (isset($user['mac-address']) && !empty($user['mac-address']))
    ) {
        $userStats[$firstChar]['active']++;
    } else {
        $userStats[$firstChar]['inactive']++;
    }

    // إضافة المستخدم إلى القائمة
    $userStats[$firstChar]['users'][] = $user;
}

$API->disconnect();
} else {
        // تسجيل فشل الاتصال بالسيرفر
        logActivity($pdo, $_SESSION['username'], "فشل دخول إلى السيرفر {$routerName}");
        die(json_encode(['success' => false, 'message' => 'فشل الاتصال بـ MikroTik.']));
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم</title>
    <link rel="stylesheet" href="styles.css">
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .dashboard-container {
        max-width: 500px;
        margin: 20px auto;
        padding: 20px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1, h2 {
        color: #333;
        text-align: center;
    }

    h1 {
        color: #4CAF50;
        margin-top: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    table, th, td {
        border: 1px solid #ddd;
    }

    th, td {
        padding: 12px;
        text-align: center;
    }

    th {
        background-color: #4CAF50;
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f9f9f9;
    }

    tr:hover {
        background-color: #f1f1f1;
    }

    .button {
        padding: 10px 20px;
        margin: 5px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .button:hover {
        background-color: #45a049;
    }

    .popup {
        display: none;
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        padding-top: 50px;
    }

    .popup-content {
        background-color: white;
        margin: auto;
        padding: 20px;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
    }

    .popup button.close {
        padding: 10px;
        background-color: #f44336;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        float: right;
    }

    .popup button.close:hover {
        background-color: #d32f2f;
    }

    .user-table {
        max-height: 300px;
        overflow-y: auto;
    }

    .search-bar {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .select-all-btn,
    .deselect-all-btn {
        margin-bottom: 10px;
        padding: 10px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .select-all-btn:hover,
    .deselect-all-btn:hover {
        background-color: #45a049;
    }

    .file-upload-btn {
        background-color: #2196F3;
    }

    .file-upload-btn:hover {
        background-color: #1e88e5;
    }

    .logout-btn {
        background-color: #f44336;
    }

    .logout-btn:hover {
        background-color: #d32f2f;
    }

    .router-link {
        background-color: #ff9800;
    }

    .router-link:hover {
        background-color: #f57c00;
    }

    .popup .button {
        margin: 5px;
        padding: 10px 20px;
        background-color: #4CAF50;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .popup .button:hover {
        background-color: #45a049;
    }

    .popup .button.delete {
        background-color: #f44336;
    }

    .popup .button.delete:hover {
        background-color: #d32f2f;
    }

    .router-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
        max-height: 70vh; /* تحديد ارتفاع أقصى للعنصر */
        overflow-y: auto; /* تمكين التمرير العمودي */
        scroll-behavior: smooth; /* تمرير سلس */
    }

    .router-card {
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        background-color: #f9f9f9;
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }

    .router-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .router-card h3 {
        margin: 0;
        color: #4CAF50;
    }

    .router-card p {
        margin: 10px 0;
        color: #666;
    }

    .router-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
    }

    .router-actions a {
        text-decoration: none;
        padding: 5px 10px;
        border-radius: 5px;
        color: white;
        font-size: 14px;
    }

    .router-actions .edit-btn {
        background-color: #2196F3;
    }

    .router-actions .edit-btn:hover {
        background-color: #1e88e5;
    }

    .router-actions .delete-btn {
        background-color: #f44336;
    }

    .router-actions .delete-btn:hover {
        background-color: #d32f2f;
    }

    .add-router-btn {
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        margin-bottom: 20px;
        font-size: 16px;
    }

    .add-router-btn:hover {
        background-color: #45a049;
    }

    .popup-content {
        background-color: white;
        margin: auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 400px;
    }

    .popup h2 {
        margin-top: 0;
        color: #4CAF50;
    }

    .popup label {
        display: block;
        margin-bottom: 5px;
        color: #333;
    }

    .popup input {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }

    .popup button[type="submit"] {
        background-color: #4CAF50;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
    }

    .popup button[type="submit"]:hover {
        background-color: #45a049;
    }
.buttons-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px; /* لضبط المسافة بين الأزرار */
    align-items: center;
}

.buttons-container .button,
.buttons-container .router-link,
.buttons-container .logout-btn {
    margin: 5px;
}

@media (min-width: 600px) {
    .buttons-container {
        width: 100%;
    }
}
    
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let selectedUsers = [];

function showPopup(category, status = '') {
    const popup = document.getElementById('popup');
    const popupTitle = document.getElementById('popup-title');
    const popupList = document.getElementById('popup-list');

    // عرض النافذة المنبثقة
    popup.style.display = 'block';
    popupTitle.textContent = `تفاصيل اليوزرات للفئة ${category} ${status ? '(' + status + ')' : ''}`;
    popupList.innerHTML = '';

    // الحصول على بيانات اليوزرات من PHP
    const users = <?= json_encode($userStats); ?>;
    const userGroup = users[category]?.users || [];

    // تصفية اليوزرات بناءً على الحالة (active أو inactive)
    const filteredUsers = status
        ? userGroup.filter(user => {
            if (status === 'active') {
                // يعتبر المستخدم نشطًا إذا كان bytes-out > 0 أو mac-address غير فارغ
                return (user['bytes-out'] > 0) || (!empty(user['mac-address']));
            } else {
                // يعتبر المستخدم غير نشط إذا كانت bytes-out <= 0 و mac-address فارغ
                return (user['bytes-out'] <= 0) && empty(user['mac-address']);
            }
        })
        : userGroup;

    // إنشاء جدول لعرض اليوزرات
    filteredUsers.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="checkbox" value="${user['.id']}" onchange="toggleUserSelection('${user['.id']}')"></td>
            <td>${user['disabled'] === 'false' ? 'مفعل' : 'معطل'}</td>
            <td>${user.name}</td>
            <td>${(user['bytes-out'] / 1024 / 1024).toFixed(2)} MB</td>
            <td>${user['uptime'] || 'غير متوفر'}</td>
            <td>${user['mac-address'] || 'غير متوفر'}</td>
            <td>${user['comment'] || 'بدون تعليق'}</td>
            <td>${user['limit-uptime'] || 'غير محدد'}</td>
            <td>${user['limit-bytes-total'] ? (user['limit-bytes-total'] / 1024 / 1024).toFixed(2) + ' MB' : 'غير محدد'}</td>
        `;
        popupList.appendChild(row);
    });
}

// دالة مساعدة للتحقق مما إذا كان القيمة فارغة
function empty(value) {
    return typeof value === 'undefined' || value === null || value === '';
}

function closePopup() {
        document.getElementById('popup').style.display = 'none';
    }

    function toggleUserSelection(userId) {
        const index = selectedUsers.indexOf(userId);
        if (index > -1) {
            selectedUsers.splice(index, 1);
        } else {
            selectedUsers.push(userId);
        }
    }

    function selectAllUsers() {
        const checkboxes = document.querySelectorAll('#popup-list input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            if (checkbox.closest('tr').style.display !== 'none') {
                checkbox.checked = true;
                if (!selectedUsers.includes(checkbox.value)) {
                    selectedUsers.push(checkbox.value);
                }
            }
        });
    }

    function deselectAllUsers() {
        const checkboxes = document.querySelectorAll('#popup-list input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectedUsers = [];
    }

    function showProgress(message) {
        Swal.fire({
            title: 'جاري المعالجة...',
            html: message,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function hideProgress() {
        Swal.close();
    }

    async function applyAction(action) {
        if (selectedUsers.length === 0 && action !== 'reset-counter') {
            Swal.fire('خطأ', 'الرجاء اختيار يوزرات أولاً.', 'error');
            return;
        }

        const confirmation = await Swal.fire({
            title: 'هل أنت متأكد؟',
            text: `هل أنت متأكد من أنك تريد ${action} اليوزرات المحددين؟`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'نعم',
            cancelButtonText: 'إلغاء'
        });

        if (!confirmation.isConfirmed) {
            return;
        }

        showProgress('جاري تنفيذ العملية...');

        try {
            const response = await fetch('apply_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    userIds: selectedUsers
                })
            });
            const data = await response.json();
            if (data.success) {
                Swal.fire('نجاح', 'تم تنفيذ العملية بنجاح.', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('خطأ', 'فشل في تنفيذ العملية: ' + (data.message || ''), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Swal.fire('خطأ', 'حدث خطأ أثناء تنفيذ العملية.', 'error');
        } finally {
            hideProgress();
        }
    }

    function openEditPopup() {
        if (selectedUsers.length === 0) {
            Swal.fire('خطأ', 'الرجاء اختيار يوزرات أولاً.', 'error');
            return;
        }
        document.getElementById('edit-popup').style.display = 'block';
    }

    function closeEditPopup() {
        document.getElementById('edit-popup').style.display = 'none';
    }
    
function submitEditForm() {
    const formData = {
        userIds: selectedUsers,
        name: document.getElementById('edit-name').value,
        password: document.getElementById('edit-password').value,
        profile: document.getElementById('edit-profile').value,
        comment: document.getElementById('edit-comment').value,
        limitUptime: document.getElementById('edit-limit-uptime').value,
        limitBytesTotal: document.getElementById('edit-limit-bytes-total').value,
        macAddress: document.getElementById('edit-mac-address').value // إضافة macAddress
    };

    showProgress('جاري تعديل اليوزرات...');

    fetch('apply_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'edit-users',
            ...formData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('نجاح', 'تم تعديل اليوزرات بنجاح.', 'success').then(() => {
                location.reload();
            });
        } else {
            Swal.fire('خطأ', 'فشل في تعديل اليوزرات: ' + (data.message || ''), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('خطأ', 'حدث خطأ أثناء تعديل اليوزرات.', 'error');
    })
    .finally(() => {
        hideProgress();
    });
}

    function searchUsers() {
        const searchValue = document.getElementById('search').value.toLowerCase();
        const rows = document.querySelectorAll('#popup-list tr');

        rows.forEach(row => {
            let match = false;
            row.querySelectorAll('td').forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchValue)) {
                    match = true;
                }
            });
            row.style.display = match ? '' : 'none';
        });
    }

function copyData() {
    const users = <?= json_encode($userStats); ?>;
    const routerName = "<?= $router['name'] ?? 'غير محدد' ?>"; // اسم السيرفر
    let csvContent = "name,inactive,Zero,One,Two,Three,Four,Five,Six,Seven,Eight,Nine\n";

    // إعداد بيانات الفئات الرقمية (0-9)
    const inactiveCounts = [];
    for (let i = 0; i <= 9; i++) {
        inactiveCounts.push(users[i]?.inactive || 0);
    }

    // إضافة البيانات للفئة "أخرى"
    const otherInactiveCount = users['other']?.inactive || 0;

    // بناء النص المطلوب
    csvContent += `${routerName},${otherInactiveCount},${inactiveCounts.join(',')}\n`;

    console.log(csvContent); // طباعة النص الذي سيتم نسخه

    // نسخ النص إلى الحافظة
    navigator.clipboard.writeText(csvContent)
        .then(() => {
            Swal.fire('نجاح', 'تم نسخ البيانات إلى الحافظة.', 'success');
        })
        .catch((err) => {
            console.error('فشل في نسخ البيانات:', err); // طباعة الخطأ في الكونسول
            Swal.fire('خطأ', 'فشل في نسخ البيانات. يرجى المحاولة مرة أخرى.', 'error');
        });
}
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('user-file').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const content = e.target.result;
                    const users = parseUserFile(content);
                    if (users.length > 0) {
                        fetch('apply_action.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'add-users-from-file',
                                users: users
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('نجاح', data.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('خطأ', data.message, 'error');
                            }
                        })
                        .catch(error => console.error('Error:', error));
                    } else {
                        Swal.fire('خطأ', 'الملف لا يحتوي على بيانات صالحة.', 'error');
                    }
                };
                reader.readAsText(file);
            }
        });
    });

function parseUserFile(content) {
    const lines = content.split('\n');
    const users = [];
    for (const line of lines) {
        const parts = line.split(',');
        if (parts.length >= 2) {
            const user = {
                name: parts[0].trim(),
                password: parts[1].trim(),
                profile: parts[2] ? parts[2].trim() : '1.5M', // ملف افتراضي
                disabled: parts[3] ? parts[3].trim() : 'no', // افتراضي لا لتعطيل الحساب
                limitBytesTotal: parts[4] ? parts[4].trim() : '272144000', // افتراضي 262144000 (بـ 250 ميجابايت)
                limitUptime: parts[5] ? parts[5].trim() : '2h', // افتراضي 2 ساعة
                comment: parts[6] ? parts[6].trim() : 'jan/01/2025#12:12:12%true' // افتراضي التعليق
            };
            users.push(user);
        }
    }
    return users;
}

</script>
</head>
<body>

    <div class="dashboard-container">
        <h1>مرحبًا، <?= htmlspecialchars($_SESSION['username']); ?></h1>
        <h2>الكافيه: <?= htmlspecialchars($routerName); ?></h2>
        <div class="buttons-container">
        <a href="hosts_users.php" class="button file-upload-btn">عرض اليوزرات الهوست</a>
        <a href="active_users.php" class="button file-upload-btn">عرض اليوزرات الاكتيف</a>
         <a href="search.php" class="button file-upload-btn">بحث تفصيلي</a>

        </div>

        <h2>تفاصيل اليوزرات</h2>
        <?php if (empty($userStats)): ?>
            <p>لا يوجد يوزرات.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>العدد</th>
                    <th>إجمالي اليوزرات</th>
                    <th>اليوزرات المستخدمين</th>
                    <th>اليوزرات غير المستخدمين</th>
                </tr>
                <?php for ($i = 0; $i <= 9; $i++): ?>
                    <tr>
                        <td><?= $i ?></td>
                        <td><button class="button" onclick="showPopup('<?= $i ?>')"><?= isset($userStats[$i]) ? $userStats[$i]['total'] : 0 ?></button></td>
                        <td><button class="button" onclick="showPopup('<?= $i ?>', 'active')"><?= isset($userStats[$i]) ? $userStats[$i]['active'] : 0 ?></button></td>
                        <td><button class="button" onclick="showPopup('<?= $i ?>', 'inactive')"><?= isset($userStats[$i]) ? $userStats[$i]['inactive'] : 0 ?></button></td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <td>أخرى</td>
                    <td><button class="button" onclick="showPopup('other')"><?= isset($userStats['other']) ? $userStats['other']['total'] : 0 ?></button></td>
                    <td><button class="button" onclick="showPopup('other', 'active')"><?= isset($userStats['other']) ? $userStats['other']['active'] : 0 ?></button></td>
                    <td><button class="button" onclick="showPopup('other', 'inactive')"><?= isset($userStats['other']) ? $userStats['other']['inactive'] : 0 ?></button></td>
                </tr>
            </table>
        <?php endif; ?>

<div class="buttons-container">
    <input type="file" id="user-file" accept=".txt" style="display: none;">
    <button class="button file-upload-btn" onclick="document.getElementById('user-file').click()">إضافة يوزرات</button>
    <button class="button copy-btn" onclick="copyData()">نسخ البيانات</button>

    <a href="index.php" class="button router-link">إدارة الرواتر</a>
    <a href="../logout.php" class="button logout-btn">تسجيل الخروج</a>
</div>
    </div>

    <div id="popup" class="popup">
        <div class="popup-content">
            <button class="close" onclick="closePopup()">إغلاق</button>
            <h2 id="popup-title"></h2>
            <input type="text" class="search-bar" id="search" onkeyup="searchUsers()" placeholder="ابحث بأي قيمة...">
            <div class="buttons-container">
            <button class="select-all-btn" onclick="selectAllUsers()">تحديد الكل</button>
            <button class="deselect-all-btn" onclick="deselectAllUsers()">إلغاء تحديد الكل</button>
            </div>

            <div class="user-table">
                <table>
                    <tr>
                        <th>اختيار</th>
                        <th>الحالة</th>
                        <th>اسم المستخدم</th>
                        <th>بيانات مرسلة (ميجابايت)</th>
                        <th>Uptime</th>
                        <th>MAC Address</th>
                        <th>التعليق</th>
                        <th>Limit Uptime</th>
                        <th>Limit Bytes Total</th>
                    </tr>
                    <tbody id="popup-list"></tbody>
                </table>
            </div>
            <div class="buttons-container">
            <button class="button" onclick="applyAction('enable')">تمكين المحددين</button>
            <button class="button" onclick="applyAction('disable')">تعطيل المحددين</button>
            <button class="button delete" onclick="applyAction('delete')">حذف المحددين</button>
            <button class="button" onclick="applyAction('reset-counter')">Reset Counter</button>
            <button class="button" onclick="openEditPopup()">تعديل المحددين</button>
            </div>
        </div>
    </div>

    <div id="edit-popup" class="popup">
        <div class="popup-content">
            <button class="close" onclick="closeEditPopup()">إغلاق</button>
            <h2>تعديل اليوزرات المحددين</h2>
            <form id="edit-form">
                <label for="edit-name">اسم المستخدم:</label>
                <input type="text" id="edit-name" name="name" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-password">كلمة المرور:</label>
                <input type="text" id="edit-password" name="password" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-profile">الملف الشخصي:</label>
                <input type="text" id="edit-profile" name="profile" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-comment">التعليق:</label>
                <input type="text" id="edit-comment" name="comment" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-limit-uptime">Limit Uptime:</label>
                <input type="text" id="edit-limit-uptime" name="limit-uptime" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-limit-bytes-total">Limit Bytes Total:</label>
                <input type="text" id="edit-limit-bytes-total" name="limit-bytes-total" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">

                <label for="edit-mac-address">MAC Address:</label>
                <input type="text" id="edit-mac-address" name="mac-address" placeholder="اتركه فارغًا للحفاظ على القيمة الحالية">


                <button type="button" class="button" onclick="submitEditForm()">حفظ التعديلات</button>
            </form>
        </div>
    </div>
</body>
</html>