<?php

/*
 * Newsletter signup strings.
 *
 * The signup endpoint is independent of any page: a frontend places the form
 * wherever it likes and posts to the newsletter route, so these strings live
 * in their own namespace rather than alongside page copy.
 */

return [

    'heading' => 'اشترك في نشرتنا البريدية',
    'email_label' => 'بريدك الإلكتروني',

    // تسمية مختصرة لخانة الاختيار (مخفية بصرياً)؛ الجملة الظاهرة أدناه
    // هي وصف الحقل، لأن وضع رابط داخل <label> يبدّل حالة الخانة.
    'consent_label' => 'أوافق على سياسة الخصوصية وعلى تلقي التحديثات',

    // ثلاثة أجزاء نصية بلا أي وسوم HTML؛ الجزء الأوسط يصبح رابطاً فقط
    // عند نشر سياسة الخصوصية، وإلا فيظهر كنص عادي.
    'consent_before' => 'بإرسال النموذج، أوافق على ',
    'consent_link' => 'سياسة الخصوصية',
    'consent_after' => ' وعلى تلقي التحديثات.',

    'submit' => 'اشترك',
    'required' => 'مطلوب',

    // نص واحد للاشتراك الأول والمتكرر — لا شيء يميّز الحالتين خارجياً.
    'success' => 'شكراً لك — تم تأكيد اشتراكك.',
    'error_summary' => 'يرجى مراجعة الحقول أدناه والمحاولة مرة أخرى.',

    'errors' => [
        'email' => 'يرجى إدخال بريد إلكتروني صحيح.',
        'consent' => 'يجب الموافقة على الإقرار قبل الاشتراك.',
    ],

];
