<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       eventHandler install class.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Install
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Table
        // Its structure has not changed for years and synchronizing an existing table
        // fails on SQLite (varchar fields with NULL default), so it is only created if missing.
        try {
            if (!in_array(App::db()->con()->prefix() . My::TABLE_NAME, App::db()->con()->schema()->getTables(), true)) {
                $s = App::db()->structure();
                $s->table(My::TABLE_NAME)
                    ->field('post_id', 'bigint', 0, false)
                    ->field('event_startdt', 'timestamp', 0, false, 'now()')
                    ->field('event_enddt', 'timestamp', 0, false, 'now()')
                    ->field('event_address', 'text', 0, true, null)
                    ->field('event_latitude', 'varchar', 25, true, null)
                    ->field('event_longitude', 'varchar', 25, true, null)
                    ->field('event_zoom', 'integer', 0, true, 0)

                    ->index('idx_event_post_id', 'btree', 'post_id')
                    ->index('idx_event_event_start', 'btree', 'event_startdt')
                    ->index('idx_event_event_end', 'btree', 'event_enddt')
                    ->reference('fk_event_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');

                App::db()->structure()->synchronize($s);
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        try {
            // Settings
            $css = file_get_contents(My::path() . '/css/default-eventhandler.css');

            $s = My::settings();
            $s->put('active', false, 'boolean', 'Enabled eventHandler extension', false, true);
            $s->put('public_events_of_post_place', 'after', 'string', 'Display related events on entries', false, true);
            $s->put('public_posts_of_event_place', 'after', 'string', 'Display related posts on events', false, true);
            $s->put('public_events_list_sortby', '', 'string', 'Default field for ordering events list', false, true);
            $s->put('public_events_list_order', '', 'string', 'Default order (asc or desc) for events list', false, true);
            $s->put('public_hidden_categories', [], 'array', 'List of categories to hide from post content and widgets', false, true);
            $s->put('public_map_zoom', 9, 'integer', 'Default zoom of map', false, true);
            $s->put('public_map_type', 'ROADMAP', 'string', 'Default type of map', false, true);
            $s->put('public_extra_css', is_string($css) ? $css : '', 'string', 'Custom CSS', false, true);
            $s->put('map_provider', 'osm', 'string', 'Map provider', false, true);
            $s->put('map_tile_layer', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'string', 'Tile Layer for OSM', false, true);
            $s->put('map_api_key', '', 'string', 'Map API Key', false, true);

            // Upgrade: hidden categories were serialized before 2026.10.29
            $value = $s->get('public_hidden_categories');
            if (is_string($value) && $value !== '') {
                $s->put('public_hidden_categories', My::hiddenCategories(), 'array');
            }

            return true;
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }
}
