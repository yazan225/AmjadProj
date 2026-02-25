<?php
// features_ar.php - صفحة الأسئلة الشائعة لبرنامج FrontAccounting بالعربية
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>الأسئلة الشائعة – MacPro Acc</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
<style>
body {
    background: #f8f9fa;
    font-family: "Tajawal", "Cairo", sans-serif;
}
.header {
    background: linear-gradient(90deg, #007bff, #00c6ff);
    color: white;
    padding: 40px 0;
    text-align: center;
    margin-bottom: 30px;
}
.accordion-button {
    font-weight: bold;
    color: #0056b3;
}
footer {
    text-align: center;
    padding: 20px;
    margin-top: 50px;
    background: #fff;
    border-top: 1px solid #ddd;
    color: #555;
}
</style>
</head>
<body>

<div class="header">
    <h1>الأسئلة الشائعة حول MacPro Acc</h1>
    <p>إجابات على أكثر الأسئلة شيوعًا حول التثبيت، الترخيص، التطوير، والمزيد.</p>
</div>

<div class="container mb-5">

    <div class="accordion" id="faqAccordion">

        <!-- القسم 0: أسئلة عامة -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading0">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse0">
                    0. أسئلة عامة
                </button>
            </h2>
            <div id="collapse0" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body">                 

                    <h5>0.2 واجهت سلوكًا غريبًا دون رسائل خطأ!</h5>
                    <p>
                        أولاً تحقق من المنتدى والويكي. إذا لم تجد شيئًا، يمكنك تفعيل المتغير <code>$go_debug</code> في بداية ملف <code>config.php</code>.
                        هذا سيساعدك في عرض التحذيرات أو الأخطاء الخفية.
                    </p>

                    <h5>0.3 وجدت خطأ (Bug)، ماذا أفعل؟</h5>
                    <p>
                        تأكد أولاً أنه ليس ميزة متعمدة. افحص المنتدى، وإن تأكدت أنه خطأ فعلي، يمكنك الإبلاغ عنه في نظام
                        <strong>Mantis bugtracking</strong> أو في منتدى الأخطاء.
                    </p>

                    <h5>0.4 أحتاج تعديلات مخصصة!</h5>
                    <p>
                        تحقق أولاً من صفحة الإضافات في قسم الإعدادات. إن لم تجد ما يناسبك، يمكنك كتابة الإضافة بنفسك إذا كنت مبرمج PHP،
                        أو طرح طلبك في منتدى الوظائف.
                    </p>

                                </div>
            </div>
        </div>

        <!-- القسم 1: التثبيت -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading1">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                    1. التثبيت
                </button>
            </h2>
            <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    <h5>1.1 أول مرة أُثبّت فيها البرنامج، كيف أبدأ؟</h5>
                    <p>
                        قم بفك ضغط الحزمة داخل مجلد في خادمك، ثم أنشئ قاعدة بيانات جديدة.
                        بعد ذلك، افتح الرابط في متصفحك واتبع الخطوات في ملف <code>install.html</code>.
                    </p>

                    <h5>1.2 حدث خطأ أثناء التثبيت!</h5>
                    <p>
                        تحقق من منتدى التثبيت لمعرفة إن كان الخطأ قد نوقش مسبقًا، أو أضف سؤالك هناك.
                    </p>

                    <h5>1.3 كيف أُحدّث النسخة الحالية؟</h5>
                    <p>
                        خذ نسخة احتياطية من قاعدة البيانات، ثم استبدل الملفات بالنسخة الجديدة.
                        بعد ذلك استخدم خيار "ترقية النظام" من قائمة الإعدادات.
                    </p>

                    <h5>1.4 فشل التحديث!</h5>
                    <p>
                        أعد قاعدة البيانات من النسخة الاحتياطية وجرب التحديث مجددًا باستخدام خيار "الترقية القسرية".
                    </p>
                </div>
            </div>
        </div>

        <!-- القسم 2: العمل مع النظام -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading2">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                    2. العمل مع MacPro Acc
                </button>
            </h2>
            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                    <h5>2.1 أضفت عميلًا أو موردًا جديدًا لكنه لا يظهر في القائمة!</h5>
                    <p>
                        ادخل إلى الإعدادات &gt; إعدادات الشركة، وتأكد أن خيار "قائمة البحث" غير مفعّل.
                        يمكنك بعد ذلك الضغط على مفتاح المسافة للبحث أو كتابة جزء من الاسم.
                    </p>
                </div>
            </div>
        </div>

        <!-- القسم 3: التطوير -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading3">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                    3. التطوير
                </button>
            </h2>
            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">

                    <h5>3.1 أريد المساهمة في تطوير البرنامج!</h5>
                    <p>
                        يمكنك الاشتراك في قائمة المطورين البريدية وشرح خبرتك عبر صفحة "اتصل بنا".
                    </p>

                    <h5>3.2 متى تصدر النسخ الجديدة؟</h5>
                    <p>
                        الإصدارات الصغيرة تصدر عادةً بعد إصلاح بعض الأخطاء كل شهر تقريبًا.
                        أما الإصدارات الكبرى فتتطلب مراحل تجريبية متعددة.
                    </p>

                    <h5>3.3 لا أملك صلاحية كتابة على المستودع (Mercury HG)!</h5>
                    <p>
                        فقط فريق المطورين الأساسي يملك صلاحية الكتابة لحفظ استقرار الكود، لكن يمكنك تنزيل نسخة محلية وتقديم التعديلات عبر البريد.
                    </p>

                    <h5>3.4 طوّرت إضافة جديدة، كيف أشاركها؟</h5>
                    <p>
                        أرسلها إلى البريد الخاص بالمساهمات أو إلى أحد المطورين عبر القائمة البريدية.
                        القرار النهائي بالدمج يعود للفريق الأساسي.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<footer>
    <p>© 2025 MacPro Accounting </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
