<?php
// config/constants.php
require_once 'config/constants.php';

// تحميل الفئات
require_once 'classes/Database.class.php';
require_once 'classes/Auth.class.php';

// بدء الجلسة
session_start();

// التحقق من تسجيل الدخول
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// جلب بيانات المستخدم
$user = $auth->getCurrentUser();

// جلب إحصائيات النظام
$db = Database::getInstance();
$stats = [
    'total_documents' => $db->fetchOne("SELECT COUNT(*) as count FROM financial_documents WHERE status != 'deleted'")['count'] ?? 0,
    'pending_reviews' => $db->fetchOne("SELECT COUNT(*) as count FROM financial_documents WHERE status = 'pending_approval'")['count'] ?? 0,
    'today_scans' => $db->fetchOne("SELECT COUNT(*) as count FROM scanning_logs WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
    'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?? 0
];

// جلب النشاطات الأخيرة
$activities = $db->fetchAll("
    SELECT al.*, u.full_name_ar, u.username 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام الأرشيف المتكامل</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/fontawesome.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body>
    <!-- الهيدر الرئيسي -->
    <?php include 'includes/header.php'; ?>
    
    <!-- السايدبار -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- المحتوى الرئيسي -->
    <div class="main-content">
        <!-- شريط التصفّح -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li class="breadcrumb-item active" aria-current="page">لوحة التحكم</li>
            </ol>
        </nav>
        
        <!-- صفحة العنوان -->
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-tachometer-alt me-2"></i>لوحة التحكم</h2>
                    <p class="text-muted mb-0">مرحباً <?php echo $user['full_name_ar']; ?>، هذه نظرة عامة على نظام الأرشيف</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> تحديث
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> طباعة التقرير
                    </button>
                </div>
            </div>
        </div>
        
        <!-- إحصائيات سريعة -->
        <div class="quick-stats mb-5">
            <div class="stat-box fade-in">
                <div class="stat-box-icon" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-box-content">
                    <h3><?php echo number_format($stats['total_documents']); ?></h3>
                    <p>إجمالي المستندات</p>
                </div>
            </div>
            
            <div class="stat-box fade-in" style="animation-delay: 0.1s;">
                <div class="stat-box-icon" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-box-content">
                    <h3><?php echo number_format($stats['pending_reviews']); ?></h3>
                    <p>قيد المراجعة</p>
                </div>
            </div>
            
            <div class="stat-box fade-in" style="animation-delay: 0.2s;">
                <div class="stat-box-icon" style="background: linear-gradient(135deg, #27ae60 0%, #219653 100%);">
                    <i class="fas fa-scanner"></i>
                </div>
                <div class="stat-box-content">
                    <h3><?php echo number_format($stats['today_scans']); ?></h3>
                    <p>مسح ضوئي اليوم</p>
                </div>
            </div>
            
            <div class="stat-box fade-in" style="animation-delay: 0.3s;">
                <div class="stat-box-icon" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-box-content">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>مستخدم نشط</p>
                </div>
            </div>
        </div>
        
        <!-- الصف الأول: الرسوم البيانية والنشاطات -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <!-- المستندات المالية الشهرية -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>المستندات المالية الشهرية</h5>
                        <select class="form-select form-select-sm w-auto" id="chartPeriod">
                            <option value="month">هذا الشهر</option>
                            <option value="quarter">هذا الربع</option>
                            <option value="year">هذه السنة</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <canvas id="financialChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- النشاطات الأخيرة -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>آخر النشاطات</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="activity-timeline">
                            <?php if (empty($activities)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-3"></i>
                                    <p>لا توجد نشاطات مؤخراً</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activities as $index => $activity): ?>
                                    <div class="activity-item slide-in" style="animation-delay: <?php echo $index * 0.05; ?>s;">
                                        <div class="activity-icon" style="background: <?php echo getActivityColor($activity['action_type']); ?>;">
                                            <i class="fas <?php echo getActivityIcon($activity['action_type']); ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <h6><?php echo $activity['full_name_ar'] ?? 'نظام'; ?></h6>
                                            <p><?php echo $activity['action_details']; ?></p>
                                            <div class="activity-time">
                                                <i class="far fa-clock"></i>
                                                <?php echo time_elapsed_string($activity['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="activities.php" class="btn btn-sm btn-outline-primary">عرض كل النشاطات</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الصف الثاني: المهام والمستندات -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <!-- المهام العاجلة -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>مهامي العاجلة</h5>
                        <span class="badge bg-danger"><?php echo $pendingTasks = getPendingTasksCount($user['id']); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="task-list">
                            <?php $tasks = getPendingTasks($user['id']); ?>
                            <?php if (empty($tasks)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-3"></i>
                                    <p>لا توجد مهام عاجلة</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                    <div class="task-item">
                                        <div class="task-checkbox">
                                            <input type="checkbox" id="task-<?php echo $task['id']; ?>" 
                                                   onchange="completeTask(<?php echo $task['id']; ?>)">
                                        </div>
                                        <div class="task-content">
                                            <h6><?php echo $task['title']; ?></h6>
                                            <p><?php echo truncateText($task['description'], 50); ?></p>
                                            <small class="text-muted">موعد التسليم: <?php echo $task['due_date']; ?></small>
                                        </div>
                                        <div class="task-priority priority-<?php echo $task['priority']; ?>">
                                            <?php echo getPriorityText($task['priority']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="tasks.php" class="btn btn-sm btn-outline-primary">عرض جميع المهام</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- مستندات قيد المراجعة -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-file-signature me-2"></i>مستندات تحتاج موافقتي</h5>
                        <span class="badge bg-warning"><?php echo $pendingApprovals = getPendingApprovalsCount($user['id']); ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="review-documents">
                            <?php $documents = getPendingApprovals($user['id']); ?>
                            <?php if (empty($documents)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-file-check fa-2x mb-3"></i>
                                    <p>لا توجد مستندات تنتظر موافقتك</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <div class="document-item">
                                        <div class="document-icon" 
                                             style="background: linear-gradient(135deg, <?php echo getDocumentTypeColor($doc['document_type']); ?>);">
                                            <i class="fas <?php echo getDocumentTypeIcon($doc['document_type']); ?>"></i>
                                        </div>
                                        <div class="document-info">
                                            <h6><?php echo $doc['title_ar']; ?></h6>
                                            <div class="document-meta">
                                                <span><i class="far fa-calendar"></i> <?php echo $doc['document_date']; ?></span>
                                                <span><i class="fas fa-money-bill-wave"></i> <?php echo number_format($doc['amount'], 2); ?> ريال</span>
                                                <span><i class="far fa-user"></i> <?php echo $doc['from_party_name']; ?></span>
                                            </div>
                                        </div>
                                        <div class="document-status status-pending">
                                            قيد المراجعة
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer text-center">
                        <a href="documents.php?status=pending_approval" class="btn btn-sm btn-outline-primary">عرض جميع المستندات</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الصف الثالث: التقويم والأدوات السريعة -->
        <div class="row">
            <div class="col-lg-8">
                <!-- التقويم -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>تقويم الأرشيف</h5>
                    </div>
                    <div class="card-body">
                        <div id="archiveCalendar"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- الأدوات السريعة -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>أدوات سريعة</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-tools">
                            <div class="tool-item" onclick="window.location.href='scanner.php'">
                                <i class="fas fa-scanner"></i>
                                <h6>المسح الضوئي</h6>
                            </div>
                            <div class="tool-item" onclick="window.location.href='documents/add.php'">
                                <i class="fas fa-file-upload"></i>
                                <h6>رفع مستند</h6>
                            </div>
                            <div class="tool-item" onclick="window.location.href='search.php'">
                                <i class="fas fa-search"></i>
                                <h6>بحث متقدم</h6>
                            </div>
                            <div class="tool-item" onclick="window.location.href='reports/financial.php'">
                                <i class="fas fa-chart-pie"></i>
                                <h6>تقارير مالية</h6>
                            </div>
                            <div class="tool-item" onclick="window.location.href='print/labels.php'">
                                <i class="fas fa-tags"></i>
                                <h6>طباعة ملصقات</h6>
                            </div>
                            <div class="tool-item" onclick="window.location.href='backup.php'">
                                <i class="fas fa-database"></i>
                                <h6>نسخ احتياطي</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- الفوتر -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- JavaScript -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/fullcalendar.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/dashboard.js"></script>
    
    <script>
    // تهيئة الرسوم البيانية
    const financialCtx = document.getElementById('financialChart').getContext('2d');
    const financialChart = new Chart(financialCtx, {
        type: 'bar',
        data: {
            labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
            datasets: [{
                label: 'سندات الصرف',
                data: [120000, 190000, 150000, 180000, 160000, 170000],
                backgroundColor: 'rgba(52, 152, 219, 0.8)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1
            }, {
                label: 'سندات القبض',
                data: [80000, 110000, 90000, 120000, 100000, 110000],
                backgroundColor: 'rgba(46, 204, 113, 0.8)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('ar-SA') + ' ر.س';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    rtl: true,
                    labels: {
                        font: {
                            family: 'Noto Kufi Arabic'
                        }
                    }
                },
                tooltip: {
                    rtl: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y.toLocaleString('ar-SA') + ' ر.س';
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    // تحديث الرسم البياني عند تغيير الفترة
    document.getElementById('chartPeriod').addEventListener('change', function() {
        // هنا نطلب البيانات الجديدة من الخادم
        fetch(`api/stats.php?period=${this.value}`)
            .then(response => response.json())
            .then(data => {
                financialChart.data.labels = data.labels;
                financialChart.data.datasets[0].data = data.payments;
                financialChart.data.datasets[1].data = data.receipts;
                financialChart.update();
            });
    });
    
    // تهيئة التقويم
    $(document).ready(function() {
        $('#archiveCalendar').fullCalendar({
            locale: 'ar',
            rtl: true,
            header: {
                right: 'today prev,next',
                center: 'title',
                left: 'month,agendaWeek,agendaDay'
            },
            events: 'api/calendar.php',
            eventClick: function(event) {
                if (event.url) {
                    window.open(event.url, '_blank');
                    return false;
                }
            }
        });
    });
    
    // وظائف المساعدة
    function refreshDashboard() {
        location.reload();
    }
    
    function completeTask(taskId) {
        fetch(`api/tasks/complete.php?id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('حدث خطأ في إكمال المهمة');
                }
            });
    }
    </script>
</body>
</html>

<?php
// دالات المساعدة
function getActivityColor($actionType) {
    $colors = [
        'login' => '#3498db',
        'logout' => '#e74c3c',
        'document_create' => '#27ae60',
        'document_update' => '#f39c12',
        'document_delete' => '#e74c3c',
        'scan' => '#9b59b6',
        'print' => '#34495e'
    ];
    return $colors[$actionType] ?? '#95a5a6';
}

function getActivityIcon($actionType) {
    $icons = [
        'login' => 'fa-sign-in-alt',
        'logout' => 'fa-sign-out-alt',
        'document_create' => 'fa-file-import',
        'document_update' => 'fa-edit',
        'document_delete' => 'fa-trash',
        'scan' => 'fa-scanner',
        'print' => 'fa-print'
    ];
    return $icons[$actionType] ?? 'fa-info-circle';
}

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

function getPendingTasksCount($userId) {
    $db = Database::getInstance();
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM tasks 
         WHERE assigned_to = ? AND status IN ('pending', 'in_progress') 
         AND due_date >= CURDATE()",
        [$userId]
    );
    return $result['count'] ?? 0;
}

function getPendingTasks($userId) {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT * FROM tasks 
         WHERE assigned_to = ? AND status IN ('pending', 'in_progress') 
         AND due_date >= CURDATE() 
         ORDER BY priority DESC, due_date ASC 
         LIMIT 5",
        [$userId]
    );
}

function getPendingApprovalsCount($userId) {
    $db = Database::getInstance();
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM financial_documents 
         WHERE status = 'pending_approval' 
         AND (approved_by = ? OR reviewed_by = ? OR prepared_by = ?)",
        [$userId, $userId, $userId]
    );
    return $result['count'] ?? 0;
}

function getPendingApprovals($userId) {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT * FROM financial_documents 
         WHERE status = 'pending_approval' 
         AND (approved_by = ? OR reviewed_by = ? OR prepared_by = ?) 
         ORDER BY document_date DESC 
         LIMIT 5",
        [$userId, $userId, $userId]
    );
}

function truncateText($text, $length = 100) {
    if (strlen($text) > $length) {
        return substr($text, 0, $length) . '...';
    }
    return $text;
}

function getPriorityText($priority) {
    $texts = [
        'low' => 'منخفض',
        'medium' => 'متوسط',
        'high' => 'عالٍ',
        'urgent' => 'عاجل'
    ];
    return $texts[$priority] ?? 'غير محدد';
}

function getDocumentTypeColor($type) {
    $colors = [
        'PAYMENT_VOUCHER' => '#27ae60, #219653',
        'RECEIPT_VOUCHER' => '#3498db, #2980b9',
        'COLLECTION_VOUCHER' => '#9b59b6, #8e44ad',
        'JOURNAL_ENTRY' => '#f39c12, #e67e22'
    ];
    return $colors[$type] ?? '#95a5a6, #7f8c8d';
}

function getDocumentTypeIcon($type) {
    $icons = [
        'PAYMENT_VOUCHER' => 'fa-money-check-alt',
        'RECEIPT_VOUCHER' => 'fa-receipt',
        'COLLECTION_VOUCHER' => 'fa-hand-holding-usd',
        'JOURNAL_ENTRY' => 'fa-book',
        'INVOICE' => 'fa-file-invoice',
        'CONTRACT' => 'fa-file-contract'
    ];
    return $icons[$type] ?? 'fa-file';
}
?>