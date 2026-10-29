<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Core\Frontend\Ctx;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Html;
use Dotclear\Schema\Extension\Post;

/**
 * @brief       eventHandler events record extension.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class RsExtension extends Post
{
    /**
     * Check if event starts and ends on the same day.
     */
    public static function isOnSameDay(MetaRecord $rs): bool
    {
        return Date::dt2str('%Y%j', $rs->strField('event_startdt')) === Date::dt2str('%Y%j', $rs->strField('event_enddt'));
    }

    /**
     * Check if event duration is less than 24 hours.
     */
    public static function isOnOneDay(MetaRecord $rs): bool
    {
        return (self::getEventTS($rs, 'enddt') - self::getEventTS($rs, 'startdt')) < 86401;
    }

    /**
     * Get the date field name related to a type.
     *
     * @param   string  $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    protected static function getDateField(string $type): string
    {
        return match ($type) {
            'upddt'   => 'post_upddt',
            'creadt'  => 'post_creadt',
            'startdt' => 'event_startdt',
            'enddt'   => 'event_enddt',
            default   => 'post_dt',
        };
    }

    /**
     * Get event date timestamp.
     *
     * @param   MetaRecord  $rs     The record
     * @param   string      $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function getEventTS(MetaRecord $rs, string $type = ''): int
    {
        return (int) strtotime($rs->strField(self::getDateField($type)));
    }

    /**
     * Get event date as ISO8601.
     *
     * @param   MetaRecord  $rs     The record
     * @param   string      $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function getEventISO8601Date(MetaRecord $rs, string $type = ''): string
    {
        $tz = $rs->strField('post_tz');
        if (in_array($type, ['upddt', 'creadt'])) {
            return Date::iso8601(self::getEventTS($rs, $type) + Date::getTimeOffset($tz), $tz);
        }

        return Date::iso8601(self::getEventTS($rs, $type), $tz);
    }

    /**
     * Get event date as RFC822.
     *
     * @param   MetaRecord  $rs     The record
     * @param   string      $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function getEventRFC822Date(MetaRecord $rs, string $type = ''): string
    {
        $tz = $rs->strField('post_tz');
        if (in_array($type, ['upddt', 'creadt'])) {
            return Date::rfc822(self::getEventTS($rs, $type) + Date::getTimeOffset($tz), $tz);
        }

        return Date::rfc822(self::getEventTS($rs, $type), $tz);
    }

    /**
     * Get formated event date.
     *
     * @param   MetaRecord  $rs         The record
     * @param   string      $format     The date format (blog date format if empty)
     * @param   string      $type       The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function getEventDate(MetaRecord $rs, string $format = '', string $type = ''): string
    {
        if ($format === '') {
            $format = (string) App::blog()->settings()->get('system')->getStr('date_format', false);
        }

        return self::formatEventDate($rs, $format, $type);
    }

    /**
     * Get formated event time.
     *
     * @param   MetaRecord  $rs         The record
     * @param   string      $format     The time format (blog time format if empty)
     * @param   string      $type       The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function getEventTime(MetaRecord $rs, string $format = '', string $type = ''): string
    {
        if ($format === '') {
            $format = (string) App::blog()->settings()->get('system')->getStr('time_format', false);
        }

        return self::formatEventDate($rs, $format, $type);
    }

    /**
     * Format an event date.
     *
     * Events dates are stored in blog timezone, posts dates in UTC.
     *
     * @param   MetaRecord  $rs         The record
     * @param   string      $format     The date format
     * @param   string      $type       The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    protected static function formatEventDate(MetaRecord $rs, string $format, string $type): string
    {
        $field = self::getDateField($type);

        return in_array($type, ['upddt', 'creadt']) ?
            Date::dt2str($format, $rs->strField($field), $rs->strField('post_tz')) :
            Date::dt2str($format, $rs->strField($field));
    }

    /**
     * Get event period (scheduled, ongoing or finished).
     */
    public static function getPeriod(MetaRecord $rs): string
    {
        $now = date('Y-m-d H:i:s');

        if ($rs->strField('event_startdt') > $now) {
            return 'scheduled';
        } elseif ($rs->strField('event_enddt') < $now) {
            return 'finished';
        }

        return 'ongoing';
    }

    /**
     * Check if current event is the first one of its day.
     *
     * @param   MetaRecord  $rs     The record
     * @param   string      $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function firstEventOfDay(MetaRecord $rs, string $type = ''): bool
    {
        if ($rs->isStart()) {
            return true;
        }

        $cdate = self::formatEventDate($rs, '%Y%m%d', $type);
        $rs->movePrev();
        $ndate = self::formatEventDate($rs, '%Y%m%d', $type);
        $rs->moveNext();

        return $ndate !== $cdate;
    }

    /**
     * Check if current event is the last one of its day.
     *
     * @param   MetaRecord  $rs     The record
     * @param   string      $type   The date type (startdt, enddt, upddt, creadt or empty for post_dt)
     */
    public static function lastEventOfDay(MetaRecord $rs, string $type = ''): bool
    {
        if ($rs->isEnd()) {
            return true;
        }

        $cdate = self::formatEventDate($rs, '%Y%m%d', $type);
        $rs->moveNext();
        $ndate = self::formatEventDate($rs, '%Y%m%d', $type);
        $rs->movePrev();

        return $ndate !== $cdate;
    }

    /**
     * Get event short description (text only).
     *
     * @param   MetaRecord  $rs         The record
     * @param   bool        $encode     Encode HTML entities
     */
    protected static function getShortDescription(MetaRecord $rs, bool $encode = true): string
    {
        return Ctx::global_filters(
            self::isExtended($rs) ? self::getExcerpt($rs) : self::getContent($rs),
            [
                0             => null,
                'encode_xml'  => $encode ? 1 : 0,
                'remove_html' => 1,
                'cut_string'  => 250,
            ]
        );
    }

    /**
     * Escape a value for iCalendar.
     *
     * @param   string  $value  The value
     */
    protected static function escapeIcal(string $value): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', '\n'], $value);
    }

    /**
     * Get event as iCalendar VEVENT.
     */
    public static function getIcalVEVENT(MetaRecord $rs): string
    {
        $tz  = (string) App::blog()->settings()->get('system')->getStr('blog_timezone', false);
        $url = self::getURL($rs);

        $lines = [
            'BEGIN:VEVENT',
            'TRANSP:OPAQUE',
            'SEQUENCE:0',
            'PRIORITY:5',
            'CLASS:PUBLIC',
            'SUMMARY;CHARSET=UTF-8:' . self::escapeIcal($rs->strField('post_title')),
            'URL:' . $url,
            'UID:' . App::blog()->uid() . '-' . $rs->intField('post_id'),
            'DTSTAMP:' . Date::dt2str('%Y%m%dT%H%M%SZ', $rs->strField('post_upddt')),
            'DTSTART;TZID=' . $tz . ':' . Date::dt2str('%Y%m%dT%H%M%S', $rs->strField('event_startdt')),
            'DTEND;TZID=' . $tz . ':' . Date::dt2str('%Y%m%dT%H%M%S', $rs->strField('event_enddt')),
            'DESCRIPTION;CHARSET=UTF-8:' . self::escapeIcal($rs->strField('post_title') . ' - ' . Html::decodeEntities(self::getShortDescription($rs, false)) . ' - ' . $url),
        ];
        if ($rs->strField('event_address') !== '') {
            $lines[] = 'LOCATION;CHARSET=UTF-8:' . self::escapeIcal($rs->strField('event_address'));
        }
        if ($rs->strField('event_latitude') !== '' && $rs->strField('event_longitude') !== '') {
            $lines[] = 'GEO:' . $rs->strField('event_latitude') . ';' . $rs->strField('event_longitude');
        }
        if ($rs->strField('cat_title') !== '') {
            $lines[] = 'CATEGORIES;CHARSET=UTF-8:' . self::escapeIcal($rs->strField('cat_title'));
        }
        $lines[] = 'END:VEVENT';

        $res = '';
        foreach ($lines as $line) {
            $res .= implode("\r\n ", mb_str_split(trim($line), 70)) . "\r\n";
        }

        return $res;
    }

    /**
     * Get event as hCalendar.
     */
    public static function getHcalVEVENT(MetaRecord $rs): string
    {
        $res = '<div class="vevent">' . "\n" .
            '<h2 class="summary">' . Html::escapeHTML($rs->strField('post_title')) . '</h2>' . "\n" .
            '<ul>' . "\n" .
            '<li>' . __('Start date:') . ' <abbr class="dtstart" title="' . self::getEventISO8601Date($rs, 'startdt') . '">' .
                self::getEventDate($rs, '', 'startdt') . ', ' . self::getEventTime($rs, '', 'startdt') . '</abbr></li>' . "\n" .
            '<li>' . __('End date:') . ' <abbr class="dtend" title="' . self::getEventISO8601Date($rs, 'enddt') . '">' .
                self::getEventDate($rs, '', 'enddt') . ', ' . self::getEventTime($rs, '', 'enddt') . '</abbr></li>' . "\n";

        if ($rs->strField('event_address') !== '') {
            $res .= '<li>' . __('Address:') . ' <abbr class="location">' . Html::escapeHTML($rs->strField('event_address')) . '</abbr></li>' . "\n";
        }
        if ($rs->strField('event_latitude') !== '' && $rs->strField('event_longitude') !== '') {
            $res .= '<li>' . __('Location:') . ' <abbr class="geo" title="' . Html::escapeHTML($rs->strField('event_latitude') . ';' . $rs->strField('event_longitude')) . '">' .
                __('latitude:') . ' ' . Html::escapeHTML($rs->strField('event_latitude')) . ', ' .
                __('longitude:') . ' ' . Html::escapeHTML($rs->strField('event_longitude')) . '</abbr></li>' . "\n";
        }
        if ($rs->strField('cat_title') !== '') {
            $res .= '<li>' . __('Category:') . ' <abbr class="categories">' . Html::escapeHTML($rs->strField('cat_title')) . '</abbr></li>' . "\n";
        }

        return $res .
            '</ul>' . "\n" .
            '<p class="description">' . self::getShortDescription($rs) . '</p>' . "\n" .
            '<p><a class="url" href="' . self::getURL($rs) . '">' . __('Read more') . '</a> <abbr class="uid">' . App::blog()->id() . $rs->intField('post_id') . '</abbr></p>' . "\n" .
            '</div>';
    }

    /**
     * Get event map marker.
     */
    public static function getMapVEvent(MetaRecord $rs): string
    {
        return
            '<div style="display:none;" class="event-map-marker">' . "\n" .
            '<p class="event-map-marker-latitude">' . Html::escapeHTML($rs->strField('event_latitude')) . '</p>' . "\n" .
            '<p class="event-map-marker-longitude">' . Html::escapeHTML($rs->strField('event_longitude')) . '</p>' . "\n" .
            '<p class="event-map-marker-title">' . Html::escapeHTML($rs->strField('post_title')) . '</p>' . "\n" .
            '<p class="event-map-marker-startdt" title="' . Html::escapeHTML(self::getEventDate($rs, '', 'startdt') . ', ' . self::getEventTime($rs, '', 'startdt')) . '">' . Html::escapeHTML($rs->strField('event_startdt')) . '</p>' . "\n" .
            '<p class="event-map-marker-enddt" title="' . Html::escapeHTML(self::getEventDate($rs, '', 'enddt') . ', ' . self::getEventTime($rs, '', 'enddt')) . '">' . Html::escapeHTML($rs->strField('event_enddt')) . '</p>' . "\n" .
            '<p class="event-map-marker-address">' . Html::escapeHTML($rs->strField('event_address')) . '</p>' . "\n" .
            '<p class="event-map-marker-link">' . self::getURL($rs) . '</p>' . "\n" .
            '</div>';
    }
}
