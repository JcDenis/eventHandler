<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Core\Backend\Listing\Pager;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Caption;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Component;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Timestamp;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Stack\Status;

/**
 * @brief       eventHandler events list.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ListingEvents extends Listing
{
    /**
     * Events checkboxes field name.
     */
    private string $field = 'entries';

    /**
     * Set events checkboxes field name.
     *
     * @param   string  $field  The field name
     */
    public function setEntriesNames(string $field): void
    {
        $this->field = $field;
    }

    /**
     * Display events list.
     *
     * @param   int     $page           The current page
     * @param   int     $nb_per_page    The number of events per page
     * @param   string  $enclose_block  The enclose block (with %s)
     * @param   bool    $filter         The list is filtered
     */
    public function display(int $page, int $nb_per_page, string $enclose_block = '', bool $filter = false): void
    {
        if ($this->rs->isEmpty()) {
            echo (new Note())
                ->class('info')
                ->text($filter ? __('No event matches the filter') : __('No event'))
                ->render();

            return;
        }

        $pager = new Pager($page, $this->rs_count, $nb_per_page, 10);

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'title' => (new Th())
                ->scope('col')
                ->colspan(2)
                ->class('first')
                ->text(__('Title')),
            'startdt' => (new Th())
                ->scope('col')
                ->text(__('Start date')),
            'enddt' => (new Th())
                ->scope('col')
                ->text(__('End date')),
            'entries' => (new Th())
                ->scope('col')
                ->text(__('Entries')),
            'date' => (new Th())
                ->scope('col')
                ->text(__('Date')),
            'category' => (new Th())
                ->scope('col')
                ->text(__('Category')),
            'author' => (new Th())
                ->scope('col')
                ->text(__('Author')),
            'address' => (new Th())
                ->scope('col')
                ->text(__('Localization')),
            'status' => (new Th())
                ->scope('col')
                ->text(__('Status')),
        ]);

        // --BEHAVIOR-- adminEventHandlerEventsListHeaders -- MetaRecord, ArrayObject
        App::behavior()->callBehavior('adminEventHandlerEventsListHeaders', $this->rs, $cols);

        $this->userColumns(My::id(), $cols);

        $selected = isset($_POST[$this->field]) && is_array($_POST[$this->field]) ? $_POST[$this->field] : [];
        $lines    = [];
        while ($this->rs->fetch()) {
            $lines[] = $this->eventLine(in_array((string) $this->rs->intField('post_id'), $selected, true));
        }

        $table = (new Div())
            ->class('table-outer')
            ->items([
                (new Table())
                    ->class(['maximal', 'dragable'])
                    ->caption(new Caption(
                        $filter ?
                        sprintf(__('List of %s events matching the filter.'), $this->rs_count) :
                        sprintf(__('List of events (%s)'), $this->rs_count)
                    ))
                    ->items([
                        (new Thead())
                            ->rows([
                                (new Tr())
                                    ->items(iterator_to_array($cols)),
                            ]),
                        (new Tbody())
                            ->rows($lines),
                    ]),
            ])
            ->render();

        echo
        $pager->getLinks() .
        ($enclose_block !== '' ? sprintf($enclose_block, $table) : $table) .
        (new Note())
            ->class('info')
            ->text(
                __('Legend: ') .
                (new Set())
                    ->separator(' - ')
                    ->items([
                        ... array_map(fn (Status $k): Img|Set|Text => App::status()->post()->image($k->id(), true), App::status()->post()->dump(false)),
                        self::getRowImage(__('Protected'), 'images/locker.svg', 'locked', true),
                        self::getRowImage(__('Selected'), 'images/selected.svg', 'selected', true),
                    ])
                    ->render()
            )
            ->render() .
        $pager->getLinks();
    }

    /**
     * Get event line.
     *
     * @param   bool    $checked    The line is checked
     */
    private function eventLine(bool $checked): Tr
    {
        $post_id = $this->rs->intField('post_id');
        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        $period  = RsExtension::getPeriod($this->rs);

        $classes = ['line', 'eventhandler', $period, 'sts-' . App::status()->post()->id($this->rs->intField('post_status'))];
        if (App::status()->post()->isRestricted($this->rs->intField('post_status'))) {
            $classes[] = 'offline';
        }

        $status = [App::status()->post()->image($this->rs->intField('post_status'))];
        if ($this->rs->strField('post_password') !== '') {
            $status[] = self::getRowImage(__('Protected'), 'images/locker.svg', 'locked');
        }
        if ($this->rs->boolField('post_selected')) {
            $status[] = self::getRowImage(__('Selected'), 'images/selected.svg', 'selected');
        }

        if ($this->rs->strField('cat_title') !== '') {
            $category = App::auth()->check(App::auth()->makePermissions([
                App::auth()::PERMISSION_CATEGORIES,
            ]), App::blog()->id()) ?
                (new Link())
                    ->href(App::backend()->url()->get('admin.category', ['id' => $this->rs->intField('cat_id')], '&amp;', true))
                    ->text(Html::escapeHTML($this->rs->strField('cat_title'))) :
                (new Text(null, Html::escapeHTML($this->rs->strField('cat_title'))));
        } else {
            $category = (new Text(null, __('(No cat)')));
        }

        $nb_entries = (new EventHandler())->countPostsByEvent($post_id);
        $entries    = $nb_entries > 0 ?
            (new Link())
                ->href(App::postTypes()->getPostAdminURL(My::POST_TYPE, $post_id, true, ['tab' => 'bind-entries']) . '#bind-entries')
                ->text((string) $nb_entries) :
            (new Text(null, '0'));

        $date = fn (string $field, string $tz = ''): Timestamp => (new Timestamp(Date::dt2str(__('%Y-%m-%d %H:%M'), $this->rs->strField($field))))
            ->datetime(Date::iso8601((int) strtotime($this->rs->strField($field)), $tz !== '' ? $tz : $user_tz));

        /**
         * @var ArrayObject<string, Component>
         */
        $cols = new ArrayObject([
            'check' => (new Td())
                ->class('nowrap')
                ->items([
                    (new Checkbox([$this->field . '[]'], $checked))
                        ->value($post_id)
                        ->disabled(!$this->rs->isEditable())
                        ->title(__('Select this event')),
                ]),
            'title' => (new Td())
                ->class('maximal')
                ->items([
                    (new Link())
                        ->href(App::postTypes()->getPostAdminURL(My::POST_TYPE, $post_id))
                        ->text(Html::escapeHTML(trim(Html::clean($this->rs->strField('post_title'))))),
                ]),
            'startdt' => (new Td())
                ->class(['nowrap', 'count', $period])
                ->items([$date('event_startdt')]),
            'enddt' => (new Td())
                ->class(['nowrap', 'count', $period])
                ->items([$date('event_enddt')]),
            'entries' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([$entries]),
            'date' => (new Td())
                ->class(['nowrap', 'count'])
                ->items([$date('post_dt')]),
            'category' => (new Td())
                ->class('nowrap')
                ->items([$category]),
            'author' => (new Td())
                ->class('nowrap')
                ->text(Html::escapeHTML($this->rs->strField('user_id'))),
            'address' => (new Td())
                ->class('nowrap')
                ->text(Html::escapeHTML($this->rs->strField('event_address'))),
            'status' => (new Td())
                ->class(['nowrap', 'status'])
                ->items([
                    (new Set())
                        ->separator(' ')
                        ->items($status),
                ]),
        ]);

        // --BEHAVIOR-- adminEventHandlerEventsListBody -- MetaRecord, ArrayObject
        App::behavior()->callBehavior('adminEventHandlerEventsListBody', $this->rs, $cols);

        $this->userColumns(My::id(), $cols, true);

        return (new Tr())
            ->id('p' . $post_id)
            ->class($classes)
            ->items(iterator_to_array($cols));
    }
}
