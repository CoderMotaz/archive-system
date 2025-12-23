<?php
// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// جلب بيانات المستخدم
require_once 'classes/Auth.class.php';
$auth = new Auth();
$user = $auth->getCurrentUser();

// جلب الإشعارات غير المقروءة
$db = Database::getInstance();
$unreadNotifications = $db->fetchOne(
    "SELECT COUNT(*) as count FROM notifications 
     WHERE user_id = ? AND is_read = FALSE",
    [$user['id']]
)['count'] ?? 0;

// جلد المهام العاجلة
$urgentTasks = $db->fetchOne(
    "SELECT COUNT(*) as count FROM tasks 
     WHERE assigned_to = ? AND status IN ('pending', 'in_progress') 
     AND priority IN ('high', 'urgent') AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)",
    [$user['id']]
)['count'] ?? 0;

// جلد المستندات التي تنتظر الموافقة
$pendingApprovals = $db->fetchOne(
    "SELECT COUNT(*) as count FROM financial_documents 
     WHERE status = 'pending_approval' 
     AND (approved_by = ? OR reviewed_by = ?)",
    [$user['id'], $user['id']]
)['count'] ?? 0;

// حساب عدد الإشعارات الكلي
$totalAlerts = $unreadNotifications + $urgentTasks + $pendingApprovals;
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>نظام الأرشيف المتكامل</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- CSS إضافي حسب الصفحة -->
    <?php if (isset($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="assets/css/<?php echo $css; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --header-height: 70px;
        }
    </style>
</head>
<body>
    <!-- الهيدر الرئيسي -->
    <header class="main-header">
        <div class="header-content">
            <!-- اليسار: الشعار وزر القائمة -->
            <div class="header-left">
                <button class="btn btn-sm btn-outline-light d-lg-none me-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="logo">
                    <img src="assets/img/logo.png" alt="شعار النظام" height="40">
                    <h1 class="d-none d-lg-inline">نظام الأرشيف المتكامل</h1>
                </div>
            </div>
            
            <!-- الوسط: البحث السريع -->
            <div class="header-center d-none d-md-flex">
                <div class="search-box">
                    <form action="search.php" method="GET" class="d-flex">
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" 
                                   placeholder="ابحث في الأرشيف..." name="q" id="globalSearch">
                            <button class="btn btn-sm btn-outline-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- اليمين: قائمة المستخدم والإشعارات -->
            <div class="header-right">
                <div class="user-menu">
                    <!-- زر التنبيهات -->
                    <div class="dropdown notification-dropdown">
                        <button class="btn btn-link text-light position-relative" 
                                type="button" id="notificationDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($totalAlerts > 0): ?>
                                <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $totalAlerts; ?>
                                    <span class="visually-hidden">إشعارات غير مقروءة</span>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-panel" 
                             aria-labelledby="notificationDropdown">
                            <div class="notification-header">
                                <h6 class="mb-0">الإشعارات</h6>
                                <small><a href="#" class="text-primary">عرض الكل</a></small>
                            </div>
                            <div class="notification-list" id="notificationList">
                                <!-- سيتم تحميل الإشعارات عبر AJAX -->
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- زر المهام -->
                    <div class="dropdown task-dropdown">
                        <button class="btn btn-link text-light position-relative" 
                                type="button" id="taskDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tasks"></i>
                            <?php if ($urgentTasks > 0): ?>
                                <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-warning">
                                    <?php echo $urgentTasks; ?>
                                    <span class="visually-hidden">مهام عاجلة</span>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" 
                             aria-labelledby="taskDropdown">
                            <div class="dropdown-header">
                                <h6 class="mb-0">المهام العاجلة</h6>
                                <small><a href="tasks.php" class="text-primary">عرض الكل</a></small>
                            </div>
                            <div class="dropdown-body">
                                <!-- سيتم تحميل المهام عبر AJAX -->
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- زر المستندات -->
                    <div class="dropdown document-dropdown">
                        <button class="btn btn-link text-light position-relative" 
                                type="button" id="documentDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-signature"></i>
                            <?php if ($pendingApprovals > 0): ?>
                                <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-info">
                                    <?php echo $pendingApprovals; ?>
                                    <span class="visually-hidden">مستندات تنتظر الموافقة</span>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" 
                             aria-labelledby="documentDropdown">
                            <div class="dropdown-header">
                                <h6 class="mb-0">تنتظر الموافقة</h6>
                                <small><a href="documents.php?status=pending_approval" class="text-primary">عرض الكل</a></small>
                            </div>
                            <div class="dropdown-body">
                                <!-- سيتم تحميل المستندات عبر AJAX -->
                                <div class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">جاري التحميل...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- زر المستخدم -->
                    <div class="dropdown user-dropdown">
                        <button class="btn btn-link text-light d-flex align-items-center" 
                                type="button" id="userDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="user-avatar me-2">
                                <?php if ($user['profile_image']): ?>
                                    <img src="<?php echo $user['profile_image']; ?>" 
                                         alt="صورة المستخدم" class="rounded-circle" width="35" height="35">
                                <?php else: ?>
                                    <span><?php echo mb_substr($user['full_name_ar'], 0, 1, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-none d-md-block">
                                <span class="user-name"><?php echo $user['full_name_ar']; ?></span>
                                <small class="d-block text-light-50"><?php echo $user['position']; ?></small>
                            </div>
                            <i class="fas fa-chevron-down me-2"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end" 
                             aria-labelledby="userDropdown">
                            <div class="dropdown-header text-center">
                                <div class="user-avatar mb-2 mx-auto">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?php echo $user['profile_image']; ?>" 
                                             alt="صورة المستخدم" class="rounded-circle" width="60" height="60">
                                    <?php else: ?>
                                        <span style="width: 60px; height: 60px; font-size: 1.5rem;">
                                            <?php echo mb_substr($user['full_name_ar'], 0, 1, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h6 class="mb-0"><?php echo $user['full_name_ar']; ?></h6>
                                <small class="text-muted"><?php echo $user['position']; ?></small>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>الملف الشخصي
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cog me-2"></i>الإعدادات
                            </a>
                            <div class="dropdown-divider"></div>
                            <?php if ($user['user_type'] === 'super_admin' || $user['user_type'] === 'admin'): ?>
                                <a class="dropdown-item" href="admin/">
                                    <i class="fas fa-shield-alt me-2"></i>لوحة الإدارة
                                </a>
                                <div class="dropdown-divider"></div>
                            <?php endif; ?>
                            <a class="dropdown-item" href="help.php">
                                <i class="fas fa-question-circle me-2"></i>مساعدة
                            </a>
                            <a class="dropdown-item" href="about.php">
                                <i class="fas fa-info-circle me-2"></i>حول النظام
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>تسجيل الخروج
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- منطقة الإشعارات العاجلة -->
    <div id="urgentAlerts" class="urgent-alerts-container"></div>

    <!-- منطقة التنبيهات -->
    <div id="toastContainer" class="toast-container position-fixed top-0 start-0 p-3" style="z-index: 1100;"></div>

    <script>
    // تحميل الإشعارات عند فتح القائمة
    document.addEventListener('DOMContentLoaded', function() {
        // تحميل الإشعارات
        loadNotifications();
        
        // تحميل المهام
        loadTasks();
        
        // تحميل المستندات
        loadPendingDocuments();
        
        // تحميل التنبيهات العاجلة
        loadUrgentAlerts();
        
        // إعداد البحث السريع
        setupQuickSearch();
    });
    
    function loadNotifications() {
        fetch('api/notifications.php?action=get_unread&limit=5')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('notificationList');
                if (data.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <p class="mb-0">لا توجد إشعارات جديدة</p>
                        </div>
                    `;
                } else {
                    let html = '';
                    data.forEach(notification => {
                        html += `
                        <a href="${notification.action_url || '#'}" class="notification-item ${notification.is_read ? '' : 'unread'}" 
                           onclick="markNotificationAsRead(${notification.id})">
                            <div class="notification-icon">
                                <i class="fas ${notification.icon || 'fa-bell'} ${notification.color ? 'text-' + notification.color : ''}"></i>
                            </div>
                            <div class="notification-content">
                                <h6>${notification.title}</h6>
                                <p>${notification.message}</p>
                                <small class="notification-time">${notification.time_ago}</small>
                            </div>
                        </a>
                        `;
                    });
                    container.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }
    
    function loadTasks() {
        fetch('api/tasks.php?action=get_urgent&limit=5')
            .then(response => response.json())
            .then(data => {
                const dropdown = document.querySelector('.task-dropdown .dropdown-body');
                if (data.length === 0) {
                    dropdown.innerHTML = `
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <p class="mb-0">لا توجد مهام عاجلة</p>
                        </div>
                    `;
                } else {
                    let html = '';
                    data.forEach(task => {
                        html += `
                        <a href="tasks.php?id=${task.id}" class="dropdown-item task-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${task.title}</h6>
                                    <small class="text-muted">${task.description}</small>
                                </div>
                                <span class="badge bg-${task.priority === 'urgent' ? 'danger' : 'warning'}">
                                    ${task.priority === 'urgent' ? 'عاجل' : 'مهم'}
                                </span>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="far fa-clock"></i> ${task.due_date}
                            </small>
                        </a>
                        `;
                    });
                    dropdown.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading tasks:', error);
            });
    }
    
    function loadPendingDocuments() {
        fetch('api/documents.php?action=get_pending_approval&limit=5')
            .then(response => response.json())
            .then(data => {
                const dropdown = document.querySelector('.document-dropdown .dropdown-body');
                if (data.length === 0) {
                    dropdown.innerHTML = `
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-file-check fa-2x mb-2"></i>
                            <p class="mb-0">لا توجد مستندات تنتظر الموافقة</p>
                        </div>
                    `;
                } else {
                    let html = '';
                    data.forEach(doc => {
                        html += `
                        <a href="documents/view.php?id=${doc.id}" class="dropdown-item document-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${doc.title_ar}</h6>
                                    <small class="text-muted">${doc.document_number}</small>
                                </div>
                                <span class="badge bg-info">${doc.amount.toLocaleString('ar-SA')} ر.س</span>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="far fa-user"></i> ${doc.from_party_name}
                            </small>
                        </a>
                        `;
                    });
                    dropdown.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading pending documents:', error);
            });
    }
    
    function loadUrgentAlerts() {
        fetch('api/alerts.php?action=get_urgent')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('urgentAlerts');
                if (data.length > 0) {
                    let html = '';
                    data.forEach(alert => {
                        html += `
                        <div class="alert alert-${alert.type} alert-dismissible fade show mb-0" role="alert">
                            <div class="container">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${alert.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        </div>
                        `;
                    });
                    container.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading urgent alerts:', error);
            });
    }
    
    function setupQuickSearch() {
        const searchInput = document.getElementById('globalSearch');
        const searchResults = document.createElement('div');
        searchResults.className = 'search-results dropdown-menu show';
        searchResults.style.position = 'absolute';
        searchResults.style.width = searchInput.offsetWidth + 'px';
        searchInput.parentNode.appendChild(searchResults);
        
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`api/search.php?q=${encodeURIComponent(query)}&type=quick`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length === 0) {
                            searchResults.innerHTML = `
                                <div class="dropdown-item text-muted">
                                    <i class="fas fa-search me-2"></i>
                                    لا توجد نتائج
                                </div>
                            `;
                        } else {
                            let html = '';
                            data.forEach(item => {
                                html += `
                                <a href="${item.url}" class="dropdown-item">
                                    <div class="d-flex align-items-center">
                                        <div class="search-icon me-2">
                                            <i class="fas ${item.icon} ${item.color ? 'text-' + item.color : ''}"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">${item.title}</h6>
                                            <small class="text-muted">${item.subtitle}</small>
                                        </div>
                                    </div>
                                </a>
                                `;
                            });
                            searchResults.innerHTML = html;
                        }
                        searchResults.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }, 300);
        });
        
        // إخفاء نتائج البحث عند فقدان التركيز
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
    
    function markNotificationAsRead(notificationId) {
        fetch(`api/notifications.php?action=mark_read&id=${notificationId}`, {
            method: 'POST'
        });
    }
    
    function showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.id = toastId;
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        document.getElementById('toastContainer').appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // إزالة الـ toast بعد إخفائه
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    }
    
    // التعامل مع الأحداث عبر EventSource (للإشعارات المباشرة)
    if (typeof(EventSource) !== "undefined") {
        const eventSource = new EventSource("api/events.php");
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            switch (data.type) {
                case 'notification':
                    showToast(data.message, 'info');
                    loadNotifications(); // تحديث قائمة الإشعارات
                    break;
                    
                case 'task_assigned':
                    showToast(`تم تعيين مهمة جديدة: ${data.task_title}`, 'warning');
                    loadTasks(); // تحديث قائمة المهام
                    break;
                    
                case 'document_approval':
                    showToast(`يوجد مستند ينتظر موافقتك: ${data.document_title}`, 'primary');
                    loadPendingDocuments(); // تحديث قائمة المستندات
                    break;
                    
                case 'system_alert':
                    showToast(data.message, 'danger');
                    loadUrgentAlerts(); // تحديث التنبيهات العاجلة
                    break;
            }
        };
        
        eventSource.onerror = function() {
            console.error("EventSource failed.");
            eventSource.close();
        };
    }
    </script>