<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\PostType;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       eventHandler prepend class.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Prepend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::PREPEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Public URLs
        App::url()->register('eventhandler_single', 'day', '^day/(.+)$', UrlHandler::eventSingle(...));
        App::url()->register('eventhandler_preview', 'daypreview', '^daypreview/(.+)$', UrlHandler::eventPreview(...));
        App::url()->register('eventhandler_list', 'days', '^days(|/.+)$', UrlHandler::eventList(...));
        App::url()->register('eventhandler_feed', 'daysfeed', '^daysfeed/(.+)$', UrlHandler::eventFeed(...));
        App::url()->register('eventhandler_pubrest', 'daysservice', '^daysservice/$', UrlHandler::eventService(...));

        // Events post type, backend URLs are set in Backend
        App::postTypes()->set(new PostType(
            My::POST_TYPE,
            '',
            App::url()->getURLFor('eventhandler_single', '%s'),
            'Events'
        ));

        // Template sort alias
        App::behavior()->addBehavior('templateCustomSortByAlias', function (ArrayObject $alias): void {
            $alias[My::POST_TYPE] = [
                'startdt' => 'event_startdt',
                'enddt'   => 'event_enddt',
            ];
        });

        // Backend REST services
        App::rest()->addFunction('unbindEventOfPost', RestMethods::unbindEventOfPost(...));

        return true;
    }
}
