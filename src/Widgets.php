<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\widgets\WidgetsElement;
use Dotclear\Plugin\widgets\WidgetsStack;

/**
 * @brief       eventHandler widgets.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Widgets
{
    /**
     * Categories combo.
     *
     * @param   string  $post_type  The post type
     *
     * @return  array<string, string>
     */
    private static function categoriesCombo(string $post_type): array
    {
        $combo = ['-' => '', __('Uncategorized') => 'null'];

        try {
            $rs = App::blog()->getCategories(['post_type' => $post_type]);
            while ($rs->fetch()) {
                $combo[str_repeat('&nbsp;&nbsp;', max(0, $rs->intField('level') - 1)) . '&bull; ' . Html::escapeHTML($rs->strField('cat_title'))] = (string) $rs->intField('cat_id');
            }
        } catch (\Exception) {
        }

        return $combo;
    }

    /**
     * Sort combo.
     *
     * @return  array<string, string>
     */
    private static function sortCombo(): array
    {
        return [
            __('Ascending')  => 'asc',
            __('Descending') => 'desc',
        ];
    }

    /**
     * Events sortby combo.
     *
     * @return  array<string, string>
     */
    private static function sortbyCombo(): array
    {
        return [
            __('Date')       => 'post_dt',
            __('Title')      => 'post_title',
            __('Start date') => 'event_startdt',
            __('End date')   => 'event_enddt',
        ];
    }

    /**
     * Widgets registration.
     */
    public static function init(WidgetsStack $w): void
    {
        $w
            ->create(
                'ehEvents',
                __('EventHandler: events'),
                self::events(...),
                null,
                __('List of events')
            )
            ->addTitle(__('Next events'))
            ->setting('category', __('Category:'), '', 'combo', self::categoriesCombo(My::POST_TYPE))
            ->setting('limit', __('Entries limit:'), 10)
            ->setting('sortby', __('Order by:'), 'event_startdt', 'combo', self::sortbyCombo())
            ->setting('sort', __('Sort:'), 'asc', 'combo', self::sortCombo())
            ->setting('selectedonly', __('Selected entries only'), 0, 'check')
            ->setting('period', __('Period:'), 'scheduled', 'combo', My::periodsCombo())
            ->setting('date_format', __('Date format of events:'), __('%A, %B %e %Y'), 'text')
            ->setting('time_format', __('Time format of events:'), __('%H:%M'), 'text')
            ->setting('item_showcat', __('Show category'), 1, 'check')
            ->setting('pagelink', __('Add link to events page'), 1, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'ehEventsOfPost',
                __('EventHandler: events of an entry'),
                self::eventsOfPost(...),
                null,
                __('List of events related to current entry')
            )
            ->addTitle(__('Related events'))
            ->setting('category', __('Category:'), '', 'combo', self::categoriesCombo(My::POST_TYPE))
            ->setting('limit', __('Entries limit:'), 10)
            ->setting('sortby', __('Order by:'), 'event_startdt', 'combo', self::sortbyCombo())
            ->setting('sort', __('Sort:'), 'asc', 'combo', self::sortCombo())
            ->setting('period', __('Period:'), 'notfinished', 'combo', My::periodsCombo())
            ->setting('pagelink', __('Add link to events page'), 0, 'check')
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'ehPostsOfEvent',
                __('EventHandler: entries of an event'),
                self::postsOfEvent(...),
                null,
                __('List of entries related to current event')
            )
            ->addTitle(__('Related entries'))
            ->setting('category', __('Category:'), '', 'combo', self::categoriesCombo('post'))
            ->setting('limit', __('Entries limit:'), 10)
            ->setting('sortby', __('Order by:'), 'post_dt', 'combo', [
                __('Date')  => 'post_dt',
                __('Title') => 'post_title',
            ])
            ->setting('sort', __('Sort:'), 'desc', 'combo', self::sortCombo())
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'ehCategories',
                __('EventHandler: events categories'),
                self::categories(...),
                null,
                __('List of events categories')
            )
            ->addTitle(__('Events by categories'))
            ->setting('postcount', __('With events counts'), 0, 'check')
            ->setting('pagelink', __('Add link to events page'), 1, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'ehMap',
                __('EventHandler: events map'),
                self::map(...),
                null,
                __('Map of events')
            )
            ->addTitle(__('Events on map'))
            ->setting('map_zoom', __('Default zoom on map:'), '4', 'combo', My::mapZoomCombo())
            ->setting('map_type', __('Default type of map:'), 'ROADMAP', 'combo', My::mapTypeCombo())
            ->setting('map_width', __('Width of map (with unit as % or px):'), '100%')
            ->setting('map_height', __('Height of map (with unit as % or px):'), '250px')
            ->setting('map_info', __('Add tooltips'), 0, 'check')
            ->setting('sortby', __('Order by:'), 'event_startdt', 'combo', self::sortbyCombo())
            ->setting('sort', __('Sort:'), 'asc', 'combo', self::sortCombo())
            ->setting('period', __('Period:'), 'scheduled', 'combo', My::periodsCombo())
            ->setting('pagelink', __('Add link to events page'), 1, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();

        $w
            ->create(
                'ehCalendar',
                __('EventHandler: events calendar'),
                self::calendar(...),
                null,
                __('Calendar of events')
            )
            ->addTitle(__('Events calendar'))
            ->setting('weekstart', __('First day of week:'), '0', 'combo', [
                __('Sunday') => '0',
                __('Monday') => '1',
            ])
            ->setting('startonly', __('Show only start date of events'), 1, 'check')
            ->setting('pagelink', __('Add link to events page'), 1, 'check')
            ->addHomeOnly()
            ->addContentOnly()
            ->addClass()
            ->addOffline();
    }

    /**
     * Get a string setting.
     *
     * @param   WidgetsElement  $w      The widget
     * @param   string          $name   The setting name
     */
    private static function str(WidgetsElement $w, string $name): string
    {
        return is_scalar($value = $w->get($name)) ? (string) $value : '';
    }

    /**
     * Check if widget must be displayed.
     *
     * @param   WidgetsElement  $w          The widget
     * @param   bool            $homeonly   Check home only setting
     */
    private static function isDisplayed(WidgetsElement $w, bool $homeonly = true): bool
    {
        return !$w->isOffline()
            && My::isActive()
            && (!$homeonly || $w->checkHomeOnly(App::url()->getType()));
    }

    /**
     * Get widget order parameter.
     *
     * @param   WidgetsElement      $w          The widget
     * @param   array<int, string>  $fields     The allowed fields
     * @param   string              $default    The default field
     * @param   string              $sort       The default sort
     */
    private static function order(WidgetsElement $w, array $fields, string $default, string $sort): string
    {
        $sortby = self::str($w, 'sortby');
        $order  = self::str($w, 'sort');

        return (in_array($sortby, $fields, true) ? $sortby : $default) . ' ' . (in_array($order, ['asc', 'desc'], true) ? $order : $sort);
    }

    /**
     * Get widget category parameters.
     *
     * @param   WidgetsElement          $w          The widget
     * @param   array<string, mixed>    $params     The parameters
     * @param   bool                    $hidden     Remove hidden categories if no category is selected
     *
     * @return  array<string, mixed>
     */
    private static function categoryParams(WidgetsElement $w, array $params, bool $hidden = true): array
    {
        $category = self::str($w, 'category');
        $sql      = is_string($params['sql'] ?? null) ? $params['sql'] : '';

        if ($category === 'null') {
            $sql .= ' AND P.cat_id IS NULL ';
        } elseif (is_numeric($category)) {
            $params['cat_id'] = (int) $category;
        } elseif ($category !== '') {
            $params['cat_url'] = $category;
        } elseif ($hidden) {
            // If no paricular category is selected, remove hidden categories
            $sql .= My::hiddenCategoriesSql();
        }

        $params['sql'] = $sql;

        return $params;
    }

    /**
     * Get link to events page.
     *
     * @param   WidgetsElement  $w  The widget
     */
    private static function pageLink(WidgetsElement $w): string
    {
        return $w->get('pagelink') ?
            '<p><strong><a href="' . App::blog()->url() . App::url()->getURLFor('eventhandler_list') . '">' . __('All events') . '</a></strong></p>' :
            '';
    }

    /**
     * Get events list.
     *
     * @param   MetaRecord  $rs             The events
     * @param   string      $date_format    The date format
     * @param   string      $time_format    The time format
     * @param   bool        $showcat        Show category
     */
    private static function eventsList(MetaRecord $rs, string $date_format, string $time_format, bool $showcat): string
    {
        $res = '';
        while ($rs->fetch()) {
            $over = str_replace(
                ['%sd', '%st', '%ed', '%et', '%%'],
                [
                    Date::dt2str($date_format, $rs->strField('event_startdt')),
                    Date::dt2str($time_format, $rs->strField('event_startdt')),
                    Date::dt2str($date_format, $rs->strField('event_enddt')),
                    Date::dt2str($time_format, $rs->strField('event_enddt')),
                    '%',
                ],
                RsExtension::isOnSameDay($rs) ? __('On %sd from %st to %et') : __('From %sd, %st to %ed, %et')
            );

            $cat = '';
            if ($showcat && $rs->strField('cat_title') !== '') {
                $cat = sprintf(
                    ' (<a href="%s" title="%s">%s</a>)',
                    RsExtension::getCategoryURL($rs),
                    __('go to this category'),
                    Html::escapeHTML($rs->strField('cat_title'))
                );
            }

            $res .= '<li><a href="' . RsExtension::getURL($rs) . '" title="' . Html::escapeHTML($over) . '">' . Html::escapeHTML($rs->strField('post_title')) . '</a>' . $cat . '</li>';
        }

        return $res === '' ? '' : '<ul>' . $res . '</ul>';
    }

    /**
     * Events widget.
     */
    public static function events(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $params = [
            'no_content' => true,
            'post_type'  => My::POST_TYPE,
            'order'      => self::order($w, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'], 'event_startdt', 'asc'),
        ];
        if (self::str($w, 'period') !== '') {
            $params['event_period'] = self::str($w, 'period');
        }
        if (is_numeric(self::str($w, 'limit'))) {
            $params['limit'] = abs((int) self::str($w, 'limit'));
        }
        if ($w->get('selectedonly')) {
            $params['post_selected'] = 1;
        }

        $rs  = (new EventHandler())->getEvents(self::categoryParams($w, $params));
        $res = self::eventsList(
            $rs,
            self::str($w, 'date_format') ?: (string) App::blog()->settings()->get('system')->getStr('date_format', false),
            self::str($w, 'time_format') ?: (string) App::blog()->settings()->get('system')->getStr('time_format', false),
            (bool) $w->get('item_showcat')
        );
        if ($res === '') {
            return '';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-events ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . $res . self::pageLink($w)
        );
    }

    /**
     * Events of an entry widget.
     */
    public static function eventsOfPost(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w, false) || !App::url()->isType('post')) {
            return '';
        }

        $post = App::frontend()->context()->posts;
        if (!$post instanceof MetaRecord || $post->isEmpty()) {
            return '';
        }

        $params = [
            'no_content' => true,
            'post_id'    => $post->intField('post_id'),
            'order'      => self::order($w, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'], 'event_startdt', 'asc'),
        ];
        if (self::str($w, 'period') !== '') {
            $params['event_period'] = self::str($w, 'period');
        }
        if (is_numeric(self::str($w, 'limit'))) {
            $params['limit'] = abs((int) self::str($w, 'limit'));
        }

        $rs  = (new EventHandler())->getEventsByPost(self::categoryParams($w, $params));
        $res = self::eventsList(
            $rs,
            (string) App::blog()->settings()->get('system')->getStr('date_format', false),
            (string) App::blog()->settings()->get('system')->getStr('time_format', false),
            false
        );
        if ($res === '') {
            return '';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-eventsofpost ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . $res . self::pageLink($w)
        );
    }

    /**
     * Entries of an event widget.
     */
    public static function postsOfEvent(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w, false) || !App::url()->isType('eventhandler_single')) {
            return '';
        }

        $post = App::frontend()->context()->posts;
        if (!$post instanceof MetaRecord || $post->isEmpty()) {
            return '';
        }

        $params = [
            'no_content' => true,
            'event_id'   => $post->intField('post_id'),
            'order'      => self::order($w, ['post_title', 'post_dt'], 'post_dt', 'desc'),
        ];
        if (is_numeric(self::str($w, 'limit'))) {
            $params['limit'] = abs((int) self::str($w, 'limit'));
        }

        $rs  = (new EventHandler())->getPostsByEvent(self::categoryParams($w, $params, false));
        $res = '';
        while ($rs->fetch()) {
            $res .= '<li><a href="' . $rs->getURL() . '">' . Html::escapeHTML($rs->strField('post_title')) . '</a></li>';
        }
        if ($res === '') {
            return '';
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-postsofevent ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . '<ul>' . $res . '</ul>'
        );
    }

    /**
     * Events categories widget.
     */
    public static function categories(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $rs = App::blog()->getCategories(['post_type' => My::POST_TYPE]);
        if ($rs->isEmpty()) {
            return '';
        }

        $current = 0;
        if (App::url()->isType('eventhandler_list') && App::frontend()->context()->categories instanceof MetaRecord) {
            $current = App::frontend()->context()->categories->intField('cat_id');
        } elseif (App::url()->isType('eventhandler_single') && App::frontend()->context()->posts instanceof MetaRecord) {
            $current = App::frontend()->context()->posts->intField('cat_id');
        }

        $base  = App::blog()->url() . App::url()->getURLFor('eventhandler_list', 'category/');
        $res   = '';
        $level = 0;
        while ($rs->fetch()) {
            $cat_level = $rs->intField('level');
            $class     = $current !== 0 && $current === $rs->intField('cat_id') ? ' class="category-current"' : '';

            if ($cat_level > $level) {
                $res .= str_repeat('<ul><li' . $class . '>', $cat_level - $level);
            } elseif ($cat_level < $level) {
                $res .= str_repeat('</li></ul>', $level - $cat_level);
            }
            if ($cat_level <= $level) {
                $res .= '</li><li' . $class . '>';
            }

            $res .= '<a href="' . $base . $rs->strField('cat_url') . '">' . Html::escapeHTML($rs->strField('cat_title')) . '</a>' .
                ($w->get('postcount') ? ' (' . $rs->intField('nb_post') . ')' : '');

            $level = $cat_level;
        }
        if ($level > 0) {
            $res .= str_repeat('</li></ul>', $level);
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-categories ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') . $res . self::pageLink($w)
        );
    }

    /**
     * Events map widget.
     */
    public static function map(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $params = [
            'no_content' => true,
            'post_type'  => My::POST_TYPE,
            'limit'      => 10,
            'order'      => self::order($w, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'], 'event_startdt', 'asc'),
            'sql'        => "AND event_latitude != '' AND event_longitude != '' " . My::hiddenCategoriesSql(),
        ];
        if (self::str($w, 'period') !== '') {
            $params['event_period'] = self::str($w, 'period');
        }

        $rs = (new EventHandler())->getEvents($params);
        if ($rs->isEmpty()) {
            return '';
        }

        $lat     = 0.0;
        $lng     = 0.0;
        $markers = '';
        while ($rs->fetch()) {
            $lat += (float) $rs->strField('event_latitude');
            $lng += (float) $rs->strField('event_longitude');
            $markers .= RsExtension::getMapVEvent($rs);
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-map ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') .
            EventHandler::getMapContent(
                self::str($w, 'map_width'),
                self::str($w, 'map_height'),
                self::str($w, 'map_type'),
                (int) self::str($w, 'map_zoom'),
                (int) (bool) $w->get('map_info'),
                round($lat / $rs->count(), 7),
                round($lng / $rs->count(), 7),
                $markers
            ) .
            self::pageLink($w)
        );
    }

    /**
     * Events calendar widget.
     */
    public static function calendar(WidgetsElement $w): string
    {
        if (!self::isDisplayed($w)) {
            return '';
        }

        $year   = (int) date('Y');
        $month  = (int) date('m');
        $params = App::frontend()->context()->event_params;
        if (is_array($params)) {
            if (!empty($params['event_start_month']) && !empty($params['event_start_year'])) {
                $year  = is_numeric($params['event_start_year']) ? (int) $params['event_start_year'] : $year;
                $month = is_numeric($params['event_start_month']) ? (int) $params['event_start_month'] : $month;
            } elseif (!empty($params['event_startdt']) && is_string($params['event_startdt'])) {
                $year  = (int) date('Y', (int) strtotime($params['event_startdt']));
                $month = (int) date('m', (int) strtotime($params['event_startdt']));
            }
        }

        return $w->renderDiv(
            (bool) $w->get('content_only'),
            'eventhandler-calendar ' . self::str($w, 'class'),
            '',
            ($w->get('title') ? $w->renderTitle(Html::escapeHTML(self::str($w, 'title'))) : '') .
            (new Calendar($year, $month, self::str($w, 'weekstart') === '1'))->parse((bool) $w->get('startonly')) .
            self::pageLink($w)
        );
    }
}
