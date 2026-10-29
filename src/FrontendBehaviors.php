<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Html;

/**
 * @brief       eventHandler frontend behaviors.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FrontendBehaviors
{
    /**
     * Public URL types of events pages.
     *
     * @var     array<int, string>  EVENTS_URL_TYPES
     */
    public const EVENTS_URL_TYPES = [
        'eventhandler_list',
        'eventhandler_single',
        'eventhandler_preview',
    ];

    /**
     * Get map scripts.
     *
     * @param   bool    $standalone     Add jQuery for standalone page
     */
    public static function mapHeaders(bool $standalone = false): string
    {
        $provider = My::mapProvider();
        $res      = '';

        if ($standalone || !App::blog()->settings()->get('system')->getBool('jquery_needed')) {
            $res .= '<script src="' . App::blog()->getQmarkURL() . 'pf=' . App::blog()->getJsJQuery() . '/jquery.js"></script>' . "\n";
        }

        if ($provider === 'osm') {
            $res .= '<link rel="stylesheet" type="text/css" href="' . My::fileURL('css/leaflet.css', true) . '">' . "\n" .
                '<script src="' . My::fileURL('js/osm/leaflet-src.js', true) . '"></script>' . "\n";
        }

        return $res . '<script src="' . My::fileURL('js/' . $provider . '/event-public-map.js', true) . '"></script>' . "\n";
    }

    /**
     * Public head content.
     */
    public static function publicHeadContent(): void
    {
        $service = App::blog()->url() . App::url()->getURLFor('eventhandler_pubrest');

        echo
        self::mapHeaders() .
        '<script src="' . My::fileURL('js/event-public-cal.js', true) . '"></script>' . "\n" .
        '<script>' . "\n" .
        '$(function(){' . "\n" .
        "  $.fn.eventHandlerCalendar.defaults.service_url = '" . Html::escapeJS($service) . "';\n" .
        "  $.fn.eventHandlerCalendar.defaults.service_func = 'eventHandlerCalendar';\n" .
        "  $.fn.eventHandlerCalendar.defaults.blog_uid = '" . Html::escapeJS(App::blog()->uid()) . "';\n" .
        "  $.fn.eventHandlerCalendar.defaults.msg_wait = '" . Html::escapeJS(__('Please wait...')) . "';\n" .
        "  $('.calendar-array').eventHandlerCalendar();\n" .
        '});' . "\n" .
        '</script>' . "\n";

        $extra_css = (string) My::settings()->getStr('public_extra_css', false);
        if ($extra_css !== '') {
            // Remove anything that could close the style tag
            echo '<style>' . "\n" . str_ireplace('</style', '', $extra_css) . "\n" . '</style>' . "\n";
        }
    }

    /**
     * Add plugin templates path.
     */
    public static function publicBeforeDocumentV2(): void
    {
        $theme = (string) App::blog()->settings()->get('system')->getStr('theme', false);
        if ($theme === '') {
            return;
        }

        $tplset = is_string($tplset = App::themes()->moduleInfo($theme, 'tplset')) ? $tplset : '';
        $root   = Path::real(My::path()) . DIRECTORY_SEPARATOR . 'default-templates' . DIRECTORY_SEPARATOR;

        App::frontend()->template()->setPath(
            App::frontend()->template()->getPath(),
            $root . ($tplset !== '' && is_dir($root . $tplset) ? $tplset : App::config()->defaultTplset())
        );
    }

    /**
     * Before entry content.
     */
    public static function publicEntryBeforeContent(): void
    {
        self::publicEntryContent('before');
    }

    /**
     * After entry content.
     */
    public static function publicEntryAfterContent(): void
    {
        self::publicEntryContent('after');
    }

    /**
     * Related entries or events of an entry content.
     *
     * @param   string  $place  The place (before or after)
     */
    protected static function publicEntryContent(string $place): void
    {
        if (App::url()->isType(self::EVENTS_URL_TYPES)) {
            // List of posts related to an event
            if (My::settings()->getStr('public_posts_of_event_place', false) === $place) {
                echo App::frontend()->template()->getData('eventhandler-postsofevent.html');
            }
        } elseif (My::settings()->getStr('public_events_of_post_place', false) === $place) {
            // List of events related to an entry
            echo App::frontend()->template()->getData('eventhandler-eventsofpost.html');
        }
    }
}
