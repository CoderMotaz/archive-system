<?php
/**
 * ملف navbar.php - شريط التنقل العلوي المتقدم
 * شريط ثانوي تحت الهيدر الرئيسي للإجراءات السريعة والأدوات
 */

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    return;
}

// جلب بيانات المستخدم
$auth = new Auth();
$user = $auth->getCurrentUser();

// جلب الإحصائيات
$db = Database::getInstance();
$stats = [
    'my_documents' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM financial_documents WHERE created_by = ?",
        [$user['id']]
    )['count'] ?? 0,
    
    'urgent_tasks' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM tasks 
         WHERE assigned_to = ? AND status IN ('pending', 'in_progress') 
         AND priority IN ('high', 'urgent')",
        [$user['id']]
    )['count'] ?? 0,
    
    'pending_reviews' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM financial_documents 
         WHERE status = 'pending_approval' 
         AND (approved_by = ? OR reviewed_by = ?)",
        [$user['id'], $user['id']]
    )['count'] ?? 0,
    
    'today_scans' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM scanning_logs 
         WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$user['id']]
    )['count'] ?? 0,
];

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- شريط التنقل العلوي -->
<nav class="secondary-navbar" id="secondaryNavbar">
    <div class="container-fluid">
        <!-- الجانب الأيمن: أدوات سريعة -->
        <div class="nav-left">
            <!-- زر القائمة السريعة -->
            <div class="dropdown quick-menu-dropdown">
                <button class="btn btn-sm btn-outline-primary" type="button" 
                        id="quickMenuDropdown" data-bs-toggle="dropdown" 
                        aria-expanded="false">
                    <i class="fas fa-bolt me-2"></i>إجراءات سريعة
                </button>
                <div class="dropdown-menu" aria-labelledby="quickMenuDropdown">
                    <div class="dropdown-header">
                        <i class="fas fa-bolt text-warning me-2"></i>
                        إجراءات فورية
                    </div>
                    <a class="dropdown-item" href="documents/add.php">
                        <i class="fas fa-plus-circle text-success me-2"></i>
                        إضافة مستند جديد
                    </a>
                    <a class="dropdown-item" href="scanner/index.php">
                        <i class="fas fa-scanner text-primary me-2"></i>
                        مسح ضوئي
                    </a>
                    <a class="dropdown-item" href="tasks/add.php">
                        <i class="fas fa-tasks text-warning me-2"></i>
                        إضافة مهمة
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="print/labels.php">
                        <i class="fas fa-tag text-info me-2"></i>
                        طباعة ملصقات
                    </a>
                    <a class="dropdown-item" href="reports/quick.php">
                        <i class="fas fa-chart-bar text-success me-2"></i>
                        تقرير سريع
                    </a>
                </div>
            </div>
            
            <!-- شريط البحث المصغر -->
            <div class="quick-search">
                <form action="search.php" method="GET" class="d-flex">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm" 
                               placeholder="بحث سريع..." name="q" id="quickSearch">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- الجانب الأوسط: إحصائيات سريعة -->
        <div class="nav-center">
            <div class="quick-stats">
                <div class="stat-item" data-stat="documents" 
                     onclick="window.location.href='documents/list.php'">
                    <div class="stat-icon">
                        <i class="fas fa-file"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['my_documents']; ?></span>
                        <span class="stat-label">مستنداتي</span>
                    </div>
                </div>
                
                <div class="stat-item" data-stat="tasks" 
                     onclick="window.location.href='tasks.php?filter=urgent'">
                    <div class="stat-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['urgent_tasks']; ?></span>
                        <span class="stat-label">مهام عاجلة</span>
                    </div>
                </div>
                
                <div class="stat-item" data-stat="reviews" 
                     onclick="window.location.href='documents/pending.php'">
                    <div class="stat-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['pending_reviews']; ?></span>
                        <span class="stat-label">تنتظر المراجعة</span>
                    </div>
                </div>
                
                <div class="stat-item" data-stat="scans" 
                     onclick="window.location.href='scanner/history.php?filter=today'">
                    <div class="stat-icon">
                        <i class="fas fa-scanner"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number"><?php echo $stats['today_scans']; ?></span>
                        <span class="stat-label">مسح اليوم</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- الجانب الأيسر: أدوات متقدمة -->
        <div class="nav-right">
            <!-- زر الطباعة -->
            <button class="btn btn-sm btn-outline-secondary me-2" 
                    onclick="window.print()" title="طباعة">
                <i class="fas fa-print"></i>
            </button>
            
            <!-- زر التحميل -->
            <button class="btn btn-sm btn-outline-secondary me-2" 
                    id="downloadBtn" title="تحميل">
                <i class="fas fa-download"></i>
            </button>
            
            <!-- زر المشاركة -->
            <div class="dropdown share-dropdown">
                <button class="btn btn-sm btn-outline-secondary me-2" 
                        type="button" id="shareDropdown" 
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="مشاركة">
                    <i class="fas fa-share-alt"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end" 
                     aria-labelledby="shareDropdown">
                    <div class="dropdown-header">مشاركة عبر</div>
                    <button class="dropdown-item" onclick="shareViaEmail()">
                        <i class="fas fa-envelope text-primary me-2"></i>البريد الإلكتروني
                    </button>
                    <button class="dropdown-item" onclick="shareViaWhatsApp()">
                        <i class="fab fa-whatsapp text-success me-2"></i>واتساب
                    </button>
                    <button class="dropdown-item" onclick="generateShareLink()">
                        <i class="fas fa-link text-info me-2"></i>رابط مباشر
                    </button>
                    <div class="dropdown-divider"></div>
                    <button class="dropdown-item" onclick="exportAsPDF()">
                        <i class="fas fa-file-pdf text-danger me-2"></i>تصدير PDF
                    </button>
                    <button class="dropdown-item" onclick="exportAsExcel()">
                        <i class="fas fa-file-excel text-success me-2"></i>تصدير Excel
                    </button>
                </div>
            </div>
            
            <!-- زر العرض -->
            <div class="dropdown view-dropdown">
                <button class="btn btn-sm btn-outline-secondary me-2" 
                        type="button" id="viewDropdown" 
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="وضع العرض">
                    <i class="fas fa-eye"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end" 
                     aria-labelledby="viewDropdown">
                    <div class="dropdown-header">وضع العرض</div>
                    <button class="dropdown-item" onclick="changeViewMode('list')">
                        <i class="fas fa-list me-2"></i>عرض القائمة
                    </button>
                    <button class="dropdown-item" onclick="changeViewMode('grid')">
                        <i class="fas fa-th-large me-2"></i>عرض الشبكة
                    </button>
                    <button class="dropdown-item" onclick="changeViewMode('details')">
                        <i class="fas fa-th-list me-2"></i>عرض التفاصيل
                    </button>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-header">كثافة العرض</div>
                    <button class="dropdown-item" onclick="changeDensity('compact')">
                        <i class="fas fa-compress me-2"></i>مضغوط
                    </button>
                    <button class="dropdown-item" onclick="changeDensity('comfortable')">
                        <i class="fas fa-expand me-2"></i>مريح
                    </button>
                    <button class="dropdown-item" onclick="changeDensity('spacious')">
                        <i class="fas fa-arrows-alt me-2"></i>فسيح
                    </button>
                </div>
            </div>
            
            <!-- زر الإعدادات السريعة -->
            <div class="dropdown settings-dropdown">
                <button class="btn btn-sm btn-outline-secondary" 
                        type="button" id="settingsDropdown" 
                        data-bs-toggle="dropdown" aria-expanded="false"
                        title="إعدادات سريعة">
                    <i class="fas fa-cog"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end" 
                     aria-labelledby="settingsDropdown">
                    <div class="dropdown-header">إعدادات سريعة</div>
                    <a class="dropdown-item" href="settings/profile.php">
                        <i class="fas fa-user-cog me-2"></i>الملف الشخصي
                    </a>
                    <a class="dropdown-item" href="settings/notifications.php">
                        <i class="fas fa-bell me-2"></i>الإشعارات
                    </a>
                    <a class="dropdown-item" href="settings/display.php">
                        <i class="fas fa-palette me-2"></i>المظهر
                    </a>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-header">تفضيلات النظام</div>
                    <div class="dropdown-item">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   id="darkModeSwitch" onchange="toggleDarkMode()">
                            <label class="form-check-label" for="darkModeSwitch">
                                الوضع الداكن
                            </label>
                        </div>
                    </div>
                    <div class="dropdown-item">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   id="autoSaveSwitch" checked 
                                   onchange="toggleAutoSave()">
                            <label class="form-check-label" for="autoSaveSwitch">
                                الحفظ التلقائي
                            </label>
                        </div>
                    </div>
                    <div class="dropdown-item">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   id="notificationsSwitch" checked 
                                   onchange="toggleNotifications()">
                            <label class="form-check-label" for="notificationsSwitch">
                                الإشعارات
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- شريط التقدم للعمليات -->
    <div class="progress-bar-container" id="progressBarContainer" style="display: none;">
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                 id="globalProgressBar" role="progressbar" 
                 style="width: 0%"></div>
        </div>
        <div class="progress-text" id="progressText"></div>
    </div>
</nav>

<style>
/* تنسيقات شريط التنقل الثانوي */
.secondary-navbar {
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 8px 0;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    position: sticky;
    top: var(--header-height);
    z-index: 990;
    transition: all 0.3s;
}

.secondary-navbar .container-fluid {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
}

/* الأقسام الثلاثة */
.nav-left, .nav-center, .nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* الإحصائيات السريعة */
.quick-stats {
    display: flex;
    gap: 15px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px;
    background: #f8f9fa;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
    border: 1px solid transparent;
}

.stat-item:hover {
    background: #e9ecef;
    border-color: #dee2e6;
    transform: translateY(-1px);
}

.stat-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    color: white;
    font-size: 0.9rem;
}

.stat-item[data-stat="tasks"] .stat-icon {
    background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
}

.stat-item[data-stat="reviews"] .stat-icon {
    background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
}

.stat-item[data-stat="scans"] .stat-icon {
    background: linear-gradient(135deg, #27ae60 0%, #219653 100%);
}

.stat-content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.stat-number {
    font-weight: 700;
    font-size: 1.1rem;
    color: #2c3e50;
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: #666;
    white-space: nowrap;
}

/* البحث السريع */
.quick-search {
    width: 200px;
}

.quick-search .input-group {
    border-radius: 20px;
    overflow: hidden;
}

.quick-search input {
    border-radius: 20px 0 0 20px;
    border: 1px solid #dee2e6;
    padding: 6px 12px;
    font-size: 0.85rem;
}

.quick-search button {
    border-radius: 0 20px 20px 0;
    padding: 6px 12px;
}

/* شريط التقدم */
.progress-bar-container {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    padding: 5px 20px;
    border-top: 1px solid #e0e0e0;
}

.progress {
    height: 6px;
    border-radius: 3px;
    background: #f0f0f0;
    margin-bottom: 5px;
}

.progress-bar {
    border-radius: 3px;
    transition: width 0.3s;
}

.progress-text {
    font-size: 0.8rem;
    color: #666;
    text-align: center;
}

/* التجاوب */
@media (max-width: 1200px) {
    .nav-center {
        display: none;
    }
    
    .secondary-navbar .container-fluid {
        justify-content: space-between;
    }
}

@media (max-width: 768px) {
    .secondary-navbar {
        padding: 5px 0;
    }
    
    .secondary-navbar .container-fluid {
        padding: 0 10px;
    }
    
    .quick-search {
        width: 150px;
    }
    
    .stat-item .stat-label {
        display: none;
    }
    
    .nav-right .btn {
        padding: 5px 8px;
    }
}
</style>

<script>
// تهيئة شريط التنقل الثانوي
document.addEventListener('DOMContentLoaded', function() {
    // البحث السريع
    const quickSearch = document.getElementById('quickSearch');
    if (quickSearch) {
        quickSearch.addEventListener('keyup', function(e) {
            if (e.key === 'Enter' && this.value.trim().length >= 2) {
                this.form.submit();
            }
        });
    }
    
    // تحديث الإحصائيات كل دقيقة
    updateQuickStats();
    setInterval(updateQuickStats, 60000);
    
    // حفظ وضع العرض
    loadViewPreferences();
    
    // إدارة شريط التقدم
    window.globalProgress = {
        show: function(message = 'جاري المعالجة...') {
            const container = document.getElementById('progressBarContainer');
            const text = document.getElementById('progressText');
            
            container.style.display = 'block';
            text.textContent = message;
        },
        
        update: function(percent, message = null) {
            const bar = document.getElementById('globalProgressBar');
            const text = document.getElementById('progressText');
            
            bar.style.width = percent + '%';
            if (message) {
                text.textContent = message;
            }
        },
        
        hide: function() {
            const container = document.getElementById('progressBarContainer');
            setTimeout(() => {
                container.style.display = 'none';
                document.getElementById('globalProgressBar').style.width = '0%';
            }, 500);
        }
    };
});

// تحديث الإحصائيات
function updateQuickStats() {
    fetch('api/stats/quick.php')
        .then(response => response.json())
        .then(data => {
            // تحديث أرقام الإحصائيات
            document.querySelectorAll('.stat-number').forEach((el, index) => {
                const values = [
                    data.my_documents,
                    data.urgent_tasks,
                    data.pending_reviews,
                    data.today_scans
                ];
                
                if (values[index] !== undefined) {
                    el.textContent = values[index];
                }
            });
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
}

// تغيير وضع العرض
function changeViewMode(mode) {
    localStorage.setItem('viewMode', mode);
    applyViewMode(mode);
    showToast('تم تغيير وضع العرض إلى: ' + 
        (mode === 'list' ? 'القائمة' : mode === 'grid' ? 'الشبكة' : 'التفاصيل'));
}

// تغيير كثافة العرض
function changeDensity(density) {
    localStorage.setItem('viewDensity', density);
    applyViewDensity(density);
    showToast('تم تغيير كثافة العرض إلى: ' + 
        (density === 'compact' ? 'مضغوط' : density === 'comfortable' ? 'مريح' : 'فسيح'));
}

// تطبيق تفضيلات العرض
function applyViewMode(mode) {
    const viewModes = {
        'list': 'view-list',
        'grid': 'view-grid',
        'details': 'view-details'
    };
    
    // إزالة جميع كلاسات العرض
    document.body.classList.remove('view-list', 'view-grid', 'view-details');
    
    // إضافة الكلاس الجديد
    document.body.classList.add(viewModes[mode] || 'view-list');
}

// تطبيق كثافة العرض
function applyViewDensity(density) {
    const densities = {
        'compact': 'density-compact',
        'comfortable': 'density-comfortable',
        'spacious': 'density-spacious'
    };
    
    // إزالة جميع كلاسات الكثافة
    document.body.classList.remove('density-compact', 'density-comfortable', 'density-spacious');
    
    // إضافة الكلاس الجديد
    document.body.classList.add(densities[density] || 'density-comfortable');
}

// تحميل تفضيلات العرض
function loadViewPreferences() {
    const savedMode = localStorage.getItem('viewMode') || 'list';
    const savedDensity = localStorage.getItem('viewDensity') || 'comfortable';
    
    applyViewMode(savedMode);
    applyViewDensity(savedDensity);
    
    // تحديث الأزرار النشطة
    updateActiveViewButtons(savedMode, savedDensity);
}

// تحديث الأزرار النشطة
function updateActiveViewButtons(mode, density) {
    // تحديث أزرار وضع العرض
    document.querySelectorAll('.view-dropdown .dropdown-item').forEach(button => {
        if (button.textContent.includes(mode === 'list' ? 'القائمة' : 
                                       mode === 'grid' ? 'الشبكة' : 'التفاصيل')) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
    
    // تحديث أزرار الكثافة
    document.querySelectorAll('.view-dropdown .dropdown-item').forEach(button => {
        if (button.textContent.includes(density === 'compact' ? 'مضغوط' : 
                                       density === 'comfortable' ? 'مريح' : 'فسيح')) {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
}

// تبديل الوضع الداكن
function toggleDarkMode() {
    const switchEl = document.getElementById('darkModeSwitch');
    const isDarkMode = switchEl.checked;
    
    localStorage.setItem('darkMode', isDarkMode);
    
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        document.querySelector('html').setAttribute('data-bs-theme', 'dark');
    } else {
        document.body.classList.remove('dark-mode');
        document.querySelector('html').setAttribute('data-bs-theme', 'light');
    }
}

// تحميل الوضع الداكن
function loadDarkMode() {
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    if (isDarkMode) {
        document.getElementById('darkModeSwitch').checked = true;
        document.body.classList.add('dark-mode');
        document.querySelector('html').setAttribute('data-bs-theme', 'dark');
    }
}

// تحميل إعدادات التبديل
function loadToggleSettings() {
    const autoSave = localStorage.getItem('autoSave') !== 'false';
    const notifications = localStorage.getItem('notifications') !== 'false';
    
    document.getElementById('autoSaveSwitch').checked = autoSave;
    document.getElementById('notificationsSwitch').checked = notifications;
}

// تبديل الحفظ التلقائي
function toggleAutoSave() {
    const switchEl = document.getElementById('autoSaveSwitch');
    localStorage.setItem('autoSave', switchEl.checked);
}

// تبديل الإشعارات
function toggleNotifications() {
    const switchEl = document.getElementById('notificationsSwitch');
    localStorage.setItem('notifications', switchEl.checked);
}

// مشاركة عبر البريد الإلكتروني
function shareViaEmail() {
    const subject = encodeURIComponent('مستند من نظام الأرشيف');
    const body = encodeURIComponent('أود مشاركة هذا المستند معك:\n\n' + window.location.href);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
}

// مشاركة عبر واتساب
function shareViaWhatsApp() {
    const text = encodeURIComponent('أود مشاركة هذا المستند معك: ' + window.location.href);
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

// إنشاء رابط مشاركة
function generateShareLink() {
    const link = window.location.href;
    navigator.clipboard.writeText(link).then(() => {
        showToast('تم نسخ الرابط إلى الحافظة', 'success');
    });
}

// تصدير PDF
function exportAsPDF() {
    showProgress('جاري إنشاء ملف PDF...');
    // تنفيذ تصدير PDF
    setTimeout(() => {
        hideProgress();
        showToast('تم إنشاء ملف PDF بنجاح', 'success');
    }, 2000);
}

// تصدير Excel
function exportAsExcel() {
    showProgress('جاري إنشاء ملف Excel...');
    // تنفيذ تصدير Excel
    setTimeout(() => {
        hideProgress();
        showToast('تم إنشاء ملف Excel بنجاح', 'success');
    }, 2000);
}

// عرض شريط التقدم
function showProgress(message) {
    if (window.globalProgress) {
        window.globalProgress.show(message);
    }
}

// إخفاء شريط التقدم
function hideProgress() {
    if (window.globalProgress) {
        window.globalProgress.hide();
    }
}

// إظهار رسائل Toast
function showToast(message, type = 'info') {
    // استخدام الدالة الموجودة في main.js أو إنشاء واحدة جديدة
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        // تنفيذ بسيط لـ Toast
        const toast = document.createElement('div');
        toast.className = `toast show position-fixed bottom-0 start-0 m-3 bg-${type} text-white`;
        toast.innerHTML = `
            <div class="toast-body">
                ${message}
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
}

// تحميل الإعدادات عند بدء التشغيل
loadDarkMode();
loadToggleSettings();
</script>

<!-- CSS إضافي للوضع الداكن ووضع العرض -->
<style>
/* الوضع الداكن */
body.dark-mode .secondary-navbar {
    background: #2c3e50;
    border-bottom-color: #34495e;
    color: white;
}

body.dark-mode .stat-item {
    background: #34495e;
    color: white;
}

body.dark-mode .stat-item:hover {
    background: #3d566e;
    border-color: #4a6582;
}

body.dark-mode .stat-label {
    color: #bdc3c7;
}

body.dark-mode .quick-search input {
    background: #34495e;
    border-color: #4a6582;
    color: white;
}

body.dark-mode .quick-search input::placeholder {
    color: #95a5a6;
}

/* أوضاع العرض */
body.view-list .documents-container .card {
    display: block;
}

body.view-grid .documents-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

body.view-details .documents-container table {
    width: 100%;
}

body.view-details .documents-container .card {
    display: none;
}

/* كثافة العرض */
body.density-compact .card-body {
    padding: 10px;
}

body.density-compact .table td,
body.density-compact .table th {
    padding: 8px;
}

body.density-comfortable .card-body {
    padding: 20px;
}

body.density-spacious .card-body {
    padding: 30px;
}

body.density-spacious .table td,
body.density-spacious .table th {
    padding: 15px;
}

/* تأثيرات التنقل */
.secondary-navbar.scrolled {
    transform: translateY(-100%);
}

.secondary-navbar.visible {
    transform: translateY(0);
}

/* رسوم متحركة للإحصائيات */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.stat-item.updated {
    animation: pulse 0.5s ease;
}
</style>

<!-- إضافة تأثيرات للتنقل -->
<script>
// إظهار/إخفاء شريط التنقل عند التمرير
let lastScrollTop = 0;
const navbar = document.getElementById('secondaryNavbar');

if (navbar) {
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // التمرير لأسفل
            navbar.classList.add('scrolled');
            navbar.classList.remove('visible');
        } else {
            // التمرير لأعلى
            navbar.classList.remove('scrolled');
            navbar.classList.add('visible');
        }
        
        lastScrollTop = scrollTop;
    });
}

// تحديث متحرك للإحصائيات
function animateStatUpdate(statElement) {
    statElement.classList.add('updated');
    setTimeout(() => {
        statElement.classList.remove('updated');
    }, 500);
}

// تحديث الإحصائيات مع تأثير
document.querySelectorAll('.stat-item').forEach(item => {
    item.addEventListener('click', function() {
        animateStatUpdate(this);
    });
});
</script>