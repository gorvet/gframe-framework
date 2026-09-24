<?php

/**
 * Helper estatico para render de menus HTML.
 */
final class MenuHelper
{
    private function __construct()
    {
    }

    private static function normalizePath(string $url): string
    {
        return rtrim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/');
    }

    private static function isCurrentHref(string $href, string $currentPath): bool
    {
        if (parse_url($href, PHP_URL_FRAGMENT) !== null) {
            return false;
        }

        return self::normalizePath($href) === $currentPath;
    }

    private static function hasCurrentChild(array $menuItems, string $currentPath): bool
    {
        foreach ($menuItems as $item) {
            $type = (string)($item[0] ?? '');
            $href = (string)($item[1] ?? '#');

            if ($type === 'item' && self::isCurrentHref($href, $currentPath)) {
                return true;
            }

            $sub = (isset($item[4]) && is_array($item[4])) ? $item[4] : [];
            if (!empty($sub) && self::hasCurrentChild($sub, $currentPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Renderiza un menu de navegacion segun el esquema de items del proyecto.
     *
     * @param array $menuItems Estructura de items.
     * @param string $currentURL URL actual para marcar item activo.
     * @param string $context Contexto del menu (nav|dropdown).
     * @return void
     */
    public static function build(array $menuItems, string $currentURL = '', string $context = 'nav'): void
    {
        $currentPath = self::normalizePath($currentURL);
        $liClass = $context === 'dropdown' ? 'li-dd' : 'nav-item';
        $linkClass = $context === 'dropdown' ? 'dropdown-item' : 'nav-link';
        $wrapUlClass = $context === 'dropdown' ? 'dropdown-menu' : 'nav-content collapse';

        foreach ($menuItems as $item) {
            $type = (string)($item[0] ?? '');
            $href = (string)($item[1] ?? '#');
            $iconClass = (string)($item[2] ?? '');
            $icon = $iconClass !== '' ? '<i class="me-2 ' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '"></i>' : '';
            $title = (string)($item[3] ?? '');

            $hasIDRaw = (isset($item[4]) && !is_array($item[4])) ? trim((string)$item[4]) : '';
            $hasID = htmlspecialchars($hasIDRaw, ENT_QUOTES, 'UTF-8');
            $idAttr = $hasIDRaw !== '' ? ' id="' . $hasID . '"' : '';
            $sub = (isset($item[4]) && is_array($item[4])) ? $item[4] : [];
            $submenuId = !empty($sub) ? 'sm_' . preg_replace('/[^a-z0-9_-]/i', '', $href) : '';

            switch ($type) {
                case 'heading':
                    echo '<li class="nav-heading">' . htmlspecialchars($title !== '' ? $title : $href, ENT_QUOTES, 'UTF-8') . '</li>';
                    break;

                case 'divider':
                    echo '<li><hr class="divider dropdown-divider"></li>';
                    break;

                case 'item':
                    $isCurrent = self::isCurrentHref($href, $currentPath);
                    $classes = $linkClass . ($isCurrent ? ' current disabled' : '');
                    echo '<li class="' . $liClass . '">
                            <a' . $idAttr . ' class="' . htmlspecialchars(trim($classes . ' ' . $hasIDRaw), ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">
                              ' . $icon . '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>
                            </a>
                          </li>';
                    break;

                case 'menu':
                    echo '<li class="nav-item">
                            <a class="' . $linkClass . ' collapsed d-flex" data-bs-target="#' . $submenuId . '" data-bs-toggle="collapse" href="#">
                              ' . $icon . '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span><i class="gicon-fd ms-auto"></i>
                            </a>';
                    if (!empty($sub)) {
                        echo '<ul id="' . $submenuId . '" class="' . $wrapUlClass . '" data-bs-parent="#sidebar-nav">';
                        self::build($sub, $currentURL, $context);
                        echo '</ul>';
                    }
                    echo '</li>';
                    break;

                case 'dropdown':
                    $hasCurrentChild = self::hasCurrentChild($sub, $currentPath);
                    $parentCurrentClass = $hasCurrentChild ? ' current' : '';
                    echo '<li class="nav-item dropdown' . $parentCurrentClass . '">
                            <a class="nav-link dropdown-toggle' . $parentCurrentClass . '" data-bs-toggle="dropdown" role="button" aria-expanded="false" href="#">
                              ' . $icon . '<span>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span>
                            </a>';
                    if (!empty($sub)) {
                        echo '<ul class="dropdown-menu">';
                        self::build($sub, $currentURL, 'dropdown');
                        echo '</ul>';
                    }
                    echo '</li>';
                    break;
            }
        }
    }
}

