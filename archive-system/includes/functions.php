<?php
/**
 * ملف الدوال المساعدة للنظام
 */

// دالة تنظيف المدخلات
function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}

// دالة التحقق من البريد الإلكتروني
function validate_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // تحقق إضافي من صيغة البريد
    $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
    return preg_match($pattern, $email);
}

// دالة التحقق من رقم الهاتف السعودي
function validate_saudi_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    // أرقام السعودية تبدأ بـ 966 أو 05
    if (preg_match('/^(9665|05)/', $phone)) {
        return (strlen($phone) === 12 && preg_match('/^9665\d{8}$/', $phone)) || 
               (strlen($phone) === 10 && preg_match('/^05\d{8}$/', $phone));
    }
    
    return false;
}

// دالة إنشاء كلمة مرور قوية
function generate_strong_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

// دالة تشفير كلمة المرور
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// دالة التحقق من كلمة المرور
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// دالة إنشاء رمز تفعيل
function generate_activation_code($length = 32) {
    return bin2hex(random_bytes($length));
}

// دالة تنسيق التاريخ العربي
function arabic_date($date, $format = 'd F Y') {
    $english = ['January', 'February', 'March', 'April', 'May', 'June', 
                'July', 'August', 'September', 'October', 'November', 'December'];
    
    $arabic = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
               'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    
    $date_str = date($format, strtotime($date));
    return str_replace($english, $arabic, $date_str);
}

// دالة تحويل الأرقام إلى العربية
function arabic_numbers($number) {
    $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    
    return str_replace($english, $arabic, $number);
}

// دالة تنسيق المبلغ
function format_amount($amount, $currency = 'SAR') {
    $formatted = number_format($amount, 2, '.', ',');
    
    switch ($currency) {
        case 'SAR':
            return arabic_numbers($formatted) . ' ر.س';
        case 'USD':
            return '$' . $formatted;
        case 'EUR':
            return '€' . $formatted;
        default:
            return $formatted . ' ' . $currency;
    }
}

// دالة حساب العمر
function calculate_age($birth_date) {
    $birth = new DateTime($birth_date);
    $now = new DateTime();
    $age = $now->diff($birth);
    return $age->y;
}

// دالة إنشاء رقم مستند فريد
function generate_document_number($prefix, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    $month = date('m');
    $day = date('d');
    
    // الحصول على آخر رقم
    $db = Database::getInstance();
    $last = $db->fetchOne(
        "SELECT document_number FROM financial_documents 
         WHERE document_number LIKE ? 
         ORDER BY id DESC LIMIT 1",
        ["{$prefix}/{$year}/%"]
    );
    
    if ($last) {
        $parts = explode('/', $last['document_number']);
        $last_num = intval($parts[2]);
        $next_num = $last_num + 1;
    } else {
        $next_num = 1;
    }
    
    return sprintf('%s/%s/%06d', $prefix, $year, $next_num);
}

// دالة رفع الملف
function upload_file($file, $allowed_types = [], $max_size = 10485760, $upload_dir = 'uploads/') {
    $errors = [];
    
    // التحقق من وجود أخطاء
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'حدث خطأ أثناء رفع الملف.';
        return ['success' => false, 'errors' => $errors];
    }
    
    // التحقق من حجم الملف
    if ($file['size'] > $max_size) {
        $errors[] = 'حجم الملف يتجاوز الحد المسموح به.';
    }
    
    // التحقق من نوع الملف
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!empty($allowed_types) && !in_array($file_ext, $allowed_types)) {
        $errors[] = 'نوع الملف غير مسموح به.';
    }
    
    // التحقق من نوع MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (isset($allowed_mimes[$file_ext]) && $allowed_mimes[$file_ext] !== $mime_type) {
        $errors[] = 'نوع الملف غير صالح.';
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    // إنشاء اسم فريد للملف
    $new_filename = uniqid() . '_' . date('Ymd_His') . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // إنشاء المجلد إذا لم يكن موجوداً
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // رفع الملف
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $new_filename,
            'original_name' => $file['name'],
            'file_path' => $upload_path,
            'file_size' => $file['size'],
            'file_type' => $file_ext,
            'mime_type' => $mime_type
        ];
    } else {
        $errors[] = 'فشل رفع الملف.';
        return ['success' => false, 'errors' => $errors];
    }
}

// دالة إنشاء صورة مصغرة
function create_thumbnail($source_path, $dest_path, $max_width = 200, $max_height = 200) {
    list($src_width, $src_height, $type) = getimagesize($source_path);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // حساب الأبعاد الجديدة
    $ratio = $src_width / $src_height;
    
    if ($max_width / $max_height > $ratio) {
        $new_width = $max_height * $ratio;
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = $max_width / $ratio;
    }
    
    // إنشاء الصورة الجديدة
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    // الحفاظ على الشفافية للصور PNG و GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($thumbnail, imagecolorallocatealpha($thumbnail, 0, 0, 0, 127));
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
    }
    
    // تغيير الحجم
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);
    
    // حفظ الصورة المصغرة
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($thumbnail, $dest_path, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($thumbnail, $dest_path, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($thumbnail, $dest_path);
            break;
    }
    
    // تحرير الذاكرة
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return true;
}

// دالة إنشاء باركود
function generate_barcode($text, $type = 'CODE128', $width = 2, $height = 30) {
    require_once 'vendor/autoload.php'; // إذا كنت تستخدم مكتبة باركود
    
    // استخدام مكتبة مثل TCPDF أو باركود
    // هذا مثال مبسط
    $barcode = new \Picqer\Barcode\BarcodeGeneratorPNG();
    return $barcode->getBarcode($text, $type, $width, $height);
}

// دالة إنشاء QR Code
function generate_qrcode($text, $size = 200, $margin = 10) {
    // استخدام مكتبة مثل chillerlan/php-qrcode
    require_once 'vendor/autoload.php';
    
    $qrcode = new \chillerlan\QRCode\QRCode();
    return $qrcode->render($text);
}

// دالة إرسال البريد الإلكتروني
function send_email($to, $subject, $body, $attachments = []) {
    require_once 'vendor/autoload.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // إعدادات الخادم
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        // المرسل والمستلم
        $mail->setFrom(SYSTEM_EMAIL, SYSTEM_NAME);
        $mail->addAddress($to);
        
        // المحتوى
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        // المرفقات
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment['path'], $attachment['name']);
        }
        
        return $mail->send();
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// دالة تسجيل الأخطاء
function log_error($message, $file = '', $line = '') {
    $log_message = sprintf(
        "[%s] %s in %s on line %s\n",
        date('Y-m-d H:i:s'),
        $message,
        $file,
        $line
    );
    
    error_log($log_message, 3, LOG_DIR . '/error.log');
}

// دالة تسجيل النشاط
function log_activity($user_id, $action, $details = '', $document_id = null) {
    $db = Database::getInstance();
    
    $activity_data = [
        'user_id' => $user_id,
        'action_type' => $action,
        'action_details' => $details,
        'record_id' => $document_id,
        'record_type' => $document_id ? 'document' : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return $db->insert('activity_logs', $activity_data);
}

// دالة التحقق من صلاحيات المستخدم
function check_permission($permission, $user = null) {
    if (!$user && isset($_SESSION['user_id'])) {
        $auth = new Auth();
        $user = $auth->getCurrentUser();
    }
    
    if (!$user) {
        return false;
    }
    
    // المستخدمون المشرفون لديهم جميع الصلاحيات
    if ($user['user_type'] === 'super_admin') {
        return true;
    }
    
    // تحقق من الصلاحيات المخزنة في قاعدة البيانات
    $db = Database::getInstance();
    $permissions = json_decode($user['permissions'] ?? '[]', true);
    
    if (in_array($permission, $permissions)) {
        return true;
    }
    
    // تحقق من الصلاحيات الافتراضية حسب نوع المستخدم
    $default_permissions = [
        'admin' => ['manage_users', 'manage_documents', 'view_reports', 'system_settings'],
        'financial_manager' => ['manage_financial', 'approve_payments', 'view_financial_reports'],
        'hr_manager' => ['manage_hr', 'view_hr_reports', 'approve_vacations'],
        'department_manager' => ['manage_department', 'view_department_reports'],
        'user' => ['view_documents', 'upload_documents', 'view_own_documents'],
        'viewer' => ['view_documents']
    ];
    
    $user_type = $user['user_type'];
    
    if (isset($default_permissions[$user_type]) && 
        in_array($permission, $default_permissions[$user_type])) {
        return true;
    }
    
    return false;
}

// دالة إعادة توجيه مع رسالة
function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit();
}

// دالة عرض رسالة الفلاش
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                    ' . $message . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    
    return '';
}

// دالة إنشاء breadcrumb ديناميكي
function generate_breadcrumb($items = []) {
    $html = '<nav aria-label="breadcrumb">';
    $html .= '<ol class="breadcrumb">';
    $html .= '<li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>';
    
    foreach ($items as $item) {
        if (isset($item['url'])) {
            $html .= '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . $item['title'] . '</a></li>';
        } else {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $item['title'] . '</li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

// دالة إنشاء pagination
function generate_pagination($total_items, $items_per_page, $current_page, $url_pattern) {
    $total_pages = ceil($total_items / $items_per_page);
    
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation">';
    $html .= '<ul class="pagination justify-content-center">';
    
    // زر السابق
    if ($current_page > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '" aria-label="Previous">';
        $html .= '<span aria-hidden="true">&laquo;</span>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-label="Previous">';
        $html .= '<span aria-hidden="true">&laquo;</span>';
        $html .= '</span></li>';
    }
    
    // أرقام الصفحات
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item">';
            $html .= '<a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a>';
            $html .= '</li>';
        }
    }
    
    // زر التالي
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '" aria-label="Next">';
        $html .= '<span aria-hidden="true">&raquo;</span>';
        $html .= '</a></li>';
    } else {
        $html .= '<li class="page-item disabled">';
        $html .= '<span class="page-link" aria-label="Next">';
        $html .= '<span aria-hidden="true">&raquo;</span>';
        $html .= '</span></li>';
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    
    return $html;
}

// دالة إنشاء select من قائمة
function generate_select($name, $options, $selected = '', $attributes = '') {
    $html = '<select name="' . $name . '" ' . $attributes . '>';
    $html .= '<option value="">اختر...</option>';
    
    foreach ($options as $value => $label) {
        $is_selected = ($value == $selected) ? 'selected' : '';
        $html .= '<option value="' . $value . '" ' . $is_selected . '>' . $label . '</option>';
    }
    
    $html .= '</select>';
    
    return $html;
}

// دالة إنشاء نموذج البحث
function generate_search_form($fields = [], $action = '') {
    $html = '<form method="GET" action="' . $action . '" class="search-form">';
    $html .= '<div class="row g-3">';
    
    foreach ($fields as $field) {
        $html .= '<div class="col-md-' . ($field['size'] ?? '3') . '">';
        
        switch ($field['type']) {
            case 'text':
                $html .= '<input type="text" class="form-control" name="' . $field['name'] . '" 
                         placeholder="' . ($field['placeholder'] ?? '') . '" 
                         value="' . ($_GET[$field['name']] ?? '') . '">';
                break;
                
            case 'select':
                $html .= '<select class="form-select" name="' . $field['name'] . '">';
                $html .= '<option value="">' . ($field['placeholder'] ?? 'اختر...') . '</option>';
                
                foreach ($field['options'] as $value => $label) {
                    $selected = ($_GET[$field['name']] ?? '') == $value ? 'selected' : '';
                    $html .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
                }
                
                $html .= '</select>';
                break;
                
            case 'date':
                $html .= '<input type="date" class="form-control" name="' . $field['name'] . '" 
                         value="' . ($_GET[$field['name']] ?? '') . '">';
                break;
                
            case 'date_range':
                $html .= '<div class="input-group">';
                $html .= '<input type="date" class="form-control" name="' . $field['name'] . '_from" 
                         value="' . ($_GET[$field['name'] . '_from'] ?? '') . '" placeholder="من تاريخ">';
                $html .= '<span class="input-group-text">إلى</span>';
                $html .= '<input type="date" class="form-control" name="' . $field['name'] . '_to" 
                         value="' . ($_GET[$field['name'] . '_to'] ?? '') . '" placeholder="إلى تاريخ">';
                $html .= '</div>';
                break;
        }
        
        $html .= '</div>';
    }
    
    $html .= '<div class="col-md-2">';
    $html .= '<button type="submit" class="btn btn-primary w-100">بحث</button>';
    $html .= '</div>';
    $html .= '<div class="col-md-1">';
    $html .= '<a href="' . $action . '" class="btn btn-outline-secondary w-100">إعادة تعيين</a>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</form>';
    
    return $html;
}

// دالة إنشاء ملف Excel
function generate_excel($data, $filename = 'report.xlsx') {
    require_once 'vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // إعداد تنسيق النص العربي
    $sheet->setRightToLeft(true);
    
    // كتابة العناوين
    $column = 'A';
    foreach ($data['headers'] as $header) {
        $sheet->setCellValue($column . '1', $header);
        $sheet->getStyle($column . '1')->getFont()->setBold(true);
        $column++;
    }
    
    // كتابة البيانات
    $row = 2;
    foreach ($data['rows'] as $row_data) {
        $column = 'A';
        foreach ($row_data as $cell) {
            $sheet->setCellValue($column . $row, $cell);
            $column++;
        }
        $row++;
    }
    
    // تنسيق الأعمدة تلقائياً
    foreach (range('A', $column) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // إضافة التصفية
    $sheet->setAutoFilter('A1:' . $column . '1');
    
    // حفظ الملف
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit();
}

// دالة إنشاء ملف PDF
function generate_pdf($html, $filename = 'document.pdf', $options = []) {
    require_once 'vendor/autoload.php';
    
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => $options['format'] ?? 'A4',
        'orientation' => $options['orientation'] ?? 'P',
        'default_font' => 'dejavusans',
        'margin_top' => $options['margin_top'] ?? 15,
        'margin_bottom' => $options['margin_bottom'] ?? 15,
        'margin_left' => $options['margin_left'] ?? 15,
        'margin_right' => $options['margin_right'] ?? 15,
    ]);
    
    // دعم اللغة العربية
    $mpdf->autoScriptToLang = true;
    $mpdf->autoLangToFont = true;
    $mpdf->SetDirectionality('rtl');
    
    // إضافة CSS
    if (isset($options['css'])) {
        $mpdf->WriteHTML($options['css'], 1);
    }
    
    // كتابة المحتوى
    $mpdf->WriteHTML($html);
    
    // إخراج الملف
    $mpdf->Output($filename, 'D');
    exit();
}

// دالة حساب hash الملف للكشف عن التكرار
function calculate_file_hash($file_path) {
    if (!file_exists($file_path)) {
        return null;
    }
    
    return hash_file('sha256', $file_path);
}

// دالة إنشاء token أمان
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// دالة التحقق من token الأمان
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// دالة إنشاء captcha
function generate_captcha() {
    $captcha_code = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
    $_SESSION['captcha_code'] = $captcha_code;
    
    // إنشاء صورة captcha (مبسطة)
    $image = imagecreatetruecolor(150, 50);
    $bg_color = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    
    imagefilledrectangle($image, 0, 0, 150, 50, $bg_color);
    imagettftext($image, 20, 0, 20, 35, $text_color, 'assets/fonts/arial.ttf', $captcha_code);
    
    ob_start();
    imagepng($image);
    $image_data = ob_get_clean();
    imagedestroy($image);
    
    return 'data:image/png;base64,' . base64_encode($image_data);
}

// دالة التحقق من captcha
function verify_captcha($input) {
    return isset($_SESSION['captcha_code']) && 
           strtoupper($input) === strtoupper($_SESSION['captcha_code']);
}

// دالة ضغط الملفات
function compress_files($files, $output_path) {
    $zip = new ZipArchive();
    
    if ($zip->open($output_path, ZipArchive::CREATE) === TRUE) {
        foreach ($files as $file) {
            if (file_exists($file['path'])) {
                $zip->addFile($file['path'], $file['name']);
            }
        }
        $zip->close();
        return true;
    }
    
    return false;
}

// دالة فك ضغط الملفات
function extract_zip($zip_path, $extract_to) {
    $zip = new ZipArchive();
    
    if ($zip->open($zip_path) === TRUE) {
        $zip->extractTo($extract_to);
        $zip->close();
        return true;
    }
    
    return false;
}

// دالة إرسال إشعارات push
function send_push_notification($user_id, $title, $message, $data = []) {
    // تنفيذ إرسال إشعارات push (FCM أو OneSignal)
    // هذا مثال مبسط
    
    $notification = [
        'user_id' => $user_id,
        'title' => $title,
        'message' => $message,
        'data' => json_encode($data),
        'sent_at' => date('Y-m-d H:i:s')
    ];
    
    $db = Database::getInstance();
    return $db->insert('push_notifications', $notification);
}

// دالة إنشاء شجرة المجلدات
function generate_folder_tree($parent_id = null, $level = 0) {
    $db = Database::getInstance();
    
    $folders = $db->fetchAll("
        SELECT * FROM virtual_folders 
        WHERE parent_id " . ($parent_id ? "= ?" : "IS NULL") . " 
        AND is_active = TRUE 
        ORDER BY sort_order, name_ar
    ", $parent_id ? [$parent_id] : []);
    
    if (empty($folders)) {
        return '';
    }
    
    $html = '';
    foreach ($folders as $folder) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $has_children = $db->fetchOne(
            "SELECT COUNT(*) as count FROM virtual_folders WHERE parent_id = ?",
            [$folder['id']]
        )['count'] > 0;
        
        $html .= '<div class="folder-item" data-folder-id="' . $folder['id'] . '">';
        $html .= $indent;
        
        if ($has_children) {
            $html .= '<i class="fas fa-caret-down folder-toggle"></i>';
        } else {
            $html .= '<i class="far fa-folder"></i>';
        }
        
        $html .= '<a href="folders/view.php?id=' . $folder['id'] . '" class="folder-link">';
        $html .= '<i class="fas fa-folder" style="color: ' . $folder['color'] . '"></i> ';
        $html .= $folder['name_ar'];
        $html .= '</a>';
        $html .= '<span class="folder-count">(' . $folder['document_count'] . ')</span>';
        $html .= '</div>';
        
        if ($has_children) {
            $html .= '<div class="folder-children" style="display: none;">';
            $html .= generate_folder_tree($folder['id'], $level + 1);
            $html .= '</div>';
        }
    }
    
    return $html;
}
?>

<!-- تضمين jQuery و Bootstrap -->
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<!-- مكتبات إضافية -->
<script src="assets/js/chart.min.js"></script>
<script src="assets/js/datatables.min.js"></script>
<script src="assets/js/select2.min.js"></script>
<script src="assets/js/datepicker.min.js"></script>
<script src="assets/js/main.js"></script>

<script>
// دوال JavaScript المساعدة
$(document).ready(function() {
    // تهيئة Select2
    $('.select2').select2({
        theme: 'bootstrap',
        language: 'ar',
        dir: 'rtl'
    });
    
    // تهيئة Datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        language: 'ar',
        rtl: true,
        autoclose: true
    });
    
    // تهيئة DataTables
    $('.datatable').DataTable({
        language: {
            url: 'assets/js/arabic.json'
        },
        pageLength: 25,
        responsive: true,
        order: [],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });
    
    // التعامل مع شجرة المجلدات
    $('.folder-toggle').on('click', function() {
        const folderItem = $(this).closest('.folder-item');
        const children = folderItem.next('.folder-children');
        
        if (children.is(':visible')) {
            children.hide();
            $(this).removeClass('fa-caret-down').addClass('fa-caret-right');
        } else {
            children.show();
            $(this).removeClass('fa-caret-right').addClass('fa-caret-down');
        }
    });
    
    // تحميل المحتوى الديناميكي عبر AJAX
    $(document).on('click', '.ajax-link', function(e) {
        e.preventDefault();
        
        const url = $(this).attr('href');
        const target = $(this).data('target') || '#content';
        
        $.ajax({
            url: url,
            type: 'GET',
            beforeSend: function() {
                $(target).html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
            },
            success: function(response) {
                $(target).html(response);
                updatePageTitle();
            },
            error: function() {
                $(target).html('<div class="alert alert-danger">حدث خطأ أثناء تحميل المحتوى</div>');
            }
        });
    });
    
    // تحديث عنوان الصفحة
    function updatePageTitle() {
        const pageTitle = $('h1.page-title').text() || $('h2.page-title').text();
        if (pageTitle) {
            document.title = pageTitle + ' - نظام الأرشيف المتكامل';
        }
    }
    
    // التحقق من النماذج
    $('.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        $(this).addClass('was-validated');
    });
    
    // إظهار/إخفاء كلمة المرور
    $('.toggle-password').on('click', function() {
        const input = $(this).closest('.input-group').find('input');
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // تحميل الملفات
    $('.file-upload').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // التحقق من حجم الملف قبل الرفع
    $('input[type="file"][data-max-size]').on('change', function() {
        const maxSize = $(this).data('max-size');
        const fileSize = this.files[0].size;
        
        if (fileSize > maxSize) {
            alert('حجم الملف يتجاوز الحد المسموح به (' + formatFileSize(maxSize) + ')');
            $(this).val('');
        }
    });
    
    // تنسيق حجم الملف
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // تحديث الوقت
    function updateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        
        const timeString = now.toLocaleDateString('ar-SA', options);
        $('.current-time').text(timeString);
    }
    
    // تحديث الوقت كل ثانية
    setInterval(updateTime, 1000);
    updateTime();
    
    // التعامل مع WebSocket للإشعارات المباشرة
    if (typeof WebSocket !== 'undefined') {
        const ws = new WebSocket('ws://' + window.location.host + '/ws');
        
        ws.onopen = function() {
            console.log('WebSocket connection established');
        };
        
        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'notification':
                    showToast(data.message, 'info');
                    break;
                    
                case 'task_update':
                    showToast('تم تحديث المهمة: ' + data.task_title, 'warning');
                    break;
                    
                case 'document_approval':
                    showToast('مستند يحتاج موافقتك: ' + data.document_title, 'primary');
                    break;
                    
                case 'system_alert':
                    showToast(data.message, 'danger');
                    break;
            }
        };
        
        ws.onerror = function(error) {
            console.error('WebSocket error:', error);
        };
        
        ws.onclose = function() {
            console.log('WebSocket connection closed');
        };
    }
    
    // إظهار رسائل Toast
    function showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const toast = $(
            '<div class="toast align-items-center text-bg-' + type + ' border-0" role="alert" aria-live="assertive" aria-atomic="true" id="' + toastId + '">' +
                '<div class="d-flex">' +
                    '<div class="toast-body">' + message + '</div>' +
                    '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
                '</div>' +
            '</div>'
        );
        
        $('#toastContainer').append(toast);
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        // إزالة الـ toast بعد إخفائه
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // النسخ إلى الحافظة
    $('.copy-to-clipboard').on('click', function() {
        const text = $(this).data('text') || $(this).text();
        
        navigator.clipboard.writeText(text).then(function() {
            showToast('تم النسخ إلى الحافظة', 'success');
        }).catch(function() {
            showToast('فشل النسخ إلى الحافظة', 'error');
        });
    });
    
    // طباعة المحتوى
    $('.print-content').on('click', function() {
        const content = $(this).data('content') || 'body';
        const originalContent = $(content).html();
        
        // إخفاء العناصر غير المرغوب فيها
        $('.no-print').hide();
        
        window.print();
        
        // إعادة العناصر المخفية
        $('.no-print').show();
    });
});
</script>