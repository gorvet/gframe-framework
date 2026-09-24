<?php

/**
 * Helper estatico para render de paginacion HTML.
 */
final class PaginationHelper
{
    private function __construct()
    {
    }

    /**
     * Construye una lista compacta de paginas con separadores.
     *
     * @param int $totalPages
     * @param int $currentPage
     * @param int $visibleCount
     * @return array<int|string>
     */
    private static function buildPageItems(int $totalPages, int $currentPage, int $visibleCount = 5): array
    {
        if ($totalPages <= 0) {
            return [];
        }

        $visibleCount = max(1, $visibleCount);
        $currentPage = max(1, min($currentPage, $totalPages));

        if ($totalPages <= $visibleCount) {
            return range(1, $totalPages);
        }

        $half = (int) floor($visibleCount / 2);
        $start = $currentPage - $half;
        $end = $currentPage + $half;

        if ($start < 1) {
            $end += (1 - $start);
            $start = 1;
        }

        if ($end > $totalPages) {
            $start -= ($end - $totalPages);
            $end = $totalPages;
        }

        if ($start < 1) {
            $start = 1;
        }

        while (($end - $start + 1) < $visibleCount && $end < $totalPages) {
            $end++;
        }

        while (($end - $start + 1) < $visibleCount && $start > 1) {
            $start--;
        }

        $items = [];

        if ($start > 1) {
            $items[] = 1;
            if ($start > 2) {
                $items[] = '...';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $items[] = $i;
        }

        if ($end < $totalPages) {
            if ($end < ($totalPages - 1)) {
                $items[] = '...';
            }
            $items[] = $totalPages;
        }

        return $items;
    }

    /**
     * Renderiza el bloque de paginacion del admin.
     *
     * @param int $totalPages Total de paginas.
     * @param int $page Pagina actual.
     * @return void
     */
    public static function render(int $totalPages, int $page): void
    {
        if ($totalPages < 1) {
            return;
        }

        $page = max(1, min($page, $totalPages));
        $pages = self::buildPageItems($totalPages, $page, 5);

        echo '<div class="row mt-5" id="pagination">
            <nav aria-label="all_items_pagination">
                <ul id="all_items_pagination" class="pagination justify-content-end pagination">';

        echo '<li class="page-item ' . ($page === 1 ? 'disabled' : '') . '">';
        echo $page === 1 ? '<span class="page-link">Anterior</span>' : '<a class="page-link prev" href="#">Anterior</a>';
        echo '</li>';

        foreach ($pages as $entry) {
            if ($entry === '...') {
                echo '<li class="page-item disabled" aria-disabled="true"><span class="page-link">...</span></li>';
                continue;
            }

            $i = (int) $entry;
            echo '<li class="page-item ' . ($page === $i ? 'active' : '') . '">';
            echo $page === $i ? '<span class="page-link">' . $i . '</span>' : '<a class="page-link linkeable" href="#">' . $i . '</a>';
            echo '</li>';
        }

        echo '<li class="page-item ' . ($page === $totalPages ? 'disabled' : '') . '">';
        echo $page === $totalPages ? '<span class="page-link">Siguiente</span>' : '<a class="page-link next" href="#">Siguiente</a>';
        echo '</li>';

        echo '</ul>
            </nav>
        </div>';
    }
}

