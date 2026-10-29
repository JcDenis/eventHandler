<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Action\ActionsPosts;
use Dotclear\Core\Backend\Favorites;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\Cursor;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Li;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Ul;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * @brief       eventHandler backend behaviors.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class BackendBehaviors
{
    /**
     * Dashboard favorite.
     */
    public static function adminDashboardFavoritesV2(Favorites $favs): void
    {
        $favs->register(My::id(), [
            'title'       => My::name(),
            'url'         => My::manageUrl([], '&'),
            'small-icon'  => My::icons(),
            'large-icon'  => My::icons(),
            'permissions' => App::auth()->makePermissions([
                App::auth()::PERMISSION_USAGE,
                App::auth()::PERMISSION_CONTENT_ADMIN,
            ]),
            'dashboard_cb' => function (ArrayObject $icon): void {
                $count = App::blog()->getPosts(['post_type' => My::POST_TYPE], true)->intField(0);
                if ($count > 0) {
                    $icon['title'] = sprintf(__('One event', '%d events', $count), $count);
                }
            },
        ]);
    }

    /**
     * Events lists columns.
     *
     * @param   ArrayObject<string, mixed>  $cols   The columns
     */
    public static function adminColumnsListsV2(ArrayObject $cols): void
    {
        $cols[My::id()] = [
            My::name(),
            [
                'startdt'  => [true, __('Start date')],
                'enddt'    => [true, __('End date')],
                'entries'  => [true, __('Entries')],
                'date'     => [true, __('Date')],
                'category' => [true, __('Category')],
                'author'   => [true, __('Author')],
                'address'  => [false, __('Localization')],
            ],
        ];
    }

    /**
     * Events lists sort options.
     *
     * @param   ArrayObject<string, mixed>  $sorts  The sort options
     */
    public static function adminFiltersListsV2(ArrayObject $sorts): void
    {
        $sorts[My::id()] = [
            My::name(),
            My::sortbyCombo(),
            'event_startdt',
            'desc',
            [__('events per page'), 30],
        ];
    }

    /**
     * Content Security Policy for map providers.
     *
     * @param   ArrayObject<string, string>     $csp    The CSP directives
     */
    public static function adminPageHTTPHeaderCSP(ArrayObject $csp): void
    {
        $directives = My::mapProvider() === 'googlemaps' ?
            [
                'img-src'     => '*.google.com *.gstatic.com *.googleapis.com',
                'script-src'  => 'maps.google.com maps.googleapis.com csi.gstatic.com',
                'connect-src' => 'maps.google.com maps.googleapis.com',
            ] :
            [
                'img-src'     => '*.tile.openstreetmap.org tile.openstreetmap.org',
                'connect-src' => 'nominatim.openstreetmap.org',
            ];

        foreach ($directives as $directive => $hosts) {
            $csp[$directive] = trim(($csp[$directive] ?? $csp['default-src'] ?? "'self'") . ' ' . $hosts);
        }
    }

    /**
     * Entry edition page headers.
     */
    public static function adminPostHeaders(): string
    {
        return self::adminCss() . My::jsLoad('post');
    }

    /**
     * Backend style sheet.
     */
    public static function adminCss(): string
    {
        return My::cssLoad(App::auth()->prefs()->get('interface')->getInt('darkmode') === 1 ? 'dark-style' : 'style');
    }

    /**
     * Entries list actions (bind and unbind events).
     */
    public static function adminPostsActions(ActionsPosts $ap): void
    {
        if (App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id())) {
            $ap->addAction(
                [__('Events') => [
                    __('Bind events')   => ActionsEvents::BIND_EVENT_ACTION,
                    __('Unbind events') => ActionsEvents::UNBIND_POST_ACTION,
                ]],
                ActionsEventsDefault::doBindUnbind(...)
            );
        }
    }

    /**
     * Events box on entry edition page.
     *
     * @param   ArrayObject<string, mixed>  $main_items     The main items
     * @param   ArrayObject<string, mixed>  $sidebar_items  The sidebar items
     * @param   null|MetaRecord             $post           The entry
     * @param   string                      $type           The post type
     */
    public static function adminPostFormItems(ArrayObject $main_items, ArrayObject $sidebar_items, ?MetaRecord $post = null, string $type = 'post'): void
    {
        if (!$post instanceof MetaRecord
            || $post->strField('post_type') === My::POST_TYPE
            || !isset($sidebar_items['metas-box'])
            || !is_array($sidebar_items['metas-box'])
            || !isset($sidebar_items['metas-box']['items'])
            || !is_array($sidebar_items['metas-box']['items'])
        ) {
            return;
        }

        $post_id = $post->intField('post_id');
        $events  = null;

        try {
            $events = (new EventHandler())->getEventsByPost([
                'post_id'    => $post_id,
                'no_content' => true,
                'order'      => 'event_startdt ASC',
            ]);
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        $items = [];
        if ($events instanceof MetaRecord) {
            while ($events->fetch()) {
                $items[] = (new Li())
                    ->class(['event-node', 'event-node-' . RsExtension::getPeriod($events)])
                    ->items([
                        (new Checkbox(['eventhandler_events[]']))
                            ->value($events->intField('post_id'))
                            ->class('event-node-value')
                            ->label((new Label(Html::escapeHTML($events->strField('post_title')), Label::IL_FT))
                                ->title(__('Check to unbind'))),
                    ]);
            }
        }

        $can_change = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());

        $sidebar_items['metas-box']['items'][My::id()] = (new Div())
            ->items([
                (new Text('h5', __('Events')))
                    ->id('eventhandler-form-title'),
                (new Div('eventhandler-form-content'))
                    ->items([
                        $items === [] ? (new None()) : (new Ul())->class('event-list')->items($items),
                        (new Para())
                            ->items([
                                (new Link())
                                    ->href(My::manageUrl(['part' => 'events', 'from_id' => $post_id]))
                                    ->text(__('Bind events')),
                            ]),
                        $can_change ?
                            (new Para())
                                ->items([
                                    (new Link())
                                        ->href(My::manageUrl(['part' => 'event', 'from_id' => $post_id]))
                                        ->title(__('Change this entry into an event'))
                                        ->text(__('Change into event')),
                                ]) :
                            (new None()),
                    ]),
            ])
            ->render();
    }

    /**
     * Delete relation between entry and checked related event (without javascript).
     *
     * @param   Cursor  $cur        The cursor
     * @param   int     $post_id    The entry ID
     */
    public static function adminAfterPostSave(Cursor $cur, int $post_id): void
    {
        $events = $_POST['eventhandler_events'] ?? [];
        if ($post_id <= 0 || !is_array($events) || $events === []) {
            return;
        }

        $eventHandler = new EventHandler();
        foreach ($events as $event_id) {
            if (is_numeric($event_id)) {
                try {
                    $eventHandler->unbindEvent($post_id, (int) $event_id);
                } catch (Exception) {
                }
            }
        }
    }

    /**
     * Clean relations on entry or event deletion.
     *
     * @param   int     $post_id    The entry ID
     */
    public static function adminBeforePostDelete(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        try {
            $eventHandler = new EventHandler();
            // Entry related to events
            $eventHandler->unbindEvent($post_id);
            // Event related to entries
            if ($eventHandler->hasEventRow($post_id)) {
                $eventHandler->cleanEvent($post_id);
            }
        } catch (Exception) {
        }
    }

    /**
     * Load map provider script.
     */
    public static function mapHeaders(): string
    {
        $provider = My::mapProvider();
        $key      = (string) My::settings()->getStr('map_api_key', false);

        return $provider === 'googlemaps' ?
            ($key !== '' ? '<script async defer src="https://maps.google.com/maps/api/js?key=' . Html::escapeURL($key) . '"></script>' . "\n" : '') .
            Page::jsLoad(My::fileURL('js/googlemaps/event-admin-map.js')) :
            Page::jsLoad(My::fileURL('js/osm/event-admin-map.js'));
    }
}
