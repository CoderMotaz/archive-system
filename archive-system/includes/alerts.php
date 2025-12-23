<?php
// فئة لإدارة التنبيهات
class AlertSystem {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * إنشاء تنبيه جديد
     */
    public function createAlert($type, $message, $userId = null, $actionUrl = null, $icon = null) {
        $alertData = [
            'user_id' => $userId,
            'alert_type' => $type,
            'message' => $message,
            'action_url' => $actionUrl,
            'icon' => $icon ?? $this->getIconByType($type),
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('notifications', $alertData);
    }
    
    /**
     * جلب تنبيهات المستخدم
     */
    public function getUserAlerts($userId, $limit = 10, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * وضع علامة مقروء على التنبيه
     */
    public function markAsRead($alertId, $userId) {
        return $this->db->update(
            'notifications',
            ['is_read' => true, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ?',
            [$alertId, $userId]
        );
    }
    
    /**
     * حذف التنبيه
     */
    public function deleteAlert($alertId, $userId) {
        return $this->db->delete(
            'notifications',
            'id = ? AND user_id = ?',
            [$alertId, $userId]
        );
    }
    
    /**
     * إنشاء تنبيهات تلقائية
     */
    public function createSystemAlerts() {
        $alerts = [];
        
        // التحقق من المستندات المنتهية الصلاحية
        $expiredDocuments = $this->db->fetchAll("
            SELECT fd.id, fd.document_number, fd.title_ar, fd.expiry_date, u.id as user_id
            FROM financial_documents fd
            JOIN users u ON fd.created_by = u.id
            WHERE fd.expiry_date IS NOT NULL 
            AND fd.expiry_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND fd.status = 'active'
        ");
        
        foreach ($expiredDocuments as $doc) {
            $this->createAlert(
                'warning',
                "المستند {$doc['document_number']} سينتهي صلاحيته بعد 7 أيام",
                $doc['user_id'],
                "documents/view.php?id={$doc['id']}",
                'fa-exclamation-triangle'
            );
        }
        
        // التحقق من المهام المتأخرة
        $overdueTasks = $this->db->fetchAll("
            SELECT t.id, t.task_number, t.title, t.assigned_to
            FROM tasks t
            WHERE t.due_date < CURDATE() 
            AND t.status IN ('pending', 'in_progress')
        ");

        foreach ($overdueTasks as $task) {
            $this->createAlert(
                'danger',
                "المهمة {$task['task_number']} متأخرة",
                $task['assigned_to'],
                "tasks.php?id={$task['id']}",
                'fa-clock'
            );
        }
        // التحقق من نسخ احتياطي
        $lastBackup = $this->db->fetchOne("
            SELECT created_at FROM backup_history 
            WHERE status = 'success' 
            ORDER BY created_at DESC LIMIT 1
        ");
        
        if ($lastBackup && strtotime($lastBackup['created_at']) < strtotime('-2 days')) {
            $admins = $this->db->fetchAll("
                SELECT id FROM users WHERE user_type IN ('super_admin', 'admin')
            ");
            
            foreach ($admins as $admin) {
                $this->createAlert(
                    'info',
                    "لم يتم إنشاء نسخة احتياطية منذ أكثر من يومين",
                    $admin['id'],
                    "settings/backup.php",
                    'fa-database'
                );
            }
        }
        
        return count($alerts);
    }
    
    /**
     * الحصول على أيقونة حسب النوع
     */
    private function getIconByType($type) {
        $icons = [
            'success' => 'fa-check-circle',
            'info' => 'fa-info-circle',
            'warning' => 'fa-exclamation-triangle',
            'danger' => 'fa-times-circle',
            'primary' => 'fa-bell',
            'secondary' => 'fa-envelope'
        ];
        
        return $icons[$type] ?? 'fa-bell';
    }
    
    /**
     * الحصول على لون حسب النوع
     */
    public function getColorByType($type) {
        $colors = [
            'success' => 'success',
            'info' => 'info',
            'warning' => 'warning',
            'danger' => 'danger',
            'primary' => 'primary',
            'secondary' => 'secondary'
        ];
        
        return $colors[$type] ?? 'primary';
    }
}

// دالة لعرض التنبيهات في الصفحة
function displayAlerts($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return '';
    
    $alertSystem = new AlertSystem();
    $alerts = $alertSystem->getUserAlerts($userId, 5, true);
    
    if (empty($alerts)) {
        return '';
    }
    
    $html = '<div class="alerts-container">';
    
    foreach ($alerts as $alert) {
        $color = $alertSystem->getColorByType($alert['alert_type']);
        $icon = $alert['icon'] ?: $alertSystem->getIconByType($alert['alert_type']);
        
        $html .= '
        <div class="alert alert-' . $color . ' alert-dismissible fade show" role="alert" data-alert-id="' . $alert['id'] . '">
            <div class="d-flex align-items-center">
                <div class="alert-icon">
                    <i class="fas ' . $icon . '"></i>
                </div>
                <div class="alert-content flex-grow-1">
                    ' . $alert['message'] . '
                    ' . ($alert['action_url'] ? '<a href="' . $alert['action_url'] . '" class="alert-link">عرض</a>' : '') . '
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// دالة لعرض منبثق للتنبيهات
function displayAlertModal($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return '';
    
    $alertSystem = new AlertSystem();
    $alerts = $alertSystem->getUserAlerts($userId, 20);
    
    ob_start();
    ?>
    <!-- مودال التنبيهات -->
    <div class="modal fade" id="alertsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bell me-2"></i>التنبيهات والإشعارات
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <?php if (empty($alerts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-bell-slash fa-3x mb-3"></i>
                            <h6>لا توجد تنبيهات</h6>
                            <p class="mb-0">ستظهر هنا أي تنبيهات أو إشعارات جديدة</p>
                        </div>
                    <?php else: ?>
                        <div class="alerts-list">
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                $color = $alertSystem->getColorByType($alert['alert_type']);
                                $icon = $alert['icon'] ?: $alertSystem->getIconByType($alert['alert_type']);
                                ?>
                                <div class="alert-item <?php echo $alert['is_read'] ? 'read' : 'unread'; ?>" 
                                     data-alert-id="<?php echo $alert['id']; ?>">
                                    <div class="alert-icon text-<?php echo $color; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="alert-content">
                                        <p class="mb-1"><?php echo $alert['message']; ?></p>
                                        <small class="text-muted">
                                            <i class="far fa-clock"></i> 
                                            <?php echo time_elapsed_string($alert['created_at']); ?>
                                        </small>
                                    </div>
                                    <div class="alert-actions">
                                        <?php if ($alert['action_url']): ?>
                                            <a href="<?php echo $alert['action_url']; ?>" class="btn btn-sm btn-outline-<?php echo $color; ?>">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!$alert['is_read']): ?>
                                            <button class="btn btn-sm btn-outline-secondary mark-as-read" 
                                                    title="وضع علامة مقروء">
                                                <i class="far fa-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline-danger delete-alert" title="حذف">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <button type="button" class="btn btn-outline-primary" id="markAllAsRead">
                        <i class="far fa-check-circle me-2"></i>تعيين الكل كمقروء
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .alerts-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .alert-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #e0e0e0;
        transition: background 0.3s;
    }
    
    .alert-item:hover {
        background: #f8f9fa;
    }
    
    .alert-item.unread {
        background: #f0f7ff;
    }
    
    .alert-item:last-child {
        border-bottom: none;
    }
    
    .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
        margin-left: 15px;
    }
    
    .alert-content {
        flex: 1;
    }
    
    .alert-content p {
        margin: 0;
        color: #333;
    }
    
    .alert-item.unread .alert-content p {
        font-weight: 500;
    }
    
    .alert-actions {
        display: flex;
        gap: 5px;
        flex-shrink: 0;
    }
    
    .alert-actions .btn {
        width: 35px;
        height: 35px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // وضع علامة مقروء على تنبيه
        document.querySelectorAll('.mark-as-read').forEach(button => {
            button.addEventListener('click', function() {
                const alertItem = this.closest('.alert-item');
                const alertId = alertItem.dataset.alertId;
                
                fetch(`api/alerts.php?action=mark_read&id=${alertId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alertItem.classList.remove('unread');
                        alertItem.classList.add('read');
                        this.remove();
                        updateNotificationCount();
                    }
                });
            });
        });
        
        // حذف تنبيه
        document.querySelectorAll('.delete-alert').forEach(button => {
            button.addEventListener('click', function() {
                const alertItem = this.closest('.alert-item');
                const alertId = alertItem.dataset.alertId;
                
                if (confirm('هل تريد حذف هذا التنبيه؟')) {
                    fetch(`api/alerts.php?action=delete&id=${alertId}`, {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alertItem.remove();
                            updateNotificationCount();
                        }
                    });
                }
            });
        });
        
        // تعيين الكل كمقروء
        document.getElementById('markAllAsRead').addEventListener('click', function() {
            if (confirm('هل تريد تعيين جميع التنبيهات كمقروءة؟')) {
                fetch('api/alerts.php?action=mark_all_read', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.alert-item').forEach(item => {
                            item.classList.remove('unread');
                            item.classList.add('read');
                        });
                        document.querySelectorAll('.mark-as-read').forEach(button => {
                            button.remove();
                        });
                        updateNotificationCount();
                    }
                });
            }
        });
        
        function updateNotificationCount() {
            // تحديث العداد في الهيدر
            fetch('api/alerts.php?action=count_unread')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-dropdown .badge');
                    if (badge) {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'block';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}

// دالة مساعدة: وقت مر منذ
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'سنة',
        'm' => 'شهر',
        'w' => 'أسبوع',
        'd' => 'يوم',
        'h' => 'ساعة',
        'i' => 'دقيقة',
        's' => 'ثانية',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'منذ ' . implode(', ', $string) : 'الآن';
}
?>

<!-- تضمين تلقائي للتنبيهات إذا لم تكن الصفحة هي تسجيل الدخول -->
<?php if (basename($_SERVER['PHP_SELF']) !== 'login.php' && isset($_SESSION['user_id'])): ?>
    <div class="global-alerts">
        <?php echo displayAlerts($_SESSION['user_id']); ?>
    </div>
    
    <script>
    // التعامل مع التنبيهات في الصفحة
    document.addEventListener('DOMContentLoaded', function() {
        // وضع علامة مقروء عند إغلاق التنبيه
        document.querySelectorAll('.alert[data-alert-id]').forEach(alert => {
            alert.addEventListener('closed.bs.alert', function() {
                const alertId = this.dataset.alertId;
                
                fetch(`api/alerts.php?action=mark_read&id=${alertId}`, {
                    method: 'POST'
                });
            });
        });
        
        // إخفاء التنبيهات تلقائياً بعد 10 ثوانٍ
        setTimeout(() => {
            document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 10000);
    });
    </script>
<?php endif; ?> 