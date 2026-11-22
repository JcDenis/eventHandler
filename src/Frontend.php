<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       eventHandler frontend class.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Frontend
{
    use TraitProcess;

    /**
     * Template blocks.
     *
     * @var     array<int, string>  TPL_BLOCKS
     */
    public const TPL_BLOCKS = [
        // Page of events
        'EventsIf',
        'EventsCount',
        'EventsEntries',
        'EventsPagination',
        'EventsEntryIf',
        'EventsDateHeader',
        'EventsDateFooter',
        // Events of an entry
        'EventsOfPost',
        'EventsOfPostHeader',
        'EventsOfPostFooter',
        'EventOfPostIf',
        // Entries of an event
        'PostsOfEvent',
        'PostsOfEventHeader',
        'PostsOfEventFooter',
        'PostOfEventIf',
    ];

    /**
     * Template values.
     *
     * @var     array<int, string>  TPL_VALUES
     */
    public const TPL_VALUES = [
        'BlogTimezone',
        // Page of events
        'EventsMenuPeriod',
        'EventsMenuSortOrder',
        'EventsFeedURL',
        'EventsURL',
        'EventsPeriod',
        'EventsInterval',
        'EventsEntryDate',
        'EventsEntryTime',
        'EventsEntryCategoryURL',
        'EventsEntryAddress',
        'EventsEntryLatitude',
        'EventsEntryLongitude',
        'EventsEntryZoom',
        'EventsEntryDuration',
        'EventsEntryPeriod',
        'EventsEntryMap',
        // Events of an entry
        'EventOfPostURL',
        'EventOfPostTitle',
        'EventOfPostDate',
        'EventOfPostTime',
        'EventOfPostAuthorCommonName',
        'EventOfPostAuthorLink',
        'EventOfPostCategory',
        'EventOfPostCategoryURL',
        'EventOfPostAddress',
        'EventOfPostDuration',
        'EventOfPostPeriod',
        // Entries of an event
        'PostOfEventURL',
        'PostOfEventTitle',
        'PostOfEventDate',
        'PostOfEventTime',
        'PostOfEventAuthorCommonName',
        'PostOfEventAuthorLink',
        'PostOfEventCategory',
        'PostOfEventCategoryURL',
    ];

    public static function init(): bool
    {
        return self::status(My::checkContext(My::FRONTEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Events for AI assistants connected to the blog with the plugin ClaudetteMcp (never required).
        // Like in backend: the plugin must be active on the blog, the user must be able to write entries.
        App::behavior()->addBehaviors([
            'ClaudetteMcpAddPostType' => function (ArrayObject $types): void {
                if (My::isActive() && App::auth()->check(App::auth()->makePermissions([
                    App::auth()::PERMISSION_USAGE,
                    App::auth()::PERMISSION_CONTENT_ADMIN,
                ]), App::blog()->id())) {
                    $types->append(My::POST_TYPE);
                }
            },
            // Classes of src/ClaudetteMcp/ need ClaudetteMcp: only used from its behaviors
            'ClaudetteMcpAddTool' => function (ArrayObject $tools): void {
                $tools->append(new ClaudetteMcp\ListEvents());
                $tools->append(new ClaudetteMcp\SetEvent());
                ClaudetteMcp\EventTool::register();
            },
        ]);

        // Localisation
        __('scheduled');
        __('ongoing');
        __('finished');

        // Widgets are registered even if plugin is not active (they check it)
        App::behavior()->addBehavior('initWidgets', Widgets::init(...));

        // Template tags
        foreach (self::TPL_BLOCKS as $block) {
            App::frontend()->template()->addBlock($block, Template::$block(...));
        }
        foreach (self::TPL_VALUES as $value) {
            App::frontend()->template()->addValue($value, Template::$value(...));
        }

        if (!My::isActive()) {
            return true;
        }

        App::behavior()->addBehaviors([
            'publicHeadContent'        => FrontendBehaviors::publicHeadContent(...),
            'publicBeforeDocumentV2'   => FrontendBehaviors::publicBeforeDocumentV2(...),
            'publicEntryBeforeContent' => FrontendBehaviors::publicEntryBeforeContent(...),
            'publicEntryAfterContent'  => FrontendBehaviors::publicEntryAfterContent(...),
            // add events to the types filter of plugin saba (optional)
            'sabaAddPostTypes' => function (ArrayObject $types): void {
                $types[My::POST_TYPE] = __('Event');
            },
            'publicBreadcrumb'         => function (string $context, string $separator): string {
                return match ($context) {
                    'eventhandler_list' => __('Events'),
                    default             => '',
                };
            },
        ]);

        return true;
    }
}
