<?php
// ==========================================
// CS-Cart API → Export Donors to CSV
// Al-Najat Charitable Society
// ==========================================

$api_url   = 'https://donate.alnajat.org.kw/api/';
$api_email = 'sameh@alnajat.com.kw';
$api_key   = getenv('CSCART_API_KEY') ?: 'YOUR_NEW_API_KEY_HERE';

$credentials = base64_encode("$api_email:$api_key");

$all_customers = [];
$page = 1;

do {
    $url = $api_url . "users/?user_type=C&items_per_page=100&page=$page";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Basic $credentials",
            "Content-Type: application/json"
        ]
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        die("خطأ في الاتصال بـ API. كود الخطأ: $httpCode\n");
    }

    $response = json_decode($raw, true);

    if (!empty($response['users'])) {
        $all_customers = array_merge($all_customers, $response['users']);
    }

    $total_items = $response['params']['total_items'] ?? 0;
    $total_pages = ceil($total_items / 100);
    $page++;

} while ($page <= $total_pages);

// --- توليد CSV ---
$filename = 'alnajat_donors_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'رقم المتبرع', 'الاسم الأول', 'الاسم الأخير', 'الاسم الكامل',
    'البريد الإلكتروني', 'الهاتف', 'الدولة', 'المدينة',
    'العنوان', 'الرمز البريدي', 'تاريخ التسجيل',
    'آخر تسجيل دخول', 'الحالة', 'نوع الحساب'
]);

foreach ($all_customers as $c) {
    $firstname = $c['firstname'] ?? '';
    $lastname  = $c['lastname']  ?? '';
    fputcsv($output, [
        $c['user_id']    ?? '',
        $firstname,
        $lastname,
        trim("$firstname $lastname"),
        $c['email']      ?? '',
        $c['phone'] ?? $c['b_phone'] ?? '',
        $c['b_country']  ?? '',
        $c['b_city']     ?? '',
        $c['b_address']  ?? '',
        $c['b_zipcode']  ?? '',
        isset($c['registered']) ? date('Y-m-d', $c['registered']) : '',
        isset($c['last_login'])  ? date('Y-m-d', $c['last_login'])  : '',
        ($c['status'] ?? '') === 'A' ? 'نشط' : 'غير نشط',
        $c['user_type']  ?? '',
    ]);
}

fclose($output);
exit;
