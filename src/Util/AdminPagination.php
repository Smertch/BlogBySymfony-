<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Builds compact page-number lists for admin tables (avoids rendering dozens of links).
 */
final class AdminPagination
{
    /**
     * First five pages, last page, and current±1 (with ellipsis between gaps).
     *
     * @return list<int|null> ints are 1-based page numbers; null renders as "…"
     */
    public static function compactPages(int $current, int $totalPages): array
    {
        if ($totalPages <= 1) {
            return [];
        }

        if ($totalPages <= 9) {
            return range(1, $totalPages);
        }

        $current = max(1, min($totalPages, $current));

        $want = [];

        foreach (range(1, min(5, $totalPages)) as $i) {
            $want[$i] = true;
        }

        $want[$totalPages] = true;

        foreach (range(max(1, $current - 1), min($totalPages, $current + 1)) as $i) {
            $want[$i] = true;
        }

        $sorted = array_keys($want);
        sort($sorted);

        $out = [];
        $prev = null;
        foreach ($sorted as $p) {
            if ($prev !== null && $p - $prev > 1) {
                $out[] = null;
            }
            $out[] = $p;
            $prev = $p;
        }

        return $out;
    }
}
