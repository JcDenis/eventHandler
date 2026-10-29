<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;

/**
 * @brief       eventHandler calendar.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Calendar
{
    /**
     * Timestamp of a sunday.
     *
     * @var     int     SUNDAY_TS
     */
    final public const SUNDAY_TS = 1_042_329_600;

    /**
     * Calendar year.
     */
    public readonly int $year;

    /**
     * Calendar month.
     */
    public readonly int $month;

    /**
     * Calendar caption.
     *
     * @var     array{prev_url: string, prev_txt: string, current: string, current_url: string, current_dt: string, next_url: string, next_txt: string}
     */
    public readonly array $caption;

    /**
     * Calendar head (days names).
     *
     * @var     array<int, string>
     */
    public readonly array $head;

    /**
     * Calendar rows (weeks of days, 0 for empty day).
     *
     * @var     array<int, array<int, int>>
     */
    public readonly array $rows;

    /**
     * Constructor.
     *
     * @param   int     $year       The year (current year if 0)
     * @param   int     $month      The month (current month if 0)
     * @param   bool    $weekstart  Week starts on monday
     */
    public function __construct(int $year = 0, int $month = 0, public readonly bool $weekstart = false)
    {
        $this->year  = $year  > 999 && $year < 10000 ? $year : (int) date('Y');
        $this->month = $month > 0   && $month < 13 ? $month : (int) date('m');

        $ts      = (int) mktime(0, 0, 0, $this->month, 1, $this->year);
        $prev_ts = (int) mktime(0, 0, 0, $this->month - 1, 1, $this->year);
        $next_ts = (int) mktime(0, 0, 0, $this->month + 1, 1, $this->year);

        $this->caption = [
            'prev_url'    => date('Y/m', $prev_ts),
            'prev_txt'    => Date::str('%B %Y', $prev_ts),
            'current'     => Date::str('%B %Y', $ts),
            'current_url' => date('Y/m', $ts),
            'current_dt'  => date('Ym', $ts),
            'next_url'    => date('Y/m', $next_ts),
            'next_txt'    => Date::str('%B %Y', $next_ts),
        ];

        // days of week
        $head     = [];
        $first_ts = self::SUNDAY_TS + ((int) $weekstart * 86400);
        for ($j = 0; $j < 7; $j++) {
            $head[] = Date::str('%a', $first_ts + ($j * 86400));
        }
        $this->head = $head;

        // days of month
        $first = (int) date('w', $ts) - (int) $weekstart;
        if ($first < 0) {
            $first += 7;
        }
        $limit = (int) date('t', $ts);

        $rows = [];
        $day  = 1;
        for ($i = 0; $i < 42; $i++) {
            $row = intdiv($i, 7);
            if ($i >= $first && $day <= $limit) {
                $rows[$row][] = $day;
                $day++;
            } else {
                $rows[$row][] = 0;
            }
            // stop at end of a week if month is complete
            if (($i + 1) % 7 === 0 && $day > $limit) {
                break;
            }
        }
        $this->rows = $rows;
    }

    /**
     * Get HTML calendar.
     *
     * @param   bool    $startonly  Only show events start dates
     * @param   bool    $rest       Only table content (for rest service)
     */
    public function parse(bool $startonly, bool $rest = false): string
    {
        $eventHandler = new EventHandler();

        $class = ($this->weekstart ? ' weekstart' : '') . ($startonly ? ' startonly' : '');
        $base  = App::blog()->url() . App::url()->getBase('eventhandler_list') . '/' . ($startonly ? 'of' : 'on') . '/';

        $res = $rest ? '' : "\n" . '<div class="calendar-array' . $class . '">';
        $res .= '<table>' . "\n" .
            ' <caption title="' . $this->caption['current_dt'] . '">' . "\n" .
            '  <a class="prev" href="' . $base . $this->caption['prev_url'] . '" title="' . Html::escapeHTML($this->caption['prev_txt']) . '">&#171;</a> ' . "\n" .
            '  <a class="current" href="' . $base . $this->caption['current_url'] . '" title="' . __('Detail') . '">' . Html::escapeHTML($this->caption['current']) . '</a>' . "\n" .
            '  <a class="next" href="' . $base . $this->caption['next_url'] . '" title="' . Html::escapeHTML($this->caption['next_txt']) . '">&#187;</a> ' . "\n" .
            ' </caption>' . "\n" .
            ' <thead>' . "\n" . '  <tr>' . "\n";

        foreach ($this->head as $day_txt) {
            $res .= '   <th>' . Html::escapeHTML($day_txt) . '</th>' . "\n";
        }

        $res .= '  </tr>' . "\n" . ' </thead>' . "\n" . ' <tbody>' . "\n";

        $hidden = My::hiddenCategoriesSql();
        foreach ($this->rows as $days) {
            $res .= '  <tr>' . "\n";
            foreach ($days as $day) {
                $content = ' ';
                $classes = '';
                if ($day !== 0) {
                    $content = (string) $day;
                    $params  = ['no_content' => true, 'sql' => $hidden];
                    if ($startonly) {
                        $params['event_start_year']  = $this->year;
                        $params['event_start_month'] = $this->month;
                        $params['event_start_day']   = $day;
                    } else {
                        $params['event_period']  = 'ongoing';
                        $params['event_startdt'] = date('Y-m-d H:i:s', (int) mktime(0, 0, 0, $this->month, $day + 1, $this->year));
                        $params['event_enddt']   = date('Y-m-d H:i:s', (int) mktime(0, 0, 0, $this->month, $day, $this->year));
                    }

                    $rs    = $eventHandler->getEvents($params);
                    $count = $rs->count();
                    if ($count === 1) {
                        $rs->fetch();
                        $content = '<a href="' . RsExtension::getURL($rs) . '" title="' . Html::escapeHTML($rs->strField('post_title')) . '">' . $day . '</a>';
                        $classes = ' class="eventsday"';
                    } elseif ($count > 1) {
                        $content = '<a href="' . $base . $this->year . '/' . sprintf('%02d', $this->month) . '/' . sprintf('%02d', $day) . '" title="' . sprintf(__('%s events'), $count) . '">' . $day . '</a>';
                        $classes = ' class="eventsday"';
                    }
                }
                $res .= '   <td' . $classes . '>' . $content . '</td>' . "\n";
            }
            $res .= '  </tr>' . "\n";
        }

        return $res . ' </tbody>' . "\n" . '</table>' . ($rest ? '' : '</div>' . "\n");
    }
}
