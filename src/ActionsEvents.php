<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Page;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       eventHandler events list actions.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ActionsEvents extends ActionsPosts
{
    /**
     * Bind events to entries action.
     *
     * @var     string  BIND_EVENT_ACTION
     */
    final public const BIND_EVENT_ACTION = 'eventhandler_bind_event';

    /**
     * Unbind entries from all their events action.
     *
     * @var     string  UNBIND_POST_ACTION
     */
    final public const UNBIND_POST_ACTION = 'eventhandler_unbind_post';

    /**
     * Unbind events from all their entries action.
     *
     * @var     string  UNBIND_EVENT_ACTION
     */
    final public const UNBIND_EVENT_ACTION = 'eventhandler_unbind_event';

    protected bool $use_render = true;

    /**
     * @param   null|string             $uri            The form uri
     * @param   array<string, string>   $redirect_args  The redirection arguments
     */
    public function __construct(?string $uri, array $redirect_args = [])
    {
        parent::__construct($uri, $redirect_args);

        $this->redirect_fields = [
            'user_id', 'cat_id', 'status', 'selected', 'month', 'lang', 'period', 'from_id', 'sortby', 'order', 'page', 'nb',
        ];
        $this->caller_title = __('Events');
    }

    public function loadDefaults(): void
    {
        // We could have added a behavior here, but we want default action to be setup first
        ActionsEventsDefault::adminEventsActionsPage($this);

        # --BEHAVIOR-- adminActionsEvents -- ActionsEvents
        App::behavior()->callBehavior('adminActionsEvents', $this);
    }

    public function beginPage(string $breadcrumb = '', string $head = ''): void
    {
        Page::openModule(
            My::name(),
            Page::jsLoad('js/_posts_actions.js') .
            BackendBehaviors::adminCss() .
            $head
        );
        echo
        $breadcrumb .
        (new Para())->items([
            (new Link())
                ->class('back')
                ->href($this->getRedirection(true))
                ->text(__('Back to events list')),
        ])->render();
    }

    public function endPage(): void
    {
        Page::closeModule();
    }

    public function error(Exception $e): void
    {
        App::error()->add($e->getMessage());
        $this->beginPage(
            Page::breadcrumb([
                Html::escapeHTML(App::blog()->name()) => '',
                $this->getCallerTitle()               => $this->getRedirection(true),
                __('Events actions')                  => '',
            ])
        );
        $this->endPage();
    }

    public function process()
    {
        $this->from['post_type'] = My::POST_TYPE;

        return parent::process();
    }
}
