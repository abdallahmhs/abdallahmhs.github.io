<?php
session_start();

require_once '../db_config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../login.php");
    exit();
}
// قراءة إعدادات MikroTik من ملف
$routers = [];
if (file_exists('mikrotik_routers.json')) {
    $routers = json_decode(file_get_contents('mikrotik_routers.json'), true);
}

// إضافة سيرفر جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_router'])) {
    $name = $_POST['name'];
    $host = $_POST['host'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];

    if (!empty($name) && !empty($host) && !empty($user) && !empty($pass)) {
        $routers[] = [
            'name' => $name,
            'host' => $host,
            'user' => $user,
            'pass' => $pass
        ];

        file_put_contents('mikrotik_routers.json', json_encode($routers));
        header('Location: index.php');
        exit;
    } else {
        $error = 'جميع الحقول مطلوبة.';
    }
}

// حذف سيرفر
if (isset($_GET['delete'])) {
    $index = $_GET['delete'];
    if (isset($routers[$index])) {
        array_splice($routers, $index, 1);
        file_put_contents('mikrotik_routers.json', json_encode($routers));
        header('Location: index.php');
        exit;
    }
}

// تعديل سيرفر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_router'])) {
    $index = $_POST['index'];
    $name = $_POST['name'];
    $host = $_POST['host'];
    $user = $_POST['user'];
    $pass = $_POST['pass'];

    if (isset($routers[$index])) {
        $routers[$index] = [
            'name' => $name,
            'host' => $host,
            'user' => $user,
            'pass' => $pass
        ];

        file_put_contents('mikrotik_routers.json', json_encode($routers));
        header('Location: index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الرواتر</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .custom-popup {
        font-family: Arial, sans-serif;
        max-width: 90%;
    }

    .custom-content {
        white-space: pre-wrap; /* للحفاظ على تنسيق النص */
        text-align: left;
        direction: ltr; /* إذا كانت البيانات بالإنجليزية */
    }
/* تنسيقات الجدول */
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

/* تلوين الحالة */
.status-on {
    color: green;
    font-weight: bold;
}

.status-off {
    color: red;
    font-weight: bold;
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
</head>
<body>
<div class="dashboard-container">
    <h1>مرحبًا، <?= htmlspecialchars($_SESSION['username']); ?></h1>
    <h1>إدارة السيرفرات</h1>
    <input type="text" id="search-routers" class="search-bar" placeholder="ابحث بالاسم ..." onkeyup="searchRouters()">
    <div class="buttons-container">
    <button class="button add-router-btn" onclick="showAddRouterPopup()">+ إضافة سيرفر</button>
    <button class="button add-router-btn" onclick="copyRoutersData()">نسخ بيانات السيرفرات</button>
    <button class="button add-router-btn" onclick="testAllRoutersConnection()">اختبار اتصال السيرفرات</button>
    </div>
    <div class="router-grid" id="router-grid">
        <?php foreach ($routers as $index => $router): ?>
            <div class="router-card" onclick="handleRouterClick(<?= $index ?>, '<?= htmlspecialchars($router['host']) ?>', '<?= htmlspecialchars($router['user']) ?>', '<?= htmlspecialchars($router['pass']) ?>', '<?= htmlspecialchars($router['name']) ?>')">
                <h3><?= htmlspecialchars($router['name']) ?></h3>
                <p><?= htmlspecialchars($router['host']) ?></p>
                <div class="router-actions">
                    <a href="#" class="edit-btn" onclick="event.stopPropagation(); showEditRouterPopup(<?= $index ?>, '<?= htmlspecialchars($router['name']) ?>', '<?= htmlspecialchars($router['host']) ?>', '<?= htmlspecialchars($router['user']) ?>', '<?= htmlspecialchars($router['pass']) ?>')">تعديل</a>
                    <a href="index.php?delete=<?= $index ?>" class="delete-btn" onclick="event.stopPropagation(); return confirm('هل أنت متأكد من حذف هذا السيرفر؟')">حذف</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="buttons-container">
    <a href="../logout.php" class="button logout-btn">تسجيل الخروج</a>
	<a href="../home.php" class="button router-link">الرئيسية</a>
    </div>
</div>

    <!-- Popup إضافة سيرفر -->
    <div id="add-router-popup" class="popup">
        <div class="popup-content">
            <button class="close" onclick="closePopup('add-router-popup')">إغلاق</button>
            <h2>إضافة سيرفر جديد</h2>
            <form method="POST" action="index.php">
                <label for="router-name">اسم السيرفر:</label>
                <input type="text" id="router-name" name="name" required>
                
                <label for="router-host">مضيف السيرفر:</label>
                <input type="text" id="router-host" name="host" required>
                
                <label for="router-user">اسم المستخدم:</label>
                <input type="text" id="router-user" name="user" required>
                
                <label for="router-pass">كلمة المرور:</label>
                <input type="password" id="router-pass" name="pass" required>
                
                <button type="submit" name="add_router" class="button">إضافة</button>
            </form>
        </div>
    </div>

    <!-- Popup تعديل سيرفر -->
    <div id="edit-router-popup" class="popup">
        <div class="popup-content">
            <button class="close" onclick="closePopup('edit-router-popup')">إغلاق</button>
            <h2>تعديل السيرفر</h2>
            <form method="POST" action="index.php">
                <input type="hidden" id="edit-index" name="index">
                <label for="edit-router-name">اسم السيرفر:</label>
                <input type="text" id="edit-router-name" name="name" required>
                
                <label for="edit-router-host">مضيف السيرفر:</label>
                <input type="text" id="edit-router-host" name="host" required>
                
                <label for="edit-router-user">اسم المستخدم:</label>
                <input type="text" id="edit-router-user" name="user" required>
                
                <label for="edit-router-pass">كلمة المرور:</label>
                <input type="password" id="edit-router-pass" name="pass" required>
                
                <button type="submit" name="edit_router" class="button">حفظ التعديلات</button>
            </form>
        </div>
    </div>

    <script>
    function searchRouters() {
        const searchValue = document.getElementById('search-routers').value.toLowerCase();
        const routerCards = document.querySelectorAll('.router-card');

        routerCards.forEach(card => {
            const routerName = card.querySelector('h3').textContent.toLowerCase();
            const routerHost = card.querySelector('p').textContent.toLowerCase();
            if (routerName.includes(searchValue) || routerHost.includes(searchValue)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
        function showAddRouterPopup() {
            document.getElementById('add-router-popup').style.display = 'block';
        }

        function showEditRouterPopup(index, name, host, user, pass) {
            document.getElementById('edit-index').value = index;
            document.getElementById('edit-router-name').value = name;
            document.getElementById('edit-router-host').value = host;
            document.getElementById('edit-router-user').value = user;
            document.getElementById('edit-router-pass').value = pass;
            document.getElementById('edit-router-popup').style.display = 'block';
        }

        function closePopup(popupId) {
            document.getElementById(popupId).style.display = 'none';
        }

        async function checkRouterConnection(host, user, pass, routerName) {
            try {
                const response = await fetch('fetch_router_stats.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ host, user, pass }),
                });
                const data = await response.json();
                if (data.success === false) {
                    throw new Error(data.message || 'فشل الاتصال بالسيرفر.');
                }
                return true;
            } catch (error) {
                console.error('Error checking router connection:', error);
                throw new Error('غير متصل');
            }
        }
        async function handleRouterClick(index, host, user, pass, routerName) {
            try {
                // التحقق من اتصال السيرفر
                const connectionStatus = await checkRouterConnection(host, user, pass, routerName);
                if (connectionStatus) {
                    // إذا كان السيرفر متصلاً، يتم التوجيه إلى dashboard.php
                    window.location.href = `dashboard.php?router=${index}`;
                }
            } catch (error) {
                // إذا حدث خطأ في الاتصال
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ في الاتصال',
                    text: `تعذر الاتصال بالسيرفر ${routerName}.`,
                    confirmButtonText: 'موافق'
                });
            }
        }

async function copyRoutersData() {
    const routers = <?= json_encode($routers); ?>;
    const csvRows = ["name,inactive,Zero,One,Two,Three,Four,Five,Six,Seven,Eight,Nine"];

    // عرض نافذة التحميل
    Swal.fire({
        title: 'جاري نسخ البيانات...',
        html: 'التقدم: <b>0%</b>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        // جلب بيانات جميع الرواترات بشكل متوازي
        const routerPromises = routers.map(async (router, index) => {
            try {
                const userStats = await fetchRouterUserStats(router.host, router.user, router.pass);
                const inactiveCounts = [];
                for (let j = 0; j <= 9; j++) {
                    inactiveCounts.push(userStats[j]?.inactive || 0);
                }
                const otherInactiveCount = userStats['other']?.inactive || 0;
                csvRows.push(`${router.name},${otherInactiveCount},${inactiveCounts.join(',')}`);

                // تحديث شريط التقدم كل 10%
                if ((index + 1) % Math.ceil(routers.length / 10) === 0) {
                    const progress = Math.round(((index + 1) / routers.length) * 100);
                    Swal.update({
                        html: `التقدم: <b>${progress}%</b>`
                    });
                }
            } catch (error) {
                csvRows.push(`${router.name},غير متصل,0,0,0,0,0,0,0,0,0,0`);
            }
        });

        await Promise.all(routerPromises); // انتظار انتهاء جميع الطلبات

        // إنشاء CSV
        const csvContent = csvRows.join('\n');

        // إخفاء نافذة التحميل بعد الانتهاء
        Swal.close();

        // عرض النتيجة في منبثق
        Swal.fire({
            title: 'نتيجة البيانات',
            html: `<pre>${csvContent}</pre>`,
            showCancelButton: true,
            confirmButtonText: 'نسخ',
            cancelButtonText: 'إغلاق',
            customClass: {
                popup: 'custom-popup',
                content: 'custom-content'
            },
            didOpen: () => {
                // إضافة زر نسخ يدوي
                const copyButton = document.createElement('button');
                copyButton.innerText = 'نسخ إلى الحافظة';
                copyButton.className = 'swal2-confirm swal2-styled';
                copyButton.style.marginTop = '10px';
                copyButton.onclick = () => {
                    copyToClipboard(csvContent);
                    Swal.close();
                };
                Swal.getHtmlContainer().appendChild(copyButton);
            }
        });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'خطأ!',
            text: 'حدث خطأ أثناء جلب البيانات. يرجى المحاولة مرة أخرى.',
        });
    }
}

        async function fetchRouterUserStats(host, user, pass) {
            try {
                const response = await fetch('fetch_router_stats.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ host, user, pass }),
                });
                const data = await response.json();
                if (data.success === false) {
                    throw new Error(data.message || 'فشل الاتصال بالسيرفر.');
                }
                return data;
            } catch (error) {
                console.error('Error fetching router stats:', error);
                throw new Error('غير متصل');
            }
        }

        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                Swal.fire({
                    icon: 'success',
                    title: 'تم النسخ!',
                    text: 'تم نسخ بيانات الرواترات إلى الحافظة.',
                });
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ!',
                    text: 'فشل في نسخ البيانات. يرجى المحاولة مرة أخرى.',
                });
            }

            document.body.removeChild(textarea);
        }
async function testAllRoutersConnection() {
    const routers = <?= json_encode($routers); ?>;
    const promises = routers.map(async (router) => {
        try {
            const connectionStatus = await checkRouterConnection(router.host, router.user, router.pass, router.name);
            return { name: router.name, status: 'ON' };
        } catch (error) {
            return { name: router.name, status: 'OFF' };
        }
    });

    // عرض نافذة التحميل
    Swal.fire({
        title: 'جاري اختبار الاتصال...',
        html: 'التقدم: <b>0%</b>',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const results = await Promise.all(promises);

    // إخفاء نافذة التحميل بعد الانتهاء
    Swal.close();

    // عرض النتائج في جدول
    displayConnectionResults(results);
}

function displayConnectionResults(results) {
    let html = '<table><tr><th>اسم السيرفر</th><th>الحالة</th></tr>';
    results.forEach(result => {
        const statusClass = result.status === 'ON' ? 'status-on' : 'status-off';
        html += `<tr>
                    <td>${result.name}</td>
                    <td class="${statusClass}">${result.status}</td>
                 </tr>`;
    });
    html += '</table>';

    Swal.fire({
        title: 'نتيجة اختبار الاتصال',
        html: html,
        confirmButtonText: 'موافق',
        customClass: {
            popup: 'custom-popup',
            content: 'custom-content'
        }
    });
}
function displayConnectionResults(results) {
    let html = '<table><tr><th>اسم السيرفر</th><th>الحالة</th></tr>';
    results.forEach(result => {
        const statusClass = result.status === 'ON' ? 'status-on' : 'status-off';
        html += `<tr>
                    <td>${result.name}</td>
                    <td class="${statusClass}">${result.status}</td>
                 </tr>`;
    });
    html += '</table>';

    Swal.fire({
        title: 'نتيجة اختبار الاتصال',
        html: html,
        confirmButtonText: 'موافق',
        customClass: {
            popup: 'custom-popup',
            content: 'custom-content'
        }
    });
}
</script>
</body>
</html>