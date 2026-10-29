<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Action\ActionsPostsDefault;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       eventHandler default actions.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ActionsEventsDefault
{
    /**
     * Add default actions to events list.
     */
    public static function adminEventsActionsPage(ActionsEvents $ap): void
    {
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Status') => App::status()->post()->action()],
                ActionsPostsDefault::doChangePostStatus(...)
            );
        }

        $ap->addAction(
            [__('Mark') => [
                __('Mark as selected')   => 'selected',
                __('Mark as unselected') => 'unselected',
            ]],
            ActionsPostsDefault::doUpdateSelectedPost(...)
        );

        $ap->addAction(
            [__('Change') => [
                __('Change category') => 'category',
            ]],
            ActionsPostsDefault::doChangePostCategory(...)
        );

        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Change') => [
                    __('Change author') => 'author',
                ]],
                ActionsPostsDefault::doChangePostAuthor(...)
            );
        }

        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_DELETE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Delete') => [
                    __('Delete') => 'delete',
                ]],
                ActionsPostsDefault::doDeletePost(...)
            );
        }

        $ap->addAction(
            [__('Entries') => [
                __('Unbind related entries') => ActionsEvents::UNBIND_EVENT_ACTION,
            ]],
            self::doBindUnbind(...)
        );
    }

    /**
     * Get selected IDs as integers.
     *
     * @return  array<int, int>
     */
    protected static function getIntIDs(ActionsPosts $ap): array
    {
        return array_values(array_filter(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ap->getIDs())));
    }

    /**
     * Bind and unbind entries and events.
     *
     * Used on entries list (selected IDs are entries) and on events list (selected IDs are events).
     *
     * @param   ActionsPosts                    $ap     The actions instance
     * @param   ArrayObject<string, mixed>      $post   The POST values
     */
    public static function doBindUnbind(ActionsPosts $ap, ArrayObject $post): void
    {
        $eventHandler = new EventHandler();
        $ids          = self::getIntIDs($ap);
        if ($ids === []) {
            throw new Exception($ap instanceof ActionsEvents ? __('No event selected') : __('No entry selected'));
        }

        switch ($ap->getAction()) {
            // Unbind selected entries from all their events
            case ActionsEvents::UNBIND_POST_ACTION:
                foreach ($ids as $post_id) {
                    $eventHandler->unbindEvent($post_id);
                }
                Notices::addSuccessNotice(sprintf(
                    __('%d entry has been unbound from its events', '%d entries have been unbound from their events', count($ids)),
                    count($ids)
                ));
                $ap->redirect(true);

                // Unbind all entries from selected events
            case ActionsEvents::UNBIND_EVENT_ACTION:
                $count = 0;
                foreach ($ids as $event_id) {
                    $count += $eventHandler->unbindPostsOfEvent($event_id);
                }
                Notices::addSuccessNotice(sprintf(
                    __('%d related entry has been unbound', '%d related entries have been unbound', $count),
                    $count
                ));
                $ap->redirect(true);

                // Bind entries and events
            case ActionsEvents::BIND_EVENT_ACTION:
                $from_id = isset($post['from_id']) && is_numeric($post['from_id']) ? (int) $post['from_id'] : 0;

                // From events list: bind selected events to one entry
                if ($ap instanceof ActionsEvents && $from_id > 0) {
                    $entry = App::blog()->getPosts(['post_id' => $from_id, 'post_type' => '', 'no_content' => true]);
                    if ($entry->isEmpty()) {
                        throw new Exception(__('No such entry ID'));
                    }
                    foreach ($ids as $event_id) {
                        $eventHandler->bindEvent($from_id, $event_id);
                    }
                    Notices::addSuccessNotice(sprintf(
                        __('Entry has been bound to %d event', 'Entry has been bound to %d events', count($ids)),
                        count($ids)
                    ));
                    App::backend()->url()->redirect('admin.post', ['id' => $from_id]);
                }

                // From entries list, events selected: bind selected entries to events
                $events_ids = [];
                if (isset($post['events']) && is_array($post['events'])) {
                    $events_ids = array_values(array_filter(array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $post['events'])));
                }
                if ($events_ids !== []) {
                    $events = $eventHandler->getEvents(['post_id' => $events_ids, 'no_content' => true]);
                    if ($events->isEmpty()) {
                        throw new Exception(__('No such event'));
                    }
                    $events_ids = [];
                    while ($events->fetch()) {
                        $events_ids[] = $events->intField('post_id');
                    }
                    foreach ($ids as $post_id) {
                        foreach ($events_ids as $event_id) {
                            $eventHandler->bindEvent($post_id, $event_id);
                        }
                    }
                    Notices::addSuccessNotice(sprintf(
                        __('%d entry has been bound %s', '%d entries have been bound %s', count($ids)),
                        count($ids),
                        sprintf(__('to the selected event', 'to the %d selected events', count($events_ids)), count($events_ids))
                    ));
                    $ap->redirect(true);
                }

                // From entries list, no event selected: select events
                self::selectEventsPage($ap);

                break;
        }
    }

    /**
     * Display events selection page for entries list.
     */
    protected static function selectEventsPage(ActionsPosts $ap): void
    {
        $eventHandler = new EventHandler();
        $params       = [
            'no_content'   => true,
            'order'        => 'event_startdt DESC',
            'event_period' => 'notfinished',
        ];

        // --BEHAVIOR-- adminEventHandlerListCustomize -- array
        App::behavior()->callBehavior('adminEventHandlerListCustomize', ['params' => &$params]);

        $list = new ListingEvents($eventHandler->getEvents($params), $eventHandler->getEvents($params, true)->intField(0));
        $list->setEntriesNames('events');

        $ap->beginPage(
            Page::breadcrumb([
                Html::escapeHTML(App::blog()->name())    => '',
                $ap->getCallerTitle()                    => $ap->getRedirection(true),
                __('Select events to link to entries') => '',
            ]),
            BackendBehaviors::adminCss()
        );

        echo
        (new Text('h3', __('Select events to link to entries')))->render() .
        (new Note())
            ->class('info')
            ->text(sprintf(__('%d entry selected', '%d entries selected', count($ap->getIDs())), count($ap->getIDs())))
            ->render();

        $list->display(
            1,
            1000,
            My::enclose((new Form('eventhandler-bind'))
                ->method('post')
                ->action((string) $ap->getURI())
                ->fields([
                    (new Text(null, My::ENCLOSE)),
                    (new Para())
                        ->items([
                            App::nonce()->formNonce(),
                            ... $ap->hiddenFields(true),
                            (new Hidden(['action'], ActionsEvents::BIND_EVENT_ACTION)),
                            (new Submit('eventhandler-bind-save', __('Save'))),
                        ]),
                ])
                ->render())
        );

        $ap->endPage();
    }
}
