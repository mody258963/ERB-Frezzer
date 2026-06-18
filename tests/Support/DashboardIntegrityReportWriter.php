<?php

namespace Tests\Support;

class DashboardIntegrityReportWriter
{
    private static bool $failed = false;

    /** @var list<array{action: string, fields: list<string>, before: array<string, mixed>, after: array<string, mixed>, verdict: string}> */
    private static array $steps = [];

    private static ?string $runDate = null;

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'business_capital' => 'رأس مال المنشأة',
        'total_stock_value_cost' => 'قيمة المخزون (بالتكلفة)',
        'must_pay_suppliers' => 'مستحق الدفع للموردين',
        'must_collect_customers' => 'مستحق التحصيل من العملاء',
        'period_supplier_payments' => 'مدفوعات الموردين (خلال الفترة)',
        'period_revenue' => 'إيراد المبيعات (خلال الفترة)',
        'period_profit' => 'صافي الربح (خلال الفترة)',
        'period_customer_refunds' => 'مرتجعات العملاء (خلال الفترة)',
        'cash_on_hand_realized' => 'النقد الفعلي في الدرج',
        'withdrawable_profit' => 'الربح القابل للسحب',
        'stock_branch_a' => 'قيمة مخزون الفرع الرئيسي',
        'stock_branch_b' => 'قيمة مخزون الفرع الثاني',
        'branch_balance_owed' => 'رصيد الدين بين الفروع',
    ];

    public static function reset(): void
    {
        self::$failed = false;
        self::$steps = [];
        self::$runDate = now()->format('Y-m-d H:i:s');
    }

    public static function markFailed(): void
    {
        self::$failed = true;
    }

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function recordStep(
        string $actionAr,
        array $fields,
        array $before,
        array $after,
        string $verdict = 'نجح',
    ): void {
        self::$steps[] = [
            'action' => $actionAr,
            'fields' => $fields,
            'before' => $before,
            'after' => $after,
            'verdict' => $verdict,
        ];
    }

    public static function write(string $path): void
    {
        if (self::$runDate === null) {
            self::$runDate = now()->format('Y-m-d H:i:s');
        }

        $passed = ! self::$failed;
        $resultLabel = $passed ? '✅ نجح — الأرقام متطابقة' : '❌ فشل — يوجد اختلاف يحتاج مراجعة';
        $integrityLabel = $passed
            ? 'نعم — كل رقم على لوحة التحكم يطابق ما هو مسجّل في قاعدة البيانات'
            : 'لا — راجع الخطوات المعلّمة بفشل';

        $lines = [
            '<div dir="rtl" lang="ar">',
            '',
            '# تقرير التحقق من دقة أرقام لوحة التحكم',
            '',
            '**نظام إدارة المخزون والمبيعات — ERB**',
            '',
            '| | |',
            '|---|---|',
            '| **تاريخ التشغيل** | '.self::$runDate.' |',
            '| **نتيجة الاختبار** | '.$resultLabel.' |',
            '',
            '---',
            '',
            '## ما هذا التقرير؟',
            '',
            'هذا التقرير يوضّح أن **نظام المحاسبة والمخزون يعمل بشكل صحيح**: عند تنفيذ عملية حقيقية (بيع، شراء، مرتجع، تحويل بين فروع، إلخ) تتغيّر الأرقام على **لوحة التحكم** بالطريقة المتوقعة، وتطابق ما هو مخزّن في قاعدة البيانات.',
            '',
            'تم تنفيذ **'.count(self::$steps).' عملية تجريبية** متسلسلة داخل بيئة اختبار آمنة (ليست بيانات الإنتاج).',
            '',
            '## أهم النتائج',
            '',
            '- '.$integrityLabel,
            '- تم اختبار: رأس المال، المشتريات، المبيعات النقدية والآجلة، المرتجعات، سحب الأرباح، تحويل البضائع بين الفروع، المدفوعات بين الفروع، تعديل وإلغاء القيود، وحذف الأصناف.',
            '- **النقد الفعلي في الدرج** يتحرك فقط مع العمليات النقدية الحقيقية (بيع نقدي، تحصيل عميل، دفع مورد، مرتجع نقدي، سحب مالك).',
            '',
            '## ملاحظة مهمة للعميل',
            '',
            '> **التحويلات والمدفوعات بين الفروع** (نقل بضاعة من فرع لآخر، أو تسجيل دين/دفع بين فرعين) **لا تغيّر** مبلغ **النقد الفعلي في الدرج** على الشاشة الرئيسية. هذه العمليات تخص **حسابات الفروع مع بعضها** وتظهر في شاشة «مالية الفروع» فقط.',
            '',
            '## شرح المؤشرات',
            '',
            '| المؤشر | المعنى ببساطة |',
            '|--------|---------------|',
            '| رأس مال المنشأة | رأس المال المسجّل للعمل |',
            '| قيمة المخزون | إجمالي قيمة البضاعة في المخازن (بالتكلفة) |',
            '| مستحق من العملاء | ما على العملاء دفعه لكم الآن |',
            '| مستحق للموردين | ما عليكم دفعه للموردين الآن |',
            '| النقد الفعلي في الدرج | النقد الحقيقي المتوقع في الصندوق (بعد المبيعات والتحصيل والمدفوعات) |',
            '| إيراد المبيعات | مجموع المبيعات في الفترة المختارة (يوم / أسبوع / شهر) |',
            '| صافي الربح | الربح بعد خصم التكلفة والمرتجعات |',
            '| رصيد الدين بين الفروع | مبلغ يدين به فرع لفرع آخر (حساب منفصل عن الصندوق الرئيسي) |',
            '',
            '---',
            '',
            '## تفاصيل كل عملية',
            '',
            '| # | العملية التي تمت | ما الذي تغيّر على الشاشة؟ | قبل | بعد | الحالة |',
            '|---|------------------|---------------------------|-----|-----|--------|',
        ];

        foreach (self::$steps as $index => $step) {
            $num = $index + 1;
            $watched = self::formatWatchedFields($step['fields']);
            $before = self::formatValues($step['before']);
            $after = self::formatValues($step['after']);
            $verdict = $step['verdict'] === 'نجح' ? '✅ مطابق' : '❌ يحتاج مراجعة';

            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s |',
                $num,
                self::escapeCell($step['action']),
                self::escapeCell($watched),
                self::escapeCell($before),
                self::escapeCell($after),
                self::escapeCell($verdict),
            );
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## الخلاصة';
        $lines[] = '';
        if ($passed) {
            $lines[] = 'يمكن الاعتماد على أن **لوحة التحكم تعكس العمليات الفعلية** في النظام: المخزون، المستحقات، النقد، والأرباح تتحدّث بشكل منطقي ومتسق مع كل إجراء.';
        } else {
            $lines[] = 'يوجد **اختلاف في خطوة واحدة أو أكثر** — يُنصح بمراجعة الفريق التقني قبل الاعتماد على الأرقام في بيئة الإنتاج.';
        }
        $lines[] = '';
        $lines[] = '_تقرير آلي للتحقق من سلامة النظام — يُعاد إنشاؤه عند كل تشغيل لاختبار الجودة._';
        $lines[] = '';
        $lines[] = '</div>';
        $lines[] = '';

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * @param  list<string>  $fields
     */
    private static function formatWatchedFields(array $fields): string
    {
        $labels = [];
        foreach ($fields as $field) {
            $labels[] = self::FIELD_LABELS[$field] ?? $field;
        }

        return implode('، ', $labels);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function formatValues(array $values): string
    {
        if ($values === []) {
            return '—';
        }

        $parts = [];
        foreach ($values as $key => $value) {
            $label = self::FIELD_LABELS[$key] ?? $key;
            if (is_float($value) || is_int($value)) {
                $parts[] = $label.': '.self::formatMoney((float) $value);
            } elseif ($value === null) {
                $parts[] = $label.': —';
            } else {
                $parts[] = $label.': '.$value;
            }
        }

        return implode('<br>', $parts);
    }

    private static function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',').' ج.م';
    }

    private static function escapeCell(string $value): string
    {
        return str_replace('|', '\\|', str_replace("\n", ' ', $value));
    }
}
