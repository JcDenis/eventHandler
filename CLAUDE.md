# eventHandler

Notes for AI assistants working on the Dotclear plugin eventHandler.

**Read first** the shared conventions of the author's Dotclear modules:
https://raw.githubusercontent.com/JcDenis/gh2da/master/CONVENTIONS.md
(if a web tool refuses the address, use `curl -s` on it from the shell).
The rules below take precedence for this module.

## Module

Manage events on your blog Events (meetings, shows, ...) are a new type of entries with start date, end date and localization. Entries can be related to events.

Admin page and configuration page. Public side: widgets, URL handlers.

Required Dotclear and PHP versions: see `_define.php`.

## Structure

* `src/ActionsEvents.php`: events list actions.
* `src/ActionsEventsDefault.php`: default actions.
* `src/Backend.php`: admin side: menu entry, favorites, behaviors registration.
* `src/BackendBehaviors.php`: admin behaviors.
* `src/Calendar.php`: calendar.
* `src/Config.php`: configuration page of the module (plugins manager).
* `src/EventHandler.php`: events are entries of type "eventhandler" with extra fields.
* `src/FilterEvents.php`: events list filters.
* `src/Frontend.php`: public side: behaviors, template tags and URL handlers registration.
* `src/FrontendBehaviors.php`: public behaviors.
* `src/Install.php`: install and update, database structure (a change needs a migration here).
* `src/ListingEvents.php`: events list.
* `src/Manage.php`: admin page of the module.
* `src/ManageEvent.php`: admin page (event).
* `src/My.php`: module helpers (`My::id()`, settings, permissions).
* `src/Prepend.php`: loaded on every request: autoload, URL handlers, permission types.
* `src/RestMethods.php`: REST methods.
* `src/RsExtension.php`: events record extension.
* `src/Template.php`: compiled templates only import Dotclear\App.
* `src/Uninstall.php`: cleaners for the plugin Uninstaller.
* `src/UrlHandler.php`: frontend URL handler.
* `src/Widgets.php`: widgets definition and rendering.
* `js/`: `event-public-cal.js`, `event.js`, `post.js`, `settings.js`.
* `default-templates/`: public templates.
* Database table `eventhandler`.
* URL handlers: `eventhandler_single`, `eventhandler_preview`, `eventhandler_list`, `eventhandler_feed`, `eventhandler_pubrest`.
* Widgets: `ehEvents`, `ehEventsOfPost`, `ehPostsOfEvent`, `ehCategories`, `ehMap`, `ehCalendar`.
* Behaviors used: `initWidgets`, `templateCustomSortByAlias`, `adminDashboardFavoritesV2`, `adminColumnsListsV2`, `adminFiltersListsV2`, `adminBeforePostDelete`, `adminPageHTTPHeaderCSP`, `adminPostHeaders`, `adminPostsActions`, `adminPostFormItems`, `adminAfterPostCreate`, `adminAfterPostUpdate`, `publicHeadContent`, `publicBeforeDocumentV2`, `publicEntryBeforeContent`, `publicEntryAfterContent`, `sabaAddPostTypes` (optional plugin saba), `publicBreadcrumb`.

## Pitfalls

* Table structure changes need a migration in `src/Install.php` (version compare), and must work on MySQL, PostgreSQL and SQLite.

## Testing

* Check the admin page, the configuration page, the widgets (admin and public), the public URLs, the admin help, in English and French.
* CI browses the common admin and public pages; extra pages can be listed in `.github/dotclear-test.txt`.
* The user guide `docs/fr/utilisation.md` describes the features: update it, and its screenshots, when the interface changes.
