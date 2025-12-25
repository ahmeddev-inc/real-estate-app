<?php

namespace App\Helpers;

class MoneyHelper
{
    /**
     * تنسيق المبلغ بالعملة المصرية
     */
    public static function formatEGP(float $amount, bool $withCurrency = true): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        
        if ($withCurrency) {
            return $formatted . ' ج.م';
        }
        
        return $formatted;
    }

    /**
     * تنسيق المبلغ بالعملة الأمريكية
     */
    public static function formatUSD(float $amount, bool $withCurrency = true): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        
        if ($withCurrency) {
            return '$' . $formatted;
        }
        
        return $formatted;
    }

    /**
     * تحويل المبلغ من نص إلى رقم
     */
    public static function parseAmount(string $amount): float
    {
        // إزالة الرموز والفواصل
        $cleaned = preg_replace('/[^0-9.]/', '', $amount);
        
        return (float) $cleaned;
    }

    /**
     * حساب العمولة
     */
    public static function calculateCommission(float $amount, float $rate): float
    {
        return ($amount * $rate) / 100;
    }

    /**
     * حساب الضريبة
     */
    public static function calculateTax(float $amount, float $taxRate = 14): float
    {
        return ($amount * $taxRate) / 100;
    }

    /**
     * حساب السعر بعد الخصم
     */
    public static function calculateDiscount(float $amount, float $discountRate): float
    {
        return $amount - (($amount * $discountRate) / 100);
    }

    /**
     * حساب السعر بعد الضريبة
     */
    public static function calculateWithTax(float $amount, float $taxRate = 14): float
    {
        return $amount + self::calculateTax($amount, $taxRate);
    }

    /**
     * تحويل العملات
     */
    public static function convertCurrency(float $amount, float $rate, string $from, string $to): float
    {
        if ($from === 'EGP' && $to === 'USD') {
            return $amount / $rate;
        } elseif ($from === 'USD' && $to === 'EGP') {
            return $amount * $rate;
        }
        
        return $amount;
    }

    /**
     * تنسيق النسبة المئوية
     */
    public static function formatPercentage(float $percentage, int $decimals = 2): string
    {
        return number_format($percentage, $decimals) . '%';
    }

    /**
     * تقسيم المبلغ إلى أقساط
     */
    public static function calculateInstallments(float $amount, int $months, float $interestRate = 0): array
    {
        $installments = [];
        
        if ($interestRate > 0) {
            $monthlyInterestRate = $interestRate / 12 / 100;
            $monthlyPayment = ($amount * $monthlyInterestRate) / (1 - pow(1 + $monthlyInterestRate, -$months));
            $totalPayment = $monthlyPayment * $months;
            $totalInterest = $totalPayment - $amount;
        } else {
            $monthlyPayment = $amount / $months;
            $totalPayment = $amount;
            $totalInterest = 0;
        }
        
        $remainingBalance = $amount;
        
        for ($i = 1; $i <= $months; $i++) {
            if ($interestRate > 0) {
                $interest = $remainingBalance * $monthlyInterestRate;
                $principal = $monthlyPayment - $interest;
                $remainingBalance -= $principal;
            } else {
                $interest = 0;
                $principal = $monthlyPayment;
                $remainingBalance -= $principal;
            }
            
            $installments[] = [
                'month' => $i,
                'payment' => round($monthlyPayment, 2),
                'principal' => round($principal, 2),
                'interest' => round($interest, 2),
                'remaining_balance' => round(max($remainingBalance, 0), 2),
            ];
        }
        
        return [
            'total_amount' => $amount,
            'months' => $months,
            'interest_rate' => $interestRate,
            'monthly_payment' => round($monthlyPayment, 2),
            'total_payment' => round($totalPayment, 2),
            'total_interest' => round($totalInterest, 2),
            'installments' => $installments,
        ];
    }

    /**
     * حساب القيمة الحالية
     */
    public static function calculatePresentValue(float $futureValue, float $rate, int $periods): float
    {
        return $futureValue / pow(1 + ($rate / 100), $periods);
    }

    /**
     * حساب القيمة المستقبلية
     */
    public static function calculateFutureValue(float $presentValue, float $rate, int $periods): float
    {
        return $presentValue * pow(1 + ($rate / 100), $periods);
    }

    /**
     * تقريب المبلغ لأقرب فئة
     */
    public static function roundToNearest(float $amount, float $nearest = 1000): float
    {
        return round($amount / $nearest) * $nearest;
    }

    /**
     * تنسيق المبلغ بالكلمات (عربي)
     */
    public static function amountToWords(float $amount): string
    {
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);
        
        $words = self::numberToWordsArabic($whole) . ' جنيهاً';
        
        if ($fraction > 0) {
            $words .= ' و' . self::numberToWordsArabic($fraction) . ' قرشاً';
        }
        
        return $words . ' فقط لا غير';
    }

    /**
     * تحويل الأرقام إلى كلمات عربية
     */
    private static function numberToWordsArabic(int $number): string
    {
        if ($number == 0) {
            return 'صفر';
        }
        
        $units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $teens = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $hundreds = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];
        $thousands = ['', 'ألف', 'ألفان', 'آلاف'];
        $millions = ['', 'مليون', 'مليونان', 'ملايين'];
        $billions = ['', 'مليار', 'ملياران', 'مليارات'];
        
        if ($number < 10) {
            return $units[$number];
        } elseif ($number < 20) {
            return $teens[$number - 10];
        } elseif ($number < 100) {
            $unit = $number % 10;
            $ten = (int) ($number / 10);
            
            if ($unit == 0) {
                return $tens[$ten];
            } else {
                return $units[$unit] . ' و' . $tens[$ten];
            }
        } elseif ($number < 1000) {
            $hundred = (int) ($number / 100);
            $remainder = $number % 100;
            
            if ($remainder == 0) {
                return $hundreds[$hundred];
            } else {
                return $hundreds[$hundred] . ' و' . self::numberToWordsArabic($remainder);
            }
        }
        
        // للأرقام الأكبر نستخدم الصيغة البسيطة
        return number_format($number) . ' (' . self::numberToWordsArabic($number) . ')';
    }

    /**
     * التحقق من صحة رقم الحساب البنكي
     */
    public static function isValidBankAccount(string $accountNumber): bool
    {
        // رقم حساب بنكي مصري نموذجي: ١٩-٢٠ رقم
        $cleaned = preg_replace('/[^0-9]/', '', $accountNumber);
        
        return strlen($cleaned) >= 15 && strlen($cleaned) <= 20;
    }
}
