<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Core\Backend\Menus;
use Dotclear\Core\PostType;
use Dotclear\Helper\Process\TraitProcess;

/**
 * @brief       eventHandler backend class.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Backend
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::BACKEND));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Events post type with backend URLs
        $icons = My::icons();
        App::postTypes()->set(new PostType(
            My::POST_TYPE,
            urldecode(My::manageUrl(['part' => 'event', 'id' => '%d'], '&')),
            App::url()->getURLFor('eventhandler_single', '%s'),
            'Events',
            urldecode(My::manageUrl(['part' => 'events'], '&')),
            $icons[0] ?? '',
            $icons[1] ?? $icons[0] ?? ''
        ));

        // backend sidebar menu icon
        My::addBackendMenuItem(Menus::MENU_BLOG);

        App::behavior()->addBehaviors([
            'initWidgets'               => Widgets::init(...),
            'adminDashboardFavoritesV2' => BackendBehaviors::adminDashboardFavoritesV2(...),
            'adminColumnsListsV2'       => BackendBehaviors::adminColumnsListsV2(...),
            'adminFiltersListsV2'       => BackendBehaviors::adminFiltersListsV2(...),
            'adminBeforePostDelete'     => BackendBehaviors::adminBeforePostDelete(...),
        ]);

        if (My::isActive()) {
            App::behavior()->addBehaviors([
                'adminPageHTTPHeaderCSP' => BackendBehaviors::adminPageHTTPHeaderCSP(...),
                'adminPostHeaders'       => BackendBehaviors::adminPostHeaders(...),
                'adminPostsActions'      => BackendBehaviors::adminPostsActions(...),
                'adminPostFormItems'     => BackendBehaviors::adminPostFormItems(...),
                'adminAfterPostCreate'   => BackendBehaviors::adminAfterPostSave(...),
                'adminAfterPostUpdate'   => BackendBehaviors::adminAfterPostSave(...),
            ]);
        }

        return true;
    }
}
