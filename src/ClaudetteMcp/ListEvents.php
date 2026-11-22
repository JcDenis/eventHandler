<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler\ClaudetteMcp;

use Dotclear\Plugin\ClaudetteMcp\McpToolException;
use Dotclear\Plugin\eventHandler\EventHandler;
use Dotclear\Plugin\eventHandler\My;

/**
 * @brief       eventHandler tool for the plugin ClaudetteMcp: list events.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ListEvents extends EventTool
{
    private const PERIODS = [
        'all'         => 'all events',
        'scheduled'   => 'not started yet',
        'ongoing'     => 'started and not finished',
        'outgoing'    => 'not started yet or already finished',
        'started'     => 'already started',
        'notfinished' => 'not finished yet',
        'finished'    => 'already finished',
    ];

    public function name(): string
    {
        return 'list_events';
    }

    public function title(): string
    {
        return 'List blog events';
    }

    public function description(): string
    {
        return 'List events of the blog (plugin eventHandler) with their start and end dates and their place, by start date. ' .
            'Events are entries of type "' . My::POST_TYPE . '": the entries tools given to the connector work on them (read one with get_post). ' .
            'Only published events are listed by default.';
    }

    public function properties(): array
    {
        $periods = [];
        foreach (self::PERIODS as $period => $text) {
            $periods[] = '"' . $period . '": ' . $text;
        }

        return [
            'period' => [
                'type'        => 'string',
                'enum'        => array_keys(self::PERIODS),
                'description' => 'Events period, default: scheduled. ' . implode(', ', $periods) . '.',
            ],
            'status' => [
                'type'        => 'string',
                'enum'        => [...array_keys($this->statuses()), 'all'],
                'description' => 'Events status, default: published.',
            ],
            'limit' => [
                'type'        => 'integer',
                'description' => 'Events per page, 1 to 50, default: 20.',
            ],
            'page' => [
                'type'        => 'integer',
                'description' => 'Page number, default: 1.',
            ],
        ];
    }

    public function call(array $args): array
    {
        $period = $this->str($args, 'period', 'scheduled');
        if (!isset(self::PERIODS[$period])) {
            throw new McpToolException('Unknown period, use one of: ' . implode(', ', array_keys(self::PERIODS)));
        }

        $limit  = $this->int($args, 'limit', 20, 1, 50);
        $page   = $this->int($args, 'page', 1, 1);
        $params = $this->visibility($this->str($args, 'status', 'published'), My::POST_TYPE);

        $params['event_period'] = $period;
        // Past events: most recent first
        $params['order'] = 'EH.event_startdt ' . (in_array($period, ['finished', 'started'], true) ? 'DESC' : 'ASC');

        $eventHandler = new EventHandler();
        $total        = $eventHandler->getEvents($params, true)->intField(0);

        $params['limit'] = [($page - 1) * $limit, $limit];

        $rs     = $eventHandler->getEvents($params);
        $events = [];
        while ($rs->fetch()) {
            $events[] = array_merge($this->describe($rs), $this->describeEvent($rs));
        }

        return [
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'events' => $events,
        ];
    }
}
