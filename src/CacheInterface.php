<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// The cache interface that all cache implementation must obey
//
// Copyright (C) 2018 Vassilis Poursalidis (poursal@gmail.com)
//
// This program is free software: you can redistribute it and/or modify it under the terms of the
// GNU General Public License as published by the Free Software Foundation, either version 3 of the
// License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
// even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// General Public License for more details.
//
// You should have received a copy of the GNU General Public License along with this program. If
// not, see <https://www.gnu.org/licenses/>.
//

declare(strict_types=1);

namespace Enceeper;

/**
 * The cache interface that all cache implementation must obey
 *
 * @author  Vassilis Poursalidis (poursal@gmail.com)
 * @package Enceeper
 */
interface CacheInterface
{
    /**
     * The array with the configuration data (either from cache or via Enceeper)
     *
     * @return array
     */
    public function get() : array;

    /**
     * Fetch a new copy of the configuration data from Enceeper and cache it. If you select the
     * batch mode strategy you must call this method manually (i.e. via crontab).
     *
     * @return array
     */
    public function update() : array;
}
