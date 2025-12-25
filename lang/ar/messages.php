<?php

return [
    'success' => [
        'created' => 'تم الإنشاء بنجاح',
        'updated' => 'تم التحديث بنجاح',
        'deleted' => 'تم الحذف بنجاح',
        'restored' => 'تم الاستعادة بنجاح',
        'saved' => 'تم الحفظ بنجاح',
    ],
    
    'errors' => [
        'not_found' => ':model غير موجود',
        'validation_failed' => 'فشل التحقق من صحة البيانات',
        'unauthorized' => 'غير مصرح لك بالوصول',
        'forbidden' => 'غير مصرح لك بتنفيذ هذا الإجراء',
        'server_error' => 'حدث خطأ في الخادم',
    ],
    
    'validation' => [
        'required' => 'حقل :attribute مطلوب',
        'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً',
        'unique' => ':attribute مسجل بالفعل',
        'min' => 'يجب أن يكون :attribute على الأقل :min',
        'max' => 'يجب ألا يزيد :attribute عن :max',
        'numeric' => 'يجب أن يكون :attribute رقماً',
    ],
    
    'models' => [
        'user' => 'المستخدم',
        'property' => 'العقار',
        'client' => 'العميل',
        'task' => 'المهمة',
        'deal' => 'الصفقة',
        'contract' => 'العقد',
        'payment' => 'الدفع',
        'notification' => 'الإشعار',
    ],
    
    'auth' => [
        'login_success' => 'تم تسجيل الدخول بنجاح',
        'logout_success' => 'تم تسجيل الخروج بنجاح',
        'invalid_credentials' => 'بيانات الدخول غير صحيحة',
        'account_inactive' => 'الحساب غير نشط',
        'account_suspended' => 'الحساب موقوف',
    ],
    
    'properties' => [
        'status_changed' => 'تم تغيير حالة العقار',
        'agent_assigned' => 'تم تعيين وسيط للعقار',
        'verified' => 'تم التحقق من العقار',
        'featured' => 'تم جعل العقار مميزاً',
    ],
    
    'clients' => [
        'converted' => 'تم تحويل العميل',
        'contacted' => 'تم تسجيل الاتصال مع العميل',
        'follow_up_scheduled' => 'تم جدولة متابعة للعميل',
        'matched' => 'تمت مطابقة العميل مع العقارات',
    ],
];
