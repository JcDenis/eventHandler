<?php
/*
 *  -- BEGIN LICENSE BLOCK ----------------------------------
 *
 *  This file is part of eventHandler, a plugin for Dotclear 2.
 *
 *  Copyright(c) 2014-2023 Nicolas Roudaire <nikrou77@gmail.com> https://www.nikrou.net
 *
 *  Copyright (c) 2009-2013 Jean-Christian Denis and contributors
 *  contact@jcdenis.fr https://chez.jcdenis.fr/
 *
 *  Licensed under the GPL version 2.0 license.
 *  A copy of this license is available in LICENSE file or at
 *  http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 *  -- END LICENSE BLOCK ------------------------------------
 */

use Dotclear\App;

App::backend()->resources()->set('help', 'eventHandler-events', __DIR__ . '/help/events.html');
App::backend()->resources()->set('help', 'eventHandler-event', __DIR__ . '/help/event.html');
App::backend()->resources()->set('help', 'eventHandler-config', __DIR__ . '/help/config.html');
