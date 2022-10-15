<?php
/*
 *  -- BEGIN LICENSE BLOCK ----------------------------------
 *
 *  This file is part of eventHandler, a plugin for Dotclear 2.
 *
 *  Copyright(c) 2014-2022 Nicolas Roudaire <nikrou77@gmail.com> https://www.nikrou.net
 *
 *  Copyright (c) 2009-2013 Jean-Christian Denis and contributors
 *  contact@jcdenis.fr http://jcd.lv
 *
 *  Licensed under the GPL version 2.0 license.
 *  A copy of this license is available in LICENSE file or at
 *  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 *  -- END LICENSE BLOCK ------------------------------------
 */

// Public side of widgets
class eventHandlerPublicWidgets
{
    public static function events($w)
    {
        if ($w->offline) {
            return;
        }

        // Plugin active ?
        if (!dcCore::app()->blog->settings->eventHandler->active) {
            return;
        }
        // Home only
        if ($w->homeonly == 1 && dcCore::app()->url->type != 'default'
        	|| $w->homeonly == 2 && dcCore::app()->url->type == 'default') {
            return;
        }
        $params['sql'] = '';
        // Period
        if ($w->period) {
            $params['event_period'] = $w->period;
        }
        // Sort field
        $params['order'] = ($w->sortby && in_array($w->sortby, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'])) ?
        	$w->sortby . ' ' : 'event_startdt ';
        // Sort order
        $params['order'] .= $w->sort == 'desc' ? 'desc' : 'asc';
        // Rows number
        if ('' !== $w->limit) {
            $params['limit'] = abs((integer) $w->limit);
        }
        // No post content
        $params['no_content'] = true;
        // Post type
        $params['post_type'] = 'eventhandler';
        // Selected post only
        if ($w->selectedonly) {
            $params['post_selected'] = 1;
        }
        // Category
        if ($w->category) {
            if ($w->category == 'null') {
                $params['sql'] .= ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->category)) {
                $params['cat_id'] = (integer) $w->category;
            } else {
                $params['cat_url'] = $w->category;
            }
        } else { // If no paricular category is selected, remove unlisted categories
            $public_hidden_categories = @unserialize(dcCore::app()->blog->settings->eventHandler->public_hidden_categories);
            if (is_array($public_hidden_categories) && !empty($public_hidden_categories)) {
                foreach ($public_hidden_categories as $k => $cat_id) {
                    $params['sql'] .= " AND P.cat_id != '$cat_id' ";
                }
            }
        }
        // Get posts
        $eventHandler = new eventHandler();
        $rs = $eventHandler->getEvents($params);
        // No result
        if ($rs->isEmpty()) {
            return;
        }

        // Display
        $res = ($w->content_only ? '' : '<div class="widget eventhandler-events' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') .
        	// Events events
        	'<ul>';

        while ($rs->fetch()) {
            // If same day
            if ($rs->isOnSameDay()) {
                $over_format = __('On %sd from %st to %et');
            } else {
                $over_format = __('From %sd, %st to %ed, %et');
            }

            // Format items
            $fsd = dt::dt2str($w->date_format, $rs->event_startdt);
            $fst = dt::dt2str($w->time_format, $rs->event_startdt);
            $fed = dt::dt2str($w->date_format, $rs->event_enddt);
            $fet = dt::dt2str($w->time_format, $rs->event_enddt);

            // Replacement
            $over = str_replace(
                ['%sd', '%st', '%ed', '%et', '%%'],
                [$fsd, $fst, $fed, $fet, '%'],
                $over_format
            );
            $title = '<a href="' . $rs->getURL() . '" title="' . $over . '">' . html::escapeHTML($rs->post_title) . '</a>';
            $cat = '';
            if ($w->item_showcat && $rs->cat_id) {
                $cat = sprintf(
                    ' (<a href="%s" title="%s">%s</a>)',
                    $rs->getCategoryURL(),
                    __('go to this category'),
                    html::escapeHTML($rs->cat_title)
                );
            }

            $res .= '<li>' . $title . $cat . '</li>';
        }
        $res .= '</ul>';

        if ($w->pagelink) {
            $res .=
            	'<p><strong><a href="' .
            	dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
            	'" >' . __('All events') . '</a></strong></p>';
        }

        $res .= ($w->content_only ? '' : '</div>');

        return $res;
    }

    public static function eventsOfPost($w)
    {
        if ($w->offline) {
            return;
        }

        // Plugin active
        if (!dcCore::app()->blog->settings->eventHandler->active) {
            return;
        }
        // Post page only
        if (dcCore::app()->url->type != 'post') {
            return;
        }
        $params['sql'] = '';
        // Period
        if ($w->period) {
            $params['event_period'] = $w->period;
        }
        // Sort field
        $params['order'] = ($w->sortby && in_array($w->sortby, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'])) ?
        	$w->sortby . ' ' : 'event_startdt ';
        // Sort order
        $params['order'] .= $w->sort == 'desc' ? 'desc' : 'asc';
        // Rows number
        if ('' !== $w->limit) {
            $params['limit'] = abs((integer) $w->limit);
        }
        // No post content
        $params['no_content'] = true;
        // Post id
        $params['post_id'] = dcCore::app()->ctx->posts->post_id;
        // Event type
        $params['event_type'] = 'eventhandler';
        // Category
        if ($w->category) {
            if ($w->category == 'null') {
                $params['sql'] .= ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->category)) {
                $params['cat_id'] = (integer) $w->category;
            } else {
                $params['cat_url'] = $w->category;
            }
        } else { // If no paricular category is selected, remove unlisted categories
            $public_hidden_categories = @unserialize(dcCore::app()->blog->settings->eventHandler->public_hidden_categories);
            if (is_array($public_hidden_categories) && !empty($public_hidden_categories)) {
                foreach ($public_hidden_categories as $k => $cat_id) {
                    $params['sql'] .= " AND P.cat_id != '$cat_id' ";
                }
            }
        }
        // Get posts
        $eventHandler = new eventHandler();
        $rs = $eventHandler->getEventsByPost($params);
        // No result
        if ($rs->isEmpty()) {
            return;
        }

        // Display
        $res = ($w->content_only ? '' : '<div class="widget eventhandler-eventsofpost' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') .
        	// Events eventsofpost
        	'<ul>';
        while ($rs->fetch()) {
            // If same day
            if ($rs->isOnSameDay()) {
                $over_format = __('On %sd from %st to %et');
            } else {
                $over_format = __('From %sd, %st to %ed, %et');
            }

            // Format items
            $fsd = dt::dt2str(dcCore::app()->blog->settings->system->date_format, $rs->event_startdt);
            $fst = dt::dt2str(dcCore::app()->blog->settings->system->time_format, $rs->event_startdt);
            $fed = dt::dt2str(dcCore::app()->blog->settings->system->date_format, $rs->event_enddt);
            $fet = dt::dt2str(dcCore::app()->blog->settings->system->time_format, $rs->event_enddt);

            // Replacement
            $over = str_replace(
                ['%sd', '%st', '%ed', '%et', ],
                [$fsd, $fst, $fed, $fet],
                $over_format
            );
            $item = html::escapeHTML($rs->post_title);

            $res .= '<li><a href="' . $rs->getURL() . '" title="' . $over . '">' . $item . '</a></li>';
        }
        $res .= '</ul>';

        if ($w->pagelink) {
            $res .=
            	'<p><strong><a href="' .
            	dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
            	'" >' . __('All events') . '</a></strong></p>';
        }

        $res .= ($w->content_only ? '' : '</div>');

        return $res;
    }

    public static function postsOfEvent($w)
    {
        if ($w->offline) {
            return;
        }

        // Plugin active
        if (!dcCore::app()->blog->settings->eventHandler->active) {
            return;
        }
        // Event page only
        if (dcCore::app()->url->type != 'eventhandler_single') {
            return;
        }

        $params['sql'] = '';
        // Sort field
        $params['order'] = ($w->sortby && in_array($w->sortby, ['post_title', 'post_dt'])) ?
        	$w->sortby . ' ' : 'post_dt ';
        // Sort order
        $params['order'] .= $w->sort == 'asc' ? 'asc' : 'desc';
        // Rows number
        if ('' !== $w->limit) {
            $params['limit'] = abs((integer) $w->limit);
        }
        // No post content
        $params['no_content'] = true;
        // Event id
        $params['event_id'] = dcCore::app()->ctx->posts->post_id;
        // Event type
        $params['event_type'] = 'eventhandler';
        // Category
        if ($w->category) {
            if ($w->category == 'null') {
                $params['sql'] = ' AND P.cat_id IS NULL ';
            } elseif (is_numeric($w->category)) {
                $params['cat_id'] = (integer) $w->category;
            } else {
                $params['cat_url'] = $w->category;
            }
        }
        // Get posts
        $eventHandler = new eventHandler();
        $rs = $eventHandler->getPostsByEvent($params);
        // No result
        if ($rs->isEmpty()) {
            return;
        }

        // Display
        $res = ($w->content_only ? '' : '<div class="widget eventhandler-postsofevent' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') .
        	// Events postsofevent
        	'<ul>';

        while ($rs->fetch()) {
            $res .= '<li><a href="' . $rs->getURL() . '">' .
            	html::escapeHTML($rs->post_title) . '</a></li>';
        }
        $res .= '</ul>';

        $res .= ($w->content_only ? '' : '</div>');

        return $res;
    }

    public static function categories($w)
    {
        if ($w->offline) {
            return;
        }
        // Plugin active ?
        if (!dcCore::app()->blog->settings->eventHandler->active) {
            return;
        }

        if ($w->homeonly == 1 && dcCore::app()->url->type != 'default'
        	|| $w->homeonly == 2 && dcCore::app()->url->type == 'default') {
            return;
        }

        // Display
        $res = ($w->content_only ? '' : '<div class="widget eventhandler-categories' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '');
        // Events categories
        $rs = dcCore::app()->blog->getCategories(['post_type' => 'eventhandler']);
        if ($rs->isEmpty()) {
            return;
        }

        $ref_level = $level = $rs->level - 1;
        while ($rs->fetch()) {
            $class = '';
            if ((dcCore::app()->url->type == 'catevents' && dcCore::app()->ctx->categories instanceof record && dcCore::app()->ctx->categories->cat_id == $rs->cat_id)
            	|| (dcCore::app()->url->type == 'event' && dcCore::app()->ctx->posts instanceof record && dcCore::app()->ctx->posts->cat_id == $rs->cat_id)) {
                $class = ' class="category-current"';
            }

            if ($rs->level > $level) {
                $res .= str_repeat('<ul><li' . $class . '>', $rs->level - $level);
            } elseif ($rs->level < $level) {
                $res .= str_repeat('</li></ul>', -($rs->level - $level));
            }

            if ($rs->level <= $level) {
                $res .= '</li><li' . $class . '>';
            }

            $res .=
            	'<a href="' . dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
            	'/category/' . $rs->cat_url . '">' .
            	html::escapeHTML($rs->cat_title) . '</a>' .
            	($w->postcount ? ' (' . $rs->nb_post . ')' : '');


            $level = $rs->level;
        }

        if ($ref_level - $level < 0) {
            $res .= str_repeat('</li></ul>', -($ref_level - $level));
        }

        if ($w->pagelink) {
            $res .=
            	'<p><strong><a href="' .
            	dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
            	'" >' . __('All events') . '</a></strong></p>';
        }

        $res .= ($w->content_only ? '' : '</div>');

        return $res;
    }

    public static function map($w)
    {
        if ($w->offline) {
            return;
        }

        // Plugin active
        if (!dcCore::app()->blog->settings->eventHandler->active
        	|| $w->homeonly == 1 && dcCore::app()->url->type != 'default'
        	|| $w->homeonly == 2 && dcCore::app()->url->type == 'default') {
            return;
        }

        $params['sql'] = '';
        // Period
        if ($w->period) {
            $params['event_period'] = $w->period;
        }
        // Sort field
        $params['order'] = ($w->sortby && in_array($w->sortby, ['post_title', 'post_dt', 'event_startdt', 'event_enddt'])) ?
        	$w->sortby . ' ' : 'event_startdt ';
        // Sort order
        $params['order'] .= $w->sort == 'desc' ? 'desc' : 'asc';
        $params['limit'] = 10;
        $params['no_content'] = true;
        $params['post_type'] = 'eventhandler';
        $params['sql'] .= "AND event_latitude != '' ";
        $params['sql'] .= "AND event_longitude != '' ";

        $public_hidden_categories = @unserialize(dcCore::app()->blog->settings->eventHandler->public_hidden_categories);
        if (is_array($public_hidden_categories) && !empty($public_hidden_categories)) {
            foreach ($public_hidden_categories as $k => $cat_id) {
                $params['sql'] .= " AND P.cat_id != '$cat_id' ";
            }
        }

        // Get posts
        $eventHandler = new eventHandler();
        $rs = $eventHandler->getEvents($params);
        // No result
        if ($rs->isEmpty()) {
            return;
        }

        $total_lat = $total_lng = 0;
        $markers = '';
        while ($rs->fetch()) {
            $total_lat += (float) $rs->event_latitude;
            $total_lng += (float) $rs->event_longitude;

            $markers .= $rs->getMapVEvent();
        }

        $lat = round($total_lat / $rs->count(), 7);
        $lng = round($total_lng / $rs->count(), 7);

        // Display
        $res = ($w->content_only ? '' : '<div class="widget eventhandler-map' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') .
        	// Events map
        	eventHandler::getMapContent(
        	    $w->map_width,
        	    $w->map_height,
        	    $w->map_type,
        	    $w->map_zoom,
        	    ((integer) $w->map_info),
        	    $lat,
        	    $lng,
        	    $markers
        	);

        if ($w->pagelink) {
            $res .=
            	'<p><strong><a href="' .
            	dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
            	'" >' . __('All events') . '</a></strong></p>';
        }

        $res .= ($w->content_only ? '' : '</div>');

        return $res;
    }

    public static function calendar($w)
    {
        if ($w->offline) {
            return;
        }

        if (!dcCore::app()->blog->settings->eventHandler->active
        	|| $w->homeonly == 1 && dcCore::app()->url->type != 'default'
        	|| $w->homeonly == 2 && dcCore::app()->url->type == 'default') {
            return;
        }

        $year = date('Y');
        $month = date('m');

        if (dcCore::app()->ctx->exists('event_params') && !empty(dcCore::app()->ctx->event_params['event_start_month'])) {
            $year = dcCore::app()->ctx->event_params['event_start_year'];
            $month = dcCore::app()->ctx->event_params['event_start_month'];
        } elseif (dcCore::app()->ctx->exists('event_params') && !empty(dcCore::app()->ctx->event_params['event_startdt'])) {
            $year = date('Y', strtotime(dcCore::app()->ctx->event_params['event_startdt']));
            $month = date('m', strtotime(dcCore::app()->ctx->event_params['event_startdt']));
        }

        // Generic calendar Object
        $calendar = eventHandlerCalendar::getArray($year, $month, $w->weekstart);

        return

        	// Display
        	$res = ($w->content_only ? '' : '<div class="widget eventhandler-calendar' . ($w->class ? ' ' . html::escapeHTML($w->class) : '') . '">') .
        	($w->title ? $w->renderTitle(html::escapeHTML($w->title)) : '') .
        	// Events calendar
        	eventHandlerCalendar::parseArray($calendar, $w->weekstart, $w->startonly) .
        	(
        	    $w->pagelink ?
        	 '<p><strong><a href="' .
        	 dcCore::app()->blog->url . dcCore::app()->url->getBase('eventhandler_list') .
        	 '" >' . __('All events') . '</a></strong></p>' : ''
        	) .
        	($w->content_only ? '' : '</div>');
    }
}
