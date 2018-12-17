<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// A file backed cache. Make sure the script has write permissions to the directory containing the file.
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
 * A file backed cache. Make sure the script has write permissions to the directory containing the file.
 *
 * @author  Vassilis Poursalidis (poursal@gmail.com)
 * @package Enceeper
 */
final class FileCache extends AbstractCache
{
    /**
     * @var string
     */
    private $filename;

    /**
     * File based cache
     *
     * @param  string   $filename
     * @param  int      $ttl
     * @param  Enceeper $enceeper
     * @param  int      $strategy
     * @return void
     */
    public function __construct(string $filename, int $ttl, Enceeper $enceeper, int $strategy = self::STRATEGY_BATCH_MODE)
    {
        $this->filename = $filename;

        parent::__construct($ttl, $enceeper, $strategy);
    }

    /**
     * Set the contents of the cache to the provided value
     *
     * @param  string   $value
     * @return array
     *
     * @throws \RuntimeException
     */
    protected function setCache(string $value) : array
    {
        if ( @file_put_contents($this->filename ."~", $value) !== FALSE ) {
            if ( @rename($this->filename ."~", $this->filename) ) {
                $array = json_decode($value, true);

                if ( $array!==NULL ) {
                    return $array;
                }
            }
        }

        @unlink($this->filename ."~");

        throw new \RuntimeException('Failed to set the cache contents');
    }

    /**
     * Get the contents of the cache or an empty array on failure (nothing in cache)
     *
     * @return array
     */
    protected function getCached() : array
    {
        $resp = [];
        $data = @file_get_contents($this->filename);

        if ( $data!==FALSE ) {
            $array = json_decode($data, true);

            if ( $array!==NULL ) {
                $resp = $array;
            }
        }

        return $resp;
    }

    /**
     * Return the last time we updated the cache contents
     *
     * @return int
     */
    protected function lastUpdate() : int
    {
        $modified = @filemtime($this->filename);

        return ($modified===FALSE)?0:$modified;
    }
}
