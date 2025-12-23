<?php
/**
 * Auth.class.php
 * فئة متقدمة للمصادقة وإدارة المستخدمين والصلاحيات
 */

class Auth {
    private $db;
    private $user = null;
    private $sessionTimeout = 3600; // ساعة واحدة
    private $maxLoginAttempts = 5;
    private $lockDuration = 900; // 15 دقيقة
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->initializeSession();
        $this->restoreUserFromSession();
    }
    
    /**
     * تهيئة الجلسة
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_lifetime' => $this->sessionTimeout,
                'cookie_secure' => isset($_SERVER['HTTPS']),
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true,
                'use_only_cookies' => true
            ]);
        }
        
        // منع هجمات fixation
        if (empty($_SESSION['initialized'])) {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // التحقق من سلامة الجلسة
        $this->validateSession();
    }
    
    /**
     * التحقق من سلامة الجلسة
     */
    private function validateSession() {
        // التحقق من انتهاء الجلسة
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $this->sessionTimeout) {
            $this->logout();
            return;
        }
        
        // التحقق من تزوير الجلسة
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIp) {
            $this->logout();
            return;
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentAgent) {
            $this->logout();
            return;
        }
        
        // تحديث وقت النشاط
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * تسجيل دخول المستخدم
     */
    public function login($username, $password, $remember = false) {
        // التحقق من محاولات الدخول الفاشلة
        if ($this->isAccountLocked($username)) {
            return [
                'success' => false,
                'message' => 'الحساب مغلق مؤقتاً بسبب محاولات دخول فاشلة متعددة. الرجاء المحاولة لاحقاً.'
            ];
        }
        
        // تنظيف المدخلات
        $username = $this->sanitizeInput($username);
        
        // البحث عن المستخدم
        $user = $this->db->fetchOne(
            "SELECT * FROM users 
             WHERE (username = ? OR email = ? OR employee_id = ?) 
             AND status IN ('active', 'on_leave')",
            [$username, $username, $username]
        );
        
        if (!$user) {
            $this->logFailedAttempt($username);
            $this->logActivity(null, 'login_failed', "مستخدم غير موجود: {$username}");
            
            return [
                'success' => false,
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'
            ];
        }
        
        // التحقق من كلمة المرور
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->logFailedAttempt($username);
            $this->updateLoginAttempts($user['id'], true);
            $this->logActivity($user['id'], 'login_failed', 'كلمة مرور خاطئة');
            
            return [
                'success' => false,
                'message' => 'اسم المستخدم أو كلمة المرور غير صحيحة'
            ];
        }
        
        // التحقق من حالة الحساب
        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'message' => $this->getAccountStatusMessage($user['status'])
            ];
        }
        
        // التحقق من انتهاء صلاحية كلمة المرور (كل 90 يوم)
        if ($user['password_changed_at'] && 
            strtotime($user['password_changed_at']) < strtotime('-90 days')) {
            $_SESSION['require_password_change'] = true;
            $_SESSION['temp_user_id'] = $user['id'];
            
            return [
                'success' => true,
                'requires_password_change' => true,
                'user' => $user
            ];
        }
        
        // تسجيل الدخول الناجح
        $this->setUserSession($user);
        $this->updateLoginSuccess($user['id']);
        
        // إذا طلب تذكرني
        if ($remember) {
            $this->setRememberMeToken($user['id']);
        }
        
        // تسجيل النشاط
        $this->logActivity($user['id'], 'login_success');
        
        return [
            'success' => true,
            'user' => $user,
            'requires_password_change' => false
        ];
    }
    
    /**
     * تسجيل خروج المستخدم
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $this->logActivity($this->user['id'], 'logout');
        }
        
        // حذف تذكرني
        $this->clearRememberMeToken();
        
        // تدمير الجلسة
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        return true;
    }
    
    /**
     * التحقق من تسجيل الدخول
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * الحصول على المستخدم الحالي
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        if ($this->user === null) {
            $this->user = $this->db->fetchOne(
                "SELECT u.*, d.name_ar as department_name 
                 FROM users u
                 LEFT JOIN departments d ON u.department_id = d.id
                 WHERE u.id = ? AND u.status = 'active'",
                [$_SESSION['user_id']]
            );
            
            if (!$this->user) {
                $this->logout();
                return null;
            }
        }
        
        return $this->user;
    }
    
    /**
     * التحقق من صلاحية
     */
    public function hasPermission($permission, $resource = null) {
        $user = $this->getCurrentUser();
        
        if (!$user) {
            return false;
        }
        
        // المدير العام لديه جميع الصلاحيات
        if ($user['user_type'] === 'super_admin') {
            return true;
        }
        
        // الحصول على صلاحيات المستخدم
        $permissions = $this->getUserPermissions($user['id']);
        
        // التحقق من الصلاحية المطلوبة
        if (in_array($permission, $permissions)) {
            return true;
        }
        
        // التحقق من الصلاحيات الخاصة بالمورد
        if ($resource) {
            $resourcePermissions = $this->getResourcePermissions($user['id'], $resource);
            if (in_array($permission, $resourcePermissions)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * التحقق من الدور
     */
    public function hasRole($role) {
        $user = $this->getCurrentUser();
        return $user && $user['user_type'] === $role;
    }
    
    /**
     * إنشاء مستخدم جديد
     */
    public function createUser($data) {
        // التحقق من البيانات المطلوبة
        $required = ['username', 'password', 'email', 'full_name_ar', 'user_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "الحقل {$field} مطلوب"
                ];
            }
        }
        
        // التحقق من عدم تكرار اسم المستخدم أو البريد
        $existing = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ? OR employee_id = ?",
            [$data['username'], $data['email'], $data['employee_id'] ?? '']
        );
        
        if ($existing) {
            return [
                'success' => false,
                'message' => 'اسم المستخدم أو البريد الإلكتروني أو رقم الموظف مستخدم مسبقاً'
            ];
        }
        
        // إعداد البيانات
        $userData = [
            'username' => $this->sanitizeInput($data['username']),
            'email' => filter_var($data['email'], FILTER_VALIDATE_EMAIL),
            'full_name_ar' => $this->sanitizeInput($data['full_name_ar']),
            'full_name_en' => $this->sanitizeInput($data['full_name_en'] ?? ''),
            'employee_id' => $this->sanitizeInput($data['employee_id'] ?? ''),
            'department_id' => $data['department_id'] ?? null,
            'position' => $this->sanitizeInput($data['position'] ?? ''),
            'phone' => $this->sanitizeInput($data['phone'] ?? ''),
            'mobile' => $this->sanitizeInput($data['mobile'] ?? ''),
            'user_type' => $data['user_type'],
            'status' => $data['status'] ?? 'active',
            'password_hash' => $this->hashPassword($data['password']),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'created_by' => $this->getCurrentUser()['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // إضافة المستخدم
        $userId = $this->db->insert('users', $userData);
        
        if ($userId) {
            // تسجيل النشاط
            $this->logActivity($userId, 'user_created', "إنشاء مستخدم جديد: {$data['username']}");
            
            // إنشاء صلاحيات افتراضية
            $this->setupDefaultPermissions($userId, $data['user_type']);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'تم إنشاء المستخدم بنجاح'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء المستخدم'
        ];
    }
    
    /**
     * تحديث بيانات المستخدم
     */
    public function updateUser($userId, $data) {
        $currentUser = $this->getCurrentUser();
        
        // التحقق من الصلاحيات
        if (!$this->canEditUser($currentUser['id'], $userId)) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية لتعديل هذا المستخدم'
            ];
        }
        
        // إعداد البيانات للتحديث
        $updateData = [];
        
        if (isset($data['full_name_ar'])) {
            $updateData['full_name_ar'] = $this->sanitizeInput($data['full_name_ar']);
        }
        
        if (isset($data['full_name_en'])) {
            $updateData['full_name_en'] = $this->sanitizeInput($data['full_name_en']);
        }
        
        if (isset($data['position'])) {
            $updateData['position'] = $this->sanitizeInput($data['position']);
        }
        
        if (isset($data['phone'])) {
            $updateData['phone'] = $this->sanitizeInput($data['phone']);
        }
        
        if (isset($data['mobile'])) {
            $updateData['mobile'] = $this->sanitizeInput($data['mobile']);
        }
        
        if (isset($data['department_id'])) {
            $updateData['department_id'] = $data['department_id'];
        }
        
        if (isset($data['user_type'])) {
            // فقط المدير العام يمكنه تغيير نوع المستخدم
            if ($currentUser['user_type'] === 'super_admin') {
                $updateData['user_type'] = $data['user_type'];
            }
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }
        
        if (isset($data['profile_image'])) {
            $updateData['profile_image'] = $data['profile_image'];
        }
        
        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'لا توجد بيانات للتحديث'
            ];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        // تحديث البيانات
        $updated = $this->db->update(
            'users',
            $updateData,
            'id = ?',
            [$userId]
        );
        
        if ($updated) {
            $this->logActivity($userId, 'user_updated', 'تم تحديث بيانات المستخدم');
            return [
                'success' => true,
                'message' => 'تم تحديث بيانات المستخدم بنجاح'
            ];
        }
        
        return [
            'success' => false,
            'message' 'حدث خطأ أثناء تحديث بيانات المستخدم'
        ];
    }
    
    /**
     * تغيير كلمة المرور
     */
    public function changePassword($userId, $currentPassword, $newPassword, $confirmPassword) {
        // التحقق من تطابق كلمات المرور الجديدة
        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'message' => 'كلمات المرور الجديدة غير متطابقة'
            ];
        }
        
        // التحقق من قوة كلمة المرور الجديدة
        if (!$this->isPasswordStrong($newPassword)) {
            return [
                'success' => false,
                'message' => 'كلمة المرور الجديدة ضعيفة. يجب أن تحتوي على 8 أحرف على الأقل، وتشمل حروف كبيرة وصغيرة وأرقاماً ورموزاً.'
            ];
        }
        
        // الحصول على بيانات المستخدم
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ];
        }
        
        // التحقق من كلمة المرور الحالية
        if (!$this->verifyPassword($currentPassword, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ];
        }
        
        // التحقق من عدم استخدام كلمة مرور سابقة
        if ($this->isPreviousPassword($userId, $newPassword)) {
            return [
                'success' => false,
                'message' => 'لا يمكن استخدام كلمة مرور سابقة'
            ];
        }
        
        // تحديث كلمة المرور
        $newHash = $this->hashPassword($newPassword);
        
        $updated = $this->db->update(
            'users',
            [
                'password_hash' => $newHash,
                'password_changed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$userId]
        );
        
        if ($updated) {
            // حفظ كلمة المرور السابقة
            $this->savePreviousPassword($userId, $user['password_hash']);
            
            // إرسال إشعار
            $this->sendPasswordChangeNotification($userId);
            
            // تسجيل النشاط
            $this->logActivity($userId, 'password_changed');
            
            return [
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء تغيير كلمة المرور'
        ];
    }
    
    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword($email, $token, $newPassword) {
        // التحقق من الرمز
        $resetRequest = $this->db->fetchOne(
            "SELECT * FROM password_resets 
             WHERE email = ? AND token = ? AND expires_at > NOW() 
             AND used = FALSE",
            [$email, $token]
        );
        
        if (!$resetRequest) {
            return [
                'success' => false,
                'message' => 'رمز إعادة التعيين غير صالح أو منتهي الصلاحية'
            ];
        }
        
        // تحديث كلمة المرور
        $newHash = $this->hashPassword($newPassword);
        
        $this->db->beginTransaction();
        
        try {
            // تحديث كلمة مرور المستخدم
            $this->db->update(
                'users',
                [
                    'password_hash' => $newHash,
                    'password_changed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'email = ?',
                [$email]
            );
            
            // تعليم الرمز كمستخدم
            $this->db->update(
                'password_resets',
                ['used' => true],
                'id = ?',
                [$resetRequest['id']]
            );
            
            $this->db->commit();
            
            // تسجيل النشاط
            $user = $this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($user) {
                $this->logActivity($user['id'], 'password_reset');
            }
            
            return [
                'success' => true,
                'message' => 'تم إعادة تعيين كلمة المرور بنجاح'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إعادة تعيين كلمة المرور'
            ];
        }
    }
    
    /**
     * طلب إعادة تعيين كلمة المرور
     */
    public function requestPasswordReset($email) {
        $user = $this->db->fetchOne(
            "SELECT id, username, full_name_ar FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if (!$user) {
            // عدم إظهار أن المستخدم غير موجود لأسباب أمنية
            return [
                'success' => true,
                'message' => 'إذا كان البريد الإلكتروني مسجلاً في نظامنا، ستتلقى رسالة تعليمات قريباً.'
            ];
        }
        
        // إنشاء رمز إعادة تعيين
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // حفظ طلب إعادة التعيين
        $this->db->insert('password_resets', [
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // إرسال البريد الإلكتروني
        $this->sendResetEmail($email, $user['full_name_ar'], $token);
        
        // تسجيل النشاط
        $this->logActivity($user['id'], 'password_reset_requested');
        
        return [
            'success' => true,
            'message' => 'تم إرسال رسالة إعادة التعيين إلى بريدك الإلكتروني'
        ];
    }
    
    /**
     * تسجيل النشاط
     */
    public function logActivity($userId, $action, $details = '', $documentId = null) {
        $activityData = [
            'user_id' => $userId,
            'action_type' => $action,
            'action_details' => $details,
            'record_id' => $documentId,
            'record_type' => $documentId ? 'document' : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('activity_logs', $activityData);
    }
    
    /**
     * الحصول على سجل الأنشطة
     */
    public function getActivityLog($limit = 50, $userId = null) {
        $sql = "SELECT al.*, u.full_name_ar, u.username 
                FROM activity_logs al 
                LEFT JOIN users u ON al.user_id = u.id";
        
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE al.user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * الحصول على جميع المستخدمين
     */
    public function getAllUsers($filters = [], $page = 1, $perPage = 20) {
        $sql = "SELECT u.*, d.name_ar as department_name 
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE 1=1";
        
        $params = [];
        
        // تطبيق الفلاتر
        if (!empty($filters['department_id'])) {
            $sql .= " AND u.department_id = ?";
            $params[] = $filters['department_id'];
        }
        
        if (!empty($filters['user_type'])) {
            $sql .= " AND u.user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (u.username LIKE ? OR u.full_name_ar LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // الحصول على العدد الإجمالي
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as count_table";
        $total = $this->db->fetchColumn($countSql, $params);
        
        // إضافة الترتيب والتجزئة
        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        
        $users = $this->db->fetchAll($sql, $params);
        
        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * الحصول على مستخدم بواسطة ID
     */
    public function getUserById($userId) {
        return $this->db->fetchOne(
            "SELECT u.*, d.name_ar as department_name 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             WHERE u.id = ?",
            [$userId]
        );
    }
    
    /**
     * الحصول على مستخدم بواسطة اسم المستخدم
     */
    public function getUserByUsername($username) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
    }
    
    /**
     * الحصول على مستخدم بواسطة البريد الإلكتروني
     */
    public function getUserByEmail($email) {
        return $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
    }
    
    /**
     * تحديث صورة الملف الشخصي
     */
    public function updateProfileImage($userId, $imagePath) {
        $updated = $this->db->update(
            'users',
            ['profile_image' => $imagePath],
            'id = ?',
            [$userId]
        );
        
        if ($updated) {
            $this->logActivity($userId, 'profile_image_updated');
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث التوقيع
     */
    public function updateSignature($userId, $signaturePath) {
        $updated = $this->db->update(
            'users',
            ['signature_image' => $signaturePath],
            'id = ?',
            [$userId]
        );
        
        if ($updated) {
            $this->logActivity($userId, 'signature_updated');
            return true;
        }
        
        return false;
    }
    
    /**
     * تحديث آخر نشاط
     */
    public function updateLastActivity($userId) {
        return $this->db->update(
            'users',
            ['last_activity' => date('Y-m-d H:i:s')],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * الحصول على عدد المستخدمين النشطين
     */
    public function getActiveUsersCount() {
        return $this->db->count('users', "status = 'active'");
    }
    
    /**
     * الحصول على إحصائيات المستخدمين
     */
    public function getUserStats() {
        $stats = [];
        
        // إحصائيات حسب النوع
        $types = $this->db->fetchAll(
            "SELECT user_type, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY user_type"
        );
        
        $stats['by_type'] = $types;
        
        // إحصائيات حسب القسم
        $departments = $this->db->fetchAll(
            "SELECT d.name_ar, COUNT(u.id) as count 
             FROM users u 
             LEFT JOIN departments d ON u.department_id = d.id 
             WHERE u.status = 'active' 
             GROUP BY d.id, d.name_ar"
        );
        
        $stats['by_department'] = $departments;
        
        // إحصائيات النشاط
        $activity = $this->db->fetchOne(
            "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as active_today,
                SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_week,
                SUM(CASE WHEN last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_month
             FROM users 
             WHERE status = 'active'"
        );
        
        $stats['activity'] = $activity;
        
        // إحصائيات التسجيل
        $registration = $this->db->fetchAll(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM users 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
             GROUP BY DATE(created_at) 
             ORDER BY date DESC"
        );
        
        $stats['registration'] = $registration;
        
        return $stats;
    }
    
    // ========== الدوال المساعدة ==========
    
    /**
     * حفظ المستخدم في الجلسة
     */
    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name_ar'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // توليد ID جديد للجلسة
        session_regenerate_id(true);
    }
    
    /**
     * استعادة المستخدم من الجلسة
     */
    private function restoreUserFromSession() {
        if ($this->isLoggedIn()) {
            $this->user = $this->db->fetchOne(
                "SELECT * FROM users WHERE id = ? AND status = 'active'",
                [$_SESSION['user_id']]
            );
            
            if (!$this->user) {
                $this->logout();
            }
        }
    }
    
    /**
     * تشفير كلمة المرور
     */
    private function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * التحقق من كلمة المرور
     */
    private function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * التحقق من قوة كلمة المرور
     */
    private function isPasswordStrong($password) {
        // على الأقل 8 أحرف
        if (strlen($password) < 8) {
            return false;
        }
        
        // تحتوي على حرف كبير وحرف صغير ورقم
        if (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // تجنب كلمات المرور الشائعة
        $commonPasswords = ['123456', 'password', 'qwerty', 'admin', 'welcome'];
        if (in_array(strtolower($password), $commonPasswords)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * تنظيف المدخلات
     */
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * التحقق من كون الحساب مغلقاً
     */
    private function isAccountLocked($username) {
        $user = $this->db->fetchOne(
            "SELECT locked_until FROM users 
             WHERE (username = ? OR email = ?) 
             AND locked_until IS NOT NULL 
             AND locked_until > NOW()",
            [$username, $username]
        );
        
        return !empty($user);
    }
    
    /**
     * تسجيل محاولة دخول فاشلة
     */
    private function logFailedAttempt($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->insert('failed_login_attempts', [
            'username' => $username,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * تحديث محاولات الدخول
     */
    private function updateLoginAttempts($userId, $failed = false) {
        if ($failed) {
            // زيادة عدد المحاولات الفاشلة
            $this->db->query(
                "UPDATE users 
                 SET login_attempts = login_attempts + 1,
                     last_failed_attempt = NOW()
                 WHERE id = ?",
                [$userId]
            );
            
            // قفل الحساب إذا تجاوز الحد
            $attempts = $this->db->fetchColumn(
                "SELECT login_attempts FROM users WHERE id = ?",
                [$userId]
            );
            
            if ($attempts >= $this->maxLoginAttempts) {
                $this->lockAccount($userId);
            }
        } else {
            // إعادة تعيين المحاولات الفاشلة
            $this->db->update(
                'users',
                [
                    'login_attempts' => 0,
                    'locked_until' => null,
                    'last_login' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$userId]
            );
        }
    }
    
    /**
     * تحديث تسجيل الدخول الناجح
     */
    private function updateLoginSuccess($userId) {
        $this->db->update(
            'users',
            [
                'last_login' => date('Y-m-d H:i:s'),
                'login_attempts' => 0,
                'locked_until' => null,
                'last_activity' => date('Y-m-d H:i:s')
            ],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * قفل الحساب
     */
    private function lockAccount($userId) {
        $lockUntil = date('Y-m-d H:i:s', time() + $this->lockDuration);
        
        $this->db->update(
            'users',
            ['locked_until' => $lockUntil],
            'id = ?',
            [$userId]
        );
        
        // إرسال إشعار
        $this->sendAccountLockedNotification($userId);
    }
    
    /**
     * الحصول على صلاحيات المستخدم
     */
    private function getUserPermissions($userId) {
        // أولاً، الحصول على الدور
        $user = $this->db->fetchOne(
            "SELECT user_type FROM users WHERE id = ?",
            [$userId]
        );
        
        if (!$user) {
            return [];
        }
        
        // الصلاحيات الافتراضية حسب الدور
        $defaultPermissions = $this->getDefaultPermissions($user['user_type']);
        
        // الصلاحيات المخصصة
        $customPermissions = $this->db->fetchAll(
            "SELECT permission FROM user_permissions WHERE user_id = ?",
            [$userId]
        );
        
        $customPermissions = array_column($customPermissions, 'permission');
        
        // دمج الصلاحيات
        return array_unique(array_merge($defaultPermissions, $customPermissions));
    }
    
    /**
     * الحصول على الصلاحيات الافتراضية
     */
    private function getDefaultPermissions($userType) {
        $permissions = [
            'super_admin' => [
                'manage_users', 'manage_departments', 'manage_system', 
                'view_all_documents', 'edit_all_documents', 'delete_documents',
                'approve_all', 'view_reports', 'export_data', 'system_settings',
                'backup_restore', 'audit_logs'
            ],
            'admin' => [
                'manage_users', 'manage_departments', 'view_all_documents',
                'edit_documents', 'approve_documents', 'view_reports',
                'export_data'
            ],
            'financial_manager' => [
                'manage_financial', 'approve_payments', 'view_financial_reports',
                'export_financial', 'manage_budget'
            ],
            'hr_manager' => [
                'manage_hr', 'view_hr_reports', 'approve_vacations',
                'manage_contracts', 'export_hr'
            ],
            'department_manager' => [
                'manage_department', 'view_department_reports',
                'approve_department_documents'
            ],
            'user' => [
                'view_documents', 'upload_documents', 'edit_own_documents',
                'view_own_reports', 'export_own_data'
            ],
            'viewer' => [
                'view_documents'
            ]
        ];
        
        return $permissions[$userType] ?? [];
    }
    
    /**
     * إعداد الصلاحيات الافتراضية
     */
    private function setupDefaultPermissions($userId, $userType) {
        $permissions = $this->getDefaultPermissions($userType);
        
        foreach ($permissions as $permission) {
            $this->db->insert('user_permissions', [
                'user_id' => $userId,
                'permission' => $permission,
                'granted_by' => $this->getCurrentUser()['id'] ?? null,
                'granted_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * التحقق من إمكانية تعديل مستخدم
     */
    private function canEditUser($currentUserId, $targetUserId) {
        if ($currentUserId == $targetUserId) {
            return true;
        }
        
        $currentUser = $this->getUserById($currentUserId);
        $targetUser = $this->getUserById($targetUserId);
        
        if (!$currentUser || !$targetUser) {
            return false;
        }
        
        // المدير العام يمكنه تعديل الجميع
        if ($currentUser['user_type'] === 'super_admin') {
            return true;
        }
        
        // المدير يمكنه تعديل المستخدمين العاديين
        if ($currentUser['user_type'] === 'admin' && 
            $targetUser['user_type'] !== 'super_admin') {
            return true;
        }
        
        // المدير المالي يمكنه تعديل المستخدمين الماليين
        if ($currentUser['user_type'] === 'financial_manager' && 
            $targetUser['department_id'] == $currentUser['department_id']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * التحقق من كونها كلمة مرور سابقة
     */
    private function isPreviousPassword($userId, $password) {
        $previousPasswords = $this->db->fetchAll(
            "SELECT password_hash FROM password_history 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 5",
            [$userId]
        );
        
        foreach ($previousPasswords as $oldHash) {
            if ($this->verifyPassword($password, $oldHash['password_hash'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * حفظ كلمة المرور السابقة
     */
    private function savePreviousPassword($userId, $oldHash) {
        $this->db->insert('password_history', [
            'user_id' => $userId,
            'password_hash' => $oldHash,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // الاحتفاظ بـ 5 كلمات مرور سابقة فقط
        $this->db->query(
            "DELETE FROM password_history 
             WHERE user_id = ? 
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM password_history 
                     WHERE user_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 5
                 ) as keep
             )",
            [$userId, $userId]
        );
    }
    
    /**
     * إعداد تذكرني
     */
    private function setRememberMeToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $this->db->insert('remember_tokens', [
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // حفظ في cookie
        setcookie('remember_token', $token, [
            'expires' => strtotime('+30 days'),
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    /**
     * مسح تذكرني
     */
    private function clearRememberMeToken() {
        if (isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            $hashedToken = hash('sha256', $token);
            
            $this->db->delete(
                'remember_tokens',
                'token = ?',
                [$hashedToken]
            );
            
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
    /**
     * الحصول على رسالة حالة الحساب
     */
    private function getAccountStatusMessage($status) {
        $messages = [
            'inactive' => 'الحساب غير مفعل. الرجاء الاتصال بالمسؤول.',
            'suspended' => 'الحساب موقوف مؤقتاً. الرجاء الاتصال بالمسؤول.',
            'on_leave' => 'الحساب في إجازة. الرجاء الاتصال بالمسؤول لإعادة التفعيل.'
        ];
        
        return $messages[$status] ?? 'الحساب غير مفعل. الرجاء الاتصال بالمسؤول.';
    }
    
    /**
     * إرسال بريد إعادة التعيين
     */
    private function sendResetEmail($email, $name, $token) {
        $resetLink = BASE_URL . "/reset-password.php?email=" . urlencode($email) . "&token=" . $token;
        
        $subject = "إعادة تعيين كلمة المرور - نظام الأرشيف المتكامل";
        $message = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                    <h2 style='color: #2c3e50; text-align: center;'>إعادة تعيين كلمة المرور</h2>
                    
                    <p>مرحباً {$name},</p>
                    
                    <p>لقد تلقينا طلباً لإعادة تعيين كلمة المرور لحسابك في نظام الأرشيف المتكامل.</p>
                    
                    <p>لإعادة تعيين كلمة المرور، يرجى النقر على الرابط التالي:</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' 
                           style='background: #3498db; color: white; padding: 12px 30px; 
                                  text-decoration: none; border-radius: 5px; font-weight: bold;'>
                            إعادة تعيين كلمة المرور
                        </a>
                    </div>
                    
                    <p>إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة.</p>
                    
                    <p>ملاحظة: هذا الرابط سينتهي خلال ساعة واحدة.</p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    
                    <p style='font-size: 12px; color: #666; text-align: center;'>
                        هذه رسالة آلية من نظام الأرشيف المتكامل. الرجاء عدم الرد على هذا البريد.
                    </p>
                </div>
            </body>
            </html>
        ";
        
        // استخدام دالة send_email من functions.php
        if (function_exists('send_email')) {
            send_email($email, $subject, $message);
        }
    }
    
    /**
     * إرسال إشعار تغيير كلمة المرور
     */
    private function sendPasswordChangeNotification($userId) {
        $user = $this->getUserById($userId);
        if (!$user) return;
        
        $subject = "تم تغيير كلمة المرور - نظام الأرشيف المتكامل";
        $message = "
            <html>
            <body>
                <h2>تم تغيير كلمة المرور</h2>
                <p>مرحباً {$user['full_name_ar']},</p>
                <p>تم تغيير كلمة المرور لحسابك بنجاح.</p>
                <p>إذا لم تقم بتغيير كلمة المرور، يرجى الاتصال بالدعم الفوري.</p>
                <p>التاريخ: " . date('Y-m-d H:i:s') . "</p>
            </body>
            </html>
        ";
        
        if (function_exists('send_email')) {
            send_email($user['email'], $subject, $message);
        }
    }
    
    /**
     * إرسال إشعار قفل الحساب
     */
    private function sendAccountLockedNotification($userId) {
        $user = $this->getUserById($userId);
        if (!$user) return;
        
        $subject = "تنبيه أمني - قفل الحساب - نظام الأرشيف المتكامل";
        $message = "
            <html>
            <body>
                <h2>تنبيه أمني</h2>
                <p>مرحباً {$user['full_name_ar']},</p>
                <p>لقد تم قفل حسابك مؤقتاً بسبب عدة محاولات دخول فاشلة.</p>
                <p>سيتم فتح الحساب تلقائياً بعد 15 دقيقة، أو يمكنك الاتصال بالدعم.</p>
                <p>التاريخ: " . date('Y-m-d H:i:s') . "</p>
            </body>
            </html>
        ";
        
        if (function_exists('send_email')) {
            send_email($user['email'], $subject, $message);
        }
    }
}

// إنشاء كائن المصادقة عالمياً
$GLOBALS['auth'] = new Auth();

// دوال اختصار
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        global $auth;
        return $auth->isLoggedIn();
    }
}

if (!function_exists('current_user')) {
    function current_user() {
        global $auth;
        return $auth->getCurrentUser();
    }
}

if (!function_exists('has_permission')) {
    function has_permission($permission, $resource = null) {
        global $auth;
        return $auth->hasPermission($permission, $resource);
    }
}

if (!function_exists('has_role')) {
    function has_role($role) {
        global $auth;
        return $auth->hasRole($role);
    }
}

if (!function_exists('log_activity')) {
    function log_activity($userId, $action, $details = '', $documentId = null) {
        global $auth;
        return $auth->logActivity($userId, $action, $details, $documentId);
    }
}
?>