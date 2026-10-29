<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;

/**
 * @brief       eventHandler template tags.
 * @ingroup     eventHandler
 *
 * Compiled templates only import Dotclear\App,
 * other classes must be fully qualified in generated code.
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Template
{
    /**
     * This plugin namespace (for generated code).
     *
     * @var     string  NS
     */
    private const NS = '\\Dotclear\\Plugin\\eventHandler\\';

    /**
     * Get a string attribute.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $name       The attribute name
     * @param   string                      $default    The default value
     */
    private static function attr(ArrayObject $attr, string $name, string $default = ''): string
    {
        return isset($attr[$name]) && is_scalar($attr[$name]) ? (string) $attr[$name] : $default;
    }

    /**
     * Get a value with filters.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $value  The PHP code of the value
     */
    private static function tplValue(ArrayObject $attr, string $value): string
    {
        return '<?= ' . sprintf(App::frontend()->template()->getFilters($attr), $value) . ' ?>';
    }

    /**
     * Get date type from attributes.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   bool                        $events     Allow events dates
     */
    private static function dateType(ArrayObject $attr, bool $events = true): string
    {
        foreach ($events ? ['startdt', 'enddt', 'upddt', 'creadt'] : ['upddt', 'creadt'] as $type) {
            if (!empty($attr[$type])) {
                return $type;
            }
        }

        return '';
    }

    /**
     * Get an if block.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   array<int, string>          $if         The conditions
     * @param   string                      $content    The block content
     */
    private static function ifBlock(ArrayObject $attr, array $if, string $content): string
    {
        if ($if === []) {
            return $content;
        }

        $operator = isset($attr['operator']) && is_string($attr['operator']) ? App::frontend()->template()->getOperator($attr['operator']) : '&&';

        return '<?php if(' . implode(' ' . $operator . ' ', $if) . ') : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * Get common query parameters from attributes.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    private static function commonParams(ArrayObject $attr): string
    {
        $p = '';

        if (isset($attr['author'])) {
            $p .= "\$params['user_id'] = " . var_export(self::attr($attr, 'author'), true) . ";\n";
        }

        if (isset($attr['category'])) {
            $p .= "\$params['cat_url'] = " . var_export(self::attr($attr, 'category'), true) . ";\n";
            $p .= "App::frontend()->context()::categoryPostParam(\$params);\n";
        }

        if (!empty($attr['no_category'])) {
            $p .= "@\$params['sql'] .= ' AND P.cat_id IS NULL ';\n";
            $p .= "unset(\$params['cat_url']);\n";
        }

        if (!empty($attr['type'])) {
            $p .= "\$params['post_type'] = preg_split('/\\s*,\\s*/', " . var_export(self::attr($attr, 'type'), true) . ", -1, PREG_SPLIT_NO_EMPTY);\n";
        }

        if (!empty($attr['no_content'])) {
            $p .= "\$params['no_content'] = true;\n";
        }

        if (isset($attr['selected'])) {
            $p .= "\$params['post_selected'] = " . (int) (bool) $attr['selected'] . ";\n";
        }

        if (isset($attr['age'])) {
            $age = App::frontend()->template()->getAge($attr);
            $p .= $age !== '' ? "@\$params['sql'] .= ' AND P.post_dt > \\'" . $age . "\\'';\n" : '';
        }

        return $p;
    }

    /*
     * Values
     */

    /**
     * tpl:BlogTimezone : blog timezone.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function BlogTimezone(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::blog()->settings()->get("system")->getStr("blog_timezone", false)');
    }

    /**
     * tpl:EventsURL : events page URL.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsURL(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::blog()->url() . App::url()->getURLFor("eventhandler_list")');
    }

    /**
     * tpl:EventsFeedURL type="atom|rss2" : events feed URL.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsFeedURL(ArrayObject $attr): string
    {
        $type = self::attr($attr, 'type', 'atom');
        if (!in_array($type, ['rss2', 'atom'])) {
            $type = 'atom';
        }

        return self::tplValue(
            $attr,
            'App::blog()->url() . App::url()->getURLFor("eventhandler_feed", ' .
            '(App::frontend()->context()->exists("categories") ? "category/" . App::frontend()->context()->categories->strField("cat_url") . "/" : "") . "' . $type . '")'
        );
    }

    /**
     * tpl:EventsMenuPeriod : events periods menu.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsMenuPeriod(ArrayObject $attr): string
    {
        return '<?= ' . self::NS . 'Template::EventsMenuPeriodHelper(' .
            var_export(self::attr($attr, 'menus'), true) . ', ' .
            var_export(self::attr($attr, 'separator'), true) . ', ' .
            var_export(self::attr($attr, 'list'), true) . ', ' .
            var_export(self::attr($attr, 'item'), true) . ', ' .
            var_export(self::attr($attr, 'active_item'), true) . ') ?>';
    }

    /**
     * Events periods menu helper.
     */
    public static function EventsMenuPeriodHelper(string $menus, string $separator, string $list, string $item, string $active_item): string
    {
        $default_menu = [
            'all'         => __('All'),
            'ongoing'     => __('Ongoing'),
            'outgoing'    => __('Outgoing'),
            'scheduled'   => __('Scheduled'),
            'started'     => __('Started'),
            'notfinished' => __('Not finished'),
            'finished'    => __('Finished'),
        ];

        $menu = [];
        foreach (explode(',', $menus) as $k) {
            if (isset($default_menu[$k])) {
                $menu[$k] = $default_menu[$k];
            }
        }
        if ($menu === []) {
            $menu = $default_menu;
        }

        $separator   = $separator   !== '' ? Html::decodeEntities($separator) : '';
        $list        = $list        !== '' ? Html::decodeEntities($list) : '<ul>%s</ul>';
        $item        = $item        !== '' ? Html::decodeEntities($item) : '<li><a href="%s">%s</a>%s</li>';
        $active_item = $active_item !== '' ? Html::decodeEntities($active_item) : '<li class="nav-active"><a href="%s">%s</a>%s</li>';

        $url    = self::listURL();
        $period = self::currentParam('event_period');

        $res = '';
        $i   = 0;
        foreach ($menu as $id => $name) {
            $i++;
            $res .= sprintf(
                $period === $id ? $active_item : $item,
                $url . $id,
                $name,
                $i < count($menu) ? $separator : ''
            );
        }

        return '<div id="eventhandler-menu-period">' . sprintf($list, $res) . '</div>';
    }

    /**
     * tpl:EventsMenuSortOrder : events sort menu.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsMenuSortOrder(ArrayObject $attr): string
    {
        return '<?= ' . self::NS . 'Template::EventsMenuSortOrdertHelper(' .
            var_export(self::attr($attr, 'menus'), true) . ', ' .
            var_export(self::attr($attr, 'separator'), true) . ', ' .
            var_export(self::attr($attr, 'list'), true) . ', ' .
            var_export(self::attr($attr, 'item'), true) . ', ' .
            var_export(self::attr($attr, 'active_item'), true) . ') ?>';
    }

    /**
     * Events sort menu helper.
     */
    public static function EventsMenuSortOrdertHelper(string $menus, string $separator, string $list, string $item, string $active_item): string
    {
        $default_sort_text = [
            'title'    => __('Title'),
            'selected' => __('Selected'),
            'author'   => __('Author'),
            'date'     => __('Published date'),
            'startdt'  => __('Start date'),
            'enddt'    => __('End date'),
        ];

        $menu = [];
        foreach (explode(',', $menus) as $k) {
            if (isset(UrlHandler::SORT_FIELDS[$k])) {
                $menu[$k] = UrlHandler::SORT_FIELDS[$k];
            }
        }
        if ($menu === []) {
            $menu = UrlHandler::SORT_FIELDS;
        }

        $separator   = $separator   !== '' ? Html::decodeEntities($separator) : '';
        $list        = $list        !== '' ? Html::decodeEntities($list) : '<ul>%s</ul>';
        $item        = $item        !== '' ? Html::decodeEntities($item) : '<li><a href="%s">%s</a>%s</li>';
        $active_item = $active_item !== '' ? Html::decodeEntities($active_item) : '<li class="nav-active"><a href="%s">%s</a>%s</li>';

        $period = self::currentParam('event_period');
        $url    = self::listURL() . ($period !== '' ? $period : 'all');

        $sortby    = '';
        $sortorder = 'asc';
        if (preg_match('/^(.+)\s(ASC|DESC)$/i', self::currentParam('order'), $m)) {
            $sortby    = $m[1];
            $sortorder = strtolower($m[2]);
        }

        $res = '';
        $i   = 0;
        foreach ($menu as $id => $field) {
            $i++;
            $sep = $i < count($menu) ? $separator : '';
            if ($sortby === $field) {
                $res .= sprintf($active_item, $url . '/' . $id . '/' . ($sortorder === 'asc' ? 'desc' : 'asc'), $default_sort_text[$id], $sep);
            } else {
                $res .= sprintf($item, $url . '/' . $id . '/' . $sortorder, $default_sort_text[$id], $sep);
            }
        }

        return '<div id="eventhandler-menu-sortorder">' . sprintf($list, $res) . '</div>';
    }

    /**
     * Get current events list URL (with category).
     */
    private static function listURL(): string
    {
        $url        = App::blog()->url() . App::url()->getURLFor('eventhandler_list') . '/';
        $categories = App::frontend()->context()->categories;
        if ($categories instanceof MetaRecord && !$categories->isEmpty()) {
            $url .= 'category/' . $categories->strField('cat_url') . '/';
        }

        return $url;
    }

    /**
     * Get a current events list parameter.
     *
     * @param   string  $name   The parameter name
     */
    private static function currentParam(string $name): string
    {
        $params = App::frontend()->context()->event_params;

        return is_array($params) && isset($params[$name]) && is_scalar($params[$name]) ? (string) $params[$name] : '';
    }

    /**
     * tpl:EventsPeriod [fulltext="0|1"] : current events period.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsPeriod(ArrayObject $attr): string
    {
        $fulltext = !isset($attr['fulltext']) ? 0 : (empty($attr['fulltext']) ? 1 : 2);

        return '<?= ' . sprintf(App::frontend()->template()->getFilters($attr), self::NS . 'Template::EventsPeriodHelper(' . $fulltext . ')') . ' ?>';
    }

    /**
     * Current events period helper.
     */
    public static function EventsPeriodHelper(int $fulltext): string
    {
        $text = match ($fulltext) {
            2 => [
                'all'         => __('All events'),
                'ongoing'     => __('Current events'),
                'outgoing'    => __('Event not being'),
                'scheduled'   => __('Scheduled events'),
                'started'     => __('Started events'),
                'notfinished' => __('Unfinished events'),
                'finished'    => __('Completed events'),
            ],
            1 => [
                'all'         => __('All'),
                'ongoing'     => __('Ongoing'),
                'outgoing'    => __('Outgoing'),
                'scheduled'   => __('Scheduled'),
                'started'     => __('Started'),
                'notfinished' => __('Not finished'),
                'finished'    => __('Finished'),
            ],
            default => array_combine(My::PERIODS, My::PERIODS),
        };

        $period = self::currentParam('event_period');

        return $text[$period] ?? $text['all'];
    }

    /**
     * tpl:EventsInterval [format="%m %d %Y"] : current events interval.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsInterval(ArrayObject $attr): string
    {
        return '<?= ' . sprintf(App::frontend()->template()->getFilters($attr), self::NS . 'Template::EventsIntervalHelper(' . var_export(self::attr($attr, 'format', __('%m %d %Y')), true) . ')') . ' ?>';
    }

    /**
     * Current events interval helper.
     */
    public static function EventsIntervalHelper(string $format): string
    {
        $year  = (int) self::currentParam('event_start_year');
        $month = (int) self::currentParam('event_start_month');
        $day   = (int) self::currentParam('event_start_day');

        if ($year !== 0) {
            if ($day !== 0) {
                return sprintf(__('For the day of %s'), Date::str($format, (int) mktime(0, 0, 0, $month, $day, $year)));
            } elseif ($month !== 0) {
                return sprintf(__('For the month of %s'), Date::str(__('%m %Y'), (int) mktime(0, 0, 0, $month, 1, $year)));
            }

            return sprintf(__('For the year of %s'), $year);
        }

        $startdt = self::currentParam('event_startdt');
        $enddt   = self::currentParam('event_enddt');
        if ($startdt === '' || $enddt === '') {
            return '';
        }

        $start = Date::dt2str($format, $startdt);
        $end   = Date::dt2str($format, $enddt);

        return strtotime($startdt) < strtotime($enddt) ?
            sprintf(__('For the period between %s and %s'), $start, $end) :
            sprintf(__('For the period through %s and %s'), $end, $start);
    }

    /*
     * Events page blocks
     */

    /**
     * tpl:EventsIf [has_interval] [has_category] [has_period] [period] : events page conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsIf(ArrayObject $attr, string $content): string
    {
        $if    = [];
        $param = fn (string $name): string => self::NS . 'Template::currentEventParam("' . $name . '")';

        if (isset($attr['has_interval'])) {
            $if[] = ((bool) $attr['has_interval'] ? '' : '!') . '(' . $param('event_interval') . ' !== "")';
        }

        if (isset($attr['has_category'])) {
            $if[] = ((bool) $attr['has_category'] ? '' : '!') . 'App::frontend()->context()->exists("categories")';
        }

        if (isset($attr['has_period'])) {
            $if[] = ((bool) $attr['has_period'] ? '' : '!') . '(' . $param('event_period') . ' !== "" && ' . $param('event_period') . ' !== "all")';
        }

        if (isset($attr['period'])) {
            $period = self::attr($attr, 'period');
            $if[]   = in_array($period, ['', 'all']) ?
                '(' . $param('event_period') . ' === "" || ' . $param('event_period') . ' === "all")' :
                '(' . $param('event_period') . ' === ' . var_export($period, true) . ')';
        }

        return self::ifBlock($attr, $if, $content);
    }

    /**
     * Current events list parameter (for generated code).
     *
     * @param   string  $name   The parameter name
     */
    public static function currentEventParam(string $name): string
    {
        return self::currentParam($name);
    }

    /**
     * tpl:EventsCount [value="0|1"] : events count condition.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsCount(ArrayObject $attr, string $content): string
    {
        if (!isset($attr['value'])) {
            return $content;
        }

        return '<?php if(App::frontend()->context()->nb_posts ' . ((bool) $attr['value'] ? '>' : '==') . ' 0) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:EventsEntries : events loop.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsEntries(ArrayObject $attr, string $content): string
    {
        $lastn = isset($attr['lastn']) && is_numeric($attr['lastn']) ? abs((int) $attr['lastn']) : -1;

        $p = '';
        if ($lastn !== 0) {
            $p .= $lastn > 0 ?
                "\$params['limit'] = " . $lastn . ";\n" :
                "\$params['limit'] = (int) App::frontend()->context()->nb_entry_per_page;\n";

            $p .= empty($attr['ignore_pagination']) ?
                "\$params['limit'] = [((App::frontend()->getPageNumber() - 1) * \$params['limit']), \$params['limit']];\n" :
                "\$params['limit'] = [0, \$params['limit']];\n";
        }

        $p .= self::commonParams($attr);

        if (!empty($attr['url'])) {
            $p .= "\$params['post_url'] = " . var_export(self::attr($attr, 'url'), true) . ";\n";
        }

        if (isset($attr['period'])) {
            $p .= "\$params['event_period'] = " . var_export(self::attr($attr, 'period'), true) . ";\n";
        }

        if (empty($attr['no_context'])) {
            $p .= 'if (App::frontend()->context()->exists("users")) { ' .
                "\$params['user_id'] = App::frontend()->context()->users->strField('user_id'); " .
                "}\n";

            $p .= 'if (App::frontend()->context()->exists("categories")) { ' .
                "\$params['cat_id'] = App::frontend()->context()->categories->intField('cat_id'); " .
                '} else { ' .
                "@\$params['sql'] .= " . self::NS . "My::hiddenCategoriesSql(); " .
                "}\n";

            $p .= 'if (App::frontend()->context()->exists("archives")) { ' .
                "\$params['post_year'] = App::frontend()->context()->archives->year(); " .
                "\$params['post_month'] = App::frontend()->context()->archives->month(); " .
                (!isset($attr['lastn']) ? "unset(\$params['limit']); " : '') .
                "}\n";

            $p .= 'if (App::frontend()->context()->exists("langs")) { ' .
                "\$params['post_lang'] = App::frontend()->context()->langs->strField('post_lang'); " .
                "}\n";

            $p .= 'if (isset($_search)) { ' .
                "\$params['search'] = \$_search; " .
                "}\n";

            $p .= 'if (is_array(App::frontend()->context()->event_params)) { ' .
                "\$params = array_merge(\$params, App::frontend()->context()->event_params); " .
                "}\n";
        }

        if (!empty($attr['order']) || !empty($attr['sortby'])) {
            $p .= "\$params['order'] = " . var_export(App::frontend()->template()->getSortByStr($attr, My::POST_TYPE), true) . ";\n";
        } else {
            $sortby = (string) My::settings()->getStr('public_events_list_sortby', false);
            $order  = (string) My::settings()->getStr('public_events_list_order', false);
            if ($order !== '' && str_contains($sortby, ':')) {
                [$table, $field] = explode(':', $sortby, 2);
                /**
                 * @var     ArrayObject<string, mixed>  $sort
                 */
                $sort = new ArrayObject(['order' => $order, 'sortby' => $field]);
                $p .= "\$params['order'] = " . var_export(App::frontend()->template()->getSortByStr($sort, $table), true) . ";\n";
            }
        }

        return "<?php\n" .
            "\$params = [];\n" .
            $p .
            'App::frontend()->context()->post_params = $params;' . "\n" .
            'App::frontend()->context()->posts = (new ' . self::NS . 'EventHandler())->getEvents($params); unset($params);' . "\n" .
            'App::frontend()->context()->nb_posts = App::frontend()->context()->posts->count();' . "\n" .
            "?>\n" .
            '<?php while (App::frontend()->context()->posts->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'App::frontend()->context()->posts = null; App::frontend()->context()->post_params = null; ?>';
    }

    /**
     * tpl:EventsPagination : events pagination.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsPagination(ArrayObject $attr, string $content): string
    {
        $p = "<?php\n" .
            '$params = is_array(App::frontend()->context()->post_params) ? App::frontend()->context()->post_params : [];' . "\n" .
            'App::frontend()->context()->pagination = (new ' . self::NS . 'EventHandler())->getEvents($params, true); unset($params);' . "\n" .
            "?>\n";

        if (!empty($attr['no_context'])) {
            return $p . $content;
        }

        return $p .
            '<?php if (App::frontend()->context()->pagination->intField(0) > App::frontend()->context()->posts->count()) : ?>' .
            $content .
            '<?php endif; ?>';
    }

    /**
     * Get record conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $rs         The record (PHP code)
     * @param   string                      $params     The record params (PHP code)
     *
     * @return  array<int, string>
     */
    private static function eventConditions(ArrayObject $attr, string $rs, string $params): array
    {
        $if = [];

        if (isset($attr['has_category'])) {
            $if[] = ((bool) $attr['has_category'] ? '' : '!') . '(' . $rs . '->strField("cat_id") !== "")';
        }

        if (isset($attr['has_address'])) {
            $if[] = ((bool) $attr['has_address'] ? '' : '!') . '(' . $rs . '->strField("event_address") !== "")';
        }

        if (isset($attr['has_geo'])) {
            $if[] = ((bool) $attr['has_geo'] ? '' : '!') . '(' . $rs . '->strField("event_latitude") !== "" && ' . $rs . '->strField("event_longitude") !== "")';
        }

        if (isset($attr['period'])) {
            $if[] = $rs . '->getPeriod() === ' . var_export(self::attr($attr, 'period'), true);
        }

        if (isset($attr['sameday'])) {
            $if[] = ((bool) $attr['sameday'] ? '' : '!') . $rs . '->isOnSameDay()';
        }

        if (isset($attr['oneday'])) {
            $if[] = ((bool) $attr['oneday'] ? '' : '!') . $rs . '->isOnOneDay()';
        }

        $orderedby = self::attr($attr, 'orderedby');
        if ($orderedby !== '') {
            $sign = '';
            if (str_starts_with($orderedby, '!')) {
                $sign      = '!';
                $orderedby = substr($orderedby, 1);
            }
            $field = match ($orderedby) {
                'date'    => 'post_dt',
                'startdt' => 'event_startdt',
                'enddt'   => 'event_enddt',
                default   => '',
            };
            if ($field !== '') {
                $if[] = $sign . '(is_array(' . $params . ') && str_contains((string) (' . $params . '["order"] ?? ""), "' . $field . '"))';
            }
        }

        return $if;
    }

    /**
     * tpl:EventsEntryIf : event conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsEntryIf(ArrayObject $attr, string $content): string
    {
        return self::ifBlock($attr, self::eventConditions($attr, 'App::frontend()->context()->posts', 'App::frontend()->context()->post_params'), $content);
    }

    /**
     * tpl:EventsDateHeader : first event of a day.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsDateHeader(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->posts->firstEventOfDay("' . self::dateType($attr) . '")) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:EventsDateFooter : last event of a day.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsDateFooter(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->posts->lastEventOfDay("' . self::dateType($attr) . '")) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * Get event date value.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $rs     The record (PHP code)
     */
    private static function eventDate(ArrayObject $attr, string $rs): string
    {
        $type = self::dateType($attr);

        if (!empty($attr['rfc822'])) {
            return self::tplValue($attr, $rs . '->getEventRFC822Date("' . $type . '")');
        } elseif (!empty($attr['iso8601'])) {
            return self::tplValue($attr, $rs . '->getEventISO8601Date("' . $type . '")');
        }

        return self::tplValue($attr, $rs . '->getEventDate(' . var_export(self::attr($attr, 'format'), true) . ', "' . $type . '")');
    }

    /**
     * Get event time value.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $rs     The record (PHP code)
     */
    private static function eventTime(ArrayObject $attr, string $rs): string
    {
        return self::tplValue($attr, $rs . '->getEventTime(' . var_export(self::attr($attr, 'format'), true) . ', "' . self::dateType($attr) . '")');
    }

    /**
     * Get event duration value.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $rs     The record (PHP code)
     */
    private static function eventDuration(ArrayObject $attr, string $rs): string
    {
        return self::tplValue($attr, self::NS . 'EventHandler::getReadableDuration(' . $rs . '->getEventTS("enddt") - ' . $rs . '->getEventTS("startdt"))');
    }

    /**
     * Get event period value.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $rs     The record (PHP code)
     */
    private static function eventPeriod(ArrayObject $attr, string $rs): string
    {
        $labels = [];
        foreach (['scheduled', 'ongoing', 'finished'] as $period) {
            $label           = self::attr($attr, $period, $period);
            $labels[$period] = empty($attr['strict']) ? __($label) : $label;
        }

        return self::tplValue($attr, '(' . var_export($labels, true) . ')[' . $rs . '->getPeriod()] ?? ""');
    }

    /**
     * Get event category URL value.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     * @param   string                      $rs     The record (PHP code)
     */
    private static function eventCategoryURL(ArrayObject $attr, string $rs): string
    {
        return self::tplValue($attr, 'App::blog()->url() . App::url()->getURLFor("eventhandler_list", "category/" . \Dotclear\Helper\Html\Html::sanitizeURL(' . $rs . '->strField("cat_url")))');
    }

    /**
     * tpl:EventsEntryDate : event date.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryDate(ArrayObject $attr): string
    {
        return self::eventDate($attr, 'App::frontend()->context()->posts');
    }

    /**
     * tpl:EventsEntryTime : event time.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryTime(ArrayObject $attr): string
    {
        return self::eventTime($attr, 'App::frontend()->context()->posts');
    }

    /**
     * tpl:EventsEntryCategoryURL : event category URL on events page.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryCategoryURL(ArrayObject $attr): string
    {
        return self::eventCategoryURL($attr, 'App::frontend()->context()->posts');
    }

    /**
     * tpl:EventsEntryAddress [ics="1"] : event address.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryAddress(ArrayObject $attr): string
    {
        return self::tplValue($attr, (!empty($attr['ics']) ? '"LOCATION;CHARSET=UTF-8:" . ' : '') . 'App::frontend()->context()->posts->strField("event_address")');
    }

    /**
     * tpl:EventsEntryLatitude : event latitude.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryLatitude(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->posts->strField("event_latitude")');
    }

    /**
     * tpl:EventsEntryLongitude : event longitude.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryLongitude(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->posts->strField("event_longitude")');
    }

    /**
     * tpl:EventsEntryZoom : event map zoom.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryZoom(ArrayObject $attr): string
    {
        return self::tplValue($attr, '(string) App::frontend()->context()->posts->intField("event_zoom")');
    }

    /**
     * tpl:EventsEntryDuration : event duration.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryDuration(ArrayObject $attr): string
    {
        return self::eventDuration($attr, 'App::frontend()->context()->posts');
    }

    /**
     * tpl:EventsEntryPeriod [scheduled] [ongoing] [finished] [strict] : event period.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryPeriod(ArrayObject $attr): string
    {
        return self::eventPeriod($attr, 'App::frontend()->context()->posts');
    }

    /**
     * tpl:EventsEntryMap [map_zoom] [map_type] [map_info] : event map.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventsEntryMap(ArrayObject $attr): string
    {
        $rs       = 'App::frontend()->context()->posts';
        $map_zoom = !empty($attr['map_zoom']) && is_numeric($attr['map_zoom']) ?
            (string) abs((int) $attr['map_zoom']) :
            '(' . $rs . '->intField("event_zoom") ?: (int) ' . self::NS . 'My::settings()->getInt("public_map_zoom", false))';
        $map_type = !empty($attr['map_type']) ?
            var_export(self::attr($attr, 'map_type'), true) :
            '(string) ' . self::NS . 'My::settings()->getStr("public_map_type", false)';
        $map_info = isset($attr['map_info']) && $attr['map_info'] == '0' ? '0' : '1';

        return '<?php if (' . $rs . '->strField("event_latitude") !== "" && ' . $rs . '->strField("event_longitude") !== "") { echo ' . self::NS . 'EventHandler::getMapContent("", "", ' . $map_type . ', ' . $map_zoom . ', ' . $map_info . ', ' .
            '(float) ' . $rs . '->strField("event_latitude"), (float) ' . $rs . '->strField("event_longitude"), ' . $rs . '->getMapVEvent()); } ?>';
    }

    /*
     * Events of an entry
     */

    /**
     * tpl:EventsOfPost : events related to current entry loop.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsOfPost(ArrayObject $attr, string $content): string
    {
        $p = '';

        if (isset($attr['lastn']) && is_numeric($attr['lastn']) && (int) $attr['lastn'] > 0) {
            $p .= "\$params['limit'] = " . abs((int) $attr['lastn']) . ";\n";
        }

        if (isset($attr['event']) && is_numeric($attr['event'])) {
            $p .= "\$params['event_id'] = " . abs((int) $attr['event']) . ";\n";
        }

        if (isset($attr['post']) && is_numeric($attr['post'])) {
            $p .= "\$params['post_id'] = " . abs((int) $attr['post']) . ";\n";
        }

        $p .= self::commonParams($attr);

        $p .= "\$params['order'] = " . var_export(App::frontend()->template()->getSortByStr($attr, !empty($attr['order']) || !empty($attr['sortby']) ? My::POST_TYPE : 'post'), true) . ";\n";

        return "<?php\n" .
            "\$params = ['sql' => " . self::NS . "My::hiddenCategoriesSql()];\n" .
            'if (App::frontend()->context()->exists("posts") && App::frontend()->context()->posts->intField("post_id")) { ' .
            '$params["post_id"] = App::frontend()->context()->posts->intField("post_id"); ' .
            "}\n" .
            $p .
            'App::frontend()->context()->eventsofpost_params = $params;' . "\n" .
            'App::frontend()->context()->eventsofpost = (new ' . self::NS . 'EventHandler())->getEventsByPost($params); unset($params);' . "\n" .
            'while (App::frontend()->context()->eventsofpost->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'App::frontend()->context()->eventsofpost = null; App::frontend()->context()->eventsofpost_params = null; ?>';
    }

    /**
     * tpl:EventsOfPostHeader : first event related to current entry.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsOfPostHeader(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->eventsofpost->isStart()) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:EventsOfPostFooter : last event related to current entry.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventsOfPostFooter(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->eventsofpost->isEnd()) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:EventOfPostIf : event related to current entry conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function EventOfPostIf(ArrayObject $attr, string $content): string
    {
        return self::ifBlock($attr, self::eventConditions($attr, 'App::frontend()->context()->eventsofpost', 'App::frontend()->context()->eventsofpost_params'), $content);
    }

    /**
     * tpl:EventOfPostTitle : related event title.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostTitle(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->strField("post_title")');
    }

    /**
     * tpl:EventOfPostURL : related event URL.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostURL(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->getURL()');
    }

    /**
     * tpl:EventOfPostDate : related event date.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostDate(ArrayObject $attr): string
    {
        return self::eventDate($attr, 'App::frontend()->context()->eventsofpost');
    }

    /**
     * tpl:EventOfPostTime : related event time.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostTime(ArrayObject $attr): string
    {
        return self::eventTime($attr, 'App::frontend()->context()->eventsofpost');
    }

    /**
     * tpl:EventOfPostAuthorCommonName : related event author.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostAuthorCommonName(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->getAuthorCN()');
    }

    /**
     * tpl:EventOfPostAuthorLink : related event author link.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostAuthorLink(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->getAuthorLink()');
    }

    /**
     * tpl:EventOfPostCategory : related event category.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostCategory(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->strField("cat_title")');
    }

    /**
     * tpl:EventOfPostCategoryURL : related event category URL on events page.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostCategoryURL(ArrayObject $attr): string
    {
        return self::eventCategoryURL($attr, 'App::frontend()->context()->eventsofpost');
    }

    /**
     * tpl:EventOfPostAddress : related event address.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostAddress(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->eventsofpost->strField("event_address")');
    }

    /**
     * tpl:EventOfPostDuration : related event duration.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostDuration(ArrayObject $attr): string
    {
        return self::eventDuration($attr, 'App::frontend()->context()->eventsofpost');
    }

    /**
     * tpl:EventOfPostPeriod : related event period.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function EventOfPostPeriod(ArrayObject $attr): string
    {
        return self::eventPeriod($attr, 'App::frontend()->context()->eventsofpost');
    }

    /*
     * Entries of an event
     */

    /**
     * tpl:PostsOfEvent : entries related to current event loop.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function PostsOfEvent(ArrayObject $attr, string $content): string
    {
        $p = '';

        if (isset($attr['lastn']) && is_numeric($attr['lastn']) && (int) $attr['lastn'] > 0) {
            $p .= "\$params['limit'] = " . abs((int) $attr['lastn']) . ";\n";
        }

        if (isset($attr['event']) && is_numeric($attr['event'])) {
            $p .= "\$params['event_id'] = " . abs((int) $attr['event']) . ";\n";
        }

        $p .= self::commonParams($attr);

        $p .= "\$params['order'] = " . var_export(App::frontend()->template()->getSortByStr($attr, 'post'), true) . ";\n";

        return "<?php\n" .
            "\$params = [];\n" .
            'if (App::frontend()->context()->exists("posts") && App::frontend()->context()->posts->intField("post_id")) { ' .
            "\$params['event_id'] = App::frontend()->context()->posts->intField('post_id'); " .
            "}\n" .
            $p .
            'App::frontend()->context()->postsofevent_params = $params;' . "\n" .
            'App::frontend()->context()->postsofevent = (new ' . self::NS . 'EventHandler())->getPostsByEvent($params); unset($params);' . "\n" .
            "?>\n" .
            '<?php while (App::frontend()->context()->postsofevent->fetch()) : ?>' . $content . '<?php endwhile; ' .
            'App::frontend()->context()->postsofevent = null; App::frontend()->context()->postsofevent_params = null; ?>';
    }

    /**
     * tpl:PostsOfEventHeader : first entry related to current event.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function PostsOfEventHeader(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->postsofevent->isStart()) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:PostsOfEventFooter : last entry related to current event.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function PostsOfEventFooter(ArrayObject $attr, string $content): string
    {
        return '<?php if (App::frontend()->context()->postsofevent->isEnd()) : ?>' . $content . '<?php endif; ?>';
    }

    /**
     * tpl:PostOfEventIf [type] [has_category] : entry related to current event conditions.
     *
     * @param   ArrayObject<string, mixed>  $attr       The attributes
     * @param   string                      $content    The block content
     */
    public static function PostOfEventIf(ArrayObject $attr, string $content): string
    {
        $if = [];

        if (isset($attr['type'])) {
            $type = trim(self::attr($attr, 'type'));
            $if[] = 'App::frontend()->context()->postsofevent->strField("post_type") === ' . var_export($type !== '' ? $type : 'post', true);
        }

        if (isset($attr['has_category'])) {
            $if[] = ((bool) $attr['has_category'] ? '' : '!') . '(App::frontend()->context()->postsofevent->strField("cat_id") !== "")';
        }

        return self::ifBlock($attr, $if, $content);
    }

    /**
     * tpl:PostOfEventTitle : related entry title.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventTitle(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->strField("post_title")');
    }

    /**
     * tpl:PostOfEventURL : related entry URL.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventURL(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getURL()');
    }

    /**
     * tpl:PostOfEventDate : related entry date.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventDate(ArrayObject $attr): string
    {
        $type = self::dateType($attr, false);

        if (!empty($attr['rfc822'])) {
            return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getRFC822Date("' . $type . '")');
        } elseif (!empty($attr['iso8601'])) {
            return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getISO8601Date("' . $type . '")');
        }

        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getDate(' . var_export(self::attr($attr, 'format'), true) . ', "' . $type . '")');
    }

    /**
     * tpl:PostOfEventTime : related entry time.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventTime(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getTime(' . var_export(self::attr($attr, 'format'), true) . ', "' . self::dateType($attr, false) . '")');
    }

    /**
     * tpl:PostOfEventAuthorCommonName : related entry author.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventAuthorCommonName(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getAuthorCN()');
    }

    /**
     * tpl:PostOfEventAuthorLink : related entry author link.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventAuthorLink(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getAuthorLink()');
    }

    /**
     * tpl:PostOfEventCategory : related entry category.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventCategory(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->strField("cat_title")');
    }

    /**
     * tpl:PostOfEventCategoryURL : related entry category URL.
     *
     * @param   ArrayObject<string, mixed>  $attr   The attributes
     */
    public static function PostOfEventCategoryURL(ArrayObject $attr): string
    {
        return self::tplValue($attr, 'App::frontend()->context()->postsofevent->getCategoryURL()');
    }
}
