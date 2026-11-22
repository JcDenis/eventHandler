<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Dotclear\Plugin\eventHandler\EventHandler;
use Dotclear\Plugin\eventHandler\My;

/**
 * @brief       eventHandler tool for the plugin ClaudetteMcp: set dates and place of an event.
 * @ingroup     eventHandler
 *
 * Same checks as EventHandler: end after start, full coordinates or none.
 * The entry itself (title, content, status) is changed with the entries tools of ClaudetteMcp.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class SetEvent extends EventTool
{
    public function name(): string
    {
        return 'set_event';
    }

    public function title(): string
    {
        return 'Set dates and place of an event';
    }

    public function description(): string
    {
        return 'Set the start and end dates and the place of an event of the blog (plugin eventHandler). Only given arguments are changed. ' .
            'Create the event before with create_post and type "' . My::POST_TYPE . '": it starts and ends at the date of the entry until set here. ' .
            'An event can be changed only by its author or by a content administrator. Changes of a published event are visible at once.';
    }

    public function properties(): array
    {
        return [
            'id' => [
                'type'        => 'integer',
                'description' => 'The event ID (see list_events).',
            ],
            'start' => [
                'type'        => 'string',
                'description' => 'The start date as "YYYY-MM-DD HH:MM", in the timezone of the user (see get_blog).',
            ],
            'end' => [
                'type'        => 'string',
                'description' => 'The end date as "YYYY-MM-DD HH:MM", in the timezone of the user, not before the start.',
            ],
            'address' => [
                'type'        => 'string',
                'description' => 'The place of the event (postal address or name), empty for none.',
            ],
            'latitude' => [
                'type'        => 'string',
                'description' => 'The latitude of the place, decimal (ex: 45.7578), empty for none. Give both latitude and longitude, or none.',
            ],
            'longitude' => [
                'type'        => 'string',
                'description' => 'The longitude of the place, decimal (ex: 4.8320), empty for none.',
            ],
        ];
    }

    public function required(): array
    {
        return ['id'];
    }

    public function readOnly(): bool
    {
        return false;
    }

    public function call(array $args): array
    {
        $id   = $this->int($args, 'id');
        $post = $this->fetch($id);

        if ($post->strField('post_type') !== My::POST_TYPE) {
            throw new McpToolException('This entry is not an event, see list_events');
        }
        if (!$this->editable($post)) {
            throw new McpToolException('You are not allowed to update this event');
        }

        $eventHandler = new EventHandler();
        $rs           = $eventHandler->getEvents(['event_id' => $id, 'post_status' => array_values($this->statuses())]);
        $current      = $rs->isEmpty() ? [
            'start'     => substr($post->strField('post_dt'), 0, 16),
            'end'       => substr($post->strField('post_dt'), 0, 16),
            'address'   => '',
            'latitude'  => '',
            'longitude' => '',
        ] : $this->describeEvent($rs);

        $value = fn (string $name): string => $this->has($args, $name) ? $this->str($args, $name) : (is_string($v = $current[$name] ?? '') ? $v : '');

        $start = $this->date($value('start'));
        $end   = $this->date($value('end'));
        $lat   = $value('latitude');
        $lng   = $value('longitude');

        if ($end < $start) {
            throw new McpToolException('The end date is before the start date');
        }
        if (($lat !== '') !== ($lng !== '')) {
            throw new McpToolException('Give both latitude and longitude, or none');
        }
        foreach ([$lat, $lng] as $coordinate) {
            if ($coordinate !== '' && !preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $coordinate)) {
                throw new McpToolException('Wrong format of coordinate, use a decimal number (ex: 45.7578)');
            }
        }

        $cur = $eventHandler->openEventCursor();
        $cur->setField('event_startdt', $start);
        $cur->setField('event_enddt', $end);
        $cur->setField('event_address', $value('address'));
        $cur->setField('event_latitude', $lat);
        $cur->setField('event_longitude', $lng);

        if ($eventHandler->hasEventRow($id)) {
            $cur->update('WHERE post_id = ' . $id . ' ');
        } else {
            $cur->setField('post_id', $id);
            $cur->insert();
        }
        App::blog()->triggerBlog();

        $rs = $eventHandler->getEvents(['event_id' => $id, 'post_status' => array_values($this->statuses())]);

        return array_merge($this->describe($post), $this->describeEvent($rs));
    }
}
