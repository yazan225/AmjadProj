<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/

define("FA_LOGOUT_PHP_FILE","");

$page_security = 'SA_OPEN';
$path_to_root="..";
include($path_to_root . "/includes/session.inc");
add_js_file('login.js');

// Remove the header include that creates the black box
// include($path_to_root . "/includes/page/header.inc");

// Don't call page_header function as it creates the black box
// page_header(_("تسجيل الخروج"), true, false, '');

// Start output buffering to capture the entire page
ob_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الخروج - النظام المحاسبي الموحد</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Droid Arabic Kufi', 'Noto Sans Arabic', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        @font-face {
            font-family: 'Droid Arabic Kufi';
            src: url('https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap');
            font-weight: normal;
            font-style: normal;
        }

        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4bb543;
            --error: #e63946;
            --gray: #6c757d;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s linear infinite;
            z-index: 0;
        }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-30px, -30px) rotate(360deg); }
        }

        .logout-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            max-height: 600px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logout-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .logout-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(30deg);
        }

        .logout-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            flex-direction: row-reverse;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 20px;
            color: white;
            font-size: 32px;
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(255, 255, 255, 0.2);
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .logo-text h1 {
            font-size: 32px;
            color: white;
            margin-bottom: 5px;
            line-height: 1.3;
        }

        .logo-text p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            font-weight: 500;
            line-height: 1.4;
        }

        .company-info {
            position: relative;
            z-index: 1;
            text-align: right;
        }

        .company-info h3 {
            font-size: 28px;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
            color: white;
        }

        .company-info h3::after {
            content: '';
            position: absolute;
            bottom: -10px;
            right: 0;
            width: 50px;
            height: 3px;
            background: var(--accent);
        }

        .features-list {
            list-style: none;
            margin-top: 30px;
            text-align: right;
        }

        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-size: 15px;
            flex-direction: row-reverse;
            color: rgba(255, 255, 255, 0.9);
        }

        .features-list i {
            margin-left: 10px;
            margin-right: 0;
            background: rgba(255, 255, 255, 0.2);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .copyright {
            margin-top: 40px;
            font-size: 12px;
            opacity: 0.8;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 30px auto 30px;
            color: white;
            font-size: 40px;
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 20px 40px rgba(67, 97, 238, 0.6); }
            100% { transform: scale(1); box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4); }
        }

        .logout-message {
            margin-bottom: 20px;
            text-align: center;
        }

        .logout-message h2 {
            font-size: 36px;
            color: var(--dark);
            margin-bottom: 15px;
            font-weight: 600;
            line-height: 1.3;
        }

        .logout-message p {
            font-size: 18px;
            color: var(--gray);
            line-height: 1.6;
        }

        .thank-you {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            font-size: 24px;
            margin: 20px 0;
            line-height: 1.4;
        }

        .countdown {
            margin: 15px 0 25px 0;
            font-size: 16px;
            color: var(--primary);
            font-weight: 600;
            text-align: center;
            background: rgba(67, 97, 238, 0.1);
            padding: 12px 15px;
            border-radius: 10px;
            border-right: 3px solid var(--primary);
            display: block !important;
        }

        .system-info {
            background: rgba(67, 97, 238, 0.1);
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            border-right: 4px solid var(--primary);
            text-align: right;
        }

        .system-info strong {
            color: var(--primary);
            font-size: 16px;
        }

        .system-info i {
            margin-left: 8px;
        }

        .login-again-btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            padding: 20px 40px;
            border-radius: 12px;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
            position: relative;
            overflow: hidden;
            margin-top: 20px;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
            min-height: 65px;
            line-height: 1.2;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .login-again-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }

        .login-again-btn:hover::before {
            left: 100%;
        }

        .login-again-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(67, 97, 238, 0.4);
        }

        .login-again-btn i {
            font-size: 22px;
            margin: 0;
        }

        .security-notice {
            margin-top: 25px;
            padding: 15px;
            background: rgba(230, 57, 70, 0.1);
            border-radius: 10px;
            border-right: 4px solid var(--error);
            font-size: 14px;
            color: var(--error);
            text-align: right;
        }

        .security-notice i {
            margin-left: 8px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(0,0,0,0.1);
            color: var(--gray);
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .logout-container {
                flex-direction: column;
                max-height: none;
            }
            
            .logout-left {
                order: -1;
                padding: 30px;
            }
            
            .logout-right {
                padding: 30px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
            
            .logo-text h1 {
                font-size: 24px;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .logout-message h2 {
                font-size: 28px;
            }
        }

        /* RTL specific adjustments */
        .logout-message, .system-info, .security-notice, .footer {
            direction: rtl;
        }
        
        /* Hide any external elements that might appear */
        body > *:not(.logout-container) {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <!-- Right Side: Arabic Logout Content -->
        <div class="logout-right">
            <div class="logo-container">
                <div class="logo">GS</div>
                <div class="logo-text">
                    <h1>النظام المحاسبي الموحد</h1>
                    <p>إصدار 2024 شامل نقطة البيع</p>
                </div>
            </div>

            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>

            <div class="logout-message">
                <h2>تم تسجيل خروجك بنجاح</h2>
                <p>تم إنهاء جلسة العمل الخاصة بك بشكل آمن ومحمي.</p>
                
                <div class="thank-you">
                    شكراً لاستخدامك<br>
                    <strong>النظام المحاسبي الموحد <?php echo $version; ?></strong>
                </div>
            </div>

            <!-- تم نقل العد التنازلي إلى هنا ليكون أعلى -->
            <div class="countdown" id="countdown" style="display: block; opacity: 1; visibility: visible;">
                <i class="fas fa-clock"></i>
                سيتم إعادة التوجيه تلقائياً خلال <span id="countdown-number" style="font-weight: bold; font-size: 18px; color: var(--secondary);">30</span> ثانية
            </div>

            <div class="system-info">
                <i class="fas fa-shield-alt"></i> 
                <strong>تنبيه أمني:</strong> تم مسح جميع بيانات الجلسة لحماية حسابك.
            </div>

            <a href="<?php echo $path_to_root; ?>/index.php" class="login-again-btn">
                <i class="fas fa-sign-in-alt"></i> الدخول مرة أخرى إلى النظام
            </a>

            <div class="security-notice">
                <i class="fas fa-lock"></i>
                لأسباب أمنية، يرجى إغلاق المتصفح إذا كان جهازك بشكبة.
            </div>

            <div class="footer">
                &copy; <?php echo date("Y"); ?> Gis Softech. جميع الحقوق محفوظة.<br>
                تم تسجيل الخروج في <?php echo date("h:i A"); ?> بتاريخ <?php echo date("Y/m/d"); ?>
            </div>
        </div>

        <!-- Left Side: Features List -->
        <div class="logout-left">
            <div class="company-info">
                <h3>نظام محاسبي متكامل</h3>
                <p>حلول محاسبية متطورة تواكب احتياجات عملك</p>
                
                <ul class="features-list">
                    <li><i class="fas fa-chart-line"></i> إدارة المخزون/الزبائن/الموردين</li>
                    <li><i class="fas fa-boxes"></i> إدارة المبيعات والمشتريات ونقاط البيع</li>
                    <li><i class="fas fa-handshake"></i> متعدد العملات/الشركات/المستودعات</li>
                    <li><i class="fas fa-calculator"></i> يشمل الاستاذ العام وادوات التصنيع ومراكز التكلفة</li>
                    <li><i class="fas fa-users"></i> يشمل تحليلات مالية وجداول احصائية</li>
	  <li><i class='fas fa-file-invoice'></i> نظام الاستاذ العام والقيود اليومية</li>
                    <li><i class="fas fa-file-invoice"></i>لوحة تحكم شاملة لكل شركة</li>
                </ul>
                
                <div class="copyright">
                   نرحب بكم من جديد في نظامنا الموحد<br>
                    نظام محاسبي موثوق وآمن
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add some interactive effects
            const logoutIcon = document.querySelector('.logout-icon');
            const loginBtn = document.querySelector('.login-again-btn');
            
            if (logoutIcon) {
                logoutIcon.addEventListener('mouseover', function() {
                    this.style.transform = 'scale(1.1)';
                });
                
                logoutIcon.addEventListener('mouseout', function() {
                    this.style.transform = 'scale(1)';
                });
            }
            
            if (loginBtn) {
                loginBtn.addEventListener('mouseover', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                loginBtn.addEventListener('mouseout', function() {
                    this.style.transform = 'translateY(0)';
                });
            }
            
            // Countdown timer
            let countdown = 30;
            const countdownElement = document.getElementById('countdown-number');
            const countdownContainer = document.getElementById('countdown');
            
            // تأكد من ظهور العنصر
            if (countdownContainer) {
                countdownContainer.style.display = 'block';
                countdownContainer.style.visibility = 'visible';
                countdownContainer.style.opacity = '1';
            }
            
            const countdownInterval = setInterval(function() {
                countdown--;
                if (countdownElement) {
                    countdownElement.textContent = countdown;
                }
                
                if (countdown <= 5) {
                    countdownElement.style.color = 'var(--error)';
                }
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    if (countdownContainer) {
                        countdownContainer.innerHTML = '<i class="fas fa-sync-alt"></i> جارٍ التوجيه الآن...';
                    }
                    setTimeout(function() {
                        window.location.href = '<?php echo $path_to_root; ?>/index.php';
                    }, 1000);
                }
            }, 1000);
            
            // Auto-redirect after 30 seconds
            setTimeout(function() {
                window.location.href = '<?php echo $path_to_root; ?>/index.php';
            }, 30000);
        });
    </script>
</body>
</html>
<?php
// Get the buffered content
$content = ob_get_clean();

// Output the content
echo $content;

// Clear session data - use minimal session cleanup
if (isset($_SESSION)) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
?>