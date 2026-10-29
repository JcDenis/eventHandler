<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Listing\ListingPosts;
use Dotclear\Core\Backend\Notices;
use Dotclear\Core\Backend\Page;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\Date;
use Dotclear\Helper\Html\Form\Capture;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Datetime;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Form;
use Dotclear\Helper\Html\Form\Hidden;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Password;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Span;
use Dotclear\Helper\Html\Form\Submit;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       eventHandler backend event edition.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class ManageEvent
{
    use TraitProcess;

    private static ?MetaRecord $post = null;

    private static int $post_id       = 0;
    private static int $from_id       = 0;
    private static int $cat_id        = 0;
    private static int $post_dt       = 0;
    private static string $post_format     = '';
    private static string $post_password   = '';
    private static string $post_url        = '';
    private static string $post_lang       = '';
    private static string $post_title      = '';
    private static string $post_excerpt    = '';
    private static string $post_excerpt_xhtml = '';
    private static string $post_content       = '';
    private static string $post_content_xhtml = '';
    private static string $post_notes         = '';
    private static int $post_status           = 0;
    private static bool $post_selected        = false;

    private static int $event_startdt    = 0;
    private static int $event_enddt      = 0;
    private static string $event_address = '';
    private static string $event_latitude  = '';
    private static string $event_longitude = '';
    private static int $event_zoom         = 0;

    private static string $page_title = '';
    private static bool $can_edit     = false;
    private static bool $can_publish  = false;
    private static bool $can_delete   = false;
    private static bool $bad_dt       = false;

    private static string $next_link     = '';
    private static string $prev_link     = '';
    private static string $next_headlink = '';
    private static string $prev_headlink = '';

    /**
     * @var     array<\Dotclear\Helper\Html\Form\Option>   $categories_combo
     */
    private static array $categories_combo = [];

    /**
     * @var     array<string, string>   $available_formats
     */
    private static array $available_formats = [];

    /**
     * @var     array<array-key, \Dotclear\Helper\Html\Form\Optgroup|\Dotclear\Helper\Html\Form\Option>   $lang_combo
     */
    private static array $lang_combo = [];

    private static ?ListingPosts $posts_list = null;

    public static function init(): bool
    {
        if (!My::checkContext(My::MANAGE)) {
            return self::status(false);
        }

        $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
        Date::setTZ($user_tz);

        self::$post_format = (string) App::auth()->prefs()->get('interface')->getStr('post_format', false);
        self::$post_lang   = is_string($post_lang = App::auth()->getInfo('user_lang')) ? $post_lang : '';
        self::$post_status = is_numeric($post_status = App::auth()->getInfo('user_post_status')) ? (int) $post_status : 0;
        self::$page_title  = __('New event');

        self::$can_edit = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_USAGE,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());
        self::$can_publish = App::auth()->check(App::auth()->makePermissions([
            App::auth()::PERMISSION_PUBLISH,
            App::auth()::PERMISSION_CONTENT_ADMIN,
        ]), App::blog()->id());

        // If user can't publish
        if (!self::$can_publish) {
            self::$post_status = App::status()->post()::PENDING;
        }

        // Categories combo
        self::$categories_combo = App::backend()->combos()->getCategoriesCombo(App::blog()->getCategories());

        // Formats combo
        $available_formats = ['' => ''];
        foreach (App::formater()->getFormaters() as $formats) {
            foreach ($formats as $format) {
                $available_formats[App::formater()->getFormaterName($format)] = $format;
            }
        }
        self::$available_formats = $available_formats;

        // Languages combo
        self::$lang_combo = App::backend()->combos()->getLangsCombo(
            App::blog()->getLangs([
                'order_by' => 'nb_post',
                'order'    => 'desc',
            ]),
            true,
            true
        );

        // Change an entry into an event
        self::$from_id = isset($_REQUEST['from_id']) && is_numeric($_REQUEST['from_id']) ? (int) $_REQUEST['from_id'] : 0;
        if (self::$from_id > 0) {
            $post = App::blog()->getPosts(['post_id' => self::$from_id, 'post_type' => '']);
            if ($post->isEmpty() || $post->strField('post_type') === My::POST_TYPE) {
                Notices::addErrorNotice(__('This entry does not exist.'));
                My::redirect(['part' => 'events']);
            }
            self::loadPost($post);
            self::$page_title = __('Change entry into event');
        }

        // Get event
        $event_id = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
        if (self::$from_id === 0 && $event_id > 0) {
            $post = (new EventHandler())->getEvents(['post_id' => $event_id]);
            if ($post->isEmpty()) {
                Notices::addErrorNotice(__('This event does not exist.'));
                My::redirect(['part' => 'events']);
            }
            self::loadPost($post);
            self::$page_title = __('Edit event');

            self::$event_startdt   = (int) strtotime($post->strField('event_startdt'));
            self::$event_enddt     = (int) strtotime($post->strField('event_enddt'));
            self::$event_address   = $post->strField('event_address');
            self::$event_latitude  = $post->strField('event_latitude');
            self::$event_longitude = $post->strField('event_longitude');
            self::$event_zoom      = $post->intField('event_zoom');

            // Navigation
            $post_link     = '<a href="' . My::manageUrl(['part' => 'event', 'id' => '%s']) . '" class="%s" title="%s">%s</a>';
            $post_headlink = '<link rel="%s" title="%s" href="' . My::manageUrl(['part' => 'event', 'id' => '%s']) . '">';

            $next_rs = App::blog()->getNextPost($post, 1);
            if ($next_rs instanceof MetaRecord) {
                $title           = Html::escapeHTML(trim(Html::clean($next_rs->strField('post_title'))));
                self::$next_link = sprintf($post_link, $next_rs->intField('post_id'), 'next', $title, __('Next event') . '&nbsp;&#187;');
                self::$next_headlink = sprintf($post_headlink, 'next', $title, $next_rs->intField('post_id'));
            }

            $prev_rs = App::blog()->getNextPost($post, -1);
            if ($prev_rs instanceof MetaRecord) {
                $title           = Html::escapeHTML(trim(Html::clean($prev_rs->strField('post_title'))));
                self::$prev_link = sprintf($post_link, $prev_rs->intField('post_id'), 'prev', $title, '&#171;&nbsp;' . __('Previous event'));
                self::$prev_headlink = sprintf($post_headlink, 'previous', $title, $prev_rs->intField('post_id'));
            }
        }

        return self::status(true);
    }

    /**
     * Load entry fields.
     */
    private static function loadPost(MetaRecord $post): void
    {
        self::$post               = $post;
        self::$post_id            = $post->intField('post_id');
        self::$cat_id             = $post->intField('cat_id');
        self::$post_dt            = (int) strtotime($post->strField('post_dt'));
        self::$post_format        = $post->strField('post_format');
        self::$post_password      = $post->strField('post_password');
        self::$post_url           = $post->strField('post_url');
        self::$post_lang          = $post->strField('post_lang');
        self::$post_title         = $post->strField('post_title');
        self::$post_excerpt       = $post->strField('post_excerpt');
        self::$post_excerpt_xhtml = $post->strField('post_excerpt_xhtml');
        self::$post_content       = $post->strField('post_content');
        self::$post_content_xhtml = $post->strField('post_content_xhtml');
        self::$post_notes         = $post->strField('post_notes');
        self::$post_status        = $post->intField('post_status');
        self::$post_selected      = $post->boolField('post_selected');

        self::$can_edit   = (bool) $post->isEditable();
        self::$can_delete = (bool) $post->isDeletable() && self::$from_id === 0;
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        // Post data helpers
        $_Int = fn (string $name, int $default = 0): int => isset($_POST[$name]) && is_numeric($val = $_POST[$name]) ? (int) $val : $default;
        $_Str = fn (string $name, string $default = ''): string => isset($_POST[$name]) && is_string($val = $_POST[$name]) ? $val : $default;
        $_Dt  = function (string $name): int {
            $value = isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : '';
            if ($value === '') {
                return 0;
            }
            $ts = strtotime($value);
            if ($ts === false || $ts <= 0) {
                self::$bad_dt = true;

                return 0;
            }

            return $ts;
        };

        // Get values
        if ($_POST !== [] && self::$can_edit) {
            self::$post_format  = $_Str('post_format');
            self::$post_excerpt = $_Str('post_excerpt');
            self::$post_content = $_Str('post_content');
            self::$post_title   = $_Str('post_title');
            self::$cat_id       = $_Int('cat_id');

            if (isset($_POST['post_status'])) {
                self::$post_status = $_Int('post_status');
            }

            self::$post_dt         = $_Dt('post_dt');
            self::$post_selected   = !empty($_POST['post_selected']);
            self::$post_lang       = $_Str('post_lang');
            self::$post_password   = $_Str('post_password');
            self::$post_url        = $_Str('post_url');
            self::$post_notes      = $_Str('post_notes');
            self::$event_startdt   = $_Dt('event_startdt');
            self::$event_enddt     = $_Dt('event_enddt');
            self::$event_address   = $_Str('event_address');
            self::$event_latitude  = trim($_Str('event_latitude'));
            self::$event_longitude = trim($_Str('event_longitude'));
            self::$event_zoom      = $_Int('event_zoom');

            if (self::$bad_dt) {
                App::error()->add(__('Invalid date.'));
            }

            [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml
            ] = [
                self::$post_excerpt,
                self::$post_excerpt_xhtml,
                self::$post_content,
                self::$post_content_xhtml,
            ];

            App::blog()->setPostContent(
                self::$post_id,
                self::$post_format,
                self::$post_lang,
                $post_excerpt,
                $post_excerpt_xhtml,
                $post_content,
                $post_content_xhtml
            );

            [
                self::$post_excerpt,
                self::$post_excerpt_xhtml,
                self::$post_content,
                self::$post_content_xhtml
            ] = [
                $post_excerpt, $post_excerpt_xhtml, $post_content, $post_content_xhtml,
            ];
        }

        $eventHandler = new EventHandler();

        // Delete event
        if (!empty($_POST['delete']) && self::$can_delete && self::$post_id > 0) {
            try {
                // --BEHAVIOR-- adminBeforeEventHandlerDelete -- int
                App::behavior()->callBehavior('adminBeforeEventHandlerDelete', self::$post_id);

                $eventHandler->delEvent(self::$post_id);

                Notices::addSuccessNotice(__('Event has been deleted.'));
                My::redirect(['part' => 'events']);
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        // Create or update event
        if (!empty($_POST['save']) && self::$can_edit && !self::$bad_dt) {
            $cur_post = App::blog()->openPostCursor();

            $cur_post->setField('cat_id', self::$cat_id !== 0 ? self::$cat_id : null);
            $cur_post->setField('post_dt', self::$post_dt !== 0 ? date('Y-m-d H:i:00', self::$post_dt) : '');
            $cur_post->setField('post_format', self::$post_format);
            $cur_post->setField('post_password', self::$post_password !== '' ? self::$post_password : null);
            $cur_post->setField('post_lang', self::$post_lang);
            $cur_post->setField('post_title', self::$post_title);
            $cur_post->setField('post_excerpt', self::$post_excerpt);
            $cur_post->setField('post_excerpt_xhtml', self::$post_excerpt_xhtml);
            $cur_post->setField('post_content', self::$post_content);
            $cur_post->setField('post_content_xhtml', self::$post_content_xhtml);
            $cur_post->setField('post_notes', self::$post_notes);
            $cur_post->setField('post_status', self::$post_status);
            $cur_post->setField('post_selected', (int) self::$post_selected);
            if (isset($_POST['post_url'])) {
                $cur_post->setField('post_url', self::$post_url !== '' ? self::$post_url : null);
            }

            $cur_event = $eventHandler->openEventCursor();
            $cur_event->setField('event_startdt', self::$event_startdt !== 0 ? date('Y-m-d H:i:00', self::$event_startdt) : '');
            $cur_event->setField('event_enddt', self::$event_enddt     !== 0 ? date('Y-m-d H:i:00', self::$event_enddt) : '');
            $cur_event->setField('event_address', self::$event_address);
            $cur_event->setField('event_latitude', self::$event_latitude);
            $cur_event->setField('event_longitude', self::$event_longitude);
            $cur_event->setField('event_zoom', self::$event_zoom);

            // Back to UTC in order to keep UTC datetime for creadt/upddt
            Date::setTZ('UTC');

            if (self::$post_id > 0) {
                // Update event (or change entry into event)
                try {
                    // --BEHAVIOR-- adminBeforeEventHandlerUpdate -- Cursor, Cursor, int
                    App::behavior()->callBehavior('adminBeforeEventHandlerUpdate', $cur_post, $cur_event, self::$post_id);

                    if (self::$from_id > 0) {
                        // An event can not be related to events
                        $eventHandler->unbindEvent(self::$post_id);
                    }

                    $eventHandler->updEvent(self::$post_id, $cur_post, $cur_event);

                    // --BEHAVIOR-- adminAfterEventHandlerUpdate -- Cursor, Cursor, int
                    App::behavior()->callBehavior('adminAfterEventHandlerUpdate', $cur_post, $cur_event, self::$post_id);

                    Notices::addSuccessNotice(self::$from_id > 0 ? __('Entry has been changed into event.') : __('Event has been updated.'));
                    My::redirect(['part' => 'event', 'id' => self::$post_id]);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            } else {
                // Create event
                $cur_post->setField('user_id', App::auth()->userID());

                try {
                    // --BEHAVIOR-- adminBeforeEventHandlerCreate -- Cursor, Cursor
                    App::behavior()->callBehavior('adminBeforeEventHandlerCreate', $cur_post, $cur_event);

                    $return_id = $eventHandler->addEvent($cur_post, $cur_event);

                    // --BEHAVIOR-- adminAfterEventHandlerCreate -- Cursor, Cursor, int
                    App::behavior()->callBehavior('adminAfterEventHandlerCreate', $cur_post, $cur_event, $return_id);

                    Notices::addSuccessNotice(__('Event has been created.'));
                    My::redirect(['part' => 'event', 'id' => $return_id]);
                } catch (Exception $e) {
                    App::error()->add($e->getMessage());
                }
            }

            $user_tz = is_string($user_tz = App::auth()->getInfo('user_tz')) ? $user_tz : 'UTC';
            Date::setTZ($user_tz);
        }

        // Related entries
        if (self::$post_id > 0 && self::$from_id === 0) {
            $page = !empty($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
            $nb   = 30;

            try {
                $params = [
                    'event_id'   => self::$post_id,
                    'no_content' => true,
                    'limit'      => [($page - 1) * $nb, $nb],
                ];
                self::$posts_list = new ListingPosts(
                    $eventHandler->getPostsByEvent($params),
                    $eventHandler->getPostsByEvent($params, true)->intField(0)
                );
            } catch (Exception $e) {
                App::error()->add($e->getMessage());
            }
        }

        return true;
    }

    public static function render(): void
    {
        if (!self::status()) {
            return;
        }

        $default_tab = self::$can_edit ? 'edit-entry' : '';
        if (!empty($_GET['tab']) && is_string($_GET['tab'])) {
            $default_tab = Html::escapeHTML($_GET['tab']);
        }

        // HTML conversion
        if (!empty($_GET['xconv'])) {
            self::$post_excerpt = self::$post_excerpt_xhtml;
            self::$post_content = self::$post_content_xhtml;
            self::$post_format  = 'xhtml';

            Notices::addMessageNotice(__('Don\'t forget to validate your HTML conversion by saving your event.'));
        }

        // Editor
        $admin_post_behavior = '';
        $post_editor         = App::auth()->prefs()->get('interface')->get('editor');
        if (is_array($post_editor) && !empty($post_editor[self::$post_format]) && is_string($post_editor[self::$post_format])) {
            # --BEHAVIOR-- adminPostEditor -- string, string, array<int,string>, string
            $admin_post_behavior = App::behavior()->callBehavior(
                'adminPostEditor',
                $post_editor[self::$post_format],
                'event',
                ['#post_excerpt', '#post_content'],
                self::$post_format
            );
        }

        $img_status = self::$post_id > 0 && self::$from_id === 0 ? App::status()->post()->image(self::$post_status)->render() : '';

        Page::openModule(
            self::$page_title . ' - ' . My::name(),
            Page::jsModal() .
            $admin_post_behavior .
            Page::jsJson('post_options', [
                'entryurl_dt'    => false,
                'entryurl_title' => false,
            ]) .
            Page::jsLoad('js/_post.js') .
            My::jsLoad('event') .
            BackendBehaviors::mapHeaders() .
            Page::jsConfirmClose('entry-form') .
            # --BEHAVIOR-- adminEventHandlerHeaders --
            App::behavior()->callBehavior('adminEventHandlerHeaders') .
            Page::jsPageTabs($default_tab) .
            BackendBehaviors::adminCss() .
            self::$next_headlink . "\n" . self::$prev_headlink
        );

        echo
        Page::breadcrumb([
            Html::escapeHTML(App::blog()->name()) => '',
            My::name()                            => My::manageUrl(['part' => 'events']),
            (self::$post_id > 0 && self::$from_id === 0 ? '&ldquo;' . Html::escapeHTML(trim(Html::clean(self::$post_title))) . '&rdquo; ' . $img_status : self::$page_title) => '',
        ]) .
        Notices::getNotices();

        // Links to public page and next/prev events
        if (self::$post instanceof MetaRecord && self::$from_id === 0) {
            if (!App::status()->post()->isRestricted(self::$post->intField('post_status'))) {
                echo (new Para())
                    ->items([
                        (new Link())
                            ->class(['onblog_link', 'outgoing'])
                            ->href(RsExtension::getURL(self::$post))
                            ->title(Html::escapeHTML(trim(Html::clean(self::$post_title))))
                            ->text(__('Go to this event on the site') . ' ' . (new Img('images/outgoing-link.svg'))->alt('')->render()),
                    ])
                    ->render();
            }

            echo (new Para())
                ->class('nav_prevnext')
                ->items([
                    (new Text(null, self::$prev_link)),
                    (new Text(null, self::$next_link)),
                    (new Capture(App::behavior()->callBehavior(...), ['adminEventHandlerNavLinks', self::$post])),
                ])
                ->render();
        }

        if (self::$can_edit) {
            self::renderForm();
        }

        // Related entries
        echo (new Div('bind-entries'))
            ->class('multi-part')
            ->title(__('Related entries'))
            ->items([
                self::$posts_list instanceof ListingPosts ?
                    (new Capture(self::$posts_list->display(...), [
                        !empty($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1,
                        30,
                        '%s',
                        false,
                        true,
                    ])) :
                    (new Note())->text(__('You must save event before adding entries')),
            ])
            ->render();

        # --BEHAVIOR-- adminEventHandlerTab -- MetaRecord|null
        App::behavior()->callBehavior('adminEventHandlerTab', self::$post);

        Page::helpBlock(My::id());
        Page::closeModule();
    }

    /**
     * Render event form.
     */
    private static function renderForm(): void
    {
        $default_zoom = (int) My::settings()->getInt('public_map_zoom', false);

        /**
         * @var ArrayObject<string, array{title: string, items: array<string, string>}> $sidebar_items
         */
        $sidebar_items = new ArrayObject([
            'status-box' => [
                'title' => __('Status'),
                'items' => [
                    'post_status' => (new Para())->class('entry-status')->items([
                        (new Select('post_status'))
                            ->items(App::status()->post()->combo())
                            ->default(self::$post_status)
                            ->disabled(!self::$can_publish)
                            ->label(new Label(__('Event status'), Label::OUTSIDE_LABEL_BEFORE)),
                    ])->render(),
                    'post_dt' => (new Para())->items([
                        (new Datetime('post_dt'))
                            ->value(Html::escapeHTML(Date::str('%Y-%m-%dT%H:%M', self::$post_dt === 0 ? null : self::$post_dt)))
                            ->class(self::$bad_dt ? 'invalid' : [])
                            ->label(new Label(__('Publication date and hour'), Label::OUTSIDE_LABEL_BEFORE)),
                    ])->render(),
                    'post_lang' => (new Para())->items([
                        (new Select('post_lang'))
                            ->items(self::$lang_combo)
                            ->default(self::$post_lang)
                            ->translate(false)
                            ->label(new Label(__('Event language'), Label::OUTSIDE_LABEL_BEFORE)),
                    ])->render(),
                    'post_format' => (new Para())->items([
                        (new Select('post_format'))
                            ->items(self::$available_formats)
                            ->default(self::$post_format)
                            ->label((new Label(__('Text formatting'), Label::OUTSIDE_LABEL_BEFORE))->id('label_format')),
                        (new Span())
                            ->class(['format_control', 'control_no_xhtml'])
                            ->items([
                                (new Link('convert-xhtml'))
                                    ->class(['button', self::$post_id !== 0 && self::$post_format === 'xhtml' ? 'hide' : ''])
                                    ->href(My::manageUrl(array_filter(['part' => 'event', 'id' => self::$from_id === 0 ? self::$post_id : 0, 'from_id' => self::$from_id, 'xconv' => '1'])))
                                    ->text(__('Convert to HTML')),
                            ]),
                    ])->render(),
                ],
            ],
            'metas-box' => [
                'title' => __('Filing'),
                'items' => [
                    'post_selected' => (new Para())->items([
                        (new Checkbox('post_selected', self::$post_selected))
                            ->value(1)
                            ->label(new Label(__('Selected event'), Label::IL_FT)),
                    ])->render(),
                    'cat_id' => (new Div())->items([
                        (new Text('h5', __('Category')))
                            ->id('label_cat_id'),
                        (new Para())->items([
                            (new Select('cat_id'))
                                ->items(self::$categories_combo)
                                ->default(self::$cat_id)
                                ->class('maximal')
                                ->label(new Label(__('Category:'), Label::OL_TF)),
                        ]),
                    ])->render(),
                ],
            ],
            'options-box' => [
                'title' => __('Options'),
                'items' => [
                    'post_password' => (new Para())->items([
                        (new Password('post_password'))
                            ->autocomplete('new-password')
                            ->class('maximal')
                            ->value(Html::escapeHTML(self::$post_password))
                            ->size(10)
                            ->maxlength(32)
                            ->translate(false)
                            ->label(new Label(__('Password'), Label::OUTSIDE_TEXT_BEFORE)),
                    ])->render(),
                    'post_url' => (new Div())->class('lockable')->items([
                        (new Para())->items([
                            (new Input('post_url'))
                                ->class('maximal')
                                ->value(Html::escapeHTML(self::$post_url))
                                ->size(10)
                                ->maxlength(255)
                                ->translate(false)
                                ->label(new Label(__('Edit basename'), Label::OUTSIDE_TEXT_BEFORE)),
                        ]),
                        (new Note())
                            ->class(['form-note', 'warn'])
                            ->text(__('Warning: If you set the URL manually, it may conflict with another entry.')),
                    ])->render(),
                ],
            ],
        ]);

        /**
         * @var ArrayObject<string, string> $main_items
         */
        $main_items = new ArrayObject([
            'event_dates' => (new Div())
                ->class('two-cols')
                ->items([
                    (new Para())->class('col')->items([
                        (new Datetime('event_startdt'))
                            ->value(Html::escapeHTML(self::$event_startdt === 0 ? '' : date('Y-m-d\TH:i', self::$event_startdt)))
                            ->required(true)
                            ->label((new Label((new Span('*'))->render() . __('Start date:'), Label::OUTSIDE_TEXT_BEFORE))->class(['required', 'bold'])),
                    ]),
                    (new Para())->class('col')->items([
                        (new Datetime('event_enddt'))
                            ->value(Html::escapeHTML(self::$event_enddt === 0 ? '' : date('Y-m-d\TH:i', self::$event_enddt)))
                            ->required(true)
                            ->label((new Label((new Span('*'))->render() . __('End date:'), Label::OUTSIDE_TEXT_BEFORE))->class(['required', 'bold'])),
                    ]),
                ])->render(),
            'event_location' => (new Div())->items([
                (new Text('h4', __('Localization')))
                    ->id('event-area-title'),
                (new Div('event-area-content'))->items([
                    (new Para())->items([
                        (new Input('event_address'))
                            ->class('maximal')
                            ->value(Html::escapeHTML(self::$event_address))
                            ->size(10)
                            ->maxlength(255)
                            ->label(new Label(__('Address:'), Label::OUTSIDE_TEXT_BEFORE)),
                    ]),
                    (new Fieldset())
                        ->legend(new Legend(__('Maps')))
                        ->items([
                            (new Note())
                                ->class('form-note')
                                ->text(__('If you want to use maps, you must enter an address as precise as possible (number, street, city, country)')),
                            (new Para())->items([
                                (new Link('event-map-link'))
                                    ->href('#')
                                    ->text(__('Find coordinates from address')),
                            ]),
                            (new Div())->class('three-cols')->items([
                                (new Para())->class('col')->items([
                                    (new Input('event_latitude'))
                                        ->value(Html::escapeHTML(self::$event_latitude))
                                        ->size(20)
                                        ->maxlength(25)
                                        ->label(new Label(__('Latitude:'), Label::OUTSIDE_TEXT_BEFORE)),
                                ]),
                                (new Para())->class('col')->items([
                                    (new Input('event_longitude'))
                                        ->value(Html::escapeHTML(self::$event_longitude))
                                        ->size(20)
                                        ->maxlength(25)
                                        ->label(new Label(__('Longitude:'), Label::OUTSIDE_TEXT_BEFORE)),
                                ]),
                                (new Para())->class('col')->items([
                                    (new Number('event_zoom', 0, 20, self::$event_zoom))
                                        ->label(new Label(__('Zoom for that map:'), Label::OUTSIDE_TEXT_BEFORE)),
                                ]),
                            ]),
                            (new Note())
                                ->class('form-note')
                                ->text(sprintf(__('If empty, defaut zoom (%d) will be used'), $default_zoom)),
                        ]),
                ]),
            ])->render(),
            'post_title' => (new Para())->items([
                (new Input('post_title'))
                    ->value(Html::escapeHTML(self::$post_title))
                    ->size(20)
                    ->maxlength(255)
                    ->required(true)
                    ->class('maximal')
                    ->placeholder(__('Title'))
                    ->lang(self::$post_lang)
                    ->spellcheck(true)
                    ->label((new Label((new Span('*'))->render() . __('Title:'), Label::OUTSIDE_TEXT_BEFORE))->class(['required', 'no-margin', 'bold']))
                    ->title(__('Required field')),
            ])->render(),
            'post_excerpt' => (new Para())->class('area')->id('excerpt-area')->items([
                (new Textarea('post_excerpt'))
                    ->value(Html::escapeHTML(self::$post_excerpt))
                    ->cols(50)
                    ->rows(5)
                    ->lang(self::$post_lang)
                    ->spellcheck(true)
                    ->label((new Label(__('Excerpt:'), Label::OUTSIDE_TEXT_BEFORE))->class('bold')),
            ])->render(),
            'post_content' => (new Para())->class('area')->id('content-area')->items([
                (new Textarea('post_content'))
                    ->value(Html::escapeHTML(self::$post_content))
                    ->cols(50)
                    ->rows((int) App::auth()->prefs()->get('interface')->getInt('edit_size', false))
                    ->required(true)
                    ->lang(self::$post_lang)
                    ->spellcheck(true)
                    ->placeholder(__('Content'))
                    ->label((new Label((new Span('*'))->render() . __('Content:'), Label::OUTSIDE_TEXT_BEFORE))->class(['required', 'bold'])),
            ])->render(),
            'post_notes' => (new Para())->class('area')->id('notes-area')->items([
                (new Textarea('post_notes'))
                    ->value(Html::escapeHTML(self::$post_notes))
                    ->cols(50)
                    ->rows(5)
                    ->lang(self::$post_lang)
                    ->spellcheck(true)
                    ->label((new Label(__('Personal notes:'), Label::OUTSIDE_TEXT_BEFORE))->class('bold')),
            ])->render(),
        ]);

        # --BEHAVIOR-- adminEventHandlerFormItems -- ArrayObject, ArrayObject, MetaRecord|null
        App::behavior()->callBehavior('adminEventHandlerFormItems', $main_items, $sidebar_items, self::$post);

        // Sidebar
        $side_part = '';
        foreach ($sidebar_items as $id => $c) {
            $side_part .= (new Div($id))
                ->class('sb-box')
                ->items([
                    (new Text('h4', $c['title'])),
                    (new Text(null, implode('', $c['items']))),
                ])
                ->render();
        }

        // Buttons
        $buttons = [
            (new Submit(['save'], __('Save') . ' (s)'))
                ->accesskey('s'),
        ];
        if (self::$post instanceof MetaRecord && self::$post_id > 0 && self::$from_id === 0) {
            $preview_url = App::blog()->url() .
                App::url()->getURLFor(
                    'eventhandler_preview',
                    App::auth()->userID() . '/' .
                    Http::browserUID(App::config()->masterKey() . App::auth()->userID() . App::auth()->cryptLegacy((string) App::auth()->userID())) .
                    '/' . self::$post->strField('post_url')
                );
            $preview_url .= (parse_url($preview_url, PHP_URL_QUERY) ? '&' : '?') . 'rand=' . md5((string) random_int(0, mt_getrandmax()));

            $blank_preview = App::auth()->prefs()->get('interface')->getBool('blank_preview', false);

            $buttons[] = (new Link('post-preview'))
                ->href($preview_url)
                ->extra($blank_preview ? 'target="_blank"' : '')
                ->class(['button', $blank_preview ? '' : 'modal'])
                ->accesskey('p')
                ->text(__('Preview event') . ' (p)');
        } else {
            $buttons[] = (new Link('post-cancel'))
                ->href(self::$from_id > 0 ? App::postTypes()->getPostAdminURL(self::$post instanceof MetaRecord ? self::$post->strField('post_type') : 'post', self::$from_id) : My::manageUrl(['part' => 'events']))
                ->class('button')
                ->accesskey('c')
                ->text(__('Cancel') . ' (c)');
        }
        if (self::$can_delete) {
            $buttons[] = (new Submit(['delete'], __('Delete')))
                ->class('delete');
        }
        if (self::$from_id > 0) {
            $buttons[] = (new Hidden(['from_id'], (string) self::$from_id));
        } elseif (self::$post_id > 0) {
            $buttons[] = (new Hidden(['id'], (string) self::$post_id));
        }

        echo (new Div('edit-entry'))
            ->class('multi-part')
            ->title(self::$post_id > 0 && self::$from_id === 0 ? __('Edit event') : self::$page_title)
            ->items([
                (new Form('entry-form'))
                    ->method('post')
                    ->action(My::manageUrl(['part' => 'event']))
                    ->fields([
                        (new Div('entry-wrapper'))
                            ->items([
                                (new Div('entry-content'))
                                    ->items([
                                        (new Div())
                                            ->class('constrained')
                                            ->items([
                                                (new Text('h3', __('Edit event')))
                                                    ->class('out-of-screen-if-js'),
                                                (new Note())
                                                    ->class('form-note')
                                                    ->text(sprintf(__('Fields preceded by %s are mandatory.'), (new Span('*'))->class('required')->render())),
                                                (new Text(null, implode('', iterator_to_array($main_items)))),
                                                (new Para())
                                                    ->class(['border-top', 'form-buttons'])
                                                    ->items([
                                                        App::nonce()->formNonce(),
                                                        ... $buttons,
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        (new Div('entry-sidebar'))
                            ->role('complementary')
                            ->items([
                                (new Text(null, $side_part)),
                            ]),
                    ]),
                self::$from_id > 0 ?
                    (new Note())
                        ->class('warning')
                        ->text(__('Once saved, this entry will become an event and will no longer be listed with entries.')) :
                    (new None()),
            ])
            ->render();
    }
}
