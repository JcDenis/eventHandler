<?php
/**
 * @file
 * @brief       The plugin eventHandler definition
 * @ingroup     eventHandler
 *
 * @defgroup    eventHandler Plugin eventHandler.
 *
 * Manage events on your blog.
 *
 * @author      Jean-Christian Denis (author)
 * @author      Nicolas Roudaire
 * @copyright   GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

if (!isset($this) || !is_object($this) || !method_exists($this, 'registerModule') || !isset($this->id) || !is_string($this->id)) {
    return;
}

$this->registerModule(
    'EventHandler',
    'Manage events on your blog',
    'Jean-Christian Denis, Nicolas Roudaire',
    '2023.12.29',
    [
        'requires'    => [['core', '2.39']],
        'permissions' => 'My',
        'type'        => 'plugin',
        'support'     => 'https://github.com/JcDenis/' . $this->id . '/issues',
        'details'     => 'https://github.com/JcDenis/' . $this->id . '/',
        'repository'  => 'https://raw.githubusercontent.com/JcDenis/' . $this->id . '/master/dcstore.xml',
        'date'        => '2026-10-29T00:00:00+00:00',
    ]
);
