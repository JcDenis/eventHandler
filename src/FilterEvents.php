<?php

declare(strict_types=1);

namespace Dotclear\Plugin\eventHandler;

use ArrayObject;
use Dotclear\App;
use Dotclear\Core\Backend\Filter\Filters;
use Dotclear\Core\Backend\Filter\FiltersLibrary;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Stack\Filter;
use Exception;

/**
 * @brief       eventHandler events list filters.
 * @ingroup     eventHandler
 *
 * @author      Jean-Christian Denis
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
class FilterEvents extends Filters
{
    public function __construct()
    {
        parent::__construct(My::id());

        $filters = new ArrayObject(array_filter([
            FiltersLibrary::getPageFilter(),
            $this->getPostUserFilter(),
            $this->getPostCategoriesFilter(),
            App::status()->post()->filter(),
            $this->getPeriodFilter(),
            $this->getPostSelectedFilter(),
            $this->getPostMonthFilter(),
            $this->getPostLangFilter(),
        ]));

        # --BEHAVIOR-- adminEventHandlerFilter -- ArrayObject
        App::behavior()->callBehavior('adminEventHandlerFilter', $filters);

        $this->add($filters->getArrayCopy());
    }

    /**
     * Events users select.
     */
    public function getPostUserFilter(): ?Filter
    {
        try {
            $users = App::blog()->getPostsUsers(My::POST_TYPE);
            if ($users->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());

            return null;
        }

        $combo = App::backend()->combos()->getUsersCombo($users);
        App::lexical()->lexicalKeySort($combo, App::lexical()::ADMIN_LOCALE);

        return (new Filter('user_id'))
            ->param()
            ->title(__('Author:'))
            ->options(array_merge(['-' => ''], $combo))
            ->prime(true);
    }

    /**
     * Events categories select.
     */
    public function getPostCategoriesFilter(): ?Filter
    {
        try {
            $categories = App::blog()->getCategories(['post_type' => My::POST_TYPE]);
            if ($categories->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());

            return null;
        }

        $combo = [
            '-'            => '',
            __('(No cat)') => 'NULL',
        ];
        while ($categories->fetch()) {
            $combo[
                str_repeat('&nbsp;', ($categories->intField('level') - 1) * 4) .
                Html::escapeHTML($categories->strField('cat_title')) . ' (' . $categories->intField('nb_post') . ')'
            ] = (string) $categories->intField('cat_id');
        }

        return (new Filter('cat_id'))
            ->param()
            ->title(__('Category:'))
            ->options($combo)
            ->prime(true);
    }

    /**
     * Events period select.
     */
    public function getPeriodFilter(): Filter
    {
        return (new Filter('period'))
            ->param('event_period')
            ->title(__('Period:'))
            ->options(array_merge(['-' => ''], My::periodsCombo(false)))
            ->prime(true);
    }

    /**
     * Events selected select.
     */
    public function getPostSelectedFilter(): Filter
    {
        return (new Filter('selected'))
            ->param('post_selected')
            ->title(__('Selected:'))
            ->options([
                '-'                 => '',
                __('Selected')      => '1',
                __('Not selected')  => '0',
            ]);
    }

    /**
     * Events month select.
     */
    public function getPostMonthFilter(): ?Filter
    {
        try {
            $dates = App::blog()->getDates([
                'type'      => 'month',
                'post_type' => My::POST_TYPE,
            ]);
            if ($dates->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());

            return null;
        }

        return (new Filter('month'))
            ->param('post_month', fn ($f): string => is_array($f) && is_scalar($f[0]) ? substr((string) $f[0], 4, 2) : '')
            ->param('post_year', fn ($f): string => is_array($f) && is_scalar($f[0]) ? substr((string) $f[0], 0, 4) : '')
            ->title(__('Month:'))
            ->options(array_merge(
                ['-' => ''],
                App::backend()->combos()->getDatesCombo($dates)
            ));
    }

    /**
     * Events lang select.
     */
    public function getPostLangFilter(): ?Filter
    {
        try {
            $langs = App::blog()->getLangs(['post_type' => My::POST_TYPE]);
            if ($langs->isEmpty()) {
                return null;
            }
        } catch (Exception $e) {
            App::error()->add($e->getMessage());

            return null;
        }

        $combo = ['-' => ''];
        foreach (App::backend()->combos()->getLangsCombo($langs, false) as $name => $code) {
            if (is_string($code)) {
                $combo[$name] = $code;
            }
        }

        return (new Filter('lang'))
            ->param('post_lang')
            ->title(__('Lang:'))
            ->options($combo);
    }
}
