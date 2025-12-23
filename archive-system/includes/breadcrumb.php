<?php
/**
 * ملف breadcrumb.php - شريط التنقل الهرمي (مسار الفتات)
 * يظهر أعلى كل صفحة ليوضح للمستخدم موقعه الحالي في النظام
 */

// دالة لتوليد breadcrumb ديناميكي
function generate_breadcrumb($page_title = '', $custom_items = []) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $breadcrumb_items = [];
    
    // العنصر الأول دائماً الرئيسية
    $breadcrumb_items[] = [
        'title' => '<i class="fas fa-home"></i> الرئيسية',
        'url' => 'index.php',
        'icon' => 'home'
    ];
    
    // إضافة العناصر بناءً على الصفحة الحالية
    $page_items = get_breadcrumb_items($current_page);
    $breadcrumb_items = array_merge($breadcrumb_items, $page_items);
    
    // إضافة العناصر المخصصة إذا وجدت
    if (!empty($custom_items)) {
        $breadcrumb_items = array_merge($breadcrumb_items, $custom_items);
    }
    
    // إضافة عنوان الصفحة الحالي (آخر عنصر بدون رابط)
    if ($page_title) {
        $breadcrumb_items[] = [
            'title' => $page_title,
            'url' => '',
            'icon' => 'file'
        ];
    }
    
    // إنشاء HTML
    $html = '<nav aria-label="breadcrumb" class="mb-4">';
    $html .= '<ol class="breadcrumb mb-0">';
    
    foreach ($breadcrumb_items as $index => $item) {
        $is_last = ($index === count($breadcrumb_items) - 1);
        $icon = isset($item['icon']) ? '<i class="fas fa-' . $item['icon'] . ' me-2"></i>' : '';
        
        if ($is_last || empty($item['url'])) {
            // العنصر الأخير أو بدون رابط
            $html .= '<li class="breadcrumb-item active" aria-current="page">';
            $html .= $icon . $item['title'];
            $html .= '</li>';
        } else {
            // عنصر مع رابط
            $html .= '<li class="breadcrumb-item">';
            $html .= '<a href="' . $item['url'] . '" class="text-decoration-none">';
            $html .= $icon . $item['title'];
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    $html .= '</ol>';
    $html .= '</nav>';
    
    return $html;
}

// دالة للحصول على عناصر breadcrumb بناءً على الصفحة
function get_breadcrumb_items($page) {
    $items = [];
    
    // تحليل الصفحة وإرجاع العناصر المناسبة
    switch (true) {
        // الأرشيف المالي
        case strpos($page, 'financial') !== false:
            $items[] = [
                'title' => 'الأرشيف المالي',
                'url' => 'financial/index.php',
                'icon' => 'file-invoice-dollar'
            ];
            
            if (strpos($page, 'payment-vouchers') !== false) {
                $items[] = [
                    'title' => 'سندات الصرف',
                    'url' => 'financial/payment-vouchers.php',
                    'icon' => 'money-check-alt'
                ];
            } elseif (strpos($page, 'receipt-vouchers') !== false) {
                $items[] = [
                    'title' => 'سندات القبض',
                    'url' => 'financial/receipt-vouchers.php',
                    'icon' => 'receipt'
                ];
            } elseif (strpos($page, 'journal-entries') !== false) {
                $items[] = [
                    'title' => 'قيود اليومية',
                    'url' => 'financial/journal-entries.php',
                    'icon' => 'book'
                ];
            }
            break;
            
        // الموارد البشرية
        case strpos($page, 'hr') !== false:
            $items[] = [
                'title' => 'الموارد البشرية',
                'url' => 'hr/index.php',
                'icon' => 'users'
            ];
            
            if (strpos($page, 'employee-files') !== false) {
                $items[] = [
                    'title' => 'ملفات الموظفين',
                    'url' => 'hr/employee-files.php',
                    'icon' => 'user-folder'
                ];
            } elseif (strpos($page, 'contracts') !== false) {
                $items[] = [
                    'title' => 'عقود العمل',
                    'url' => 'hr/contracts.php',
                    'icon' => 'file-contract'
                ];
            }
            break;
            
        // إدارة المستندات
        case strpos($page, 'documents') !== false:
            $items[] = [
                'title' => 'إدارة المستندات',
                'url' => 'documents/index.php',
                'icon' => 'folder-open'
            ];
            
            if (strpos($page, 'add.php') !== false) {
                $items[] = [
                    'title' => 'إضافة مستند جديد',
                    'url' => 'documents/add.php',
                    'icon' => 'plus-circle'
                ];
            } elseif (strpos($page, 'view.php') !== false) {
                $items[] = [
                    'title' => 'عرض المستند',
                    'url' => 'documents/list.php',
                    'icon' => 'eye'
                ];
            } elseif (strpos($page, 'edit.php') !== false) {
                $items[] = [
                    'title' => 'تعديل المستند',
                    'url' => 'documents/list.php',
                    'icon' => 'edit'
                ];
            }
            break;
            
        // المسح الضوئي
        case strpos($page, 'scanner') !== false:
            $items[] = [
                'title' => 'المسح والرفع',
                'url' => 'scanner/index.php',
                'icon' => 'scanner'
            ];
            break;
            
        // التصنيفات
        case strpos($page, 'categories') !== false:
            $items[] = [
                'title' => 'التصنيفات',
                'url' => 'categories/index.php',
                'icon' => 'tags'
            ];
            break;
            
        // التقارير
        case strpos($page, 'reports') !== false:
            $items[] = [
                'title' => 'التقارير',
                'url' => 'reports/index.php',
                'icon' => 'chart-bar'
            ];
            break;
            
        // الإعدادات
        case strpos($page, 'settings') !== false:
            $items[] = [
                'title' => 'الإعدادات',
                'url' => 'settings/index.php',
                'icon' => 'cogs'
            ];
            break;
            
        // لوحة الإدارة
        case strpos($page, 'admin') !== false:
            $items[] = [
                'title' => 'لوحة الإدارة',
                'url' => 'admin/index.php',
                'icon' => 'shield-alt'
            ];
            break;
            
        // المهام
        case strpos($page, 'tasks') !== false:
            $items[] = [
                'title' => 'المهام',
                'url' => 'tasks.php',
                'icon' => 'tasks'
            ];
            break;
            
        // البحث
        case strpos($page, 'search') !== false:
            $items[] = [
                'title' => 'البحث',
                'url' => 'search.php',
                'icon' => 'search'
            ];
            break;
    }
    
    return $items;
}

// دالة للتعامل مع breadcrumb ديناميكي بناءً على البيانات الفعلية
function generate_dynamic_breadcrumb($type, $id = null, $title = null) {
    global $db;
    
    $breadcrumb_items = [];
    
    switch ($type) {
        case 'document':
            if ($id) {
                $document = $db->fetchOne(
                    "SELECT d.*, c.name_ar as category_name 
                     FROM financial_documents d
                     LEFT JOIN archive_categories c ON d.category_id = c.id
                     WHERE d.id = ?",
                    [$id]
                );
                
                if ($document) {
                    $breadcrumb_items[] = [
                        'title' => 'الأرشيف المالي',
                        'url' => 'financial/index.php',
                        'icon' => 'file-invoice-dollar'
                    ];
                    
                    $breadcrumb_items[] = [
                        'title' => $document['category_name'] ?? 'المستندات',
                        'url' => 'categories/view.php?id=' . $document['category_id'],
                        'icon' => 'folder'
                    ];
                    
                    if ($title) {
                        $breadcrumb_items[] = [
                            'title' => $title,
                            'url' => '',
                            'icon' => 'file'
                        ];
                    }
                }
            }
            break;
            
        case 'employee':
            if ($id) {
                $employee = $db->fetchOne(
                    "SELECT * FROM users WHERE id = ?",
                    [$id]
                );
                
                if ($employee) {
                    $breadcrumb_items[] = [
                        'title' => 'الموارد البشرية',
                        'url' => 'hr/index.php',
                        'icon' => 'users'
                    ];
                    
                    $breadcrumb_items[] = [
                        'title' => 'ملفات الموظفين',
                        'url' => 'hr/employee-files.php',
                        'icon' => 'user-folder'
                    ];
                    
                    if ($title) {
                        $breadcrumb_items[] = [
                            'title' => $title,
                            'url' => '',
                            'icon' => 'user'
                        ];
                    }
                }
            }
            break;
            
        case 'category':
            if ($id) {
                $category = $db->fetchOne(
                    "SELECT * FROM archive_categories WHERE id = ?",
                    [$id]
                );
                
                if ($category) {
                    $breadcrumb_items[] = [
                        'title' => 'التصنيفات',
                        'url' => 'categories/index.php',
                        'icon' => 'tags'
                    ];
                    
                    // إضافة التصنيفات الأب إذا وجدت
                    if ($category['parent_id']) {
                        $parent = $db->fetchOne(
                            "SELECT * FROM archive_categories WHERE id = ?",
                            [$category['parent_id']]
                        );
                        
                        if ($parent) {
                            $breadcrumb_items[] = [
                                'title' => $parent['name_ar'],
                                'url' => 'categories/view.php?id=' . $parent['id'],
                                'icon' => 'folder'
                            ];
                        }
                    }
                    
                    if ($title) {
                        $breadcrumb_items[] = [
                            'title' => $title,
                            'url' => '',
                            'icon' => 'folder-open'
                        ];
                    }
                }
            }
            break;
    }
    
    return $breadcrumb_items;
}

// دالة لتحسين عرض breadcrumb في الصفحة
function display_breadcrumb($page_title = '', $type = null, $id = null, $custom_title = null) {
    $breadcrumb_items = [];
    
    if ($type && $id) {
        $breadcrumb_items = generate_dynamic_breadcrumb($type, $id, $custom_title);
    }
    
    return generate_breadcrumb($page_title, $breadcrumb_items);
}

// CSS خاص ب breadcrumb
function breadcrumb_styles() {
    return '
    <style>
    .breadcrumb {
        background: linear-gradient(90deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: 1px solid #e9ecef;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    
    .breadcrumb-item {
        font-size: 0.95rem;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        color: #6c757d;
        margin: 0 10px;
        font-weight: bold;
        font-size: 1.2rem;
    }
    
    .breadcrumb-item a {
        color: #3498db;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .breadcrumb-item a:hover {
        color: #2980b9;
        transform: translateY(-1px);
    }
    
    .breadcrumb-item.active {
        color: #2c3e50;
        font-weight: 600;
    }
    
    .breadcrumb-item .fas {
        font-size: 0.9rem;
    }
    
    /* breadcrumb متجاوب */
    @media (max-width: 768px) {
        .breadcrumb {
            padding: 10px 15px;
            overflow-x: auto;
            white-space: nowrap;
            flex-wrap: nowrap;
        }
        
        .breadcrumb-item {
            display: inline-block;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            margin: 0 8px;
        }
    }
    </style>
    ';
}

// إضافة CSS إلى الصفحة
add_action('wp_head', 'breadcrumb_styles');

// دالة محاكاة لـ add_action (لتوافق WordPress)
function add_action($hook, $function) {
    if ($hook === 'wp_head') {
        echo $function();
    }
}
?>

<!-- تضمين الـ CSS -->
<?php echo breadcrumb_styles(); ?>