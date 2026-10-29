<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       eventHandler frontend URL handler.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class UrlHandler
{
    /**
     * Events list sort fields.
     *
     * @var     array<string, string>   SORT_FIELDS
     */
    public const SORT_FIELDS = [
        'title'    => 'LOWER(post_title)',
        'selected' => 'post_selected',
        'author'   => 'LOWER(user_id)',
        'date'     => 'post_dt',
        'startdt'  => 'event_startdt',
        'enddt'    => 'event_enddt',
    ];

    /**
     * Public REST service (calendar).
     */
    public static function eventService(?string $args): void
    {
        App::rest()->addFunction('eventHandlerCalendar', RestMethods::calendar(...));
        App::rest()->serve();
        dotclear_exit();
    }

    /**
     * Single event page.
     */
    public static function eventSingle(?string $args): void
    {
        $args = (string) $args;
        if ($args === '' || !App::frontend()->context()->preview && !My::isActive()) {
            App::url()::p404();
        }

        $is_ical = self::isDocument($args, '/ical.ics');
        $is_hcal = self::isDocument($args, '/hcal.html');
        $is_gmap = self::isDocument($args, '/gmap');

        App::blog()->withoutPassword(false);

        $posts = (new EventHandler())->getEvents([
            'post_type' => My::POST_TYPE,
            'post_url'  => $args,
        ]);

        App::frontend()->context()->posts = $posts;

        App::frontend()->context()->comment_preview = new ArrayObject([
            'content'    => '',
            'rawcontent' => '',
            'name'       => '',
            'mail'       => '',
            'site'       => '',
            'preview'    => false,
            'remember'   => false,
        ]);

        App::blog()->withoutPassword(true);

        if ($posts->isEmpty()) {
            // The specified page does not exist.
            App::url()::p404();
        }

        $post_id       = $posts->intField('post_id');
        $post_password = $posts->strField('post_password');

        // Password protected entry
        if ($post_password !== '' && !App::frontend()->context()->preview) {
            // Get passwords cookie
            $pwd_cookie = [];
            if (isset($_COOKIE['dc_passwd']) && is_string($_COOKIE['dc_passwd'])) {
                try {
                    $pwd_cookie = (array) json_decode($_COOKIE['dc_passwd'], null, 512, JSON_THROW_ON_ERROR);
                } catch (Exception) {
                    $pwd_cookie = [];
                }
            }

            // Check for match (post_id key is prefixed by '#' as in core)
            if ((!empty($_POST['password']) && is_string($_POST['password']) && $_POST['password'] === $post_password)
                || (isset($pwd_cookie['#' . $post_id]) && $pwd_cookie['#' . $post_id] === $post_password)) {
                $pwd_cookie['#' . $post_id] = $post_password;
                setcookie('dc_passwd', json_encode($pwd_cookie, JSON_THROW_ON_ERROR), ['expires' => 0, 'path' => '/']);
            } else {
                App::url()::serveDocument('password-form.html', 'text/html', false);

                return;
            }
        }

        if ($is_ical) {
            self::serveIcalDocument($posts, $args);
        } elseif ($is_hcal) {
            self::serveHcalDocument($posts, $args);
        } elseif ($is_gmap) {
            self::serveGmapDocument($posts);
        } else {
            App::url()::serveDocument('eventhandler-single.html');
        }
    }

    /**
     * Preview single event from admin side.
     */
    public static function eventPreview(?string $args): void
    {
        if (!preg_match('#^(.+?)/([0-9a-z]{40})/(.+?)$#', (string) $args, $m)) {
            // The specified Preview URL is malformed.
            App::url()::p404();
        }

        if (!App::auth()->checkUser($m[1], null, $m[2])) {
            // The user has no access to the entry.
            App::url()::p404();
        }

        App::frontend()->context()->preview = true;
        if (App::config()->adminUrl() !== '') {
            App::frontend()->context()->xframeoption = App::config()->adminUrl();
        }

        self::eventSingle($m[3]);
    }

    /**
     * Multiple events page.
     */
    public static function eventList(?string $args): void
    {
        if (!My::isActive()) {
            App::url()::p404();
        }

        $args = (string) $args;
        $n    = App::url()::getPageNumber($args);
        $args = is_string($args) ? $args : '';
        App::frontend()->setPageNumber(is_int($n) && $n > 0 ? $n : 1);

        $is_ical = self::isDocument($args, '/ical.ics');
        $is_hcal = self::isDocument($args, '/hcal.html');
        $is_gmap = self::isDocument($args, '/gmap');

        $event_params = self::getEventsParams($args);

        App::frontend()->context()->event_params = $event_params;

        if (!$is_hcal && !$is_ical && !$is_gmap) {
            App::url()::serveDocument('eventhandler-list.html');

            return;
        }

        // Documents: do all the job here
        $params = ['sql' => My::hiddenCategoriesSql()];
        if ($is_gmap) {
            // force limit on gmap
            $params['limit'] = [0, 30];
        } else {
            $nbppf           = (int) App::blog()->settings()->get('system')->getInt('nb_post_per_feed', false);
            $params['limit'] = [(App::frontend()->getPageNumber() - 1) * $nbppf, $nbppf];
        }
        $categories = App::frontend()->context()->categories;
        if ($categories instanceof MetaRecord && !$categories->isEmpty()) {
            $params['cat_id'] = $categories->intField('cat_id');
            $params['sql']    = '';
        }

        $rs = (new EventHandler())->getEvents(array_merge($params, $event_params));

        if ($is_ical) {
            self::serveIcalDocument($rs, $args);
        } elseif ($is_hcal) {
            self::serveHcalDocument($rs, $args);
        } else {
            self::serveGmapDocument($rs);
        }
    }

    /**
     * Events feeds.
     */
    public static function eventFeed(?string $args): void
    {
        if (!My::isActive()) {
            App::url()::p404();
        }

        $args     = (string) $args;
        $type     = '';
        $cat_url  = '';
        $subtitle = '';
        $mime     = 'application/xml';

        if (preg_match('!^([a-z]{2}(-[a-z]{2})?)/(.*)$!', $args, $m)) {
            $args = $m[3];

            App::frontend()->context()->langs = App::blog()->getLangs(['lang' => $m[1]]);

            if (App::frontend()->context()->langs->isEmpty()) {
                // The specified language does not exist.
                App::url()::p404();
            }
            App::frontend()->context()->cur_lang = $m[1];
        }

        if (preg_match('#^rss2/xslt$#', $args)) {
            // RSS XSLT stylesheet
            App::url()::serveDocument('rss2.xsl', 'text/xml');

            return;
        } elseif (preg_match('#^(?:category/(.+)/)?(atom|rss2)$#', $args, $m)) {
            // All posts or comments feed
            $type    = $m[2];
            $cat_url = $m[1];
        } else {
            // The specified Feed URL is malformed.
            App::url()::p404();
        }

        if ($cat_url !== '') {
            App::frontend()->context()->categories = App::blog()->getCategories([
                'cat_url'   => $cat_url,
                'post_type' => My::POST_TYPE,
            ]);

            if (App::frontend()->context()->categories->isEmpty()) {
                // The specified category does no exist.
                App::url()::p404();
            }

            $subtitle = ' - ' . App::frontend()->context()->categories->strField('cat_title');
        }

        if ($type === 'atom') {
            $mime = 'application/atom+xml';
        }

        App::frontend()->context()->nb_entry_per_page = App::blog()->settings()->get('system')->getInt('nb_post_per_feed', false);
        App::frontend()->context()->short_feed_items  = App::blog()->settings()->get('system')->getBool('short_feed_items', false);
        App::frontend()->context()->feed_subtitle     = $subtitle;

        header('X-Robots-Tag: ' . App::frontend()->context()::robotsPolicy((string) App::blog()->settings()->get('system')->getStr('robots_policy', false), ''));
        App::url()::serveDocument('eventhandler-' . $type . '.xml', $mime);
        if ($cat_url === '') {
            App::blog()->publishScheduledEntries();
        }
    }

    /**
     * Parse URI for multiple events page.
     *
     * @param   string  $args   The URI arguments
     *
     * @return  array<string, mixed>
     */
    public static function getEventsParams(string $args): array
    {
        $params = ['post_type' => My::POST_TYPE];

        // Test URI
        if (!preg_match(
            '#^' .
            '((/category/([^/]+))|)' .
            '(' .
            '(/(' . implode('|', My::PERIODS) . '))|' . // period
            '(/(on|in|of)/([0-9]{4})(/([0-9]{1,2})|)(/([0-9]{1,2})|)(/([0-9]{4})(/([0-9]{1,2})|)(/([0-9]{1,2})|)|))|' . // interval
            ')' .
            '(/(' . implode('|', array_keys(self::SORT_FIELDS)) . ')(/(asc|desc)|)|)' . // order
            '(/|)$#i',
            $args,
            $m
        )) {
            App::url()::p404();
        }

        // Get category
        if (!empty($m[3])) {
            App::frontend()->context()->categories = App::blog()->getCategories([
                'cat_url'   => $m[3],
                'post_type' => My::POST_TYPE,
            ]);

            if (App::frontend()->context()->categories->isEmpty()) {
                // The specified category does no exist.
                App::url()::p404();
            }
        }

        $int = fn (int $i): int => isset($m[$i]) && is_numeric($m[$i]) ? (int) $m[$i] : 0;

        if (!empty($m[6])) {
            // Get period
            $params['event_period'] = $m[6];
        } elseif (!empty($m[8])) {
            // Get interval
            $params['event_interval'] = $m[8];
            $start                    = '';
            $end                      = '';

            // Make start date
            if ($int(13) !== 0) {
                $start = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(11), $int(13), $int(9)));
                $end   = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(11), $int(13) + 1, $int(9)));
            } elseif ($int(11) !== 0) {
                $start = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(11), 1, $int(9)));
                $end   = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(11) + 1, 1, $int(9)));
            } elseif ($int(9) !== 0) {
                $start = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, 1, 1, $int(9)));
                $end   = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, 1, 1, $int(9) + 1));
            }
            // Make end date
            if ($int(19) !== 0) {
                $end = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(17), $int(19), $int(15)));
            } elseif ($int(17) !== 0) {
                $end = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, $int(17), 1, $int(15)));
            } elseif ($int(15) !== 0) {
                $end = date('Y-m-d 00:00:00', (int) mktime(0, 0, 0, 1, 1, $int(15)));
            }
            // Make interval
            if ($m[8] === 'on') {
                $params['event_period']  = 'ongoing';
                $params['event_startdt'] = $end;
                $params['event_enddt']   = $start;
            } elseif ($m[8] === 'in') {
                $params['event_period']  = 'ongoing';
                $params['event_startdt'] = $start;
                $params['event_enddt']   = $end;
            } else {
                if ($int(9) !== 0) {
                    $params['event_start_year'] = $int(9);
                }
                if ($int(11) !== 0) {
                    $params['event_start_month'] = $int(11);
                }
                if ($int(13) !== 0) {
                    $params['event_start_day'] = $int(13);
                }
            }
        } else {
            // default
            $params['event_period'] = 'scheduled';
        }

        // Get order
        $params['order'] = 'event_startdt ASC';
        if (!empty($m[21]) && isset(self::SORT_FIELDS[strtolower($m[21])])) {
            $params['order'] = self::SORT_FIELDS[strtolower($m[21])] . ' ' . (!empty($m[23]) ? strtoupper($m[23]) : 'ASC');
        }

        return $params;
    }

    /**
     * Check and remove document suffix from URI.
     *
     * @param   string  $args       The URI arguments
     * @param   string  $suffix     The document suffix
     */
    protected static function isDocument(string &$args, string $suffix): bool
    {
        if (str_ends_with($args, $suffix)) {
            $args = substr($args, 0, -strlen($suffix));

            return true;
        }

        return false;
    }

    /**
     * Serve iCalendar document.
     *
     * @param   MetaRecord  $rs             The events
     * @param   string      $x_dc_folder    The folder
     */
    public static function serveIcalDocument(MetaRecord $rs, string $x_dc_folder = ''): void
    {
        if ($rs->isEmpty()) {
            App::url()::p404();
        }

        $res = "BEGIN:VCALENDAR\r\n" .
            'PRODID:-//eventHandler for Dotclear//eventHandler ' . (is_string($version = App::plugins()->moduleInfo(My::id(), 'version')) ? $version : '') . "//EN\r\n" .
            "VERSION:2.0\r\n" .
            "METHOD:PUBLISH\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            implode("\r\n ", mb_str_split(trim('X-DC-BLOGNAME:' . App::blog()->name()), 70)) . "\r\n";

        if ($x_dc_folder !== '') {
            $res .= implode("\r\n ", mb_str_split(trim('X-DC-FOLDER:' . $x_dc_folder), 70)) . "\r\n";
        }

        while ($rs->fetch()) {
            $res .= RsExtension::getIcalVEVENT($rs);
        }

        $res .= "END:VCALENDAR\r\n";

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Length: ' . strlen($res));
        header('Content-Disposition: attachment; filename="events.ics"');
        echo $res;
        dotclear_exit();
    }

    /**
     * Serve hCalendar document.
     *
     * @param   MetaRecord  $rs             The events
     * @param   string      $x_dc_folder    The folder
     */
    public static function serveHcalDocument(MetaRecord $rs, string $x_dc_folder = ''): void
    {
        if ($rs->isEmpty()) {
            App::url()::p404();
        }

        $lang = Html::escapeHTML((string) App::blog()->settings()->get('system')->getStr('lang', false));
        $res  = '<!DOCTYPE html>' . "\n" .
            '<html lang="' . $lang . '">' . "\n" .
            '<head>' . "\n" .
            '<meta charset="UTF-8">' . "\n" .
            '<title>' . Html::escapeHTML(App::blog()->name()) . ' - ' . __('Events') . '</title>' . "\n" .
            '<link rel="stylesheet" href="' . App::blog()->getQmarkURL() . 'pf=' . My::id() . '/css/event-hcalendar.css">' . "\n" .
            '</head>' . "\n" .
            '<body>' . "\n" .
            '<div id="page">' . "\n" .
            '<div id="top">' . "\n" .
            '<h1><a href="' . App::blog()->url() . '">' . Html::escapeHTML(App::blog()->name()) . ' - ' . __('Events') . '</a></h1>' . "\n";

        if ($x_dc_folder !== '') {
            $res .= '<p>' . __('Directory:') . ' <a href="' . App::blog()->url() . App::url()->getBase('eventhandler_list') . Html::escapeHTML($x_dc_folder) . '">' .
                Html::escapeHTML($x_dc_folder) . '</a></p>' . "\n";
        }

        $res .= '</div>' . "\n" . '<div id="items">' . "\n";

        while ($rs->fetch()) {
            $res .= RsExtension::getHcalVEVENT($rs) . "\n";
        }

        $res .= '</div>' . "\n" .
            '<div id="footer">' . "\n" .
            '<p>' . __('This page is powered by Dotclear and eventHandler') . '</p>' . "\n" .
            '</div>' . "\n" .
            '</div>' . "\n" .
            '</body></html>';

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Length: ' . strlen($res));
        echo $res;
        dotclear_exit();
    }

    /**
     * Serve map document.
     *
     * @param   MetaRecord  $rs     The events
     */
    public static function serveGmapDocument(MetaRecord $rs): void
    {
        $lang     = Html::escapeHTML((string) App::blog()->settings()->get('system')->getStr('lang', false));
        $res  = '<!DOCTYPE html>' . "\n" .
            '<html lang="' . $lang . '">' . "\n" .
            '<head>' . "\n" .
            '<meta charset="UTF-8">' . "\n" .
            '<title>' . Html::escapeHTML(App::blog()->name()) . ' - ' . __('Events') . '</title>' . "\n" .
            '<meta name="viewport" content="initial-scale=1.0, user-scalable=no">' . "\n" .
            FrontendBehaviors::mapHeaders(true) .
            '<style>html { height: 100%; } body { height: 100%; margin: 0px; padding: 0px; } ' .
            '.event-map, .event-map-place { height: 100%; } h2 { margin: 2em;}</style>' . "\n" .
            '</head>' . "\n" .
            '<body>' . "\n";

        $markers = '';
        $lat     = 0.0;
        $lng     = 0.0;
        $count   = 0;
        while ($rs->fetch()) {
            if ($rs->strField('event_latitude') === '' || $rs->strField('event_longitude') === '') {
                continue;
            }
            $lat += (float) $rs->strField('event_latitude');
            $lng += (float) $rs->strField('event_longitude');
            $count++;
            $markers .= RsExtension::getMapVEvent($rs);
        }

        if ($count > 0) {
            $res .= EventHandler::getMapContent(
                '',
                '',
                (string) My::settings()->getStr('public_map_type', false),
                $count === 1 && $rs->count() === 1 ? max(3, (int) My::settings()->getInt('public_map_zoom', false)) : 2,
                1,
                round($lat / $count, 7),
                round($lng / $count, 7),
                $markers
            );
        } else {
            $res .= '<h2>' . __("There's no event at this time.") . '</h2>';
        }

        $res .= "\n" . '</body>' . "\n" . '</html>';

        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Length: ' . strlen($res));
        echo $res;
        dotclear_exit();
    }
}
