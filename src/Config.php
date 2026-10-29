<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use Dotclear\App;
use Dotclear\Core\Backend\ModulesList;
use Dotclear\Core\Backend\Notices;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Input;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Link;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Select;
use Dotclear\Helper\Html\Form\Table;
use Dotclear\Helper\Html\Form\Tbody;
use Dotclear\Helper\Html\Form\Td;
use Dotclear\Helper\Html\Form\Text;
use Dotclear\Helper\Html\Form\Textarea;
use Dotclear\Helper\Html\Form\Th;
use Dotclear\Helper\Html\Form\Thead;
use Dotclear\Helper\Html\Form\Tr;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Process\TraitProcess;
use Exception;

/**
 * @brief       eventHandler configuration class.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class Config
{
    use TraitProcess;

    public static function init(): bool
    {
        return self::status(My::checkContext(My::CONFIG));
    }

    /**
     * Content places combo.
     *
     * @return  array<string, string>
     */
    private static function placeCombo(): array
    {
        return [
            __('hide')           => '',
            __('before content') => 'before',
            __('after content')  => 'after',
        ];
    }

    /**
     * Events list sort combo.
     *
     * @return  array<string, string>
     */
    private static function listSortbyCombo(): array
    {
        return [
            '-'                    => '',
            __('Post title')       => 'post:title',
            __('Post selected')    => 'post:selected',
            __('Post author')      => 'post:author',
            __('Post date')        => 'post:date',
            __('Post id')          => 'post:id',
            __('Event start date') => 'eventhandler:startdt',
            __('Event end date')   => 'eventhandler:enddt',
        ];
    }

    /**
     * Events list order combo.
     *
     * @return  array<string, string>
     */
    private static function listOrderCombo(): array
    {
        return [
            '-'               => '',
            __('Ascending')   => 'asc',
            __('Descending')  => 'desc',
        ];
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        if (empty($_POST['save'])) {
            return true;
        }

        $str  = fn (string $name): string => isset($_POST[My::id() . $name]) && is_string($_POST[My::id() . $name]) ? $_POST[My::id() . $name] : '';
        $pick = fn (string $name, array $combo, string $default): string => in_array($str($name), $combo, true) ? $str($name) : $default;

        try {
            $s          = My::settings();
            $was_active = My::isActive();

            $s->put('active', !empty($_POST[My::id() . 'active']), 'boolean');

            // Other settings are displayed only if plugin is active
            if ($was_active) {
                $hidden = [];
                if (isset($_POST[My::id() . 'public_hidden_categories']) && is_array($_POST[My::id() . 'public_hidden_categories'])) {
                    $hidden = array_values(array_map(intval(...), array_filter($_POST[My::id() . 'public_hidden_categories'], is_numeric(...))));
                }
                $zoom = (int) $str('public_map_zoom');

                $s->put('public_posts_of_event_place', $pick('public_posts_of_event_place', self::placeCombo(), ''), 'string');
                $s->put('public_events_of_post_place', $pick('public_events_of_post_place', self::placeCombo(), ''), 'string');
                $s->put('public_events_list_sortby', $pick('public_events_list_sortby', self::listSortbyCombo(), ''), 'string');
                $s->put('public_events_list_order', $pick('public_events_list_order', self::listOrderCombo(), ''), 'string');
                $s->put('public_hidden_categories', $hidden, 'array');
                $s->put('public_map_zoom', $zoom > 2 && $zoom < 21 ? $zoom : 9, 'integer');
                $s->put('public_map_type', $pick('public_map_type', My::mapTypeCombo(), 'ROADMAP'), 'string');
                $s->put('public_extra_css', $str('public_extra_css'), 'string');
                $s->put('map_provider', $pick('map_provider', My::mapProviderCombo(), 'osm'), 'string');
                $s->put('map_tile_layer', $str('map_tile_layer'), 'string');
                $s->put('map_api_key', $str('map_api_key'), 'string');

                // --BEHAVIOR-- adminEventHandlerSettingsSave --
                App::behavior()->callBehavior('adminEventHandlerSettingsSave');
            }

            App::blog()->triggerBlog();

            Notices::addSuccessNotice(__('Configuration has been successfully updated.'));
            App::backend()->url()->redirect('admin.plugins', [
                'module' => My::id(),
                'conf'   => '1',
                'redir'  => !(App::backend()->__get('list') instanceof ModulesList) ? '' : App::backend()->__get('list')->getRedir(),
            ]);
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

        $s = My::settings();

        echo (new Fieldset())
            ->legend(new Legend(__('Activation')))
            ->items([
                (new Para())->items([
                    (new Checkbox(My::id() . 'active', My::isActive()))
                        ->value(1)
                        ->label(new Label(__('Enable plugin'), Label::IL_FT)),
                ]),
                My::isActive() ?
                    (new Para())->items([
                        (new Link())
                            ->class(['onblog_link', 'outgoing'])
                            ->href(App::blog()->url() . App::url()->getBase('eventhandler_list'))
                            ->text(__('View the public list of events') . ' <img alt="" src="images/outgoing-link.svg">'),
                    ]) :
                    (new None()),
            ])
            ->render();

        if (!My::isActive()) {
            echo (new Note())
                ->class('info')
                ->text(__('Enable plugin to configure it.'))
                ->render();

            return;
        }

        // Categories
        $rows = [];

        try {
            $hidden     = My::hiddenCategories();
            $categories = App::blog()->getCategories(['post_type' => 'post']);
            while ($categories->fetch()) {
                $cat_id    = $categories->intField('cat_id');
                $nb_events = App::blog()->getPosts(['cat_id' => $cat_id, 'post_type' => My::POST_TYPE], true)->intField(0);
                $nb_posts  = $categories->intField('nb_post');

                $rows[] = (new Tr())
                    ->class('line')
                    ->items([
                        (new Td())->class('minimal')->items([
                            (new Checkbox([My::id() . 'public_hidden_categories[]', My::id() . 'public_hidden_categories_' . $cat_id], in_array($cat_id, $hidden, true)))
                                ->value($cat_id),
                        ]),
                        (new Td())->class('nowrap')->items([
                            (new Label(
                                str_repeat('&nbsp;&nbsp;', max(0, $categories->intField('level') - 1)) . Html::escapeHTML($categories->strField('cat_title')),
                                Label::OL_FT
                            ))
                                ->for(My::id() . 'public_hidden_categories_' . $cat_id)
                                ->class('classic'),
                        ]),
                        (new Td())->class(['nowrap', 'count'])->items([
                            $nb_posts > 0 ?
                                (new Link())
                                    ->href(App::backend()->url()->get('admin.posts', ['cat_id' => $cat_id]))
                                    ->title(__('List of entries related to this category'))
                                    ->text((string) $nb_posts) :
                                (new Text(null, '0')),
                        ]),
                        (new Td())->class(['nowrap', 'count'])->items([
                            $nb_events > 0 ?
                                (new Link())
                                    ->href(My::manageUrl(['part' => 'events', 'cat_id' => $cat_id]))
                                    ->title(__('List of events related to this category'))
                                    ->text((string) $nb_events) :
                                (new Text(null, '0')),
                        ]),
                    ]);
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        echo
        (new Fieldset())
            ->legend(new Legend(__('Contents')))
            ->items([
                (new Para())->items([
                    (new Select(My::id() . 'public_posts_of_event_place'))
                        ->items(self::placeCombo())
                        ->default((string) $s->getStr('public_posts_of_event_place', false))
                        ->label(new Label(__('Show related entries on event:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Select(My::id() . 'public_events_of_post_place'))
                        ->items(self::placeCombo())
                        ->default((string) $s->getStr('public_events_of_post_place', false))
                        ->label(new Label(__('Show related events on entry:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Select(My::id() . 'public_events_list_sortby'))
                        ->items(self::listSortbyCombo())
                        ->default((string) $s->getStr('public_events_list_sortby', false))
                        ->label(new Label(__('Default field for ordering events list:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Select(My::id() . 'public_events_list_order'))
                        ->items(self::listOrderCombo())
                        ->default((string) $s->getStr('public_events_list_order', false))
                        ->label(new Label(__('Default order for events list:'), Label::OL_TF)),
                ]),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Maps')))
            ->items([
                (new Para())->items([
                    (new Select(My::id() . 'map_provider'))
                        ->items(My::mapProviderCombo())
                        ->default(My::mapProvider())
                        ->label(new Label(__('Map provider:'), Label::OL_TF)),
                ]),
                (new Para())->class('map-api-key')->items([
                    (new Input(My::id() . 'map_api_key'))
                        ->size(60)
                        ->maxlength(255)
                        ->value(Html::escapeHTML((string) $s->getStr('map_api_key', false)))
                        ->label(new Label(__('API Key:'), Label::OL_TF)),
                ]),
                (new Note())->class(['form-note', 'map-api-key'])->text(
                    __('URL to create API Key:') . ' <a href="https://console.developers.google.com/">https://console.developers.google.com/</a>'
                ),
                (new Para())->class('map-tile-layer')->items([
                    (new Input(My::id() . 'map_tile_layer'))
                        ->size(60)
                        ->maxlength(255)
                        ->value(Html::escapeHTML((string) $s->getStr('map_tile_layer', false)))
                        ->label(new Label(__('Map tile layer:'), Label::OL_TF)),
                ]),
                (new Note())->class(['form-note', 'map-tile-layer'])->text(__('Default map tile layer for OpenStreetMap.')),
                (new Para())->items([
                    (new Select(My::id() . 'public_map_zoom'))
                        ->items(My::mapZoomCombo())
                        ->default((string) $s->getInt('public_map_zoom', false))
                        ->label(new Label(__('Default zoom on map:'), Label::OL_TF)),
                ]),
                (new Para())->items([
                    (new Select(My::id() . 'public_map_type'))
                        ->items(My::mapTypeCombo())
                        ->default((string) $s->getStr('public_map_type', false))
                        ->label(new Label(__('Default type of map:'), Label::OL_TF)),
                ]),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Categories')))
            ->items([
                (new Note())
                    ->class('form-note')
                    ->text(__('When an event has an hidden category, it will only display on its category page.')),
                $rows === [] ?
                    (new Note())->text(__('No category')) :
                    (new Table())
                        ->class('clear')
                        ->items([
                            (new Thead())->rows([
                                (new Tr())->items([
                                    (new Th())->text(__('Hide')),
                                    (new Th())->text(__('Category')),
                                    (new Th())->text(__('Entries')),
                                    (new Th())->text(__('Events')),
                                ]),
                            ]),
                            (new Tbody())->rows($rows),
                        ]),
            ])
            ->render() .
        (new Fieldset())
            ->legend(new Legend(__('Additionnal style sheet:')))
            ->items([
                (new Para())->items([
                    (new Textarea(My::id() . 'public_extra_css'))
                        ->cols(164)
                        ->rows(10)
                        ->class('maximal')
                        ->value(Html::escapeHTML((string) $s->getStr('public_extra_css', false))),
                ]),
            ])
            ->render() .
        My::jsLoad('settings');

        /* Add a adminEventHandlerSettings behavior handler to add custom settings
         * and add a adminEventHandlerSettingsSave behavior handler to save your custom settings.
         */
        # --BEHAVIOR-- adminEventHandlerSettings --
        App::behavior()->callBehavior('adminEventHandlerSettings');
    }
}
