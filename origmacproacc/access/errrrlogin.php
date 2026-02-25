<?php
    if (!isset($path_to_root) || isset($_GET['path_to_root']) || isset($_POST['path_to_root']))
        die(_("Restricted access"));
    include_once($path_to_root . "/includes/ui.inc");
    include_once($path_to_root . "/includes/page/header.inc");

    // Get company name from URL parameter
    $company_name_from_url = isset($_GET['company']) ? $_GET['company'] : '';
    
    // Define available companies and their details
    $available_companies = array(
        'softech' => array(
            'id' => 0,
            'name' => 'Softech',
            'display_name' => 'شركتكم'
        ),
        'company2' => array(
            'id' => 1,
            'name' => 'Company2',
            'display_name' => 'الشركة الثانية'
        ),
        'company3' => array(
            'id' => 2,
            'name' => 'Company3',
            'display_name' => 'الشركة الثالثة'
        )
        // Add more companies as needed
    );
    
    // Validate company name from URL or use default
    if (!empty($company_name_from_url) && array_key_exists($company_name_from_url, $available_companies)) {
        $company_info = $available_companies[$company_name_from_url];
        $fixed_company_name = $company_info['display_name'];
        $company_id = $company_info['id'];
    } else {
        // Default company if none specified or invalid
        $company_info = $available_companies['softech'];
        $fixed_company_name = $company_info['display_name'];
        $company_id = $company_info['id'];
    }

    $js = "<script language='JavaScript' type='text/javascript'>
function defaultCompany()
{
    document.forms[0].company_login_name.options[".user_company()."].selected = true;
}
</script>";

    add_js_file('login.js');
    
    // Display demo user name and password within login form if allow_demo_mode option is true
    if ($SysPrefs->allow_demo_mode == true)
    {
        $demo_text = _("الدخول كمسخدم: demouser وكلمة المرور: password");
    }
    else
    {
        $demo_text = _("يرجى تسجيل الدخول هنا");
        if (@$SysPrefs->allow_password_reset) {
            $demo_text .= " "._("أو")." <a href='$path_to_root/index.php?reset=1&company=".$company_name_from_url."'>"._("طلب كلمة مرور جديدة")."</a>";
        }
    }

    if (check_faillog())
    {
        $blocked = true;
        $js .= "<script>setTimeout(function() {
            document.getElementsByName('SubmitUser')[0].disabled=0;
            document.getElementById('log_msg').innerHTML='$demo_text'}, 1000*".$SysPrefs->login_delay.");</script>";
        $demo_text = '<span class="redfg">'._('عدد كبير جداً من محاولات الدخول الفاشلة.<br>يرجى الانتظار قليلاً أو المحاولة لاحقاً.').'</span>';
    } elseif ($_SESSION["wa_current_user"]->login_attempt > 1) {
        $demo_text = '<span class="redfg">'._("كلمة المرور أو اسم المستخدم غير صحيح. يرجى المحاولة مرة أخرى.").'</span>';
    }

    flush_dir(user_js_cache());
    if (!isset($def_coy))
        $def_coy = 0;
    $def_theme = "default";

    $login_timeout = $_SESSION["wa_current_user"]->last_act;

    $title = $login_timeout ? _('انتهت جلسة العمل') : $SysPrefs->app_title." ".$version." - "._("تسجيل الدخول");
    $encoding = isset($_SESSION['language']->encoding) ? $_SESSION['language']->encoding : "iso-8859-1";
    $rtl = isset($_SESSION['language']->dir) ? $_SESSION['language']->dir : "ltr";
    $onload = !$login_timeout ? "onload='defaultCompany()'" : "";

    echo "<!DOCTYPE html>\n";
    echo "<html lang='ar' dir='ltr'>\n";
    echo "<head>\n";
    echo "    <meta charset='UTF-8'>\n";
    echo "    <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "    <title>شاشة الدخول للنظام المحاسبي - " . $fixed_company_name . "</title>\n";
    echo "    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>\n";
    echo "    <link href='https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap' rel='stylesheet'>\n";
    echo "    <style>\n";
    echo "        * {\n";
    echo "            margin: 0;\n";
    echo "            padding: 0;\n";
    echo "            box-sizing: border-box;\n";
    echo "            font-family: 'Droid Arabic Kufi', 'Noto Sans Arabic', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;\n";
    echo "        }\n";
    echo "\n";
    echo "        @font-face {\n";
    echo "            font-family: 'Droid Arabic Kufi';\n";
    echo "            src: url('https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap');\n";
    echo "            font-weight: normal;\n";
    echo "            font-style: normal;\n";
    echo "        }\n";
    echo "\n";
    echo "        :root {\n";
    echo "            --primary: #4361ee;\n";
    echo "            --secondary: #3a0ca3;\n";
    echo "            --accent: #4cc9f0;\n";
    echo "            --light: #f8f9fa;\n";
    echo "            --dark: #212529;\n";
    echo "            --success: #4bb543;\n";
    echo "            --error: #e63946;\n";
    echo "            --gray: #6c757d;\n";
    echo "        }\n";
    echo "\n";
    echo "        body {\n";
    echo "            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);\n";
    echo "            min-height: 100vh;\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            justify-content: center;\n";
    echo "            padding: 15px;\n";
    echo "            position: relative;\n";
    echo "            overflow: hidden;\n";
    echo "        }\n";
    echo "\n";
    echo "        body::before {\n";
    echo "            content: '';\n";
    echo "            position: absolute;\n";
    echo "            top: -50%;\n";
    echo "            left: -50%;\n";
    echo "            width: 200%;\n";
    echo "            height: 200%;\n";
    echo "            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);\n";
    echo "            background-size: 30px 30px;\n";
    echo "            animation: float 20s linear infinite;\n";
    echo "            z-index: 0;\n";
    echo "        }\n";
    echo "\n";
    echo "        @keyframes float {\n";
    echo "            0% { transform: translate(0, 0) rotate(0deg); }\n";
    echo "            100% { transform: translate(-30px, -30px) rotate(360deg); }\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-container {\n";
    echo "            display: flex;\n";
    echo "            width: 100%;\n";
    echo "            max-width: 900px;\n";
    echo "            max-height: 600px;\n";
    echo "            background: rgba(255, 255, 255, 0.95);\n";
    echo "            border-radius: 15px;\n";
    echo "            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);\n";
    echo "            overflow: hidden;\n";
    echo "            backdrop-filter: blur(10px);\n";
    echo "            border: 1px solid rgba(255, 255, 255, 0.3);\n";
    echo "            position: relative;\n";
    echo "            z-index: 1;\n";
    echo "            animation: fadeIn 0.8s ease-out;\n";
    echo "        }\n";
    echo "\n";
    echo "        @keyframes fadeIn {\n";
    echo "            from { opacity: 0; transform: translateY(20px); }\n";
    echo "            to { opacity: 1; transform: translateY(0); }\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-left {\n";
    echo "            flex: 1;\n";
    echo "            padding: 30px;\n";
    echo "            display: flex;\n";
    echo "            flex-direction: column;\n";
    echo "            justify-content: center;\n";
    echo "            overflow-y: auto;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-right {\n";
    echo "            flex: 1;\n";
    echo "            background: linear-gradient(135deg, var(--primary), var(--secondary));\n";
    echo "            color: white;\n";
    echo "            padding: 30px;\n";
    echo "            display: flex;\n";
    echo "            flex-direction: column;\n";
    echo "            justify-content: center;\n";
    echo "            position: relative;\n";
    echo "            overflow: hidden;\n";
    echo "            overflow-y: auto;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-right::before {\n";
    echo "            content: '';\n";
    echo "            position: absolute;\n";
    echo "            top: -50%;\n";
    echo "            right: -50%;\n";
    echo "            width: 100%;\n";
    echo "            height: 200%;\n";
    echo "            background: rgba(255, 255, 255, 0.1);\n";
    echo "            transform: rotate(30deg);\n";
    echo "        }\n";
    echo "\n";
    echo "        .logo-container {\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            margin-bottom: 20px;\n";
    echo "            flex-direction: row-reverse;\n";
    echo "        }\n";
    echo "\n";
    echo "        .logo {\n";
    echo "            width: 60px;\n";
    echo "            height: 60px;\n";
    echo "            background: linear-gradient(135deg, var(--primary), var(--secondary));\n";
    echo "            border-radius: 12px;\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            justify-content: center;\n";
    echo "            margin-left: 15px;\n";
    echo "            color: white;\n";
    echo "            font-size: 24px;\n";
    echo "            font-weight: bold;\n";
    echo "            box-shadow: 0 3px 10px rgba(67, 97, 238, 0.3);\n";
    echo "        }\n";
    echo "\n";
    echo "        .logo-text h1 {\n";
    echo "            font-size: 24px;\n";
    echo "            color: var(--dark);\n";
    echo "            margin-bottom: 5px;\n";
    echo "            background: linear-gradient(135deg, var(--primary), var(--secondary));\n";
    echo "            -webkit-background-clip: text;\n";
    echo "            -webkit-text-fill-color: transparent;\n";
    echo "            line-height: 1.3;\n";
    echo "        }\n";
    echo "\n";
    echo "        .logo-text p {\n";
    echo "            color: var(--gray);\n";
    echo "            font-size: 14px;\n";
    echo "            font-weight: 500;\n";
    echo "            line-height: 1.4;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-header {\n";
    echo "            margin-bottom: 25px;\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-header h2 {\n";
    echo "            font-size: 28px;\n";
    echo "            color: var(--dark);\n";
    echo "            margin-bottom: 8px;\n";
    echo "            line-height: 1.3;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-header p {\n";
    echo "            color: var(--gray);\n";
    echo "            font-size: 14px;\n";
    echo "            line-height: 1.5;\n";
    echo "        }\n";
    echo "\n";
    echo "        .company-badge {\n";
    echo "            display: inline-block;\n";
    echo "            background: linear-gradient(135deg, var(--primary), var(--secondary));\n";
    echo "            color: white;\n";
    echo "            padding: 6px 12px;\n";
    echo "            border-radius: 15px;\n";
    echo "            font-size: 12px;\n";
    echo "            font-weight: 600;\n";
    echo "            margin-top: 8px;\n";
    echo "            box-shadow: 0 2px 8px rgba(67, 97, 238, 0.2);\n";
    echo "        }\n";
    echo "\n";
    echo "        .form-group {\n";
    echo "            margin-bottom: 20px;\n";
    echo "            position: relative;\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .form-group label {\n";
    echo "            display: block;\n";
    echo "            margin-bottom: 6px;\n";
    echo "            color: #555;\n";
    echo "            font-weight: 600;\n";
    echo "            font-size: 14px;\n";
    echo "        }\n";
    echo "\n";
    echo "        .input-with-icon {\n";
    echo "            position: relative;\n";
    echo "        }\n";
    echo "\n";
    echo "        .input-with-icon i {\n";
    echo "            position: absolute;\n";
    echo "            right: 12px;\n";
    echo "            top: 50%;\n";
    echo "            transform: translateY(-50%);\n";
    echo "            color: #999;\n";
    echo "            z-index: 1;\n";
    echo "        }\n";
    echo "\n";
    echo "        .form-control {\n";
    echo "            width: 100%;\n";
    echo "            padding: 12px 40px 12px 12px;\n";
    echo "            border: 2px solid #e1e5ee;\n";
    echo "            border-radius: 10px;\n";
    echo "            font-size: 14px;\n";
    echo "            transition: all 0.3s ease;\n";
    echo "            background: #f8f9fa;\n";
    echo "            position: relative;\n";
    echo "            text-align: right;\n";
    echo "            direction: rtl;\n";
    echo "        }\n";
    echo "\n";
    echo "        .form-control:focus {\n";
    echo "            outline: none;\n";
    echo "            border-color: var(--primary);\n";
    echo "            background: white;\n";
    echo "            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);\n";
    echo "            transform: translateY(-1px);\n";
    echo "        }\n";
    echo "\n";
    echo "        .btn-login {\n";
    echo "            width: 100%;\n";
    echo "            padding: 12px;\n";
    echo "            background: linear-gradient(135deg, var(--primary), var(--secondary));\n";
    echo "            border: none;\n";
    echo "            border-radius: 10px;\n";
    echo "            color: white;\n";
    echo "            font-size: 16px;\n";
    echo "            font-weight: 600;\n";
    echo "            cursor: pointer;\n";
    echo "            transition: all 0.3s ease;\n";
    echo "            letter-spacing: 0;\n";
    echo "            margin-top: 10px;\n";
    echo "            position: relative;\n";
    echo "            overflow: hidden;\n";
    echo "        }\n";
    echo "\n";
    echo "        .btn-login::before {\n";
    echo "            content: '';\n";
    echo "            position: absolute;\n";
    echo "            top: 0;\n";
    echo "            left: -100%;\n";
    echo "            width: 100%;\n";
    echo "            height: 100%;\n";
    echo "            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);\n";
    echo "            transition: 0.5s;\n";
    echo "        }\n";
    echo "\n";
    echo "        .btn-login:hover::before {\n";
    echo "            left: 100%;\n";
    echo "        }\n";
    echo "\n";
    echo "        .btn-login:hover {\n";
    echo "            transform: translateY(-2px);\n";
    echo "            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);\n";
    echo "        }\n";
    echo "\n";
    echo "        .btn-login:active {\n";
    echo "            transform: translateY(0);\n";
    echo "        }\n";
    echo "\n";
    echo "        .alert {\n";
    echo "            padding: 12px;\n";
    echo "            border-radius: 8px;\n";
    echo "            margin-bottom: 15px;\n";
    echo "            font-weight: 500;\n";
    echo "            animation: slideIn 0.5s ease-out;\n";
    echo "            text-align: right;\n";
    echo "            font-size: 13px;\n";
    echo "        }\n";
    echo "\n";
    echo "        @keyframes slideIn {\n";
    echo "            from { opacity: 0; transform: translateX(-20px); }\n";
    echo "            to { opacity: 1; transform: translateX(0); }\n";
    echo "        }\n";
    echo "\n";
    echo "        .alert-error {\n";
    echo "            background: #ffe6e6;\n";
    echo "            color: var(--error);\n";
    echo "            border-left: 4px solid var(--error);\n";
    echo "        }\n";
    echo "\n";
    echo "        .alert-success {\n";
    echo "            background: #e6f7e6;\n";
    echo "            color: var(--success);\n";
    echo "            border-left: 4px solid var(--success);\n";
    echo "        }\n";
    echo "\n";
    echo "        .additional-links {\n";
    echo "            display: flex;\n";
    echo "            justify-content: space-between;\n";
    echo "            margin-top: 20px;\n";
    echo "            padding-top: 15px;\n";
    echo "            border-top: 1px solid #eee;\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .additional-links a {\n";
    echo "            color: var(--primary);\n";
    echo "            text-decoration: none;\n";
    echo "            font-weight: 500;\n";
    echo "            transition: color 0.3s ease;\n";
    echo "            font-size: 13px;\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            flex-direction: row-reverse;\n";
    echo "        }\n";
    echo "\n";
    echo "        .additional-links a i {\n";
    echo "            margin-left: 5px;\n";
    echo "            margin-right: 0;\n";
    echo "        }\n";
    echo "\n";
    echo "        .additional-links a:hover {\n";
    echo "            color: var(--secondary);\n";
    echo "            text-decoration: underline;\n";
    echo "        }\n";
    echo "\n";
    echo "        .company-info {\n";
    echo "            position: relative;\n";
    echo "            z-index: 1;\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .company-info h3 {\n";
    echo "            font-size: 22px;\n";
    echo "            margin-bottom: 15px;\n";
    echo "            position: relative;\n";
    echo "            display: inline-block;\n";
    echo "            line-height: 1.3;\n";
    echo "        }\n";
    echo "\n";
    echo "        .company-info h3::after {\n";
    echo "            content: '';\n";
    echo "            position: absolute;\n";
    echo "            bottom: -8px;\n";
    echo "            right: 0;\n";
    echo "            width: 40px;\n";
    echo "            height: 2px;\n";
    echo "            background: var(--accent);\n";
    echo "        }\n";
    echo "\n";
    echo "        .features-list {\n";
    echo "            list-style: none;\n";
    echo "            margin-top: 20px;\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .features-list li {\n";
    echo "            margin-bottom: 12px;\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            font-size: 13px;\n";
    echo "            flex-direction: row-reverse;\n";
    echo "            line-height: 1.4;\n";
    echo "        }\n";
    echo "\n";
    echo "        .features-list i {\n";
    echo "            margin-left: 8px;\n";
    echo "            margin-right: 0;\n";
    echo "            background: rgba(255, 255, 255, 0.2);\n";
    echo "            width: 25px;\n";
    echo "            height: 25px;\n";
    echo "            border-radius: 50%;\n";
    echo "            display: flex;\n";
    echo "            align-items: center;\n";
    echo "            justify-content: center;\n";
    echo "            font-size: 12px;\n";
    echo "        }\n";
    echo "\n";
    echo "        .copyright {\n";
    echo "            margin-top: 25px;\n";
    echo "            font-size: 11px;\n";
    echo "            opacity: 0.8;\n";
    echo "            text-align: center;\n";
    echo "            line-height: 1.4;\n";
    echo "        }\n";
    echo "\n";
    echo "        .password-toggle {\n";
    echo "            position: absolute;\n";
    echo "            left: 12px;\n";
    echo "            top: 50%;\n";
    echo "            transform: translateY(-50%);\n";
    echo "            background: none;\n";
    echo "            border: none;\n";
    echo "            color: #999;\n";
    echo "            cursor: pointer;\n";
    echo "            font-size: 13px;\n";
    echo "            z-index: 2;\n";
    echo "        }\n";
    echo "\n";
    echo "        .system-info {\n";
    echo "            background: rgba(255, 255, 255, 0.7);\n";
    echo "            padding: 8px 12px;\n";
    echo "            border-radius: 6px;\n";
    echo "            margin-top: 15px;\n";
    echo "            font-size: 11px;\n";
    echo "            color: var(--gray);\n";
    echo "            border-left: 3px solid var(--primary);\n";
    echo "            text-align: right;\n";
    echo "        }\n";
    echo "\n";
    echo "        .redfg {\n";
    echo "            color: var(--error) !important;\n";
    echo "            font-weight: 500;\n";
    echo "        }\n";
    echo "\n";
    echo "        @media (max-width: 768px) {\n";
    echo "            .login-container {\n";
    echo "                flex-direction: column;\n";
    echo "                max-height: none;\n";
    echo "                max-width: 95%;\n";
    echo "            }\n";
    echo "            \n";
    echo "            .login-right {\n";
    echo "                order: -1;\n";
    echo "                padding: 20px;\n";
    echo "            }\n";
    echo "            \n";
    echo "            .login-left {\n";
    echo "                padding: 20px;\n";
    echo "            }\n";
    echo "            \n";
    echo "            .logo-text h1 {\n";
    echo "                font-size: 20px;\n";
    echo "            }\n";
    echo "            \n";
    echo "            .login-header h2 {\n";
    echo "                font-size: 24px;\n";
    echo "            }\n";
    echo "        }\n";
    echo "\n";
    echo "        /* Scrollbar styling */\n";
    echo "        .login-left::-webkit-scrollbar,\n";
    echo "        .login-right::-webkit-scrollbar {\n";
    echo "            width: 6px;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-left::-webkit-scrollbar-track,\n";
    echo "        .login-right::-webkit-scrollbar-track {\n";
    echo "            background: #f1f1f1;\n";
    echo "            border-radius: 3px;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-left::-webkit-scrollbar-thumb,\n";
    echo "        .login-right::-webkit-scrollbar-thumb {\n";
    echo "            background: #c1c1c1;\n";
    echo "            border-radius: 3px;\n";
    echo "        }\n";
    echo "\n";
    echo "        .login-left::-webkit-scrollbar-thumb:hover,\n";
    echo "        .login-right::-webkit-scrollbar-thumb:hover {\n";
    echo "            background: #a8a8a8;\n";
    echo "        }\n";
    echo "    </style>\n";
    echo "</head>\n";
    echo "<body>\n";
    echo "    <div class='login-container'>\n";
    echo "        <div class='login-left'>\n";
    echo "            <div class='logo-container'>\n";
    echo "                <div class='logo'>GS</div>\n";
    echo "                <div class='logo-text'>\n";
    echo "                    <div class='company-badge'>         النظام المحاسبي الموحد ".$fixed_company_name."</div>\n";
    echo "                </div>\n";
    echo "            </div>\n";
    echo "            <div class='login-header'>\n";
    echo "                <h2>مرحبا بكم من جديد</h2>\n";
    echo "                <p>لطفاً قم بتسجيل الدخول للنظام المحاسبي الموحد لشركة <strong>".$fixed_company_name."</strong></p>\n";
    echo "            </div>\n";
    echo "\n";
    
    // Display error messages if any
    if (check_faillog() || ($_SESSION["wa_current_user"]->login_attempt > 1 && !$login_timeout)) {
        echo '<div class="alert alert-error">' . $demo_text . '</div>';
    } elseif ($SysPrefs->allow_demo_mode == true) {
        echo '<div class="alert alert-success">' . $demo_text . '</div>';
    }
    
    $allow = SECURE_ONLY !== true ? true : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_NAME'] === "localhost";
    
    if (!$allow) {
        echo '<div class="alert alert-error">' . _("الوصول عبر HTTP غير مسموح به على هذا الموقع. هذا غير آمن. إذا كنت ترغب حقًا في الوصول إلى هذا الموقع غير الآمن ، فاضبط SECURE_ONLY على false في 

ملف /includes/session.inc.") . '</div>';
    }
    
    if ($login_timeout) {
        // Timeout login form
        start_form(false, false, $_SESSION['timeout']['uri'] . "&company=" . $company_name_from_url, "loginform");
        echo "                <div class='form-group'>\n";
        echo "                    <label>اسم المستخدم</label>\n";
        echo "                    <div class='input-with-icon'>\n";
        echo "                        <i class='fas fa-user'></i>\n";
        echo "                        <input type='text' name='user_name_entry_field' value='".$_SESSION['wa_current_user']->loginname."' class='form-control' readonly>\n";
        echo "                    </div>\n";
        echo "                </div>\n";
        echo "                <input type='hidden' name='company_login_name' value='".$company_id."'>\n";
        echo "                <button type='submit' class='btn-login'><i class='fas fa-sign-in-alt'></i> إعادة الدخول</button>\n";
        end_form();
    } else {
        // Normal login form
        if ($allow) {
            start_form(false, false, $_SESSION['timeout']['uri'] . "&company=" . $company_name_from_url, "loginform");
            
            // Username field
            $value = $login_timeout ? $_SESSION['wa_current_user']->loginname : ($SysPrefs->allow_demo_mode ? "demouser" : "");
            echo "                <div class='form-group'>\n";
            echo "                    <label for='user_name_entry_field'>اسم المستخدم</label>\n";
            echo "                    <div class='input-with-icon'>\n";
            echo "                        <i class='fas fa-user'></i>\n";
            echo "                        <input type='text' id='user_name_entry_field' name='user_name_entry_field' value='$value' class='form-control' placeholder='أدخل اسم المستخدم' 

required>\n";
            echo "                    </div>\n";
            echo "                </div>\n";
            
            // Password field
            $password = $SysPrefs->allow_demo_mode ? "password" : "";
            echo "                <div class='form-group'>\n";
            echo "                    <label for='password'>كلمة المرور</label>\n";
            echo "                    <div class='input-with-icon'>\n";
            echo "                        <i class='fas fa-lock'></i>\n";
            echo "                        <input type='password' id='password' name='password' value='$password' class='form-control' placeholder='أدخل كلمة المرور' required>\n";
            echo "                        <button type='button' class='password-toggle' onclick='togglePassword()'>\n";
            echo "                            <i class='fas fa-eye'></i>\n";
            echo "                        </button>\n";
            echo "                    </div>\n";
            echo "                </div>\n";
            
            // Fixed company selection based on URL parameter
            echo "                <input type='hidden' name='company_login_name' value='".$company_id."'>";
            echo "                <div class='system-info'>\n";
            echo "                    <i class='fas fa-info-circle'></i> أنت تصل إلى شركة <strong>".$fixed_company_name."</strong>\n";
            echo "                </div>\n";
            
            // Hidden fields
            echo "                <input type='hidden' id='ui_mode' name='ui_mode' value='".!fallback_mode()."'>\n";
            
            // Submit button
            echo "                <button type='submit' name='SubmitUser' class='btn-login'";
            echo isset($blocked) ? " disabled" : '';
            echo ">\n";
            echo "                    <i class='fas fa-sign-in-alt'></i> الدخول إلى شركة ".$fixed_company_name."\n";
            echo "                </button>\n";
            
            // Hidden fields for timeout data
            foreach($_SESSION['timeout']['post'] as $p => $val) {
                if (!in_array($p, array('ui_mode', 'user_name_entry_field', 'password', 'SubmitUser', 'company_login_name'))) {
                    if (!is_array($val))
                        echo "<input type='hidden' name='$p' value='$val'>";
                    else
                        foreach($val as $i => $v)
                            echo "<input type='hidden' name='{$p}[$i]' value='$v'>";
                }
            }
            
            end_form();
        }
    }
    
    echo "            <div class='additional-links'>\n";
    if (@$SysPrefs->allow_password_reset) {
        echo "                <a href='$path_to_root/index.php?reset=1&company=".$company_name_from_url."'><i class='fas fa-key'></i> نسيت كلمة المرور؟</a>";
    }
    echo "                <a href='#support'><i class='fas fa-question-circle'></i> شاشات المساعدة</a>\n";
    echo "            </div>\n";
    echo "        </div>\n";
    echo "\n";
    echo "        <div class='login-right'>\n";
    echo "            <div class='company-info'>\n";
    echo "                <h3>النظام المحاسبي الموحد - جملة</h3>\n";
    echo "                <p>نظام محاسبي متطور يشمل <strong>عمليات التصنيع وإدارة التكاليف</strong>. إدارة شركتكم ومصنعكم بطريقة احترافية.</p>\n";
    echo "                \n";
    echo "                <ul class='features-list'>\n";
    echo "                    <li><i class='fas fa-chart-line'></i> تقارير مالية شاملة وتحليلات</li>\n";
    echo "                    <li><i class='fas fa-boxes'></i> إدارة المخزون والمستودعات</li>\n";
    echo "                    <li><i class='fas fa-handshake'></i> إدارة المشتريات والمبيعات</li>\n";
    echo "                    <li><i class='fas fa-globe'></i> متعدد العملات واللغات</li>\n";
    echo "                    <li><i class='fas fa-file-invoice'></i> فواتير الشراء والبيع ونقطة بيع متطورة</li>\n";
    echo "                    <li><i class='fas fa-cogs'></i> لوحة تحكم متخصصة وبيانات هامة</li>\n";
    echo "                </ul>\n";
    echo "                \n";
    echo "                <div class='copyright'>\n";
    if (isset($_SESSION['wa_current_user'])) 
        $date = Today() . " | " . Now();
    else    
        $date = date("Y/m/d") . " | " . date("h:i a");
    echo "                    &copy; " . date("Y") . " النظام المحاسبي الموحد. " . $SysPrefs->app_title . " " . $version . ". جميع الحقوق محفوظة.<br>\n";
    echo "                    " . $date . "\n";
    echo "                </div>\n";
    echo "            </div>\n";
    echo "        </div>\n";
    echo "    </div>\n";
    echo "\n";
    echo "    <script>\n";
    echo "        // Password toggle functionality\n";
    echo "        function togglePassword() {\n";
    echo "            const passwordInput = document.getElementById('password');\n";
    echo "            const toggleIcon = document.querySelector('.password-toggle i');\n";
    echo "            \n";
    echo "            if (passwordInput.type === 'password') {\n";
    echo "                passwordInput.type = 'text';\n";
    echo "                toggleIcon.classList.remove('fa-eye');\n";
    echo "                toggleIcon.classList.add('fa-eye-slash');\n";
    echo "            } else {\n";
    echo "                passwordInput.type = 'password';\n";
    echo "                toggleIcon.classList.remove('fa-eye-slash');\n";
    echo "                toggleIcon.classList.add('fa-eye');\n";
    echo "            }\n";
    echo "        }\n";
    echo "\n";
    echo "        // Add focus effects\n";
    echo "        document.addEventListener('DOMContentLoaded', function() {\n";
    echo "            const inputs = document.querySelectorAll('.form-control');\n";
    echo "            inputs.forEach(input => {\n";
    echo "                input.addEventListener('focus', function() {\n";
    echo "                    this.parentElement.classList.add('focused');\n";
    echo "                });\n";
    echo "                \n";
    echo "                input.addEventListener('blur', function() {\n";
    echo "                    if (this.value === '') {\n";
    echo "                        this.parentElement.classList.remove('focused');\n";
    echo "                    }\n";
    echo "                });\n";
    echo "            });\n";
    echo "            \n";
    echo "            // Add floating animation to elements\n";
    echo "            const logo = document.querySelector('.logo');\n";
    echo "            if (logo) {\n";
    echo "                logo.style.animation = 'float 6s ease-in-out infinite';\n";
    echo "            }\n";
    echo "            \n";
    echo "            // Set focus to username field\n";
    echo "            if (document.forms.length && document.forms[0].user_name_entry_field) {\n";
    echo "                document.forms[0].user_name_entry_field.focus();\n";
    echo "            }\n";
    echo "        });\n";
    echo "    </script>\n";
    echo "</body>\n";
    echo "</html>\n";
?>