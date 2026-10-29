<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Module\MyPlugin;

/**
 * @brief       eventHandler My helper.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class My extends MyPlugin
{
    /**
     * Events post type.
     *
     * @var     string  POST_TYPE
     */
    public const POST_TYPE = 'eventhandler';

    /**
     * Events meta type (link between entries and events).
     *
     * @var     string  META_TYPE
     */
    public const META_TYPE = 'eventhandler';

    /**
     * Events database table name.
     *
     * @var     string  TABLE_NAME
     */
    public const TABLE_NAME = 'eventhandler';

    /**
     * Events periods.
     *
     * @var     array<int, string>  PERIODS
     */
    public const PERIODS = [
        'all',
        'ongoing',
        'outgoing',
        'scheduled',
        'started',
        'notfinished',
        'finished',
    ];

    /**
     * Placeholder for lists enclose block.
     *
     * @var     string  ENCLOSE
     */
    public const ENCLOSE = '__EVENTHANDLER_LIST__';

    /**
     * Prepare an enclose block for sprintf().
     *
     * @param   string  $html   The HTML with ENCLOSE placeholder
     */
    public static function enclose(string $html): string
    {
        return str_replace(['%', self::ENCLOSE], ['%%', '%s'], $html);
    }

    public static function checkCustomContext(int $context): ?bool
    {
        return match ($context) {
            // Add usage perm to backend
            self::MANAGE, self::MENU => App::task()->checkContext('BACKEND')
                && App::blog()->isDefined()
                && App::auth()->check(App::auth()->makePermissions([
                    App::auth()::PERMISSION_USAGE,
                    App::auth()::PERMISSION_CONTENT_ADMIN,
                ]), App::blog()->id()),

            default => null,
        };
    }

    /**
     * Check if plugin is active on current blog.
     */
    public static function isActive(): bool
    {
        return App::blog()->isDefined() && (bool) self::settings()->getBool('active', false);
    }

    /**
     * Get categories ids hidden on public side.
     *
     * Setting was serialized before 2026.10.29, now it is an array.
     *
     * @return  array<int, int>
     */
    public static function hiddenCategories(): array
    {
        $value = self::settings()->get('public_hidden_categories');
        if (is_string($value) && $value !== '') {
            $value = @unserialize($value, ['allowed_classes' => false]);
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), array_filter($value, is_numeric(...)))));
    }

    /**
     * Get SQL part to exclude hidden categories.
     *
     * @param   string  $alias  The category table alias (P for posts)
     */
    public static function hiddenCategoriesSql(string $alias = 'P'): string
    {
        $ids = self::hiddenCategories();

        return $ids === [] ? '' : ' AND (' . $alias . '.cat_id IS NULL OR ' . $alias . '.cat_id NOT ' . App::db()->con()->in($ids) . ') ';
    }

    /**
     * Events periods combo.
     *
     * @param   bool    $with_all   Add "all periods" entry
     *
     * @return  array<string, string>
     */
    public static function periodsCombo(bool $with_all = true): array
    {
        return array_merge(
            $with_all ? [__('All periods') => ''] : [],
            [
                __('Not started')  => 'scheduled',
                __('Started')      => 'started',
                __('Finished')     => 'finished',
                __('Not finished') => 'notfinished',
                __('Ongoing')      => 'ongoing',
                __('Outgoing')     => 'outgoing',
            ]
        );
    }

    /**
     * Events list sort combo.
     *
     * @return  array<string, string>
     */
    public static function sortbyCombo(): array
    {
        return [
            __('Start date')   => 'event_startdt',
            __('End date')     => 'event_enddt',
            __('Date')         => 'post_dt',
            __('Title')        => 'post_title',
            __('Category')     => 'cat_title',
            __('Author')       => 'user_id',
            __('Status')       => 'post_status',
            __('Selected')     => 'post_selected',
            __('Localization') => 'event_address',
        ];
    }

    /**
     * Map zoom combo.
     *
     * @return  array<int|string, string>
     */
    public static function mapZoomCombo(): array
    {
        $combo = [];
        for ($i = 3; $i < 21; $i++) {
            $combo[(string) $i] = (string) $i;
        }

        return $combo;
    }

    /**
     * Map type combo.
     *
     * @return  array<string, string>
     */
    public static function mapTypeCombo(): array
    {
        return [
            __('road map')  => 'ROADMAP',
            __('satellite') => 'SATELLITE',
            __('hybrid')    => 'HYBRID',
            __('terrain')   => 'TERRAIN',
        ];
    }

    /**
     * Map provider combo.
     *
     * @return  array<string, string>
     */
    public static function mapProviderCombo(): array
    {
        return [
            'OpenStreetMap' => 'osm',
            'GoogleMaps'    => 'googlemaps',
        ];
    }

    /**
     * Get map provider.
     */
    public static function mapProvider(): string
    {
        $provider = (string) self::settings()->getStr('map_provider', false);

        return in_array($provider, self::mapProviderCombo(), true) ? $provider : 'osm';
    }
}
