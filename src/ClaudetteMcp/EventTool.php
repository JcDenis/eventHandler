<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler\ClaudetteMcp;

use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Plugin\ClaudetteMcp\Tool\PostTool;
use Dotclear\Plugin\eventHandler\EventHandler;
use Dotclear\Plugin\eventHandler\My;

/**
 * @brief       eventHandler tools helper for the plugin ClaudetteMcp.
 * @ingroup     eventHandler
 *
 * ClaudetteMcp connects an AI assistant to the blog. Events are entries of type "eventhandler":
 * the type is opened to the entries tools of ClaudetteMcp (create_post, update_post, set_post_status...),
 * and these tools add what is special to events (dates and place).
 * These classes are only loaded by ClaudetteMcp, which is not required by eventHandler.
 *
 * @author      Jean-Christian Denis
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class EventTool extends PostTool
{
    /**
     * Only when ClaudetteMcp builds its tools: an event created with create_post gets its event row.
     */
    public static function register(): void
    {
        App::behavior()->addBehaviors([
            'coreBeforePostCreate' => self::beforePostCreate(...),
            'coreAfterPostCreate'  => self::afterPostCreate(...),
        ]);
    }

    /**
     * Like EventHandler: no comment and no trackback on events.
     */
    public static function beforePostCreate(mixed $blog, Cursor $cur): void
    {
        if ($cur->getField('post_type') === My::POST_TYPE) {
            $cur->setField('post_open_comment', 0);
            $cur->setField('post_open_tb', 0);
        }
    }

    /**
     * An event without event row is not seen by eventHandler: start and end at the date of the entry.
     */
    public static function afterPostCreate(mixed $blog, Cursor $cur): void
    {
        $post_id = is_numeric($id = $cur->getField('post_id')) ? (int) $id : 0;
        if ($cur->getField('post_type') !== My::POST_TYPE || $post_id === 0) {
            return;
        }

        $eventHandler = new EventHandler();
        if (!$eventHandler->hasEventRow($post_id)) {
            $dt  = is_string($dt = $cur->getField('post_dt')) && $dt !== '' ? $dt : date('Y-m-d H:i:00');
            $row = $eventHandler->openEventCursor();
            $row->setField('post_id', $post_id);
            $row->setField('event_startdt', $dt);
            $row->setField('event_enddt', $dt);
            $row->insert();
        }
    }

    /**
     * Like in backend: the plugin must be active on the blog, the user must be able to write entries.
     */
    public function available(): bool
    {
        return My::isActive() && $this->canWrite();
    }

    /**
     * Describe the event part of an events record.
     *
     * @return  array<string, mixed>
     */
    protected function describeEvent(MetaRecord $rs): array
    {
        return [
            'start'     => substr($rs->strField('event_startdt'), 0, 16),
            'end'       => substr($rs->strField('event_enddt'), 0, 16),
            'address'   => $rs->strField('event_address'),
            'latitude'  => $rs->strField('event_latitude'),
            'longitude' => $rs->strField('event_longitude'),
        ];
    }
}
