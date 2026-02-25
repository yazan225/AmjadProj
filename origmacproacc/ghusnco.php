<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ديجيتال لايف - متجر الكتروني</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* General Styles - الأنماط العامة للصفحة */
        body {
            font-family: 'Cairo', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            line-height: 1.6;
            color: #333;
            direction: rtl; /* اتجاه النص من اليمين لليسار */
            text-align: right; /* محاذاة النص الافتراضية لليمين */
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Header / Navbar - شريط التنقل العلوي */
        .header {
            background-color: #fff;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky; /* يظل شريط التنقل ثابتًا عند التمرير */
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: #007bff;
        }

        .nav-links {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
        }

        .nav-links li {
            margin-right: 30px;
        }

        .nav-links li:last-child {
            margin-right: 0;
        }

        .nav-links a {
            color: #555;
            font-weight: 400;
            transition: color 0.3s ease;
        }

        .nav-links a:hover {
            color: #007bff;
        }

        .auth-links {
            display: flex;
            align-items: center;
        }

        .auth-links .login-btn {
            color: #007bff;
            padding: 10px 15px;
            margin-left: 15px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .auth-links .login-btn:hover {
            background-color: #eaf5ff;
        }

        .auth-links .get-store-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .auth-links .get-store-btn:hover {
            background-color: #218838;
        }

        /* Large Image Slider Section - سلايدر الصور الكبير (يتحرك من اليمين لليسار) */
        .image-slider-section {
            padding: 0;
            background-color: #f8f9fa;
        }

        .large-image-slider-container {
            max-width: 100%;
            margin: 0;
            overflow: hidden;
            position: relative;
        }

        .large-image-slider {
            display: flex;
            animation: slideImagesRTL 40s infinite linear; /* حركة تلقائية مستمرة - تم تغيير 20s إلى 40s */
            width: fit-content; /* يسمح للمحتوى بالتجاوز للحلقة السلسة */
        }

        .large-image-slide {
            min-width: 100vw; /* كل شريحة تأخذ عرض الشاشة بالكامل */
            box-sizing: border-box;
            height: 500px; /* ارتفاع ثابت للشرائح */
            display: flex;
            justify-content: center;
            align-items: center;
            background-size: cover; /* تغطية الشريحة بالكامل بالصورة */
            background-position: center; /* توسيط الصورة */
            color: white;
            font-size: 2.5em;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5); /* ظل للنص ليظهر بوضوح */
        }

        /* هنا قم بتغيير روابط هذه الصور إلى صورك الحقيقية */
        /* تأكد أن الصور التي تضعها ذات جودة عالية وتتناسب مع الأبعاد 1920x600 أو أكبر */
        .large-image-slide:nth-child(1), .large-image-slide:nth-child(5) {
            background-image: url('web/1.jpg'); /* الصورة 1 */
        }
        .large-image-slide:nth-child(2), .large-image-slide:nth-child(6) {
            background-image: url('web/2.jpg'); /* الصورة 2 */
        }
        .large-image-slide:nth-child(3), .large-image-slide:nth-child(7) {
            background-image: url('web/3.jpg'); /* الصورة 3 (تم التصحيح هنا) */
        }
        .large-image-slide:nth-child(4), .large-image-slide:nth-child(8) {
            background-image: url('web/4.jpg'); /* الصورة 4 (تم التصحيح هنا) */
        }

        /* حركة السلايدر من اليمين لليسار */
        @keyframes slideImagesRTL {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); } /* التحرك لليسار */
        }

        /* إخفاء النقاط والأزرار للسلايدر التلقائي */
        .large-image-slider-dots,
        .large-slider-button {
            display: none !important;
        }

        /* Hero Section - قسم المقدمة / الشعار الرئيسي */
        .hero {
            background-color: #f8f9fa;
            color: #333;
            text-align: right;
            padding: 80px 0;
        }

        .hero h1 {
            font-size: 3em;
            margin-bottom: 15px;
            font-weight: 700;
            color: #007bff;
        }

        .hero p {
            font-size: 1.3em;
            margin-bottom: 30px;
            max-width: 900px;
            margin-right: 0;
            margin-left: auto;
            color: #555;
        }

        .hero .btn-group {
            display: flex;
            gap: 20px;
            justify-content: flex-end; /* محاذاة الأزرار لليمين */
            flex-wrap: wrap; /* السماح بتجاوز الأزرار إلى سطر جديد في الشاشات الصغيرة */
        }

        .hero .btn {
            background-color: #28a745;
            color: white;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 1.2em;
            font-weight: 700;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .hero .btn.secondary {
            background-color: #007bff;
            color: white;
        }

        .hero .btn.secondary:hover {
            background-color: #0056b3;
        }

        .hero .btn:hover {
            background-color: #218838;
        }


        /* Features Section - قسم الميزات */
        .features {
            padding: 60px 0;
            background-color: #f0f4f8;
        }

        .features h2 {
            font-size: 2.5em;
            color: #007bff;
            text-align: center;
            margin-bottom: 50px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-item {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            text-align: center;
        }

        .feature-item h3 {
            font-size: 1.8em;
            color: #333;
            margin-bottom: 15px;
        }

        .feature-item p {
            font-size: 1.1em;
            color: #666;
        }

        /* Testimonials Section (قالوا عنا) - سلايدر شهادات العملاء */
        .testimonials {
            padding: 60px 0;
            background-color: #e9ecef;
            overflow: hidden;
            position: relative;
        }

        .testimonials h2 {
            font-size: 2.5em;
            color: #007bff;
            text-align: center;
            margin-bottom: 30px;
        }

        .small-testimonial-slider-container {
            max-width: 900px;
            margin: 0 auto;
            overflow: hidden;
        }

        .small-testimonial-slider {
            display: flex;
            animation: slideTestimonials 30s infinite linear; /* حركة تلقائية مستمرة أبطأ */
            width: fit-content;
            box-sizing: border-box;
        }

        .small-testimonial-item {
            display: flex;
            flex-direction: column; /* ترتيب الصورة والنص عمودياً */
            align-items: center;
            text-align: center;
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin: 0 20px;
            flex-shrink: 0;
            width: 350px; /* عرض ثابت لكل شهادة */
        }

        .testimonial-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
        }

        .testimonial-text {
            font-size: 1.1em;
            color: #555;
            margin-bottom: 10px;
        }
        .testimonial-author {
            font-weight: 700;
            color: #333;
            font-size: 1em;
        }
        .testimonial-link {
            font-size: 0.9em;
            color: #007bff;
            margin-top: 5px;
        }
        .testimonial-link:hover {
            text-decoration: underline;
        }

        /* حركة السلايدر لشهادات العملاء */
        @keyframes slideTestimonials {
            0% { transform: translateX(0); }
            100% { transform: translateX(calc(-100% - (var(--item-spacing-small) / 2) * var(--num-small-items) )); }
        }

        .small-testimonial-slider {
            --item-spacing-small: 40px; /* المسافة بين العناصر (20px يمين + 20px يسار) */
            --num-small-items: 4; /* عدد العناصر الفريدة في السلايدر */
        }

        .small-testimonial-slider:hover {
            animation-play-state: paused; /* تتوقف الحركة عند تمرير الماوس */
        }


        /* Pricing Section (أسعار الإشتراك) - قسم أسعار الباقات */
        .pricing {
            padding: 60px 0;
            background-color: #f0f4f8;
            text-align: center;
        }

        .pricing h2 {
            font-size: 2.5em;
            color: #007bff;
            margin-bottom: 20px;
        }

        .pricing p.subtitle {
            font-size: 1.2em;
            color: #666;
            margin-bottom: 50px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            justify-content: center;
        }

        .pricing-card {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            border-top: 5px solid #007bff; /* حدود علوية ملونة */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .pricing-card:hover {
            transform: translateY(-10px); /* تأثير ارتفاع البطاقة عند التحويم */
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .pricing-card h3 {
            font-size: 2em;
            color: #007bff;
            margin-bottom: 20px;
        }

        .price {
            font-size: 3.5em;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }

        .price span {
            font-size: 0.5em; /* تصغير حجم "سنوياً" */
            font-weight: 400;
        }

        .features-list {
            list-style: none;
            padding: 0;
            margin-bottom: 30px;
            width: 100%;
            text-align: right;
        }

        .features-list li {
            font-size: 1.1em;
            color: #555;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: flex-end; /* محاذاة عناصر القائمة لليمين */
        }

        .features-list li::before {
            content: '✓';
            color: #28a745; /* علامة صح خضراء */
            font-weight: bold;
            margin-left: 10px;
            font-size: 1.3em;
        }

        .features-list li.no-feature::before {
            content: '✕';
            color: #dc3545; /* علامة خطأ حمراء */
            font-weight: bold;
        }

        .pricing-card .btn {
            background-color: #28a745;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 1.1em;
            font-weight: 700;
            transition: background-color 0.3s ease;
            display: inline-block;
            margin-top: auto; /* يدفع الزر إلى أسفل البطاقة */
        }

        .pricing-card .btn:hover {
            background-color: #218838;
        }

        /* Call to Action (CTA) Section - قسم "تحدث معنا" */
        .cta-contact {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 60px 0;
        }

        .cta-contact h2 {
            font-size: 3em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .cta-contact p {
            font-size: 1.3em;
            margin-bottom: 40px;
        }

        .cta-contact .btn {
            background-color: #ffc107;
            color: #333;
            padding: 15px 30px;
            border-radius: 5px;
            font-size: 1.2em;
            font-weight: 700;
            transition: background-color 0.3s ease;
            display: inline-block;
        }

        .cta-contact .btn:hover {
            background-color: #e0a800;
        }

        /* Footer - ذيل الصفحة */
        .footer {
            background-color: #333;
            color: white;
            padding: 40px 0;
            text-align: center;
            font-size: 0.9em;
        }

        .footer p {
            margin: 0;
        }

        .footer .social-links a {
            color: white;
            font-size: 1.5em;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .footer .social-links a:hover {
            color: #007bff;
        }

        /* Responsive Design - تصميم متجاوب للشاشات المختلفة */
        @media (max-width: 992px) {
             .pricing-grid {
                grid-template-columns: 1fr; /* ترتيب البطاقات عمودياً في الشاشات الأصغر */
            }
             .small-testimonial-item {
                width: 300px; /* تعديل عرض الشهادة للشاشات الأصغر */
             }
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-end;
            }

            .nav-links {
                flex-direction: column;
                margin-top: 15px;
                width: 100%;
                text-align: right;
            }

            .nav-links li {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .auth-links {
                flex-direction: column;
                width: 100%;
                margin-top: 15px;
            }
            .auth-links .login-btn,
            .auth-links .get-store-btn {
                width: 100%;
                text-align: center;
                margin-left: 0;
                margin-bottom: 10px;
            }

            .hero h1 {
                font-size: 2.5em;
            }

            .hero p {
                font-size: 1.1em;
            }
            .hero .btn-group {
                justify-content: center; /* توسيط الأزرار في الشاشات الصغيرة */
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }

            .cta-contact h2 {
                font-size: 2.2em;
            }

            .small-testimonial-item {
                width: 250px;
                padding: 15px;
            }
            .testimonial-text {
                font-size: 1em;
            }

            .large-image-slide {
                min-width: 100vw;
                height: 300px; /* تعديل ارتفاع السلايدر للشاشات الصغيرة */
            }
        }
    </style>
</head>
<body>

    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="logo">ديجيتال لايف</div>
                <ul class="nav-links">
                    <li><a href="#new-service">طلب خدمة جديدة</a></li>
                    <li><a href="#service-agreement">اتفاقية الخدمة</a></li>
                    <li><a href="#about-us">من نحن</a></li>
                    <li><a href="#our-agents">وكلاؤنا</a></li>
                    <li><a href="#contact">اتصل بنا</a></li>
                </ul>
                <div class="auth-links">
                    <a href="#" class="login-btn">دخول</a>
                    <a href="#get-your-store" class="get-store-btn">احصل على متجرك الآن</a>
                </div>
            </nav>
        </div>
    </header>

    ---

    <section class="image-slider-section" id="home">
        <div class="large-image-slider-container">
            <div class="large-image-slider">
                <div class="large-image-slide">متجر أنيق احترافي</div>
                <div class="large-image-slide">تجربة تسوق سهلة وممتعة</div>
                <div class="large-image-slide">إدارة مخزونك بكل بساطة</div>
                <div class="large-image-slide">تحليلات مبيعات دقيقة</div>

                <div class="large-image-slide">متجر أنيق احترافي</div>
                <div class="large-image-slide">تجربة تسوق سهلة وممتعة</div>
                <div class="large-image-slide">إدارة مخزونك بكل بساطة</div>
                <div class="large-image-slide">تحليلات مبيعات دقيقة</div>
            </div>
        </div>
    </section>

    ---

    <section class="hero" id="get-your-store">
        <div class="container">
            <h1>الحصول على متجر الكتروني خاص بك وباسمك اصبح أسهل من أي وقت مضى</h1>
            <p>نقوم بتجهيز متجرك خلال 24 ساعة وتدريبك على استخدامه خلال 10 دقائق فقط</p>
            <div class="btn-group">
                <a href="#" class="btn">اطلب متجرك الآن</a>
                <a href="#contact" class="btn secondary">تحدث معنا</a>
            </div>
        </div>
    </section>

    ---

    <section class="features" id="features">
        <div class="container">
            <h2>أهم مميزات متجرك</h2>
            <div class="feature-grid">
                <div class="feature-item">
                    <h3>كل منتجاتك في مكان واحد منظمة ومرتبة</h3>
                    <p>أضف جميع منتجاتك بسهولة مع تنظيمها في فئات واضحة.</p>
                </div>
                <div class="feature-item">
                    <h3>ارفاق صور وفيديوهات متعددة لمنتجاتك</h3>
                    <p>اجذب عملائك بعرض مرئي شامل لمنتجاتك بجودة عالية.</p>
                </div>
                <div class="feature-item">
                    <h3>ادارة مخزونك والكميات المتوفرة</h3>
                    <p>تحكم كامل في مخزونك وتنبيهات للكميات المنخفضة لضمان توفر المنتجات.</p>
                </div>
                <div class="feature-item">
                    <h3>ربط متجرك مع شركات التوصيل الفلسطينية</h3>
                    <p>توصيل سهل وسريع لمنتجاتك لعملائك في فلسطين.</p>
                </div>
                <div class="feature-item">
                    <h3>البيع بالمفرق و/أو الجملة</h3>
                    <p>مرونة في البيع لتلبية احتياجات جميع عملائك، سواء كانوا أفرادًا أو تجار جملة.</p>
                </div>
                <div class="feature-item">
                    <h3>احصائيات فورية لمبيعاتك وارباحك</h3>
                    <p>راقب أداء متجرك بتحليلات دقيقة تساعدك على اتخاذ القرارات الصحيحة.</p>
                </div>
            </div>
        </div>
    </section>

    ---

    <section class="testimonials" id="small-testimonials-section">
        <div class="container">
            <h2>قالوا عنا</h2>
            <div class="small-testimonial-slider-container">
                <div class="small-testimonial-slider" style="--num-small-items: 4;">
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/f0f/fff?text=AS" alt="أشرف شلبي" class="testimonial-image">
                        <p class="testimonial-text">"المتجر الإلكتروني أحدث فرق لدينا في عمليات البيع والتسويق اون لاين، سواء البيع بالمفرق أو حتى بالجملة، وسهل علينا ايضاً عمليات ادارة المخزون، ومن أكثر الميزات التي احببتها هو ربط المتجر مع شركات التوصيل وإرسال الطرود الى شركات التوصيل بنقرة زر، اشكركم على هذا الإبداع البرمجي منقطع النظير وعلى التحديثات المستمرة."</p>
                        <span class="testimonial-author">أشرف شلبي من جنين / متجر eshalabi</span>
                        <a href="https://eshalabi.ps" target="_blank" class="testimonial-link">Eshalabi.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/0ff/fff?text=AA" alt="آية عطية" class="testimonial-image">
                        <p class="testimonial-text">"انشاء تطبيق هاجر كان من اهدافنا للعام 2023 والحمد لله وقع اختيارنا على شركة ديجتال لايف لنحقق هذا الهدف، تفاجئنا من القيمة العالية الي قدمولنا اياها، مستندين على دراسات تحاكي احتياجات زبائن التطبيقات العالمية من حيث سرعة التطبيق و جذب المتسوقين، اما فريق الشركة فاستجابته سريعة لأي استفسار و يقومون بالتحديثات المستمرة على تطبيقنا. كل التوفيق لشركة ديجتال لايف الرائعة ونتمنى لكم التقدم المستمر."</p>
                        <span class="testimonial-author">آية عطية من نابلس / متجر هاجر</span>
                        <a href="https://hajarwear.ps" target="_blank" class="testimonial-link">Hajarwear.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/4f4/fff?text=NS" alt="نجاح صلاحات" class="testimonial-image">
                        <p class="testimonial-text">"عن تجربة والله جدا ممتاز المتجر و مريح وسهل التعامل معه سواء للزبون او التاجر. مبسوطة جدا بالتعامل معكم و بنصح الكل يختاره وما يتردد ابدا .. وما بننسى التحديثات الدورية و المتابعة اليومية لاي استفسار او مساعدة . ما بقصروا ابدا بالتوفيق يا رب."</p>
                        <span class="testimonial-author">نجاح صلاحات من نابلس / متجر اوزان</span>
                        <a href="https://ozan.ps" target="_blank" class="testimonial-link">Ozan.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/888/fff?text=OH" alt="علا حسام الدين" class="testimonial-image">
                        <p class="testimonial-text">"متجر (سلوفان) حلم وتحقق بكل تفاصيله .. جربت قبل هيك متاجر تغلبت جدا بخدمتهم وخياراتهم المحدودة، أما المتجر الحالي مع ديجيتال لايف سلس جدا وسهل التعامل بالنسبة الي ولزبايني، والتحديثات مستمرة بشكل دائم، الخدمة سريعة جدا وعالية الجودة. انا ممتنة لشركة ديجيتال لايف الي اتاحت لي و لزبائني الاستفادة من هاد المستوى العالي من التسوق الإلكتروني"</p>
                        <span class="testimonial-author">علا حسام الدين من نابلس</span>
                        <a href="https://cellophane.ps" target="_blank" class="testimonial-link">Cellophane.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/f0f/fff?text=AS" alt="أشرف شلبي" class="testimonial-image">
                        <p class="testimonial-text">"المتجر الإلكتروني أحدث فرق لدينا في عمليات البيع والتسويق اون لاين، سواء البيع بالمفرق أو حتى بالجملة، وسهل علينا ايضاً عمليات ادارة المخزون، ومن أكثر الميزات التي احببتها هو ربط المتجر مع شركات التوصيل وإرسال الطرود الى شركات التوصيل بنقرة زر، اشكركم على هذا الإبداع البرمجي منقطع النظير وعلى التحديثات المستمرة."</p>
                        <span class="testimonial-author">أشرف شلبي من جنين / متجر eshalabi</span>
                        <a href="https://eshalabi.ps" target="_blank" class="testimonial-link">Eshalabi.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/0ff/fff?text=AA" alt="آية عطية" class="testimonial-image">
                        <p class="testimonial-text">"انشاء تطبيق هاجر كان من اهدافنا للعام 2023 والحمد لله وقع اختيارنا على شركة ديجتال لايف لنحقق هذا الهدف، تفاجئنا من القيمة العالية الي قدمولنا اياها، مستندين على دراسات تحاكي احتياجات زبائن التطبيقات العالمية من حيث سرعة التطبيق و جذب المتسوقين، اما فريق الشركة فاستجابته سريعة لأي استفسار و يقومون بالتحديثات المستمرة على تطبيقنا. كل التوفيق لشركة ديجتال لايف الرائعة ونتمنى لكم التقدم المستمر."</p>
                        <span class="testimonial-author">آية عطية من نابلس / متجر هاجر</span>
                        <a href="https://hajarwear.ps" target="_blank" class="testimonial-link">Hajarwear.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/4f4/fff?text=NS" alt="نجاح صلاحات" class="testimonial-image">
                        <p class="testimonial-text">"عن تجربة والله جدا ممتاز المتجر و مريح وسهل التعامل معه سواء للزبون او التاجر. مبسوطة جدا بالتعامل معكم و بنصح الكل يختاره وما يتردد ابدا .. وما بننسى التحديثات الدورية و المتابعة اليومية لاي استفسار او مساعدة . ما بقصروا ابدا بالتوفيق يا رب."</p>
                        <span class="testimonial-author">نجاح صلاحات من نابلس / متجر اوزان</span>
                        <a href="https://ozan.ps" target="_blank" class="testimonial-link">Ozan.ps</a>
                    </div>
                    <div class="small-testimonial-item">
                        <img src="https://via.placeholder.com/80/888/fff?text=OH" alt="علا حسام الدين" class="testimonial-image">
                        <p class="testimonial-text">"متجر (سلوفان) حلم وتحقق بكل تفاصيله .. جربت قبل هيك متاجر تغلبت جدا بخدمتهم وخياراتهم المحدودة، أما المتجر الحالي مع ديجيتال لايف سلس جدا وسهل التعامل بالنسبة الي ولزبايني، والتحديثات مستمرة بشكل دائم، الخدمة سريعة جدا وعالية الجودة. انا ممتنة لشركة ديجيتال لايف الي اتاحت لي و لزبائني الاستفادة من هاد المستوى العالي من التسوق الإلكتروني"</p>
                        <span class="testimonial-author">علا حسام الدين من نابلس</span>
                        <a href="https://cellophane.ps" target="_blank" class="testimonial-link">Cellophane.ps</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    ---

    <section class="pricing" id="pricing-section">
        <div class="container">
            <h2>أسعار الإشتراك</h2>
            <p class="subtitle">أسعار الإشتراك معقولة وفي متناول الجميع تبدأ من 240 دولار سنويا، لا يوجد أي رسوم اضافية أو تكاليف مخفية.</p>

            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>متجر الكتروني بدون تطبيق</h3>
                    <div class="price">240 <span>$ سنوياً</span></div>
                    <ul class="features-list">
                        <li>عدد المنتجات: غير محدود</li>
                        <li>موقع ويب: نعم</li>
                        <li class="no-feature">تطبيق App Store: لا</li>
                        <li class="no-feature">تطبيق Google Play: لا</li>
                        <li>تطبيق أندرويد مدمج: نعم</li>
                        <li>دعم فني مستمر: نعم</li>
                    </ul>
                    <a href="#" class="btn">احصل على متجرك الآن</a>
                </div>

                <div class="pricing-card">
                    <h3>متجر الكتروني مع تطبيق</h3>
                    <div class="price">360 <span>$ سنوياً</span></div>
                    <ul class="features-list">
                        <li>عدد المنتجات: غير محدود</li>
                        <li>موقع ويب: نعم</li>
                        <li>تطبيق App Store: نعم</li>
                        <li>تطبيق Google Play: نعم</li>
                        <li>تطبيق أندرويد مدمج: نعم</li>
                        <li>دعم فني مستمر: نعم</li>
                    </ul>
                    <a href="#" class="btn">اطلب خطتك الآن</a>
                </div>
            </div>
        </div>
    </section>

    ---

    <section class="cta-contact" id="contact">
        <div class="container">
            <h2>تحدث معنا!</h2>
            <p>جاهز لتبدأ متجرك الإلكتروني الخاص بك؟ أو لديك أي استفسارات؟</p>
            <a href="#" class="btn">اتصل بنا الآن</a>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 ديجيتال لايف. جميع الحقوق محفوظة.</p>
            <div class="social-links">
                <a href="#">فيسبوك</a> | <a href="#">تويتر</a> | <a href="#">لينكد إن</a>
            </div>
        </div>
    </footer>

    </body>
</html>