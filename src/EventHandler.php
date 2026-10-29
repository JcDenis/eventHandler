<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\JoinStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       eventHandler main class.
 * @ingroup     eventHandler
 *
 * Events are entries of type "eventhandler" with extra fields
 * in eventhandler table. Relations between entries and events
 * are stored as entries metadata.
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class EventHandler
{
    /**
     * Events post type.
     *
     * @deprecated  since 2026.10.29, use My::POST_TYPE instead
     */
    final public const POST_TYPE = My::POST_TYPE;

    /**
     * Event table name (with prefix).
     */
    protected string $table;

    /**
     * Constructor.
     *
     * @param   string  $type   The events post type
     */
    public function __construct(
        protected string $type = My::POST_TYPE
    ) {
        $this->table = App::db()->con()->prefix() . My::TABLE_NAME;
    }

    /**
     * Get events table name.
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Open an event cursor.
     */
    public function openEventCursor(): Cursor
    {
        return App::db()->con()->openCursor($this->table);
    }

    /**
     * Get events.
     *
     * Extra parameters:
     * - event_id: alias of post_id
     * - event_period: all, ongoing, outgoing, scheduled, started, notfinished, finished
     * - event_startdt, event_enddt: dates used by event_period (default: now)
     * - event_start_year, event_start_month, event_start_day: filter on event start date
     * - event_end_year, event_end_month, event_end_day: filter on event end date
     * - event_address: filter on event address
     *
     * @param   array<string, mixed>|ArrayObject<string, mixed>     $params         The parameters
     * @param   bool                                                $count_only     Only count events
     * @param   null|SelectStatement                                $ext_sql        Extra SQL statement
     */
    public function getEvents(array|ArrayObject $params = [], bool $count_only = false, ?SelectStatement $ext_sql = null): MetaRecord
    {
        $params = $params instanceof ArrayObject ? $params->getArrayCopy() : $params;

        if (isset($params['event_id'])) {
            $params['post_id'] = $params['event_id'];
            unset($params['event_id']);
        }

        if (isset($params['event_type'])) {
            $params['post_type'] = $params['event_type'];
            unset($params['event_type']);
        }

        if (!isset($params['post_type'])) {
            $params['post_type'] = $this->type;
        }

        $columns = [];
        if (isset($params['columns'])) {
            $columns = is_array($params['columns']) ? $params['columns'] : [$params['columns']];
        }
        $params['columns'] = array_merge($columns, [
            'event_startdt',
            'event_enddt',
            'event_address',
            'event_latitude',
            'event_longitude',
            'event_zoom',
        ]);

        $str = fn (string $key): string => isset($params[$key]) && is_scalar($params[$key]) ? (string) $params[$key] : '';
        $int = fn (string $key): int => isset($params[$key]) && is_numeric($params[$key]) ? (int) $params[$key] : 0;

        $sql = $ext_sql instanceof SelectStatement ? clone $ext_sql : new SelectStatement();
        $sql->join(
            (new JoinStatement())
                ->inner()
                ->from($sql->as($this->table, 'EH'))
                ->on('EH.post_id = P.post_id')
                ->statement()
        );

        $where = '';

        $period = $str('event_period');
        if ($period !== '' && $period !== 'all') {
            $op = match ($period) {
                'ongoing'  => ['<', '>', 'AND'],
                'outgoing' => ['>', '<', 'OR'],
                'notstarted', 'scheduled' => ['>', '!', ''],
                'started'     => ['<', '!', ''],
                'notfinished' => ['!', '>', ''],
                'finished'    => ['!', '<', ''],
                default       => ['=', '=', 'AND'],
            };
            $now   = date('Y-m-d H:i:s');
            $start = $str('event_startdt') !== '' ? $str('event_startdt') : $now;
            $end   = $str('event_enddt')   !== '' ? $str('event_enddt') : $now;

            $parts = [];
            if ($op[0] !== '!') {
                $parts[] = 'EH.event_startdt ' . $op[0] . ' ' . $sql->quote($start);
            }
            if ($op[1] !== '!') {
                $parts[] = 'EH.event_enddt ' . $op[1] . ' ' . $sql->quote($end);
            }
            $where .= 'AND (' . implode(' ' . ($op[2] !== '' ? $op[2] : 'AND') . ' ', $parts) . ') ';
        }

        $con = App::db()->con();
        foreach ([
            'event_start_year'  => ['EH.event_startdt', '%Y', '%04d'],
            'event_start_month' => ['EH.event_startdt', '%m', '%02d'],
            'event_start_day'   => ['EH.event_startdt', '%d', '%02d'],
            'event_end_year'    => ['EH.event_enddt', '%Y', '%04d'],
            'event_end_month'   => ['EH.event_enddt', '%m', '%02d'],
            'event_end_day'     => ['EH.event_enddt', '%d', '%02d'],
        ] as $key => $def) {
            if ($int($key) !== 0) {
                $where .= 'AND ' . $con->dateFormat($def[0], $def[1]) . " = '" . sprintf($def[2], $int($key)) . "' ";
            }
        }

        // Localization
        if ($str('event_address') !== '') {
            $where .= 'AND EH.event_address = ' . $sql->quote($str('event_address')) . ' ';
        }

        $params['sql'] = $where . $str('sql');

        // --BEHAVIOR-- coreEventHandlerBeforeGetEvents -- EventHandler, array
        App::behavior()->callBehavior('coreEventHandlerBeforeGetEvents', $this, ['params' => &$params]);

        $rs = App::blog()->getPosts($params, $count_only, $sql);

        if (!$count_only && empty($params['sql_only'])) {
            $rs->extend(RsExtension::class);
        }

        // --BEHAVIOR-- coreEventHandlerGetEvents -- MetaRecord
        App::behavior()->callBehavior('coreEventHandlerGetEvents', $rs);

        return $rs;
    }

    /**
     * Get events related to an entry.
     *
     * Parameter post_id is required.
     *
     * @param   array<string, mixed>|ArrayObject<string, mixed>     $params         The parameters
     * @param   bool                                                $count_only     Only count events
     */
    public function getEventsByPost(array|ArrayObject $params = [], bool $count_only = false): MetaRecord
    {
        $params  = $params instanceof ArrayObject ? $params->getArrayCopy() : $params;
        $post_id = isset($params['post_id']) && is_numeric($params['post_id']) ? (int) $params['post_id'] : 0;
        if ($post_id === 0) {
            return MetaRecord::newFromArray([]);
        }
        unset($params['post_id']);

        $meta_type = isset($params['meta_type']) && is_string($params['meta_type']) ? $params['meta_type'] : My::META_TYPE;
        unset($params['meta_type']);

        $sql = new SelectStatement();
        $on  = App::db()->con()->syntax() === 'mysql' ?
            'EM.meta_id = CAST(P.post_id as char)' :
            'CAST(EM.meta_id as int) = P.post_id';

        $sql->join(
            (new JoinStatement())
                ->inner()
                ->from($sql->as(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME, 'EM'))
                ->on($on)
                ->statement()
        );
        $sql->and('EM.post_id = ' . $post_id);
        $sql->and('EM.meta_type = ' . $sql->quote($meta_type));

        return $this->getEvents($params, $count_only, $sql);
    }

    /**
     * Get entries related to an event.
     *
     * Parameter event_id is required.
     *
     * @param   array<string, mixed>|ArrayObject<string, mixed>     $params         The parameters
     * @param   bool                                                $count_only     Only count entries
     */
    public function getPostsByEvent(array|ArrayObject $params = [], bool $count_only = false): MetaRecord
    {
        $params   = $params instanceof ArrayObject ? $params->getArrayCopy() : $params;
        $event_id = isset($params['event_id']) && is_numeric($params['event_id']) ? (int) $params['event_id'] : 0;
        if ($event_id === 0) {
            return MetaRecord::newFromArray([]);
        }
        unset($params['event_id']);

        $meta_type = isset($params['meta_type']) && is_string($params['meta_type']) ? $params['meta_type'] : My::META_TYPE;
        unset($params['meta_type'], $params['event_type']);

        if (!isset($params['post_type'])) {
            $params['post_type'] = '';
        }

        $sql = new SelectStatement();
        $sql->join(
            (new JoinStatement())
                ->inner()
                ->from($sql->as(App::db()->con()->prefix() . App::meta()::META_TABLE_NAME, 'EM'))
                ->on('EM.post_id = P.post_id')
                ->statement()
        );
        $sql->and('EM.meta_id = ' . $sql->quote((string) $event_id));
        $sql->and('EM.meta_type = ' . $sql->quote($meta_type));

        return App::blog()->getPosts($params, $count_only, $sql);
    }

    /**
     * Count entries related to an event.
     *
     * @param   int     $event_id   The event ID
     */
    public function countPostsByEvent(int $event_id): int
    {
        return $this->getPostsByEvent(['event_id' => $event_id], true)->intField(0);
    }

    /**
     * Add an event.
     *
     * @param   Cursor  $cur_post   The post cursor
     * @param   Cursor  $cur_event  The event cursor
     *
     * @return  int     The new event ID
     */
    public function addEvent(Cursor $cur_post, Cursor $cur_event): int
    {
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('You are not allowed to create an event'));
        }

        $this->getEventCursor(0, $cur_post, $cur_event);

        // --BEHAVIOR-- coreEventHandlerBeforeEventAdd -- EventHandler, Cursor, Cursor
        App::behavior()->callBehavior('coreEventHandlerBeforeEventAdd', $this, $cur_post, $cur_event);

        // Blog::addPost() locks post table, so no transaction here
        $post_id = App::blog()->addPost($cur_post);

        try {
            $cur_event->setField('post_id', $post_id);
            $cur_event->insert();
        } catch (Exception $e) {
            // Do not keep an event entry without event data
            App::blog()->delPost($post_id);

            throw $e;
        }

        // --BEHAVIOR-- coreEventHandlerAfterEventAdd -- EventHandler, int, Cursor, Cursor
        App::behavior()->callBehavior('coreEventHandlerAfterEventAdd', $this, $post_id, $cur_post, $cur_event);

        return $post_id;
    }

    /**
     * Update an event.
     *
     * @param   int     $post_id    The event ID
     * @param   Cursor  $cur_post   The post cursor
     * @param   Cursor  $cur_event  The event cursor
     */
    public function updEvent(int $post_id, Cursor $cur_post, Cursor $cur_event): void
    {
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('You are not allowed to update events'));
        }

        if ($post_id <= 0) {
            throw new Exception(__('No such event ID'));
        }

        $this->getEventCursor($post_id, $cur_post, $cur_event);

        App::db()->con()->begin();

        try {
            // --BEHAVIOR-- coreEventHandlerBeforeEventUpdate -- EventHandler, int, Cursor, Cursor
            App::behavior()->callBehavior('coreEventHandlerBeforeEventUpdate', $this, $post_id, $cur_post, $cur_event);

            App::blog()->updPost($post_id, $cur_post);

            // An entry changed into an event has no event row yet
            if ($this->hasEventRow($post_id)) {
                $cur_event->update('WHERE post_id = ' . $post_id . ' ');
            } else {
                $cur_event->setField('post_id', $post_id);
                $cur_event->insert();
            }
        } catch (Exception $e) {
            App::db()->con()->rollback();

            throw $e;
        }

        App::db()->con()->commit();
    }

    /**
     * Delete an event.
     *
     * @param   int     $post_id    The event ID
     */
    public function delEvent(int $post_id): void
    {
        if (!App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            throw new Exception(__('You are not allowed to delete events'));
        }

        if ($post_id <= 0) {
            throw new Exception(__('No such event ID'));
        }

        // --BEHAVIOR-- coreEventHandlerEventDelete -- EventHandler, int
        App::behavior()->callBehavior('coreEventHandlerEventDelete', $this, $post_id);

        App::blog()->delPost($post_id);
        $this->cleanEvent($post_id);
    }

    /**
     * Delete event row and relations to an event.
     *
     * Used when an event entry is deleted.
     *
     * @param   int     $post_id    The event ID
     */
    public function cleanEvent(int $post_id): void
    {
        $sql = new DeleteStatement();
        $sql
            ->from($this->table)
            ->where('post_id = ' . $post_id)
            ->delete();

        App::meta()->delMeta((string) $post_id, My::META_TYPE);
    }

    /**
     * Check if an entry has an event row.
     *
     * @param   int     $post_id    The entry ID
     */
    public function hasEventRow(int $post_id): bool
    {
        $sql = new SelectStatement();
        $rs  = $sql
            ->column($sql->count('post_id'))
            ->from($this->table)
            ->where('post_id = ' . $post_id)
            ->select();

        return $rs instanceof MetaRecord && $rs->intField(0) > 0;
    }

    /**
     * Bind an entry to an event.
     *
     * @param   int     $post_id    The entry ID
     * @param   int     $event_id   The event ID
     */
    public function bindEvent(int $post_id, int $event_id): void
    {
        if ($post_id <= 0 || $event_id <= 0 || $post_id === $event_id) {
            return;
        }

        App::meta()->delPostMeta($post_id, My::META_TYPE, (string) $event_id);
        App::meta()->setPostMeta($post_id, My::META_TYPE, (string) $event_id);
    }

    /**
     * Unbind an entry from an event or from all its events.
     *
     * @param   int     $post_id    The entry ID
     * @param   int     $event_id   The event ID, 0 for all events
     */
    public function unbindEvent(int $post_id, int $event_id = 0): void
    {
        if ($post_id <= 0) {
            return;
        }

        App::meta()->delPostMeta($post_id, My::META_TYPE, $event_id > 0 ? (string) $event_id : null);
    }

    /**
     * Unbind all entries from an event.
     *
     * @param   int     $event_id   The event ID
     *
     * @return  int     Number of unbound entries
     */
    public function unbindPostsOfEvent(int $event_id): int
    {
        if ($event_id <= 0) {
            return 0;
        }

        return count(App::meta()->delMeta((string) $event_id, My::META_TYPE));
    }

    /**
     * Check and complete event cursors.
     *
     * @param   int     $post_id    The event ID (0 for new event)
     * @param   Cursor  $cur_post   The post cursor
     * @param   Cursor  $cur_event  The event cursor
     */
    private function getEventCursor(int $post_id, Cursor $cur_post, Cursor $cur_event): void
    {
        $field = fn (string $name): string => is_scalar($value = $cur_event->getField($name)) ? (string) $value : '';

        // Required a start date
        if ($field('event_startdt') === '') {
            throw new Exception(__('No event start date'));
        }
        // Required an end date
        if ($field('event_enddt') === '') {
            throw new Exception(__('No event end date'));
        }
        // Compare dates
        if (strtotime($field('event_enddt')) < strtotime($field('event_startdt'))) {
            throw new Exception(__('Start date greater than end date'));
        }
        // Full coordinates or nothing
        if (($field('event_latitude') !== '') !== ($field('event_longitude') !== '')) {
            throw new Exception(__('Not full coordinate'));
        }
        // Coordinates format
        foreach (['event_latitude', 'event_longitude'] as $coordinate) {
            if ($field($coordinate) !== '' && !preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $field($coordinate))) {
                throw new Exception(__('Wrong format of coordinate'));
            }
        }

        // Set post type
        $cur_post->setField('post_type', $this->type);

        // Force no comment and no trackback
        $cur_post->setField('post_open_comment', 0);
        $cur_post->setField('post_open_tb', 0);

        // unset post_id
        $cur_event->unsetField('post_id');

        // --BEHAVIOR-- coreEventHandlerGetEventCursor -- EventHandler, int, Cursor, Cursor
        App::behavior()->callBehavior('coreEventHandlerGetEventCursor', $this, $post_id, $cur_post, $cur_event);
    }

    /**
     * Get human readable duration from timestamp.
     *
     * @param   int     $timestamp  The duration in seconds
     */
    public static function getReadableDuration(int $timestamp): string
    {
        $timestamp = max(0, $timestamp);

        $day       = intdiv($timestamp, 86400);
        $timestamp = $timestamp % 86400;
        $hou       = intdiv($timestamp, 3600);
        $timestamp = $timestamp % 3600;
        $min       = intdiv($timestamp, 60);

        $time = [];
        if ($day > 0) {
            $time[] = $day === 1 ? __('one day') : sprintf(__('%s days'), $day);
        }
        if ($hou > 0) {
            $time[] = $hou === 1 ? __('one hour') : sprintf(__('%s hours'), $hou);
        }
        if ($min > 0) {
            $time[] = $min === 1 ? __('one minute') : sprintf(__('%s minutes'), $min);
        }

        return $time === [] ? __('instantaneous') : implode(' ', $time);
    }

    /**
     * Build HTML content for events maps.
     *
     * Markers are build in RsExtension::getMapVEvent().
     *
     * @param   string  $width      The map width (with unit)
     * @param   string  $height     The map height (with unit)
     * @param   string  $type       The map type
     * @param   int     $zoom       The map zoom
     * @param   int     $info       Show markers info
     * @param   float   $lat        The map center latitude
     * @param   float   $lng        The map center longitude
     * @param   string  $markers    The map markers
     */
    public static function getMapContent(string $width, string $height, string $type, int $zoom, int $info, float $lat, float $lng, string $markers): string
    {
        $style = [];
        if ($width !== '') {
            $style[] = 'width:' . Html::escapeHTML($width) . ';';
        }
        if ($height !== '') {
            $style[] = 'height:' . Html::escapeHTML($height) . ';';
        }

        $tile_layer = (string) My::settings()->getStr('map_tile_layer', false);

        return
            '<div style="display:none;" class="event-map">' . "\n" .
            '<div ' . ($style !== [] ? 'style="' . implode('', $style) . '" ' : '') . 'class="event-map-place"><p>' . __('Please wait, try to create map...') . '</p></div>' . "\n" .
            '<div style="display:none;" class="event-map-info">' . "\n" .
            '<p class="event-map-info-zoom">' . $zoom . '</p>' . "\n" .
            '<p class="event-map-info-type">' . Html::escapeHTML($type) . '</p>' . "\n" .
            '<p class="event-map-info-info">' . $info . '</p>' . "\n" .
            '<p class="event-map-info-lat">' . $lat . '</p>' . "\n" .
            '<p class="event-map-info-lng">' . $lng . '</p>' . "\n" .
            ($tile_layer !== '' ? '<p class="event-map-info-tile-layer">' . Html::escapeHTML($tile_layer) . '</p>' . "\n" : '') .
            '</div>' . "\n" .
            $markers . "\n" .
            '</div>';
    }
}
