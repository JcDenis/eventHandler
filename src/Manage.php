<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       eventHandler backend events list.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Manage
{
    use TraitProcess;

    /**
     * Entry to bind events to.
     */
    private static ?MetaRecord $from_post = null;

    /**
     * Events list filters.
     */
    private static ?FilterEvents $filter = null;

    /**
     * Events list.
     */
    private static ?ListingEvents $list = null;

    /**
     * Events list actions.
     */
    private static ?ActionsEvents $actions = null;

    /**
     * An action has been performed.
     */
    private static bool $action_done = false;

    public static function init(): bool
    {
        self::status(My::checkContext(My::MANAGE));

        // call event manage page
        if (($_REQUEST['part'] ?? 'events') === 'event') {
            self::status(ManageEvent::init());
        }

        return self::status();
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // call event manage page
        if (($_REQUEST['part'] ?? 'events') === 'event') {
            return ManageEvent::process();
        }

        // Entry to bind events to
        $from_id = isset($_REQUEST['from_id']) && is_numeric($_REQUEST['from_id']) ? (int) $_REQUEST['from_id'] : 0;
        if ($from_id > 0) {
            $rs = App::blog()->getPosts(['post_id' => $from_id, 'post_type' => '', 'no_content' => true]);
            if ($rs->isEmpty()) {
                App::error()->add(__('No such post ID'));
            } else {
                self::$from_post = $rs;
            }
        }

        // Actions
        $redir = ['part' => 'events'];
        if (self::$from_post instanceof MetaRecord) {
            $redir['from_id'] = (string) self::$from_post->intField('post_id');
        }
        self::$actions = new ActionsEvents(My::manageUrl([], '&'), $redir);
        if (self::$from_post instanceof MetaRecord) {
            self::$actions->addAction(
                [__('Entries') => [__('Bind to entry') => ActionsEvents::BIND_EVENT_ACTION]],
                ActionsEventsDefault::doBindUnbind(...)
            );
        }
        if (self::$actions->process()) {
            self::$action_done = true;

            return true;
        }

        // Filters
        self::$filter = new FilterEvents();
        $params       = self::$filter->params();

        $params['no_content'] = true;
        if (self::$from_post instanceof MetaRecord) {
            // Do not list events already bound to this entry
            $bound = [];
            $rs    = (new EventHandler())->getEventsByPost(['post_id' => self::$from_post->intField('post_id'), 'no_content' => true]);
            while ($rs->fetch()) {
                $bound[] = $rs->intField('post_id');
            }
            if ($bound !== []) {
                $params['exclude_post_id'] = $bound;
            }
        }

        // Events list
        try {
            $eventHandler = new EventHandler();
            self::$list   = new ListingEvents(
                $eventHandler->getEvents($params),
                $eventHandler->getEvents($params, true)->intField(0)
            );
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        // call event manage page
        if (($_REQUEST['part'] ?? 'events') === 'event') {
            ManageEvent::render();

            return;
        }

        // Actions page
        if (self::$action_done && self::$actions instanceof ActionsEvents) {
            self::$actions->render();

            return;
        }

        Page::openModule(
            My::name(),
            (self::$filter instanceof FilterEvents ? self::$filter->js(My::manageUrl(['part' => 'events'], '&')) : '') .
            Page::jsLoad('js/_posts_list.js') .
            BackendBehaviors::adminCss()
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => '',
        ]) .
        Notices::getNotices();

        if (!My::isActive()) {
            echo (new Note())
                ->class('warning')
                ->text(__('Events are disabled on this blog. Enable them from plugin configuration.'))
                ->render();
        }

        echo (new Para())
            ->class('top-add')
            ->items([
                (new Link())
                    ->class(['button', 'add'])
                    ->href(My::manageUrl(['part' => 'event']))
                    ->text(__('New event')),
            ])
            ->render();

        if (self::$from_post instanceof MetaRecord) {
            echo (new Note())
                ->class('info')
                ->text(sprintf(
                    __('Select events to bind to entry "%s".'),
                    (new Link())
                        ->href(App::postTypes()->getPostAdminURL(self::$from_post->strField('post_type'), self::$from_post->intField('post_id')))
                        ->text(Html::escapeHTML(self::$from_post->strField('post_title')))
                        ->render()
                ))
                ->render();
        }

        if (self::$filter instanceof FilterEvents && self::$list instanceof ListingEvents && self::$actions instanceof ActionsEvents) {
            self::$filter->display(
                'admin.plugin.' . My::id(),
                My::parsedHiddenFields(array_filter([
                    'part'    => 'events',
                    'from_id' => self::$from_post instanceof MetaRecord ? (string) self::$from_post->intField('post_id') : '',
                ]))
            );

            $page = is_numeric(self::$filter->value('page')) ? (int) self::$filter->value('page') : 1;
            $nb   = is_numeric(self::$filter->value('nb')) ? (int) self::$filter->value('nb') : 30;

            if (self::$from_post instanceof MetaRecord) {
                $buttons = [
                    (new Hidden(['action'], ActionsEvents::BIND_EVENT_ACTION)),
                    (new Hidden(['from_id'], (string) self::$from_post->intField('post_id'))),
                    (new Submit('do-action', __('Bind selected events'))),
                ];
            } else {
                $buttons = [
                    (new Select('action'))
                        ->items(self::$actions->getCombo())
                        ->label(new Label(__('Selected events action:'), Label::OL_TF)),
                    (new Submit('do-action', __('ok'))),
                ];
            }

            self::$list->display(
                $page,
                $nb,
                My::enclose((new Form('form-entries'))
                    ->method('post')
                    ->action(My::manageUrl(['part' => 'events']))
                    ->fields([
                        (new Text(null, My::ENCLOSE)),
                        (new Div())
                            ->class('two-cols')
                            ->items([
                                (new Para())
                                    ->class(['col', 'checkboxes-helpers']),
                                (new Para())
                                    ->class(['col', 'right', 'form-buttons'])
                                    ->items([
                                        ... $buttons,
                                        App::nonce()->formNonce(),
                                        ... My::hiddenFields(array_filter(self::$filter->values(), is_string(...))),
                                        (new Hidden(['part'], 'events')),
                                    ]),
                            ]),
                    ])
                    ->render()),
                self::$filter->show()
            );
        }

        Page::helpBlock(My::id());
        Page::closeModule();
    }
}
