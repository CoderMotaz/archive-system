<!-- الفوتر -->
<footer class="main-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4">
                <div class="footer-section">
                    <h6><i class="fas fa-info-circle me-2"></i>حول النظام</h6>
                    <p class="text-muted">
                        نظام الأرشيف المتكامل هو حل متكامل لإدارة المستندات الرقمية، 
                        يدعم جميع أنواع المستندات المالية والإدارية والقانونية.
                    </p>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="footer-section">
                    <h6><i class="fas fa-link me-2"></i>روابط سريعة</h6>
                    <div class="quick-links">
                        <a href="help.php"><i class="fas fa-question-circle me-2"></i>مساعدة</a>
                        <a href="about.php"><i class="fas fa-info-circle me-2"></i>حول النظام</a>
                        <a href="contact.php"><i class="fas fa-envelope me-2"></i>اتصل بنا</a>
                        <a href="privacy.php"><i class="fas fa-shield-alt me-2"></i>الخصوصية</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="footer-section">
                    <h6><i class="fas fa-chart-line me-2"></i>إحصائيات النظام</h6>
                    <div class="system-stats">
                        <div class="stat-item">
                            <span class="stat-label">المستندات النشطة:</span>
                            <span class="stat-value" id="footerTotalDocs">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">المستخدمون النشطون:</span>
                            <span class="stat-value" id="footerActiveUsers">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">مسح اليوم:</span>
                            <span class="stat-value" id="footerTodayScans">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">وقت التشغيل:</span>
                            <span class="stat-value" id="footerUptime">99.9%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <i class="fas fa-copyright"></i>
                        جميع الحقوق محفوظة &copy; <?php echo date('Y'); ?> 
                        <a href="#" class="text-primary">نظام الأرشيف المتكامل</a>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-technologies">
                        <span class="tech-badge">PHP</span>
                        <span class="tech-badge">MySQL</span>
                        <span class="tech-badge">Bootstrap</span>
                        <span class="tech-badge">JavaScript</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- زر العودة للأعلى -->
<button class="btn btn-primary btn-back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</button>

<style>
.main-footer {
    background: #2c3e50;
    color: white;
    padding: 40px 0 20px;
    margin-top: 50px;
    border-top: 3px solid var(--secondary-color);
}

.footer-section {
    margin-bottom: 30px;
}

.footer-section h6 {
    color: white;
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 1rem;
}

.footer-section p {
    font-size: 0.9rem;
    line-height: 1.6;
}

.quick-links {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.quick-links a {
    color: #bdc3c7;
    text-decoration: none;
    transition: color 0.3s;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
}

.quick-links a:hover {
    color: white;
    transform: translateX(-5px);
}

.system-stats {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 15px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.stat-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.stat-label {
    color: #bdc3c7;
    font-size: 0.85rem;
}

.stat-value {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

.footer-bottom {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    font-size: 0.85rem;
}

.footer-bottom a {
    color: #3498db;
    text-decoration: none;
}

.footer-bottom a:hover {
    text-decoration: underline;
}

.footer-technologies {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.tech-badge {
    background: rgba(255,255,255,0.1);
    color: white;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* زر العودة للأعلى */
.btn-back-to-top {
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.btn-back-to-top.show {
    opacity: 1;
    visibility: visible;
    bottom: 30px;
}

.btn-back-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}

/* التجاوب */
@media (max-width: 768px) {
    .main-footer {
        padding: 30px 0 15px;
    }
    
    .footer-section {
        margin-bottom: 25px;
    }
    
    .btn-back-to-top {
        bottom: 15px;
        left: 15px;
        width: 45px;
        height: 45px;
    }
    
    .footer-technologies {
        justify-content: flex-start;
        margin-top: 15px;
    }
}
</style>

<script>
// تحديث إحصائيات الفوتر
document.addEventListener('DOMContentLoaded', function() {
    fetch('api/stats.php?action=footer')
        .then(response => response.json())
        .then(data => {
            document.getElementById('footerTotalDocs').textContent = data.total_documents.toLocaleString('ar-SA');
            document.getElementById('footerActiveUsers').textContent = data.active_users.toLocaleString('ar-SA');
            document.getElementById('footerTodayScans').textContent = data.today_scans.toLocaleString('ar-SA');
        })
        .catch(error => {
            console.error('Error loading footer stats:', error);
        });
    
    // زر العودة للأعلى
    const backToTopButton = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.classList.add('show');
        } else {
            backToTopButton.classList.remove('show');
        }
    });
    
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});
</script>