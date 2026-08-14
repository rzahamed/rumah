<?php

return [

    'post_status' => [
        'draft' => 'مسودة',
        'published' => 'منشور',
    ],

    'fields' => [
        'title' => 'العنوان',
        'name' => 'الاسم',
        'slug' => 'المعرّف',
        'category' => 'التصنيف',
        'excerpt' => 'المقتطف',
        'body' => 'المحتوى',
        'status' => 'الحالة',
        'published_at' => 'تاريخ النشر',
        'position' => 'المسمى الوظيفي',
        'bio' => 'نبذة',
        'photo' => 'الصورة',
        'featured_image' => 'الصورة البارزة',
        'email' => 'البريد الإلكتروني',
        'highlights' => 'أبرز المجالات',
        'highlight' => 'مجال بارز',
        'credentials' => 'المؤهلات',
        'credential_title' => 'المسمى',
        'credential_institution' => 'الجهة',
        'credential_description' => 'الوصف',
        'expertise' => 'مجالات الخبرة',
        'expertise_item' => 'مجال الخبرة',
        'licence_image' => 'صورة الترخيص',
        'question' => 'السؤال',
        'answer' => 'الإجابة',
        'sort_order' => 'ترتيب العرض',
        'is_visible' => 'ظاهر',
        'is_active' => 'مفعّل',
        'form' => 'النموذج',
        'form_fields' => 'الحقول',
        'field_name' => 'اسم الحقل',
        'field_type' => 'نوع الحقل',
        'field_required' => 'إلزامي',
        'field_required_consent' => 'يجب تحديدها (موافقة)',
        'field_label' => 'التسمية',
        'field_options' => 'الخيارات',
        'option_value' => 'قيمة الخيار',
        'option_label' => 'تسمية الخيار',
        'payload' => 'البيانات المرسلة',
        'contact_name' => 'الاسم',
        'phone' => 'رقم الهاتف',
        'case_interest' => 'نوع القضية',
        'reviewed_at' => 'تاريخ المراجعة',
        'reviewed_by' => 'راجعها',
        'submitted_at' => 'تاريخ الإرسال',
        'submissions_count' => 'الطلبات المستلمة',
        'posts_count' => 'المقالات',
        'created_at' => 'تاريخ الإنشاء',
        'locale' => 'اللغة',
        'consented_at' => 'تاريخ الموافقة',
        'subscribed_at' => 'تاريخ الاشتراك',
        'policy_key' => 'معرّف الصفحة',
        'is_published' => 'منشورة',
        'updated_at' => 'آخر تحديث',
    ],

    'field_types' => [
        'text' => 'نص',
        'textarea' => 'نص طويل',
        'email' => 'بريد إلكتروني',
        'tel' => 'هاتف',
        'number' => 'رقم',
        'select' => 'قائمة اختيار',
        'checkbox' => 'خانة اختيار',
    ],

    'faq' => [
        'singular' => 'سؤال شائع',
        'plural' => 'الأسئلة الشائعة',
    ],

    'posts' => [
        'singular' => 'مقال',
        'plural' => 'المقالات',
    ],

    'categories' => [
        'singular' => 'تصنيف',
        'plural' => 'التصنيفات',
    ],

    'team' => [
        'singular' => 'عضو الفريق',
        'plural' => 'أعضاء الفريق',
    ],

    // Product-specific wording, deliberately not the generic "طلب مستلم":
    // every submission the panel shows comes from the one Contact form.
    'submissions' => [
        'singular' => 'طلب تواصل',
        'plural' => 'طلبات التواصل',
        'contact_section' => 'بيانات التواصل',
        'all_fields_section' => 'جميع البيانات المُرسلة',
        'metadata_section' => 'تفاصيل السجل',
        'yes' => 'نعم',
        'no' => 'لا',
        'update_status' => 'تحديث الحالة',
        'save_status' => 'حفظ الحالة',
        'status_updated' => 'تم تحديث الحالة.',
        'status_invalid' => 'هذه الحالة غير معروفة.',
        // صيغة عامة عن قصد: لا تكشف وجود مستخدم مخفي.
        'recipients_invalid' => 'بعض المستلمين المحددين غير متاحين. يرجى مراجعة اختيارك.',
        'not_provided' => 'غير متوفر',
        'configure_notifications' => 'إعداد الإشعارات',
        'configure_notifications_hint' => 'اختر أعضاء الفريق الذين يصلهم بريد إلكتروني عند ورود طلب تواصل جديد.',
        'recipients' => 'مستلمو البريد',
        'recipients_hint' => 'يمكن اختيار أعضاء الفريق النشطين الذين لديهم صلاحية الدخول إلى لوحة التحكم فقط.',
        'save_recipients' => 'حفظ المستلمين',
        'recipients_saved' => 'سيصل الإشعار إلى :count مستلم.',
        'email' => [
            'subject' => 'طلب تواصل جديد — :app',
            'greeting' => 'مرحباً :name،',
            'intro' => 'تم استلام طلب تواصل جديد.',
            'name' => 'الاسم: :value',
            'email' => 'البريد الإلكتروني: :value',
            'phone' => 'رقم الهاتف: :value',
            'interest' => 'نوع القضية: :value',
            'action' => 'عرض الطلب',
            'outro' => 'افتح لوحة التحكم لقراءة الرسالة كاملة والرد عليها.',
        ],
    ],

    'submission_status' => [
        'new' => 'جديد',
        'reviewed' => 'تمت المراجعة',
        'archived' => 'مؤرشف',
    ],

    // مشتركة بين مساري الإرسال العامين (نماذج لوحة التحكم والنشرة البريدية).
    'turnstile' => [
        // Visually hidden: the widget is an opaque third-party iframe, so the
        // field still needs a name a screen reader can announce.
        'label' => 'التحقق الأمني',
        'required' => 'يرجى إكمال التحقق الأمني قبل الإرسال.',
        'failed' => 'تعذّر التحقق من أنك لست روبوتاً. يرجى المحاولة مرة أخرى.',
    ],

    'hints' => [
        'body_markdown' => 'يدعم الحقل تنسيق Markdown: العناوين (## و###)، والنص **العريض**، والقوائم النقطية (- عنصر). لا يُنفَّذ كود HTML بل يُعرض كنص عادي.',
        'policy_publication' => 'لا تظهر الصفحة على الموقع إلا بعد نشرها. يجب تعبئة نص الصفحة بكلتا اللغتين قبل النشر.',
        'field_name' => 'المعرّف الداخلي الذي يُحفظ مع كل طلب، ولا يظهر للزوار. استخدم الحروف اللاتينية الصغيرة والأرقام والشرطة السفلية، مثل full_name. وأي صيغة أخرى تُحوَّل تلقائياً.',
        'field_name_locked' => 'هذا الحقل يحتوي على طلبات مستلمة، لذلك لا يمكن تغيير معرّفه. غيّر التسمية بدلاً من ذلك، فهي ما يراه الزوار.',
    ],

    'policies' => [
        'singular' => 'صفحة سياسة',
        'plural' => 'صفحات السياسات',
        'publish_blocked_title' => 'لا يمكن نشر هذه الصفحة بعد',
        'publish_blocked_body' => 'أضف نص الصفحة للغات التالية قبل النشر: :locales. لم يتم حفظ أي تغيير.',
    ],

    'subscribers' => [
        'singular' => 'مشترك',
        'plural' => 'مشتركو النشرة البريدية',
        'export' => 'تصدير CSV',
    ],

    'forms' => [
        'singular' => 'نموذج التواصل',
        'plural' => 'نموذج التواصل',
        'submitted' => 'شكراً لك — تم استلام رسالتك.',
        'duplicate_field_name' => 'سيؤدي ذلك إلى تطابق المعرّف الداخلي لحقلين. امنح كل حقل معرّفاً مختلفاً.',
        'invalid_field_name' => 'يجب أن يبدأ المعرّف الداخلي بحرف لاتيني صغير، وألا يحتوي إلا على الحروف اللاتينية الصغيرة والأرقام والشرطة السفلية، بحد أقصى ٦٤ حرفاً، مثل full_name.',
    ],

];
