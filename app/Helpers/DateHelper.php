<?php

namespace App\Helpers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DateHelper
{
    /**
     * تنسيق التاريخ بالعربية
     */
    public static function arabicFormat(CarbonInterface $date, string $format = 'd F Y'): string
    {
        $months = [
            'January' => 'يناير',
            'February' => 'فبراير',
            'March' => 'مارس',
            'April' => 'أبريل',
            'May' => 'مايو',
            'June' => 'يونيو',
            'July' => 'يوليو',
            'August' => 'أغسطس',
            'September' => 'سبتمبر',
            'October' => 'أكتوبر',
            'November' => 'نوفمبر',
            'December' => 'ديسمبر',
        ];

        $days = [
            'Monday' => 'الاثنين',
            'Tuesday' => 'الثلاثاء',
            'Wednesday' => 'الأربعاء',
            'Thursday' => 'الخميس',
            'Friday' => 'الجمعة',
            'Saturday' => 'السبت',
            'Sunday' => 'الأحد',
        ];

        $formatted = $date->translatedFormat($format);
        
        foreach ($months as $english => $arabic) {
            $formatted = str_replace($english, $arabic, $formatted);
        }
        
        foreach ($days as $english => $arabic) {
            $formatted = str_replace($english, $arabic, $formatted);
        }
        
        return $formatted;
    }

    /**
     * حساب الفرق بين تاريخين بالنص العربي
     */
    public static function diffForHumansArabic(CarbonInterface $date): string
    {
        $diff = $date->diffForHumans(null, CarbonInterface::DIFF_RELATIVE_TO_NOW, false, 2);
        
        $translations = [
            'seconds' => 'ثواني',
            'second' => 'ثانية',
            'minutes' => 'دقائق',
            'minute' => 'دقيقة',
            'hours' => 'ساعات',
            'hour' => 'ساعة',
            'days' => 'أيام',
            'day' => 'يوم',
            'weeks' => 'أسابيع',
            'week' => 'أسبوع',
            'months' => 'شهور',
            'month' => 'شهر',
            'years' => 'سنوات',
            'year' => 'سنة',
            'ago' => 'منذ',
            'from now' => 'من الآن',
            'after' => 'بعد',
            'before' => 'قبل',
        ];
        
        foreach ($translations as $english => $arabic) {
            $diff = str_replace($english, $arabic, $diff);
        }
        
        return $diff;
    }

    /**
     * التحقق إذا كان التاريخ ضمن نطاق
     */
    public static function isWithinRange(CarbonInterface $date, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $date->between($start, $end);
    }

    /**
     * الحصول على بداية ونهاية الأسبوع
     */
    public static function getWeekRange(CarbonInterface $date = null): array
    {
        $date = $date ?: now();
        
        return [
            'start' => $date->copy()->startOfWeek(),
            'end' => $date->copy()->endOfWeek(),
        ];
    }

    /**
     * الحصول على بداية ونهاية الشهر
     */
    public static function getMonthRange(CarbonInterface $date = null): array
    {
        $date = $date ?: now();
        
        return [
            'start' => $date->copy()->startOfMonth(),
            'end' => $date->copy()->endOfMonth(),
        ];
    }

    /**
     * الحصول على بداية ونهاية السنة
     */
    public static function getYearRange(CarbonInterface $date = null): array
    {
        $date = $date ?: now();
        
        return [
            'start' => $date->copy()->startOfYear(),
            'end' => $date->copy()->endOfYear(),
        ];
    }

    /**
     * التحقق إذا كان التاريخ يوم عمل
     */
    public static function isBusinessDay(CarbonInterface $date): bool
    {
        // الأحد إلى الخميس أيام عمل في مصر
        return !in_array($date->dayOfWeek, [5, 6]); // الجمعة والسبت
    }

    /**
     * إضافة أيام العمل
     */
    public static function addBusinessDays(CarbonInterface $date, int $days): CarbonInterface
    {
        $result = $date->copy();
        $addedDays = 0;
        
        while ($addedDays < $days) {
            $result->addDay();
            
            if (self::isBusinessDay($result)) {
                $addedDays++;
            }
        }
        
        return $result;
    }

    /**
     * حساب أيام العمل بين تاريخين
     */
    public static function businessDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $days = 0;
        $current = $start->copy();
        
        while ($current->lessThan($end)) {
            if (self::isBusinessDay($current)) {
                $days++;
            }
            $current->addDay();
        }
        
        return $days;
    }

    /**
     * تنسيق المدة الزمنية
     */
    public static function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} دقيقة";
        }
        
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return "{$hours} ساعة";
        }
        
        return "{$hours} ساعة و {$remainingMinutes} دقيقة";
    }

    /**
     * الحصول على قائمة الأشهر بالعربية
     */
    public static function arabicMonths(): array
    {
        return [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'أبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر',
        ];
    }

    /**
     * الحصول على قائمة أيام الأسبوع بالعربية
     */
    public static function arabicDays(): array
    {
        return [
            0 => 'الأحد',
            1 => 'الاثنين',
            2 => 'الثلاثاء',
            3 => 'الأربعاء',
            4 => 'الخميس',
            5 => 'الجمعة',
            6 => 'السبت',
        ];
    }

    /**
     * التحقق من صحة التاريخ الهجري
     */
    public static function isValidHijriDate(int $year, int $month, int $day): bool
    {
        // التحقق الأساسي
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1) {
            return false;
        }
        
        // أيام الأشهر الهجرية
        $hijriMonthDays = [
            1 => 30,  // محرم
            2 => 29,  // صفر
            3 => 30,  // ربيع الأول
            4 => 29,  // ربيع الآخر
            5 => 30,  // جمادى الأولى
            6 => 29,  // جمادى الآخرة
            7 => 30,  // رجب
            8 => 29,  // شعبان
            9 => 30,  // رمضان
            10 => 29, // شوال
            11 => 30, // ذو القعدة
            12 => 29, // ذو الحجة (30 في السنة الكبيسة)
        ];
        
        // السنة الهجرية الكبيسة
        if (($year % 30) == 2 || ($year % 30) == 5 || ($year % 30) == 7 || 
            ($year % 30) == 10 || ($year % 30) == 13 || ($year % 30) == 16 || 
            ($year % 30) == 18 || ($year % 30) == 21 || ($year % 30) == 24 || 
            ($year % 30) == 26 || ($year % 30) == 29) {
            $hijriMonthDays[12] = 30; // ذو الحجة 30 يوم في السنة الكبيسة
        }
        
        return $day <= $hijriMonthDays[$month];
    }
}
