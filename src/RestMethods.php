<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Exception;

/**
 * @brief       eventHandler REST methods.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class RestMethods
{
    /**
     * Unbind an event from an entry (backend).
     *
     * @param   array<string, mixed>    $get    The GET parameters
     * @param   array<string, mixed>    $post   The POST parameters
     *
     * @return  array<string, mixed>
     */
    public static function unbindEventOfPost(array $get, array $post): array
    {
        $post_id  = isset($post['postId'])  && is_numeric($post['postId']) ? (int) $post['postId'] : 0;
        $event_id = isset($post['eventId']) && is_numeric($post['eventId']) ? (int) $post['eventId'] : 0;

        if ($post_id <= 0) {
            throw new Exception(__('No such post ID'));
        }

        if ($event_id <= 0) {
            throw new Exception(__('No such event ID'));
        }

        try {
            (new EventHandler())->unbindEvent($post_id, $event_id);
        } catch (Exception) {
            throw new Exception(__('An error occured when trying de unbind event'));
        }

        return ['message' => __('Event removed from post')];
    }

    /**
     * Get calendar (frontend).
     *
     * @param   array<string, mixed>    $get    The GET parameters
     *
     * @return  array<string, mixed>
     */
    public static function calendar(array $get): array
    {
        if (!My::isActive()) {
            throw new Exception(__('Event is disabled on this blog'));
        }

        $current   = isset($get['curDate'])   && is_string($get['curDate']) ? $get['curDate'] : '';
        $direction = isset($get['reqDirection']) && $get['reqDirection'] === 'next' ? 1 : -1;
        $weekstart = !empty($get['weekStart']);
        $startonly = !isset($get['startOnly']) || !empty($get['startOnly']);

        try {
            $ts = (int) mktime(0, 0, 0, (int) substr($current, 4, 2) + $direction, 1, (int) substr($current, 0, 4));

            $table = (new Calendar((int) date('Y', $ts), (int) date('m', $ts), $weekstart))->parse($startonly, true);
        } catch (Exception) {
            throw new Exception(__('Failed to get calendar'));
        }

        return ['calendar' => $table];
    }
}
