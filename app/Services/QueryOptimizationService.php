<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class QueryOptimizationService
{
    /**
     * تحليل وتقديم نصائح لتحسين الاستعلام
     */
    public function analyzeQuery(string $query, array $bindings = []): array
    {
        $analysis = [
            'query' => $query,
            'bindings' => $bindings,
            'suggestions' => [],
            'warnings' => [],
            'estimated_cost' => null,
        ];

        // تحليل SELECT *
        if (str_contains(strtoupper($query), 'SELECT *')) {
            $analysis['warnings'][] = 'تجنب استخدام SELECT *، حدد الأعمدة المطلوبة فقط';
        }

        // تحليل JOIN بدون فهارس
        if (preg_match('/JOIN\s+(\w+)\s+ON/i', $query, $matches)) {
            $analysis['suggestions'][] = 'تأكد من وجود فهارس على أعمدة JOIN في الجدول: ' . $matches[1];
        }

        // تحليل WHERE مع عمليات LIKE
        if (preg_match('/WHERE.*LIKE\s+\'%/i', $query)) {
            $analysis['warnings'][] = 'استخدام LIKE مع % في البداية يمنع استخدام الفهارس';
        }

        // تحليل ORDER BY بدون فهرس
        if (preg_match('/ORDER BY\s+(\w+)/i', $query, $matches)) {
            $analysis['suggestions'][] = 'فكر في إضافة فهرس على عمود ORDER BY: ' . $matches[1];
        }

        // تحليل GROUP BY
        if (str_contains(strtoupper($query), 'GROUP BY')) {
            $analysis['suggestions'][] = 'تأكد من وجود فهارس على أعمدة GROUP BY';
        }

        return $analysis;
    }

    /**
     * تنفيذ EXPLAIN على الاستعلام
     */
    public function explainQuery(string $query, array $bindings = []): array
    {
        try {
            $explainQuery = "EXPLAIN ANALYZE " . $query;
            $results = DB::select($explainQuery, $bindings);
            
            return [
                'success' => true,
                'explanation' => $results,
                'summary' => $this->summarizeExplainResults($results),
            ];
        } catch (QueryException $e) {
            Log::error('Query explain failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * تلخيص نتائج EXPLAIN
     */
    private function summarizeExplainResults(array $results): array
    {
        $summary = [
            'total_cost' => 0,
            'rows' => 0,
            'has_index_scan' => false,
            'has_seq_scan' => false,
            'has_nested_loop' => false,
            'suggestions' => [],
        ];

        foreach ($results as $result) {
            $plan = json_decode(json_encode($result), true);
            
            if (isset($plan['Total Cost'])) {
                $summary['total_cost'] = max($summary['total_cost'], (float) $plan['Total Cost']);
            }

            if (isset($plan['Plan Rows'])) {
                $summary['rows'] = max($summary['rows'], (int) $plan['Plan Rows']);
            }

            // تحليل نوع المسح
            if (stripos($plan['QUERY PLAN'] ?? '', 'Index Scan') !== false) {
                $summary['has_index_scan'] = true;
            }

            if (stripos($plan['QUERY PLAN'] ?? '', 'Seq Scan') !== false) {
                $summary['has_seq_scan'] = true;
                $summary['suggestions'][] = 'تم اكتشاف مسح تسلسلي (Seq Scan)، فكر في إضافة فهارس';
            }

            if (stripos($plan['QUERY PLAN'] ?? '', 'Nested Loop') !== false) {
                $summary['has_nested_loop'] = true;
            }
        }

        // تقدير التكلفة
        if ($summary['total_cost'] > 1000) {
            $summary['suggestions'][] = 'تكلفة الاستعلام عالية (' . $summary['total_cost'] . ')، فكر في تحسينه';
        }

        if ($summary['rows'] > 10000) {
            $summary['suggestions'][] = 'الاستعلام يعالج عدد كبير من الصفوف (' . $summary['rows'] . ')';
        }

        return $summary;
    }

    /**
     * اقتراح فهارس جديدة بناءً على أنماط الاستعلام
     */
    public function suggestIndexes(array $queryPatterns): array
    {
        $suggestions = [];

        foreach ($queryPatterns as $pattern) {
            if (isset($pattern['where_columns']) && count($pattern['where_columns']) > 0) {
                $suggestions[] = [
                    'table' => $pattern['table'],
                    'columns' => $pattern['where_columns'],
                    'type' => 'BTREE',
                    'name' => 'idx_' . $pattern['table'] . '_' . implode('_', $pattern['where_columns']),
                    'reason' => 'تحسين استعلامات WHERE المتكررة',
                ];
            }

            if (isset($pattern['order_columns']) && count($pattern['order_columns']) > 0) {
                $suggestions[] = [
                    'table' => $pattern['table'],
                    'columns' => $pattern['order_columns'],
                    'type' => 'BTREE',
                    'name' => 'idx_' . $pattern['table'] . '_order_' . implode('_', $pattern['order_columns']),
                    'reason' => 'تحسين استعلامات ORDER BY',
                ];
            }

            if (isset($pattern['join_columns']) && count($pattern['join_columns']) > 0) {
                $suggestions[] = [
                    'table' => $pattern['table'],
                    'columns' => $pattern['join_columns'],
                    'type' => 'BTREE',
                    'name' => 'idx_' . $pattern['table'] . '_join_' . implode('_', $pattern['join_columns']),
                    'reason' => 'تحسين عمليات JOIN',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * توليد SQL لإنشاء الفهارس المقترحة
     */
    public function generateIndexSql(array $suggestions): array
    {
        $sqlStatements = [];

        foreach ($suggestions as $suggestion) {
            $sql = sprintf(
                "CREATE INDEX IF NOT EXISTS %s ON %s (%s) USING %s;",
                $suggestion['name'],
                $suggestion['table'],
                implode(', ', $suggestion['columns']),
                $suggestion['type']
            );

            $sqlStatements[] = [
                'sql' => $sql,
                'reason' => $suggestion['reason'],
                'table' => $suggestion['table'],
            ];
        }

        return $sqlStatements;
    }

    /**
     * مراقبة بطيء الاستعلامات
     */
    public function monitorSlowQueries(int $thresholdMs = 1000): array
    {
        $slowQueries = [];

        try {
            // لـ PostgreSQL
            $queries = DB::select("
                SELECT query, calls, total_time, mean_time, rows
                FROM pg_stat_statements
                WHERE mean_time > ?
                ORDER BY mean_time DESC
                LIMIT 10
            ", [$thresholdMs]);

            foreach ($queries as $query) {
                $slowQueries[] = [
                    'query' => $query->query,
                    'calls' => $query->calls,
                    'total_time_ms' => $query->total_time,
                    'mean_time_ms' => $query->mean_time,
                    'rows' => $query->rows,
                    'analysis' => $this->analyzeQuery($query->query),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch slow queries', ['error' => $e->getMessage()]);
        }

        return $slowQueries;
    }
}
