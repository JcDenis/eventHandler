eventHandler 2026.10.29
===========================================================
* Require Dotclear 2.39
* Require PHP 8.1
* Use Dotclear 2.39 API (TraitProcess, Form components, typed records)
* Rewrite backend pages (events list, event edition, configuration)
* Fix fatal error on Dotclear 2.39 (backend unavailable)
* Fix events queries on SQLite
* Fix event deletion permission
* Fix change of an entry into an event
* Fix events preview
* Fix bind events to entries from entry page
* Fix template tags (EventsIf period, EventOfPostIf orderedby, PostOfEventDate, EventsPeriod)
* Fix default templates path
* Fix calendar first day of week and REST service URL
* Fix iCalendar and hCalendar documents
* Hidden categories now apply to events list
* Clean entries relations when an event is deleted
* Use JSON for public password cookie
* Use OpenStreetMap as default map provider for new installs
* Add plugin Uninstaller support
* Replace behaviors adminEventHandlerForm and adminEventHandlerFormSidebar by adminEventHandlerFormItems
* Code review (phpstan max)

eventHandler 2023.12.29
===========================================================
* Fix pagination in events list. Thanks to [end4urip](https://github.com/end4urip) for report.
* Map must be displayed only when gps coordinates are available.

eventHandler 2023.12.25
===========================================================
* Fix all days page ; event one. Close #53. Thanks to [end4urip](https://github.com/end4urip)

eventHandler 2023.12.23
===========================================================
* Fix all days page. Close #43.
* Use json for public rest method

eventHandler 2023.12.22
===========================================================
* Adapt plugin for dotclear 2.28

eventHandler 2023.10.28
===========================================================
* Fix missing translations. Close #52. Thanks to [Franck](https://github.com/franck-tomek) for report.

eventHandler 2023.10.07
===========================================================
* Adapt plugin for dotclear 2.27

eventHandler 2023.03-14
===========================================================
* Fix attribute name in mustek template set.

eventHandler 2023.02-04
===========================================================
* Fix incorrect js file insertion in admin

eventHandler 2023.02-04
===========================================================
* Fix missing attribute. Closes #50
* Fix issue for non super admin user. Closes #51

eventHandler 2023.01-27
===========================================================
* Fix plugin upgrade problem
* Fix navigation of calendar widget
* Fix sorting of events in post page. Fix #45

eventHandler 2023.01-22
===========================================================
* Fix installation problem. Thanks to Grégory.
* Remove currywurst templates

eventHandler 2022.12-26
===========================================================
* Fix sort parameter in tpl:EventsOfPost. Closes #45

eventHandler 2022.04-09
===========================================================
* Fix issue with datetime field.
  Thanks to [Franck](https://github.com/franck-tomek) for report.

eventHandler 2022.02-13-2
===========================================================
* Fix issue in \_install.php because of incorrect length for field.
  Thanks to [Franck](https://github.com/franck-tomek) for report.
  Add SVG icons, thanks to [Franck](https://github.com/franck-paul).

eventHandler 2022.02-13
===========================================================
* Fix issue in dcBlog::getPosts because of join query.
  Thanks to [Franck](https://github.com/franck-tomek) for report. Thanks to [Franck](https://github.com/franck-paul) for the way to fix the issue.

eventHandler 2021.02-08
===========================================================
* Add templates for dotty tpl set.

eventHandler 2019.04-05
===========================================================
* Fix issue creating archive.

eventHandler 2019.03.26
===========================================================
* Issue : style.css color are not suitable for admin darkmode.
  Sets an alternative admin css (dark-style.css) when darkmode is enabled
  - adminEventHandler::adminCss() returns css link according to darkmode user setting
  - all hardcoded references to css/style.css replaced

eventHandler 2019-03-07
===========================================================
* Fix issue #42. Archibve plugin name must be prefix by plugin-
  Thanks to Franck @franck-tomek for report.

eventHandler 2019-02-24
===========================================================
* Fix issue #41. Allow sql_only params for getEvents like in core for getPosts()
  Thanks to @onurb-taktile for report

eventHandler 2019-02-22
===========================================================
* Fix issue #40. Navigation in widget of events works only for first click.
  Thanks to @onurb-taktile for report

eventHandler 2018-01-25
===========================================================
* Fix issue #38. entry content is now wrap in #entry-wrapper
* Add new template tag (EventsCount) to display special message when there's no events

eventHandler 2017-06-15
===========================================================
* Fix issue #37. The same protocol (https) must be used. Thanks to @franck-tomek for report.

eventHandler 2016-11-10
===========================================================
* Fix issue with CSP (for maps)
* Fix issue #35 (CSP for tags).

eventHandler 2016-11-07
===========================================================
* Add API Key to allow request to googlemaps API.

eventHandler 2016-09-08
===========================================================
* Avoid lock in db transaction because of SQLite. Thanks to @scolin
* Fix a bug cause by the lack of TIMESTAMP function in sqlite. Thanks to @scolin.
* Fixes #7 : deals with CSP policy. Add related csp header. Thanks to @franck-tomek for report.

eventHandler 2015-08-05
===========================================================
* Fix issue with preview

eventHandler 2015-08-04
===========================================================
* Allow admin to choose betwween googleMaps and OpenStreetMap to display maps.
* Update icons
* Update translations.

eventHandler 2015-07-27
===========================================================
* Fix #28. Thanks for Tomek and ReBoLyF

eventHandler 2015-05-31
===========================================================
* Update translation. Thanks to @franck-tomek.
* Fix issue with link on calendar widget. Thanks to Onurb Teva <dev@taktile.fr>.

eventHandler 2015-04-22 - 2015-04-22
===========================================================
Fixes #23 - Lost automatic address completion from GMaps when entering partial address.
Thanks to @franck-tomek for reporting issue.

eventHandler 2015-03-28 - 2015-03-28
===========================================================
* Fix breadcrumb and message info
* Fix widget names "EventHandler: widgetname" + subtitle
* Add content_only, class, and offline mode for widgets
* Fix localization and misspelling
* Add html5 switch using mustek/currywurst default templates
* Add options to \_define
  Thanks to Pierre Van Glabeke
* Split some classes in their own file
* Fix misspelling, thanks to Onurb Teva <dev@taktile.fr>.

eventHandler 2015-03-22 - 2015-03-22
===========================================================
* Remove deprecated behaviors (adminPostsActionsCombo, adminPostsActionsHeaders, adminPostsActionsContent)
* Add severals behaviors to customize admin pages.
* And many others fixes.
* A big thanks to Onurb Teva <dev@taktile.fr>.

eventHandler 2014-12-17 - 2014-12-17
===========================================================
* Fix issue for bulk actions (authors and categories).
  Thanks to vdanjean for reporting issue.

eventHandler 2014-12-08 - 2014-12-08
===========================================================
* Fix misspeling in template. Thanks to @franck-tomek.

eventHandler 2014-12-02 - 2014-12-02
===========================================================
* Add more translations
* Prepare for new dotclear release (editor)

eventHandler 2014-12-01 - 2014-12-01
===========================================================
* Fix javascript issue preventing editor to be displayed.
* Add some translations.

## Previous history

2013.07.07

* Added options to widgets (closes #693)
* Added dashboard icon
* Fixed admin pages titles and messages
* Fixed map on Ductile theme's widget
* Fixed typo

  1.0-RC4 20110102

* Removed priority (useless with new version of plugin kUtRL)

  1.0-RC3 20100918

* Fixed bug on arrayObject

  1.0-RC2 20100915

* Fixed some bugs and typo

  1.0-RC1 20100903

* First lab release

  1.0-beta 20100724

* First public beta release
