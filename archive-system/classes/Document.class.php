<?php
/**
 * Document.class.php
 * فئة متقدمة لإدارة جميع أنواع المستندات في النظام
 */

class Document {
    private $db;
    private $auth;
    private $allowedExtensions = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 
        'gif', 'tiff', 'txt', 'rtf', 'csv', 'zip', 'rar'
    ];
    
    private $maxFileSize = 104857600; // 100MB
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * إنشاء مستند جديد
     */
    public function createDocument($data, $files = []) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return [
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ];
        }
        
        // التحقق من البيانات المطلوبة
        $required = ['document_type', 'title_ar', 'document_date'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'success' => false,
                    'message' => "الحقل {$field} مطلوب"
                ];
            }
        }
        
        // بدء Transaction
        $this->db->beginTransaction();
        
        try {
            // إنشاء رقم المستند
            $documentNumber = $this->generateDocumentNumber($data['document_type']);
            
            // إعداد بيانات المستند
            $documentData = [
                'document_number' => $documentNumber,
                'document_type' => $data['document_type'],
                'financial_year' => date('Y', strtotime($data['document_date'])),
                'document_date' => $data['document_date'],
                'title_ar' => $this->sanitizeText($data['title_ar']),
                'title_en' => $this->sanitizeText($data['title_en'] ?? ''),
                'description' => $this->sanitizeText($data['description'] ?? ''),
                'keywords' => $this->sanitizeText($data['keywords'] ?? ''),
                'amount' => $data['amount'] ?? 0.00,
                'currency' => $data['currency'] ?? 'SAR',
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'from_party_id' => $data['from_party_id'] ?? null,
                'from_party_name' => $this->sanitizeText($data['from_party_name'] ?? ''),
                'from_party_type' => $data['from_party_type'] ?? null,
                'to_party_id' => $data['to_party_id'] ?? null,
                'to_party_name' => $this->sanitizeText($data['to_party_name'] ?? ''),
                'to_party_type' => $data['to_party_type'] ?? null,
                'debit_account' => $data['debit_account'] ?? null,
                'credit_account' => $data['credit_account'] ?? null,
                'cost_center' => $data['cost_center'] ?? null,
                'project_code' => $data['project_code'] ?? null,
                'budget_code' => $data['budget_code'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'priority' => $data['priority'] ?? 'normal',
                'confidentiality' => $data['confidentiality'] ?? 'internal',
                'access_level' => $data['access_level'] ?? 'department',
                'allowed_users' => $data['allowed_users'] ? json_encode($data['allowed_users']) : null,
                'retention_years' => $data['retention_years'] ?? 10,
                'archive_date' => $data['archive_date'] ?? date('Y-m-d'),
                'expiry_date' => $data['expiry_date'] ?? null,
                'prepared_by' => $currentUser['id'],
                'prepared_date' => date('Y-m-d'),
                'created_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // إدخال المستند
            $documentId = $this->db->insert('financial_documents', $documentData);
            
            // رفع الملفات إذا وجدت
            if (!empty($files)) {
                $uploadResults = $this->uploadDocumentFiles($documentId, $files, $data);
                
                if (!$uploadResults['success']) {
                    throw new Exception($uploadResults['message']);
                }
                
                // تحديث معلومات الملفات في المستند
                $this->updateDocumentFileInfo($documentId, $uploadResults);
            }
            
            // إضافة الوسوم إذا وجدت
            if (!empty($data['tags'])) {
                $this->addDocumentTags($documentId, $data['tags']);
            }
            
            // إنشاء باركود إذا مطلوب
            if ($data['generate_barcode'] ?? false) {
                $this->generateDocumentBarcode($documentId);
            }
            
            // إنشاء OCR إذا كان الملف نصياً
            if ($data['auto_ocr'] ?? true) {
                $this->processOCR($documentId);
            }
            
            // تسجيل النشاط
            $this->auth->logActivity(
                $currentUser['id'],
                'document_created',
                "إنشاء مستند جديد: {$documentNumber}",
                $documentId
            );
            
            // إرسال إشعارات إذا لزم الأمر
            if ($documentData['status'] === 'pending_approval') {
                $this->sendApprovalNotifications($documentId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'document_id' => $documentId,
                'document_number' => $documentNumber,
                'message' => 'تم إنشاء المستند بنجاح'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء المستند: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * الحصول على مستند بواسطة ID
     */
    public function getDocument($documentId, $includeFiles = true) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return null;
        }
        
        // التحقق من صلاحية الوصول
        if (!$this->canAccessDocument($currentUser['id'], $documentId)) {
            return null;
        }
        
        // الحصول على بيانات المستند
        $document = $this->db->fetchOne("
            SELECT fd.*, 
                   u1.full_name_ar as prepared_by_name,
                   u2.full_name_ar as reviewed_by_name,
                   u3.full_name_ar as approved_by_name,
                   c1.name_ar as category_name,
                   c2.name_ar as subcategory_name,
                   d1.name_ar as from_department_name,
                   d2.name_ar as to_department_name
            FROM financial_documents fd
            LEFT JOIN users u1 ON fd.prepared_by = u1.id
            LEFT JOIN users u2 ON fd.reviewed_by = u2.id
            LEFT JOIN users u3 ON fd.approved_by = u3.id
            LEFT JOIN archive_categories c1 ON fd.category_id = c1.id
            LEFT JOIN archive_categories c2 ON fd.subcategory_id = c2.id
            LEFT JOIN departments d1 ON fd.from_department_id = d1.id
            LEFT JOIN departments d2 ON fd.to_department_id = d2.id
            WHERE fd.id = ? AND fd.status != 'deleted'
        ", [$documentId]);
        
        if (!$document) {
            return null;
        }
        
        // فك تشفير الحقول JSON
        if ($document['allowed_users']) {
            $document['allowed_users'] = json_decode($document['allowed_users'], true);
        }
        
        if ($document['tags']) {
            $document['tags'] = json_decode($document['tags'], true);
        }
        
        if ($document['metadata']) {
            $document['metadata'] = json_decode($document['metadata'], true);
        }
        
        // الحصول على المرفقات إذا مطلوب
        if ($includeFiles) {
            $document['attachments'] = $this->getDocumentAttachments($documentId);
            $document['versions'] = $this->getDocumentVersions($documentId);
        }
        
        // الحصول على سجل التعديلات
        $document['revisions'] = $this->getDocumentRevisions($documentId);
        
        // تسجيل مشاهدة المستند
        $this->recordDocumentView($documentId, $currentUser['id']);
        
        return $document;
    }
    
    /**
     * تحديث مستند
     */
    public function updateDocument($documentId, $data, $files = []) {
        $currentUser = $this->auth->getCurrentUser();
        $document = $this->getDocument($documentId, false);
        
        if (!$currentUser || !$document) {
            return [
                'success' => false,
                'message' => 'المستند غير موجود أو لا تملك صلاحية التعديل'
            ];
        }
        
        // التحقق من صلاحية التعديل
        if (!$this->canEditDocument($currentUser['id'], $documentId)) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية لتعديل هذا المستند'
            ];
        }
        
        // التحقق من حالة المستند
        if (!$this->isDocumentEditable($document['status'])) {
            return [
                'success' => false,
                'message' => 'لا يمكن تعديل المستند في حالته الحالية'
            ];
        }
        
        $this->db->beginTransaction();
        
        try {
            // حفظ نسخة من البيانات القديمة
            $oldData = $document;
            
            // إعداد بيانات التحديث
            $updateData = [];
            $changes = [];
            
            // تحديث الحقول الأساسية
            $updatableFields = [
                'title_ar', 'title_en', 'description', 'keywords',
                'amount', 'currency', 'category_id', 'subcategory_id',
                'from_party_name', 'to_party_name', 'debit_account',
                'credit_account', 'cost_center', 'project_code',
                'budget_code', 'priority', 'confidentiality',
                'access_level', 'allowed_users', 'expiry_date',
                'notes', 'status'
            ];
            
            foreach ($updatableFields as $field) {
                if (isset($data[$field]) && $data[$field] != $document[$field]) {
                    $updateData[$field] = $data[$field];
                    $changes[$field] = [
                        'old' => $document[$field],
                        'new' => $data[$field]
                    ];
                }
            }
            
            // تحديث الحقول JSON
            if (isset($data['allowed_users'])) {
                $updateData['allowed_users'] = json_encode($data['allowed_users']);
            }
            
            if (isset($data['tags'])) {
                $updateData['tags'] = json_encode($data['tags']);
                $this->updateDocumentTags($documentId, $data['tags']);
            }
            
            // إذا لم تكن هناك تغييرات
            if (empty($updateData)) {
                return [
                    'success' => false,
                    'message' => 'لا توجد تغييرات لحفظها'
                ];
            }
            
            // إضافة معلومات التحديث
            $updateData['updated_by'] = $currentUser['id'];
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // تحديث المستند
            $updated = $this->db->update(
                'financial_documents',
                $updateData,
                'id = ?',
                [$documentId]
            );
            
            if (!$updated) {
                throw new Exception('فشل تحديث المستند');
            }
            
            // رفع الملفات الجديدة إذا وجدت
            if (!empty($files)) {
                $uploadResults = $this->uploadDocumentFiles($documentId, $files, $data);
                
                if (!$uploadResults['success']) {
                    throw new Exception($uploadResults['message']);
                }
                
                // تحديث معلومات الملفات
                $this->updateDocumentFileInfo($documentId, $uploadResults);
            }
            
            // إنشاء نسخة جديدة إذا تغير الملف الرئيسي
            if (isset($files['main_file']) && $files['main_file']['error'] == 0) {
                $this->createDocumentVersion($documentId, $currentUser['id'], 'تحديث الملف الرئيسي');
            }
            
            // تسجيل التغييرات
            $this->logDocumentChanges($documentId, $currentUser['id'], $changes);
            
            // تسجيل النشاط
            $this->auth->logActivity(
                $currentUser['id'],
                'document_updated',
                "تم تحديث المستند: {$document['document_number']}",
                $documentId
            );
            
            // إرسال إشعارات إذا تغيرت الحالة
            if (isset($data['status']) && $data['status'] !== $document['status']) {
                $this->handleStatusChange($documentId, $document['status'], $data['status']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'تم تحديث المستند بنجاح'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث المستند: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * حذف مستند (حذف منطقي)
     */
    public function deleteDocument($documentId, $permanent = false) {
        $currentUser = $this->auth->getCurrentUser();
        $document = $this->getDocument($documentId, false);
        
        if (!$currentUser || !$document) {
            return [
                'success' => false,
                'message' => 'المستند غير موجود'
            ];
        }
        
        // التحقق من صلاحية الحذف
        if (!$this->canDeleteDocument($currentUser['id'], $documentId)) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية لحذف هذا المستند'
            ];
        }
        
        if ($permanent) {
            // حذف فعلي (للمسؤولين فقط)
            if (!$this->auth->hasPermission('delete_documents')) {
                return [
                    'success' => false,
                    'message' => 'ليس لديك صلاحية للحذف الفعلي'
                ];
            }
            
            $this->db->beginTransaction();
            
            try {
                // حذف المرفقات
                $attachments = $this->getDocumentAttachments($documentId);
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['file_path'])) {
                        unlink($attachment['file_path']);
                    }
                }
                
                // حذف من قاعدة البيانات
                $this->db->delete('document_attachments', 'document_id = ?', [$documentId]);
                $this->db->delete('document_versions', 'document_id = ?', [$documentId]);
                $this->db->delete('document_tags', 'document_id = ?', [$documentId]);
                $this->db->delete('financial_documents', 'id = ?', [$documentId]);
                
                $this->db->commit();
                
                // تسجيل النشاط
                $this->auth->logActivity(
                    $currentUser['id'],
                    'document_deleted_permanent',
                    "حذف مستند نهائي: {$document['document_number']}",
                    $documentId
                );
                
                return [
                    'success' => true,
                    'message' => 'تم حذف المستند نهائياً'
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => 'حدث خطأ أثناء حذف المستند: ' . $e->getMessage()
                ];
            }
            
        } else {
            // حذف منطقي
            $updated = $this->db->update(
                'financial_documents',
                [
                    'status' => 'deleted',
                    'deleted_by' => $currentUser['id'],
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = ?',
                [$documentId]
            );
            
            if ($updated) {
                // تسجيل النشاط
                $this->auth->logActivity(
                    $currentUser['id'],
                    'document_deleted',
                    "حذف مستند: {$document['document_number']}",
                    $documentId
                );
                
                return [
                    'success' => true,
                    'message' => 'تم حذف المستند'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف المستند'
            ];
        }
    }
    
    /**
     * استعادة مستند محذوف
     */
    public function restoreDocument($documentId) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return [
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً'
            ];
        }
        
        // التحقق من الصلاحية
        if (!$this->auth->hasPermission('restore_documents')) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية لاستعادة المستندات'
            ];
        }
        
        $updated = $this->db->update(
            'financial_documents',
            [
                'status' => 'draft',
                'deleted_by' => null,
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $currentUser['id']
            ],
            'id = ? AND status = ?',
            [$documentId, 'deleted']
        );
        
        if ($updated) {
            // تسجيل النشاط
            $this->auth->logActivity(
                $currentUser['id'],
                'document_restored',
                "استعادة مستند محذوف: {$documentId}",
                $documentId
            );
            
            return [
                'success' => true,
                'message' => 'تم استعادة المستند'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'فشل استعادة المستند'
        ];
    }
    
    /**
     * البحث في المستندات
     */
    public function searchDocuments($filters = [], $page = 1, $perPage = 20) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return ['documents' => [], 'total' => 0];
        }
        
        $sql = "SELECT fd.*, 
                       u.full_name_ar as prepared_by_name,
                       c.name_ar as category_name
                FROM financial_documents fd
                LEFT JOIN users u ON fd.prepared_by = u.id
                LEFT JOIN archive_categories c ON fd.category_id = c.id
                WHERE fd.status != 'deleted'";
        
        $params = [];
        $conditions = [];
        
        // تطبيق فلاتر الوصول
        $accessConditions = $this->getAccessConditions($currentUser['id']);
        $conditions[] = "(" . implode(" OR ", $accessConditions) . ")";
        
        // تطبيق الفلاتر
        if (!empty($filters['document_type'])) {
            $conditions[] = "fd.document_type = ?";
            $params[] = $filters['document_type'];
        }
        
        if (!empty($filters['category_id'])) {
            $conditions[] = "fd.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "fd.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['financial_year'])) {
            $conditions[] = "fd.financial_year = ?";
            $params[] = $filters['financial_year'];
        }
        
        if (!empty($filters['from_date'])) {
            $conditions[] = "fd.document_date >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $conditions[] = "fd.document_date <= ?";
            $params[] = $filters['to_date'];
        }
        
        if (!empty($filters['amount_from'])) {
            $conditions[] = "fd.amount >= ?";
            $params[] = $filters['amount_from'];
        }
        
        if (!empty($filters['amount_to'])) {
            $conditions[] = "fd.amount <= ?";
            $params[] = $filters['amount_to'];
        }
        
        if (!empty($filters['prepared_by'])) {
            $conditions[] = "fd.prepared_by = ?";
            $params[] = $filters['prepared_by'];
        }
        
        if (!empty($filters['confidentiality'])) {
            $conditions[] = "fd.confidentiality = ?";
            $params[] = $filters['confidentiality'];
        }
        
        // البحث النصي
        if (!empty($filters['search'])) {
            $searchTerm = "%{$filters['search']}%";
            $conditions[] = "(fd.document_number LIKE ? OR 
                             fd.title_ar LIKE ? OR 
                             fd.description LIKE ? OR 
                             fd.keywords LIKE ? OR 
                             fd.ocr_text LIKE ?)";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // تطبيق جميع الشروط
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        // الحصول على العدد الإجمالي
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as count_table";
        $total = $this->db->fetchColumn($countSql, $params);
        
        // إضافة الترتيب والتجزئة
        $orderBy = $filters['order_by'] ?? 'fd.document_date';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        
        $sql .= " ORDER BY {$orderBy} {$orderDir} LIMIT ? OFFSET ?";
        $offset = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;
        
        $documents = $this->db->fetchAll($sql, $params);
        
        // فك تشفير الحقول JSON
        foreach ($documents as &$doc) {
            if ($doc['tags']) {
                $doc['tags'] = json_decode($doc['tags'], true);
            }
        }
        
        return [
            'documents' => $documents,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * البحث المتقدم
     */
    public function advancedSearch($criteria) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return ['documents' => [], 'total' => 0];
        }
        
        // استخدام البحث النصي الكامل إذا كان متاحاً
        if (!empty($criteria['fulltext'])) {
            return $this->fulltextSearch($criteria['fulltext'], $criteria);
        }
        
        return $this->searchDocuments($criteria);
    }
    
    /**
     * البحث النصي الكامل
     */
    private function fulltextSearch($searchTerm, $additionalFilters = []) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return ['documents' => [], 'total' => 0];
        }
        
        // الحصول على شروط الوصول
        $accessConditions = $this->getAccessConditions($currentUser['id']);
        $accessWhere = "(" . implode(" OR ", $accessConditions) . ")";
        
        // إعداد معاملات البحث
        $searchParams = [];
        $searchParams[] = $searchTerm;
        $searchParams[] = $searchTerm;
        
        // إضافة فلاتر إضافية
        $additionalWhere = $accessWhere;
        $additionalParams = [];
        
        if (!empty($additionalFilters['document_type'])) {
            $additionalWhere .= " AND document_type = ?";
            $additionalParams[] = $additionalFilters['document_type'];
        }
        
        if (!empty($additionalFilters['category_id'])) {
            $additionalWhere .= " AND category_id = ?";
            $additionalParams[] = $additionalFilters['category_id'];
        }
        
        if (!empty($additionalFilters['status'])) {
            $additionalWhere .= " AND status = ?";
            $additionalParams[] = $additionalFilters['status'];
        }
        
        $searchParams = array_merge($searchParams, $additionalParams);
        
        // إضافة حد النتائج
        $limit = $additionalFilters['limit'] ?? 100;
        $searchParams[] = $limit;
        
        // البحث
        $results = $this->db->fulltextSearch(
            'financial_documents',
            ['title_ar', 'description', 'keywords', 'ocr_text'],
            $searchTerm,
            $additionalWhere,
            $additionalParams,
            $limit
        );
        
        return [
            'documents' => $results,
            'total' => count($results)
        ];
    }
    
    /**
     * الحصول على المستندات حسب التصنيف
     */
    public function getDocumentsByCategory($categoryId, $includeSubcategories = true) {
        $currentUser = $this->auth->getCurrentUser();
        
        if (!$currentUser) {
            return [];
        }
        
        $sql = "SELECT fd.*, c.name_ar as category_name
                FROM financial_documents fd
                JOIN archive_categories c ON fd.category_id = c.id
                WHERE fd.status != 'deleted'";
        
        $params = [];
        
        // شروط الوصول
        $accessConditions = $this->getAccessConditions($currentUser['id']);
        $sql .= " AND (" . implode(" OR ", $accessConditions) . ")";
        
        // التصنيف
        if ($includeSubcategories) {
            // الحصول على جميع التصنيفات الفرعية
            $subcategories = $this->getSubcategoryIds($categoryId);
            $categoryIds = array_merge([$categoryId], $subcategories);
            
            $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
            $sql .= " AND fd.category_id IN ($placeholders)";
            $params = array_merge($params, $categoryIds);
        } else {
            $sql .= " AND fd.category_id = ?";
            $params[] = $categoryId;
        }
        
        $sql .= " ORDER BY fd.document_date DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * الحصول على إحصائيات المستندات
     */
    public function getDocumentStats($period = 'month', $userId = null) {
        $currentUser = $this->auth->getCurrentUser();
        $userId = $userId ?: $currentUser['id'];
        
        if (!$currentUser) {
            return [];
        }
        
        $stats = [];
        
        // إحصائيات حسب النوع
        $typeStats = $this->db->fetchAll("
            SELECT document_type, COUNT(*) as count, SUM(amount) as total_amount
            FROM financial_documents
            WHERE status != 'deleted'
            " . ($userId ? " AND created_by = ?" : "") . "
            GROUP BY document_type
            ORDER BY count DESC
        ", $userId ? [$userId] : []);
        
        $stats['by_type'] = $typeStats;
        
        // إحصائيات حسب الحالة
        $statusStats = $this->db->fetchAll("
            SELECT status, COUNT(*) as count
            FROM financial_documents
            WHERE status != 'deleted'
            " . ($userId ? " AND created_by = ?" : "") . "
            GROUP BY status
        ", $userId ? [$userId] : []);
        
        $stats['by_status'] = $statusStats;
        
        // إحصائيات حسب الشهر
        $monthStats = $this->db->fetchAll("
            SELECT 
                DATE_FORMAT(document_date, '%Y-%m') as month,
                COUNT(*) as document_count,
                SUM(amount) as total_amount
            FROM financial_documents
            WHERE status != 'deleted'
                AND document_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            " . ($userId ? " AND created_by = ?" : "") . "
            GROUP BY DATE_FORMAT(document_date, '%Y-%m')
            ORDER BY month DESC
        ", $userId ? [$userId] : []);
        
        $stats['by_month'] = $monthStats;
        
        // إحصائيات حسب التصنيف
        $categoryStats = $this->db->fetchAll("
            SELECT 
                c.name_ar as category_name,
                COUNT(fd.id) as document_count,
                SUM(fd.amount) as total_amount
            FROM financial_documents fd
            LEFT JOIN archive_categories c ON fd.category_id = c.id
            WHERE fd.status != 'deleted'
            " . ($userId ? " AND fd.created_by = ?" : "") . "
            GROUP BY c.id, c.name_ar
            ORDER BY document_count DESC
            LIMIT 10
        ", $userId ? [$userId] : []);
        
        $stats['by_category'] = $categoryStats;
        
        // الإجماليات
        $totals = $this->db->fetchOne("
            SELECT 
                COUNT(*) as total_documents,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                MIN(amount) as min_amount,
                MAX(amount) as max_amount
            FROM financial_documents
            WHERE status != 'deleted'
            " . ($userId ? " AND created_by = ?" : "") . "
        ", $userId ? [$userId] : []);
        
        $stats['totals'] = $totals;
        
        return $stats;
    }
    
    /**
     * الحصول على المستندات التي تحتاج مراجعة
     */
    public function getPendingApprovals($userId = null) {
        $currentUser = $this->auth->getCurrentUser();
        $userId = $userId ?: $currentUser['id'];
        
        if (!$currentUser) {
            return [];
        }
        
        $sql = "SELECT fd.*, u.full_name_ar as prepared_by_name
                FROM financial_documents fd
                JOIN users u ON fd.prepared_by = u.id
                WHERE fd.status = 'pending_approval'
                AND (fd.reviewed_by = ? OR fd.approved_by = ? OR fd.prepared_by = ?)
                ORDER BY fd.document_date ASC";
        
        return $this->db->fetchAll($sql, [$userId, $userId, $userId]);
    }
    
    /**
     * الحصول على المستندات المنتهية الصلاحية
     */
    public function getExpiredDocuments($daysBefore = 7) {
        $sql = "SELECT fd.*, u.full_name_ar as owner_name, u.email as owner_email
                FROM financial_documents fd
                JOIN users u ON fd.created_by = u.id
                WHERE fd.status = 'active'
                AND fd.expiry_date IS NOT NULL
                AND fd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND fd.expiry_date >= CURDATE()
                ORDER BY fd.expiry_date ASC";
        
        return $this->db->fetchAll($sql, [$daysBefore]);
    }
    
    /**
     * الموافقة على مستند
     */
    public function approveDocument($documentId, $action, $notes = '') {
        $currentUser = $this->auth->getCurrentUser();
        $document = $this->getDocument($documentId, false);
        
        if (!$currentUser || !$document) {
            return [
                'success' => false,
                'message' => 'المستند غير موجود'
            ];
        }
        
        // التحقق من صلاحية الموافقة
        if (!$this->canApproveDocument($currentUser['id'], $documentId)) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية للموافقة على هذا المستند'
            ];
        }
        
        $this->db->beginTransaction();
        
        try {
            $updateData = [];
            $activityType = '';
            
            switch ($action) {
                case 'review':
                    if ($document['reviewed_by'] && $document['reviewed_by'] != $currentUser['id']) {
                        throw new Exception('تمت مراجعة المستند مسبقاً');
                    }
                    
                    $updateData['reviewed_by'] = $currentUser['id'];
                    $updateData['reviewed_date'] = date('Y-m-d');
                    $updateData['review_notes'] = $notes;
                    $activityType = 'document_reviewed';
                    break;
                    
                case 'approve':
                    if ($document['approved_by'] && $document['approved_by'] != $currentUser['id']) {
                        throw new Exception('تمت الموافقة على المستند مسبقاً');
                    }
                    
                    $updateData['approved_by'] = $currentUser['id'];
                    $updateData['approved_date'] = date('Y-m-d');
                    $updateData['status'] = 'approved';
                    $activityType = 'document_approved';
                    break;
                    
                case 'reject':
                    $updateData['status'] = 'rejected';
                    $updateData['rejection_reason'] = $notes;
                    $activityType = 'document_rejected';
                    break;
                    
                default:
                    throw new Exception('إجراء غير معروف');
            }
            
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $updateData['updated_by'] = $currentUser['id'];
            
            $updated = $this->db->update(
                'financial_documents',
                $updateData,
                'id = ?',
                [$documentId]
            );
            
            if (!$updated) {
                throw new Exception('فشل تحديث حالة المستند');
            }
            
            // تسجيل النشاط
            $this->auth->logActivity(
                $currentUser['id'],
                $activityType,
                "{$action} المستند: {$document['document_number']}",
                $documentId
            );
            
            // إرسال إشعارات
            $this->sendApprovalResultNotification($documentId, $action, $notes);
            
            // إذا تمت الموافقة النهائية
            if ($action === 'approve') {
                $this->finalizeDocument($documentId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => "تم {$action} المستند بنجاح"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'message' => 'حدث خطأ أثناء المعالجة: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * إنشاء إصدار جديد من المستند
     */
    public function createNewVersion($documentId, $fileData, $changes = '') {
        $currentUser = $this->auth->getCurrentUser();
        $document = $this->getDocument($documentId, false);
        
        if (!$currentUser || !$document) {
            return [
                'success' => false,
                'message' => 'المستند غير موجود'
            ];
        }
        
        // التحقق من صلاحية إنشاء إصدار جديد
        if (!$this->canCreateVersion($currentUser['id'], $documentId)) {
            return [
                'success' => false,
                'message' => 'ليس لديك صلاحية لإنشاء إصدار جديد'
            ];
        }
        
        $this->db->beginTransaction();
        
        try {
            // رفع الملف الجديد
            $uploadResult = $this->uploadDocumentFile($documentId, $fileData, 'version');
            
            if (!$uploadResult['success']) {
                throw new Exception($uploadResult['message']);
            }
            
            // إنشاء سجل الإصدار
            $versionData = [
                'document_id' => $documentId,
                'version' => $this->getNextVersionNumber($documentId),
                'file_path' => $uploadResult['file_path'],
                'file_hash' => $uploadResult['file_hash'],
                'file_size' => $uploadResult['file_size'],
                'changes' => $changes,
                'created_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $versionId = $this->db->insert('document_versions', $versionData);
            
           