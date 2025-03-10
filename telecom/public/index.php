<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تطبيق الاتصالات</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background-color: #e9f3ff;
            margin: 0;
            padding: 0;
            direction: rtl;
        }
        h1, h2, h3 {
            text-align: center;
            color: #333;
            font-weight: bold;
        }
        form {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        p {
            text-align: center;
            color: red;
            margin-top: 10px;
        }

        /* تصميم مربع المنبثق */
        .modal {
            display: none; /* إخفاء المربع افتراضيًا */
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* خلفية شفافة */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 600px;
            max-height: 80%; /* تحديد ارتفاع أقصى */
            overflow-y: auto; /* إضافة شريط تمرير إذا كانت النتائج طويلة */
            text-align: center;
        }
        .modal-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .modal-body ul {
            list-style-type: none;
            padding: 0;
        }
        .modal-body li {
            background-color: #e9ecef;
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
        }
        .modal-footer button {
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        .modal-footer button:hover {
            background-color: #218838;
        }

        /* تصميم أزرار النسخ */
        .copy-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .copy-buttons button {
            width: 200px;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .copy-buttons button:hover {
            background-color: #0056b3;
        }
        /* تصميم قسم توليد التقرير */
        .report-section {
            text-align: center;
            margin: 20px 0;
        }
        .report-section a {
            display: inline-block;
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .report-section a:hover {
            background-color: #0056b3;
        }

        /* مؤشر التحميل */
        .loading-spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 24px;
            color: #007bff;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
        /* مؤشر التحميل */
        #loading img {
            display: inline-block;
        }
    </style>
    <script>
        // دالة لملء الحقول بناءً على الخيار المختار
        function fillUserData() {
            const userSelect = document.getElementById('user_id');
            const locationNameInput = document.getElementById('location_name');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const selectedOption = userSelect.options[userSelect.selectedIndex];
            const userData = selectedOption.getAttribute('data-user-info');
            if (userData) {
                const { name, id, password: userPassword } = JSON.parse(userData);
                locationNameInput.value = name;
                usernameInput.value = id;
                passwordInput.value = userPassword;
            } else {
                locationNameInput.value = '';
                usernameInput.value = '';
                passwordInput.value = '';
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            // اختيار العناصر
            const modal = document.getElementById('reportModal');
            const closeModalButton = document.getElementById('closeModalButton');
            const reportResultsContainer = document.getElementById('reportResults');

            // فتح المربع المنبثق عند توفر نتائج
            function openModal() {
                modal.style.display = 'flex';
            }

            // إغلاق المربع المنبثق
            function closeModal() {
                modal.style.display = 'none';
            }

            // إضافة مستمع للنقر على زر الإغلاق
            closeModalButton.addEventListener('click', closeModal);

            // فحص وجود نتائج للتقرير
            <?php if (isset($_SESSION['report_results']) && !empty($_SESSION['report_results'])): ?>
                // إنشاء قائمة بنود التقرير
                const results = <?php echo json_encode($_SESSION['report_results']); ?>;
                const ul = document.createElement('ul');
                results.forEach(result => {
                    const li = document.createElement('li');
                    li.textContent = result;
                    ul.appendChild(li);
                });
                reportResultsContainer.appendChild(ul);
                // فتح المربع المنبثق تلقائيًا
                openModal();
            <?php endif; ?>

            // إضافة مؤشر تحميل لعملية تسجيل الدخول
            const loginForm = document.querySelector('form[action="/telecom/"]');
            const spinner = document.createElement('i');
            spinner.className = 'fas fa-spinner fa-spin loading-spinner';
            loginForm.appendChild(spinner);
            loginForm.addEventListener('submit', () => {
                spinner.style.display = 'block';
            });

// دالة نسخ البيانات
function copyData(content, messageElement, successMessage, errorMessage, fallbackFileUrl) {
    try {
        messageElement.textContent = 'جارٍ نسخ البيانات...';

        if (navigator.clipboard) {
            // حاول نسخ البيانات باستخدام navigator.clipboard
            navigator.clipboard.writeText(content).then(() => {
                messageElement.textContent = successMessage;
            }).catch((error) => {
                handleCopyError(messageElement, errorMessage, error, fallbackFileUrl);
            });
        } else {
            // إذا لم يكن navigator.clipboard مدعومًا، اعرض رسالة تحذير ووفر خطة بديلة
            handleCopyError(messageElement, errorMessage, new Error('المتصفح لا يدعم هذه الميزة!'), fallbackFileUrl);
        }
    } catch (error) {
        handleCopyError(messageElement, errorMessage, error, fallbackFileUrl);
    }
}

// التعامل مع أخطاء النسخ
function handleCopyError(messageElement, errorMessage, error, fallbackFileUrl) {
    messageElement.textContent = errorMessage + ': ' + error.message;

    // إنشاء رابط تنزيل كخطة بديلة
    const downloadLink = document.createElement('a');
    downloadLink.href = fallbackFileUrl; // استخدام الرابط البديل
    downloadLink.download = 'telecomreport.csv'; // اسم الملف الذي سيتم تنزيله
    downloadLink.textContent = 'انقر هنا لتنزيل الملف';
    downloadLink.style.display = 'block';
    downloadLink.style.marginTop = '10px';
    messageElement.parentNode.appendChild(downloadLink);
}

            // زر نسخ التقرير
            document.getElementById('copyReportButton').addEventListener('click', async () => {
                try {
                    // عرض رسالة التحميل
                    document.getElementById('copyMessage').textContent = 'جارٍ نسخ بيانات التقرير...';

                    // تجميع نتائج التقرير كنص
                    const resultsList = document.querySelectorAll('#reportResults li');
                    let reportText = '';
                    resultsList.forEach(item => {
                        reportText += item.textContent + '\n';
                    });

                    // نسخ النص إلى الحافظة
                    await navigator.clipboard.writeText(reportText);

                    // عرض رسالة النجاح
                    document.getElementById('copyMessage').textContent = 'تم نسخ بيانات التقرير إلى الحافظة بنجاح!';
                } catch (error) {
                    // التعامل مع الأخطاء
                    document.getElementById('copyMessage').textContent = 'حدث خطأ أثناء نسخ بيانات التقرير: ' + error.message;
                }
            });


// زر نسخ ملف CSV
document.getElementById('copyCSVButton').addEventListener('click', async () => {
    try {
        const response = await fetch('data/telecomreport.csv');
        if (!response.ok) {
            throw new Error('لم يتم العثور على الملف!');
        }
        const csvContent = await response.text();

        // استدعاء دالة النسخ مع تحديد الملف البديل
        copyData(
            csvContent,
            document.getElementById('copyMessage'),
            'تم نسخ محتويات الملف إلى الحافظة بنجاح!',
            'حدث خطأ أثناء نسخ الملف',
            'data/telecomreport.csv' // الملف البديل
        );
    } catch (error) {
        document.getElementById('copyMessage').textContent = 'حدث خطأ أثناء نسخ محتويات الملف: ' + error.message;
    }
});            // دالة معالجة تسجيل الدخول
            function handleLogin(event) {
                event.preventDefault();
                const loadingIndicator = document.getElementById('loading');
                loadingIndicator.style.display = 'block'; // إظهار مؤشر التحميل
                const formData = new FormData(event.target);
                fetch('/', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingIndicator.style.display = 'none'; // إخفاء مؤشر التحميل
                    if (data.success) {
                        alert('تم تسجيل الدخول بنجاح!');
                    } else {
                        alert('خطأ: ' + data.message);
                    }
                })
                .catch(error => {
                    loadingIndicator.style.display = 'none'; // إخفاء مؤشر التحميل
                    alert('حدث خطأ أثناء تسجيل الدخول: ' + error.message);
                });
            }
        });
    </script>
</head>
<body>
    <h1>مرحبًا، <?= htmlspecialchars($_SESSION['username']); ?></h1>
    <h1>وي تيليكوم للكافيهات</h1>
    <form method="POST" action="/telecom/" onsubmit="handleLogin(event)">
        <input type="hidden" name="action" value="login">
        <!-- قائمة منسددة للمواقع -->
        <label>اختر الكافيه:</label>
        <select id="user_id" name="user_id" onchange="fillUserData()">
            <option value="" disabled selected>اختر الكافيه...</option>
            <?php
            // قراءة ملف CSV
            $file = fopen("data/telecomusers.csv", "r");
            if ($file) {
                while (($row = fgetcsv($file)) !== false) {
                    list($name, $id, $password) = $row;
                    $userData = json_encode(['name' => $name, 'id' => $id, 'password' => $password]);
                    echo '<option value="' . htmlspecialchars($id) . '" data-user-info=\'' . htmlspecialchars($userData) . '\'>' . htmlspecialchars($name) . ' (ID: ' . htmlspecialchars($id) . ')</option>';
                }
                fclose($file); // إغلاق الملف بعد القراءة
            } else {
                echo '<option disabled>لم يتم العثور على الملف!</option>';
            }
            ?>
        </select><br>
        <!-- حقل اسم الكافيه -->
        <label>اسم الكافيه:</label>
        <input type="text" id="location_name" name="location_name" placeholder="أدخل اسم الكافيه"><br>
        <!-- حقل الرقم الأرضي -->
        <label>الرقم الأرضي:</label>
        <input type="text" id="username" name="username" placeholder="أدخل الرقم الأرضي"><br>
        <!-- حقل كلمة المرور -->
        <label>كلمة المرور:</label>
        <input type="password" id="password" name="password" placeholder="أدخل كلمة المرور"><br>
        <!-- زر تسجيل الدخول -->
        <button type="submit">تسجيل الدخول</button>
    </form>
    <!-- عرض نتيجة تسجيل الدخول -->
    <?php if (isset($_SESSION['login_result'])): ?>
        <p><?php echo htmlspecialchars($_SESSION['login_result']); ?></p>
    <?php endif; ?>
    <!-- قسم توليد التقرير -->
    <div class="report-section">
        <a href="?action=report" style="display: inline-block;">توليد تقرير</a>
    </div>
    <!-- أزرار النسخ -->
    <div class="copy-buttons">
        <button id="copyReportButton">نسخ التقرير</button>
        <button id="copyCSVButton">نسخ ملف CSV</button>
    </div>
    <p id="copyMessage" style="text-align: center; color: green; margin-top: 10px;"></p>
    <!-- مربع المنبثق -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">نتائج التقرير</div>
            <div class="modal-body" id="reportResults"></div>
            <div class="modal-footer">
                <button id="closeModalButton">إغلاق</button>
            </div>
        </div>
    </div>
    <div id="loading" style="display: none; text-align: center; margin-top: 20px;">
        <img src="loading.gif" alt="Loading..." width="50">
    </div>
	    <div class="copy-buttons">
        <a href="../logout.php" class="btn btn-danger m-2">تسجيل الخروج</a> <!-- توجيه لتسجيل الخروج الموحد -->
		<a href="../home.php" class="btn btn-success m-2">الرئيسية</a> <!-- توجيه لتسجيل الخروج الموحد -->
		    </div>

</body>
</html>