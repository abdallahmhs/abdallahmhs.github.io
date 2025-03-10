<?php
require 'routeros_api.class.php';

// قراءة البيانات المرسلة من العميل
$input = json_decode(file_get_contents('php://input'), true);
$host = $input['host'];
$user = $input['user'];
$pass = $input['pass'];

// إنشاء كائن API والتواصل مع جهاز MikroTik
$API = new RouterosAPI();
if ($API->connect($host, $user, $pass)) {
    // استرداد بيانات المستخدمين المسجلين في خدمة Hotspot
    $users = $API->comm('/ip/hotspot/user/print');
    $userStats = [];

    foreach ($users as $user) {
        // تحديد الحرف الأول من اسم المستخدم
        $firstChar = substr($user['name'], 0, 1);

        // التحقق مما إذا كان الحرف الأول رقمًا
        if (is_numeric($firstChar)) {
            // تهيئة الفئة إذا لم تكن موجودة
            if (!isset($userStats[$firstChar])) {
                $userStats[$firstChar] = [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0,
                    'users' => []
                ];
            }
        } else {
            // تصنيف المستخدمين الذين لا يبدأ اسمهم برقم تحت فئة "other"
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

        // زيادة العدد الإجمالي للمستخدمين في هذه الفئة
        $userStats[$firstChar]['total']++;

        // فحص النشاط بناءً على bytes-out أو mac-address
        if (
            (isset($user['bytes-out']) && $user['bytes-out'] > 0) || 
            (isset($user['mac-address']) && !empty($user['mac-address']))
        ) {
            $userStats[$firstChar]['active']++;
        } else {
            $userStats[$firstChar]['inactive']++;
        }

        // إضافة المستخدم إلى قائمة المستخدمين لهذه الفئة
        $userStats[$firstChar]['users'][] = $user;
    }

    // إغلاق الاتصال وإرجاع النتائج كاستجابة JSON
    $API->disconnect();
    echo json_encode($userStats);
} else {
    // في حالة فشل الاتصال، إرجاع رسالة خطأ
    echo json_encode(['success' => false, 'message' => 'فشل الاتصال بـ MikroTik.']);
}