<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>مميزات برنامج MacPro Accounting</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body {
        font-family: 'Tahoma', Arial, sans-serif;
        margin: 0;
        background-color: #f3f6fa;
        color: #333;
        line-height: 1.8;
        direction: rtl;
    }
    header {
        background-color: #003366;
        color: #fff;
        padding: 20px 40px;
        text-align: center;
    }
    header h1 {
        margin: 0;
        font-size: 28px;
        letter-spacing: 1px;
    }
    nav {
        background-color: #004080;
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    nav a {
        color: #fff;
        text-decoration: none;
        padding: 12px 18px;
        display: block;
        font-weight: bold;
        transition: background 0.3s;
    }
    nav a:hover {
        background-color: #0066cc;
    }
    main {
        max-width: 1100px;
        margin: 40px auto;
        background: #fff;
        padding: 30px 40px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        border-radius: 12px;
    }
    h2 {
        color: #004080;
        border-bottom: 2px solid #004080;
        padding-bottom: 8px;
        margin-top: 40px;
    }
    ul {
        list-style-type: square;
        padding-right: 25px;
    }
    li {
        margin-bottom: 6px;
    }
    footer {
        background-color: #003366;
        color: #fff;
        text-align: center;
        padding: 15px;
        margin-top: 40px;
        font-size: 14px;
    }
    @media (max-width: 768px) {
        main {
            padding: 20px;
        }
        header h1 {
            font-size: 22px;
        }
        nav a {
            padding: 10px;
            font-size: 14px;
        }
    }
</style>
</head>
<body>

<header>
    <h1>مميزات برنامج FrontAccounting</h1>
</header>

<nav>
    <a href="#sales">المبيعات</a>
    <a href="#purchases">المشتريات</a>
    <a href="#inventory">المخزون</a>
    <a href="#manufacturing">التصنيع</a>
    <a href="#assets">الأصول الثابتة</a>
    <a href="#ledger">دفتر الأستاذ</a>
    <a href="#reports">التقارير</a>
    <a href="#features">ميزات متقدمة</a>
</nav>

<main>

<section id="sales">
<h2>المبيعات وحسابات القبض</h2>
<ul>
<li>فتح حسابات العملاء وفروع العملاء.</li>
<li>إنشاء مناطق البيع وأنواع المبيعات ومجموعات المبيعات ومندوبي المبيعات.</li>
<li>تحضير عروض الأسعار وأوامر المبيعات وملاحظات التسليم.</li>
<li>إعداد الفواتير والإشعارات الدائنة.</li>
<li>إصدار فواتير مجمعة لعدة أوامر تسليم.</li>
<li>كل مستندات المبيعات قابلة للتحرير والطباعة.</li>
<li>تعريف نقاط البيع للمبيعات النقدية.</li>
<li>مدفوعات العملاء وتخصيصها.</li>
<li>إرفاق شعار الشركة في المستندات.</li>
<li>اختيار الأبعاد في أوامر التسليم والفواتير.</li>
<li>إضافة تكاليف الشحن والنصوص القانونية في الفواتير.</li>
</ul>
</section>

<section id="purchases">
<h2>المشتريات وحسابات الدائنين</h2>
<ul>
<li>فتح حسابات الموردين.</li>
<li>إعداد أوامر الشراء وملاحظات استلام البضائع وشروط الدفع.</li>
<li>تسوية ملاحظات الاستلام.</li>
<li>إدخال قوائم أسعار الموردين ومعاملات التحويل.</li>
<li>إعداد ملاحظات الخصم وتسجيل إشعارات الموردين.</li>
<li>مدفوعات الموردين وتخصيصها.</li>
<li>إرفاق شعار الشركة بالمستندات.</li>
<li>اختيار الأبعاد لفواتير وأوامر الشراء.</li>
<li>إرفاق المستندات الممسوحة ضوئيًا بالمعاملات.</li>
</ul>
</section>

<section id="inventory">
<h2>المخزون والمستودعات</h2>
<ul>
<li>تسجيل الأصناف وتحديد الفئات والمواقع.</li>
<li>تسجيل التحويلات والتعديلات.</li>
<li>تحديد مستويات إعادة الطلب.</li>
<li>احتساب متوسط التكلفة تلقائياً.</li>
<li>تطبيق التكاليف الإضافية والمصنعية.</li>
<li>تسجيل الأكواد الأجنبية لأجهزة الباركود.</li>
</ul>
</section>

<section id="manufacturing">
<h2>التصنيع</h2>
<ul>
<li>إعداد قوائم المواد ومراكز العمل وأوامر التشغيل.</li>
<li>إضافة خصائص الإنتاج المتقدمة والتجميع.</li>
</ul>
</section>

<section id="assets">
<h2>الأصول الثابتة</h2>
<ul>
<li>شراء ونقل وبيع الأصول الثابتة.</li>
<li>تحركات الأصول واستفساراتها.</li>
<li>عمليات الإهلاك وتصنيفات الأصول.</li>
</ul>
</section>

<section id="ledger">
<h2>دفتر الأستاذ العام</h2>
<ul>
<li>فتح الحسابات والفئات والمجموعات.</li>
<li>تسجيل القيود والميزانيات.</li>
<li>تقارير تفصيلية مع خاصية التتبع.</li>
<li>إغلاق السنة المالية وترحيل الأرباح.</li>
<li>تسجيل قيود الإهلاك.</li>
<li>إدخالات سريعة للمعاملات المتكررة.</li>
</ul>
</section>

<section id="reports">
<h2>التقارير</h2>
<ul>
<li>طباعة وتحويل التقارير إلى PDF أو Excel.</li>
<li>إرسال المستندات بالبريد الإلكتروني.</li>
<li>تحليل بياني (أعمدة، خطوط، دوائر).</li>
<li>حفظ تفضيلات التقارير.</li>
</ul>
</section>

<section id="features">
<h2>ميزات متقدمة</h2>
<ul>
<li>دعم العملات المتعددة وأسعار الصرف التاريخية.</li>
<li>حسابات بنكية متعددة العملات.</li>
<li>نظام متقدم لضريبة القيمة المضافة (VAT / GST).</li>
<li>تقارير ضريبة متقدمة.</li>
<li>دعم لغات متعددة واتجاه من اليمين لليسار.</li>
<li>دعم التقويم الهجري والجلالي.</li>
<li>إمكانية تخصيص مظهر ملفات PDF.</li>
<li>إضافة المشاريع ومراكز التكلفة عبر الأبعاد.</li>
</ul>
</section>

</main>

<footer>
<p>حقوق النشر © 2025 - 2025 فريق MacPro Accounting</p>
</footer>

</body>
</html>
