<?php
// جلب البيانات اللازمة للقائمة
$db = Database::getInstance();
$user = (new Auth())->getCurrentUser();

// جلب التصنيفات الرئيسية
$mainCategories = $db->fetchAll("
    SELECT * FROM archive_categories 
    WHERE parent_id IS NULL AND is_active = TRUE 
    ORDER BY sort_order, name_ar
");

// جلد الإحصائيات
$stats = [
    'my_documents' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM financial_documents WHERE created_by = ?",
        [$user['id']]
    )['count'] ?? 0,
    
    'my_tasks' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM tasks 
         WHERE assigned_to = ? AND status IN ('pending', 'in_progress')",
        [$user['id']]
    )['count'] ?? 0,
    
    'recent_scans' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM scanning_logs 
         WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$user['id']]
    )['count'] ?? 0,
];

// تحديد الصفحة الحالية
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- القائمة الجانبية -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <!-- معلومات المستخدم المصغرة -->
        <div class="user-mini-profile">
            <div class="user-avatar">
                <?php if ($user['profile_image']): ?>
                    <img src="<?php echo $user['profile_image']; ?>" 
                         alt="صورة المستخدم" class="rounded-circle">
                <?php else: ?>
                    <span><?php echo mb_substr($user['full_name_ar'], 0, 1, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h6><?php echo $user['full_name_ar']; ?></h6>
                <small class="text-muted"><?php echo $user['position']; ?></small>
                <div class="user-stats">
                    <span class="badge bg-primary" title="مستنداتي">
                        <i class="fas fa-file"></i> <?php echo $stats['my_documents']; ?>
                    </span>
                    <span class="badge bg-warning" title="مهامي">
                        <i class="fas fa-tasks"></i> <?php echo $stats['my_tasks']; ?>
                    </span>
                    <span class="badge bg-success" title="مسح اليوم">
                        <i class="fas fa-scanner"></i> <?php echo $stats['recent_scans']; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- قائمة التنقل الرئيسية -->
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <!-- الرئيسية -->
                <li class="<?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                    <a href="index.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>لوحة التحكم</span>
                    </a>
                </li>
                
                <!-- الأرشيف المالي -->
                <li class="has-submenu <?php echo strpos($currentPage, 'financial') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>الأرشيف المالي</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="financial/payment-vouchers.php">
                                <i class="fas fa-money-check-alt"></i>
                                <span>سندات الصرف</span>
                                <span class="badge bg-success">جديد</span>
                            </a>
                        </li>
                        <li>
                            <a href="financial/receipt-vouchers.php">
                                <i class="fas fa-receipt"></i>
                                <span>سندات القبض</span>
                            </a>
                        </li>
                        <li>
                            <a href="financial/collection-vouchers.php">
                                <i class="fas fa-hand-holding-usd"></i>
                                <span>سندات التحصيل</span>
                            </a>
                        </li>
                        <li>
                            <a href="financial/journal-entries.php">
                                <i class="fas fa-book"></i>
                                <span>قيود اليومية</span>
                            </a>
                        </li>
                        <li>
                            <a href="financial/invoices.php">
                                <i class="fas fa-file-invoice"></i>
                                <span>الفواتير</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="financial/reports.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>التقارير المالية</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- الأرشيف البشري -->
                <li class="has-submenu <?php echo strpos($currentPage, 'hr') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-users"></i>
                        <span>الموارد البشرية</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="hr/employee-files.php">
                                <i class="fas fa-user-folder"></i>
                                <span>ملفات الموظفين</span>
                            </a>
                        </li>
                        <li>
                            <a href="hr/contracts.php">
                                <i class="fas fa-file-contract"></i>
                                <span>عقود العمل</span>
                            </a>
                        </li>
                        <li>
                            <a href="hr/vacations.php">
                                <i class="fas fa-umbrella-beach"></i>
                                <span>الإجازات</span>
                            </a>
                        </li>
                        <li>
                            <a href="hr/evaluations.php">
                                <i class="fas fa-chart-line"></i>
                                <span>تقييمات الأداء</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="hr/reports.php">
                                <i class="fas fa-chart-pie"></i>
                                <span>تقارير الموارد البشرية</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- إدارة المستندات -->
                <li class="has-submenu <?php echo strpos($currentPage, 'documents') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-folder-open"></i>
                        <span>إدارة المستندات</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="documents/add.php">
                                <i class="fas fa-plus-circle"></i>
                                <span>إضافة مستند جديد</span>
                            </a>
                        </li>
                        <li>
                            <a href="documents/list.php">
                                <i class="fas fa-list"></i>
                                <span>عرض جميع المستندات</span>
                            </a>
                        </li>
                        <li>
                            <a href="documents/pending.php">
                                <i class="fas fa-clock"></i>
                                <span>قيد المراجعة</span>
                                <span class="badge bg-warning"><?php echo $pendingApprovals; ?></span>
                            </a>
                        </li>
                        <li>
                            <a href="documents/archived.php">
                                <i class="fas fa-archive"></i>
                                <span>المستندات المؤرشفة</span>
                            </a>
                        </li>
                        <li>
                            <a href="documents/templates.php">
                                <i class="fas fa-file-alt"></i>
                                <span>قوالب المستندات</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="search.php">
                                <i class="fas fa-search"></i>
                                <span>بحث متقدم</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- المسح والرفع -->
                <li class="has-submenu <?php echo strpos($currentPage, 'scanner') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-scanner"></i>
                        <span>المسح والرفع</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="scanner/index.php">
                                <i class="fas fa-scanner"></i>
                                <span>المسح الضوئي</span>
                            </a>
                        </li>
                        <li>
                            <a href="scanner/camera.php">
                                <i class="fas fa-camera"></i>
                                <span>الكاميرا</span>
                            </a>
                        </li>
                        <li>
                            <a href="scanner/batch.php">
                                <i class="fas fa-copy"></i>
                                <span>مسح دفعي</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="scanner/history.php">
                                <i class="fas fa-history"></i>
                                <span>سجل المسح</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- التصنيفات -->
                <li class="<?php echo strpos($currentPage, 'categories') !== false ? 'active' : ''; ?>">
                    <a href="categories/index.php">
                        <i class="fas fa-tags"></i>
                        <span>التصنيفات</span>
                    </a>
                </li>
                
                <!-- المهام -->
                <li class="<?php echo strpos($currentPage, 'tasks') !== false ? 'active' : ''; ?>">
                    <a href="tasks.php">
                        <i class="fas fa-tasks"></i>
                        <span>المهام</span>
                        <span class="badge bg-danger"><?php echo $urgentTasks; ?></span>
                    </a>
                </li>
                
                <!-- التقارير -->
                <li class="has-submenu <?php echo strpos($currentPage, 'reports') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-chart-bar"></i>
                        <span>التقارير والإحصائيات</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="reports/financial.php">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>تقارير مالية</span>
                            </a>
                        </li>
                        <li>
                            <a href="reports/hr.php">
                                <i class="fas fa-users"></i>
                                <span>تقارير بشرية</span>
                            </a>
                        </li>
                        <li>
                            <a href="reports/system.php">
                                <i class="fas fa-chart-line"></i>
                                <span>إحصائيات النظام</span>
                            </a>
                        </li>
                        <li>
                            <a href="reports/custom.php">
                                <i class="fas fa-cogs"></i>
                                <span>تقارير مخصصة</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="print/reports.php">
                                <i class="fas fa-print"></i>
                                <span>طباعة التقارير</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- الطباعة -->
                <li class="has-submenu <?php echo strpos($currentPage, 'print') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-print"></i>
                        <span>الطباعة</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="print/documents.php">
                                <i class="fas fa-file-pdf"></i>
                                <span>طباعة المستندات</span>
                            </a>
                        </li>
                        <li>
                            <a href="print/labels.php">
                                <i class="fas fa-tag"></i>
                                <span>طباعة ملصقات</span>
                            </a>
                        </li>
                        <li>
                            <a href="print/barcodes.php">
                                <i class="fas fa-barcode"></i>
                                <span>طباعة باركود</span>
                            </a>
                        </li>
                        <li>
                            <a href="print/templates.php">
                                <i class="fas fa-file-alt"></i>
                                <span>قوالب الطباعة</span>
                            </a>
                        </li>
                    </ul>
                </li>
                
                <!-- الإعدادات -->
                <?php if ($user['user_type'] === 'super_admin' || $user['user_type'] === 'admin'): ?>
                <li class="has-submenu <?php echo strpos($currentPage, 'settings') !== false ? 'active' : ''; ?>">
                    <a href="#" class="submenu-toggle">
                        <i class="fas fa-cogs"></i>
                        <span>الإعدادات</span>
                        <span class="menu-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                    </a>
                    <ul class="submenu">
                        <li>
                            <a href="settings/general.php">
                                <i class="fas fa-cog"></i>
                                <span>الإعدادات العامة</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings/users.php">
                                <i class="fas fa-users-cog"></i>
                                <span>إدارة المستخدمين</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings/departments.php">
                                <i class="fas fa-building"></i>
                                <span>الأقسام والإدارات</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings/workflows.php">
                                <i class="fas fa-project-diagram"></i>
                                <span>سير العمل</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings/backup.php">
                                <i class="fas fa-database"></i>
                                <span>النسخ الاحتياطي</span>
                            </a>
                        </li>
                        <li class="divider"></li>
                        <li>
                            <a href="settings/security.php">
                                <i class="fas fa-shield-alt"></i>
                                <span>الأمان</span>
                            </a>
                        </li>
                        <li>
                            <a href="settings/logs.php">
                                <i class="fas fa-clipboard-list"></i>
                                <span>سجلات النظام</span>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- التصنيفات السريعة -->
        <div class="sidebar-section">
            <h6 class="sidebar-title">
                <i class="fas fa-folder"></i>
                التصنيفات السريعة
            </h6>
            <div class="quick-categories">
                <?php foreach ($mainCategories as $category): ?>
                    <a href="categories/view.php?id=<?php echo $category['id']; ?>" 
                       class="quick-category-item">
                        <span class="category-color" style="background: <?php echo $category['color']; ?>"></span>
                        <span class="category-name"><?php echo $category['name_ar']; ?></span>
                        <span class="category-count">
                            <?php 
                            $count = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM financial_documents WHERE category_id = ?",
                                [$category['id']]
                            )['count'] ?? 0;
                            echo $count;
                            ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- الإجراءات السريعة -->
        <div class="sidebar-section">
            <h6 class="sidebar-title">
                <i class="fas fa-bolt"></i>
                إجراءات سريعة
            </h6>
            <div class="quick-actions">
                <button class="btn btn-sm btn-primary w-100 mb-2" onclick="window.location.href='documents/add.php'">
                    <i class="fas fa-plus me-2"></i>إضافة مستند
                </button>
                <button class="btn btn-sm btn-success w-100 mb-2" onclick="window.location.href='scanner/index.php'">
                    <i class="fas fa-scanner me-2"></i>مسح ضوئي
                </button>
                <button class="btn btn-sm btn-info w-100 mb-2" onclick="window.location.href='search.php'">
                    <i class="fas fa-search me-2"></i>بحث متقدم
                </button>
                <button class="btn btn-sm btn-warning w-100" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>طباعة
                </button>
            </div>
        </div>
        
        <!-- حالة النظام -->
        <div class="sidebar-section">
            <h6 class="sidebar-title">
                <i class="fas fa-server"></i>
                حالة النظام
            </h6>
            <div class="system-status">
                <div class="status-item">
                    <span class="status-label">مساحة التخزين:</span>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: 65%"></div>
                    </div>
                    <span class="status-value">65%</span>
                </div>
                <div class="status-item">
                    <span class="status-label">المستندات النشطة:</span>
                    <span class="status-value"><?php echo number_format($stats['total_documents'] ?? 0); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">المستخدمون النشطون:</span>
                    <span class="status-value"><?php echo number_format($stats['total_users'] ?? 0); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label">آخر نسخة احتياطية:</span>
                    <span class="status-value">أمس</span>
                </div>
            </div>
        </div>
        
        <!-- معلومات النظام -->
        <div classsidebar-footer">
            <div class="system-info">
                <small class="text-muted d-block">
                    <i class="fas fa-code-branch me-1"></i>
                    الإصدار 1.0.0
                </small>
                <small class="text-muted d-block">
                    <i class="fas fa-database me-1"></i>
                    <?php echo date('Y-m-d H:i'); ?>
                </small>
            </div>
        </div>
    </div>
</aside>

<!-- زر إغلاق/فتح القائمة للموبايل -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
.sidebar {
    position: fixed;
    top: var(--header-height);
    right: 0;
    bottom: 0;
    width: 280px;
    background: white;
    border-left: 1px solid #e0e0e0;
    overflow-y: auto;
    transition: transform 0.3s ease;
    z-index: 1000;
    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
}

.sidebar.collapsed {
    transform: translateX(100%);
}

.sidebar-inner {
    padding: 20px 0;
}

.user-mini-profile {
    padding: 0 20px 20px;
    border-bottom: 1px solid #e0e0e0;
    margin-bottom: 20px;
    text-align: center;
}

.user-mini-profile .user-avatar {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    margin: 0 auto 15px;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    overflow: hidden;
}

.user-mini-profile .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-mini-profile .user-info h6 {
    margin: 0 0 5px;
    font-weight: 600;
    color: #333;
}

.user-mini-profile .user-stats {
    display: flex;
    gap: 5px;
    justify-content: center;
    margin-top: 10px;
}

.user-mini-profile .badge {
    font-size: 0.7rem;
    padding: 3px 8px;
}

.sidebar-nav {
    margin-bottom: 20px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sidebar-menu > li {
    position: relative;
}

.sidebar-menu > li > a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #555;
    text-decoration: none;
    transition: all 0.3s;
    border-right: 3px solid transparent;
    position: relative;
}

.sidebar-menu > li > a:hover {
    background: #f8f9fa;
    color: var(--secondary-color);
    border-right-color: var(--secondary-color);
}

.sidebar-menu > li.active > a {
    background: linear-gradient(90deg, rgba(52,152,219,0.1) 0%, rgba(52,152,219,0.05) 100%);
    color: var(--secondary-color);
    border-right-color: var(--secondary-color);
    font-weight: 600;
}

.sidebar-menu > li > a i {
    width: 25px;
    text-align: center;
    margin-left: 10px;
    font-size: 1.1rem;
}

.sidebar-menu > li > a span {
    flex: 1;
}

.sidebar-menu > li > a .menu-arrow {
    transition: transform 0.3s;
}

.sidebar-menu > li.active.has-submenu > a .menu-arrow {
    transform: rotate(180deg);
}

.sidebar-menu .badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    margin-right: 5px;
}

/* القوائم الفرعية */
.submenu {
    list-style: none;
    padding: 0;
    margin: 0;
    background: #f8f9fa;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.sidebar-menu > li.active.has-submenu .submenu {
    max-height: 500px;
}

.submenu li {
    position: relative;
}

.submenu li a {
    display: flex;
    align-items: center;
    padding: 10px 20px 10px 50px;
    color: #666;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.9rem;
}

.submenu li a:hover {
    background: #e9ecef;
    color: var(--secondary-color);
    padding-right: 55px;
}

.submenu li a i {
    width: 20px;
    text-align: center;
    margin-left: 10px;
    font-size: 0.9rem;
}

.submenu li.divider {
    height: 1px;
    background: #dee2e6;
    margin: 5px 20px;
}

/* الأقسام */
.sidebar-section {
    padding: 0 20px;
    margin-bottom: 25px;
}

.sidebar-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.sidebar-title i {
    margin-left: 5px;
}

/* التصنيفات السريعة */
.quick-categories {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
}

.quick-category-item {
    display: flex;
    align-items: center;
    padding: 8px 10px;
    color: #555;
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s;
    margin-bottom: 5px;
}

.quick-category-item:hover {
    background: white;
    color: var(--secondary-color);
    transform: translateX(-5px);
}

.quick-category-item:last-child {
    margin-bottom: 0;
}

.quick-category-item .category-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-left: 10px;
}

.quick-category-item .category-name {
    flex: 1;
    font-size: 0.85rem;
}

.quick-category-item .category-count {
    background: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    color: #666;
}

/* الإجراءات السريعة */
.quick-actions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

.quick-actions .btn {
    font-size: 0.85rem;
    padding: 8px 12px;
}

/* حالة النظام */
.system-status {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.status-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.status-item:last-child {
    margin-bottom: 0;
}

.status-label {
    flex: 1;
    font-size: 0.85rem;
    color: #666;
}

.status-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
    margin-right: 10px;
}

.status-item .progress {
    flex: 2;
    margin: 0 10px;
    background: #e9ecef;
}

/* معلومات النظام */
.sidebar-footer {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    margin-top: 20px;
}

.system-info {
    text-align: center;
}

.system-info small {
    display: block;
    margin-bottom: 3px;
}

/* طبقة التغطية للموبايل */
.sidebar-overlay {
    position: fixed;
    top: var(--header-height);
    right: 0;
    left: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none;
}

/* التجاوب */
@media (max-width: 992px) {
    .sidebar {
        transform: translateX(100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-overlay.show {
        display: block;
    }
    
    .main-content {
        margin-right: 0 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    
    // تبديل القائمة الجانبية
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            sidebarOverlay.classList.toggle('show');
        });
    }
    
    // إغلاق القائمة عند النقر على التغطية
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            this.classList.remove('show');
        });
    }
    
    // تبديل القوائم الفرعية
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.parentElement;
            
            // إغلاق جميع القوائم الفرعية الأخرى
            document.querySelectorAll('.sidebar-menu > li').forEach(li => {
                if (li !== parentLi) {
                    li.classList.remove('active');
                }
            });
            
            // تبديل القائمة الحالية
            parentLi.classList.toggle('active');
        });
    });
    
    // إغلاق القائمة عند النقر على عنصر
    document.querySelectorAll('.sidebar-menu a:not(.submenu-toggle)').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            }
        });
    });
    
    // حفظ حالة القائمة في localStorage
    const sidebarState = localStorage.getItem('sidebarState');
    if (sidebarState === 'collapsed' && window.innerWidth >= 992) {
        sidebar.classList.add('collapsed');
        document.querySelector('.main-content').classList.add('expanded');
    }
    
    // زر تبديل القائمة في الهيدر (للشاشات الكبيرة)
    const sidebarCollapseBtn = document.createElement('button');
    sidebarCollapseBtn.className = 'btn btn-sm btn-outline-light d-none d-lg-block me-3';
    sidebarCollapseBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
    sidebarCollapseBtn.title = 'طي/فتح القائمة';
    sidebarCollapseBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
        
        // حفظ الحالة
        if (sidebar.classList.contains('collapsed')) {
            localStorage.setItem('sidebarState', 'collapsed');
        } else {
            localStorage.setItem('sidebarState', 'expanded');
        }
    });
    
    // إضافة الزر إلى الهيدر
    document.querySelector('.header-left').prepend(sidebarCollapseBtn);
});
</script>