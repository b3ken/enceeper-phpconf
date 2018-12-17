<?php

//
// SPDX-License-Identifier: GPL-3.0-or-later
//
// The full scrypt implementation. This code has the following limitations:
// 1. It runs very slow (about 20 secs for N=32768)
// 2. It requires memory so you will need to edit php.ini (about 400MB for N=32768)
// 3. It requires PHP 64-bit, since PHP does not support the unsinged int type
//
// In general it is best to go with a native implementation of scrypt (https://github.com/enceeper/scrypt)
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

/**
* Code taken from http://bitwiseshiftleft.github.io/sjcl/
* and transformed to work with PHP.
*/

declare(strict_types=1);

namespace Enceeper;

class Scrypt
{
    public static function scrypt(string $password, string $salt, int $N = 32768, int $r = 8, int $p = 1, int $length = 0, string $hash = 'sha512') : string
    {
        $SIZE_MAX = pow(2, 32) - 1;

        if ($r * $p >= pow(2, 30)) {
            throw new Exception("The parameters r, p must satisfy r * p < 2^30");
        }

        if (($N < 2) || ($N & ($N - 1) != 0)) {
            throw new Exception("The parameter N must be a power of 2.");
        }

        if ($N > $SIZE_MAX / 128 / $r) {
            throw new Exception("N too big.");
        }

        if ($r > $SIZE_MAX / 128 / $p) {
            throw new Exception("r too big.");
        }

        $blocks = hash_pbkdf2($hash, $password, $salt, 1, $p * 128 * $r, true);

        $blocks = self::binstringtoarray($blocks);
        $len = count($blocks) / $p;

        self::reverse($blocks);

        for ($i = 0; $i < $p; $i++) {
            $block = array_slice($blocks, $i * $len, ($i + 1) * $len);
            self::blockcopy(self::ROMix($block, $N), 0, $blocks, $i * $len);
        }

        self::reverse($blocks);
        $blocks = self::arraytobinstring($blocks);

        return hash_pbkdf2($hash, $password, $blocks, 1, $length);
    }

    private static function salsa20Core(array &$word, int $rounds) : void
    {
        $R = function($a, $b) {
            $in = $a & 0xFFFFFFFF;
            return ((($in << $b) | ($in >> (32 - $b))) & 0xFFFFFFFF);
        };
        $x = $word;

        for ($i = $rounds; $i > 0; $i -= 2) {
            $x[ 4] ^= $R($x[ 0]+$x[12], 7);  $x[ 8] ^= $R($x[ 4]+$x[ 0], 9);
            $x[12] ^= $R($x[ 8]+$x[ 4],13);  $x[ 0] ^= $R($x[12]+$x[ 8],18);
            $x[ 9] ^= $R($x[ 5]+$x[ 1], 7);  $x[13] ^= $R($x[ 9]+$x[ 5], 9);
            $x[ 1] ^= $R($x[13]+$x[ 9],13);  $x[ 5] ^= $R($x[ 1]+$x[13],18);
            $x[14] ^= $R($x[10]+$x[ 6], 7);  $x[ 2] ^= $R($x[14]+$x[10], 9);
            $x[ 6] ^= $R($x[ 2]+$x[14],13);  $x[10] ^= $R($x[ 6]+$x[ 2],18);
            $x[ 3] ^= $R($x[15]+$x[11], 7);  $x[ 7] ^= $R($x[ 3]+$x[15], 9);
            $x[11] ^= $R($x[ 7]+$x[ 3],13);  $x[15] ^= $R($x[11]+$x[ 7],18);
            $x[ 1] ^= $R($x[ 0]+$x[ 3], 7);  $x[ 2] ^= $R($x[ 1]+$x[ 0], 9);
            $x[ 3] ^= $R($x[ 2]+$x[ 1],13);  $x[ 0] ^= $R($x[ 3]+$x[ 2],18);
            $x[ 6] ^= $R($x[ 5]+$x[ 4], 7);  $x[ 7] ^= $R($x[ 6]+$x[ 5], 9);
            $x[ 4] ^= $R($x[ 7]+$x[ 6],13);  $x[ 5] ^= $R($x[ 4]+$x[ 7],18);
            $x[11] ^= $R($x[10]+$x[ 9], 7);  $x[ 8] ^= $R($x[11]+$x[10], 9);
            $x[ 9] ^= $R($x[ 8]+$x[11],13);  $x[10] ^= $R($x[ 9]+$x[ 8],18);
            $x[12] ^= $R($x[15]+$x[14], 7);  $x[13] ^= $R($x[12]+$x[15], 9);
            $x[14] ^= $R($x[13]+$x[12],13);  $x[15] ^= $R($x[14]+$x[13],18);
        }

        for ($i = 0; $i < 16; $i++) $word[$i] = $x[$i]+$word[$i];

        // Remove upper 32 bits
        for ($i = 0; $i < 16; $i++) {
            $word[$i] = $word[$i] & 0xFFFFFFFF;
        }
    }

    private static function blockMix(array $blocks) : array
    {
        $X = array_slice($blocks, -16);
        $out = [];
        $len = count($blocks) / 16;

        for ($i = 0; $i < $len; $i++) {
            self::blockxor($blocks, 16 * $i, $X, 0, 16);
            self::salsa20Core($X, 8);

            if (($i & 1) == 0) {
                self::blockcopy($X, 0, $out, 8 * $i);
            } else {
                self::blockcopy($X, 0, $out, 8 * ($i^1 + $len));
            }
        }

        return $out;
    }

    private static function ROMix(array $block, int $N) : array
    {
        $X = $block;
        $V = [];

        for ($i = 0; $i < $N; $i++) {
            array_push($V, $X);
            $X = self::blockMix($X);
        }

        for ($i = 0; $i < $N; $i++) {
            $j = $X[count($X) - 16] & ($N - 1);

            self::blockxor($V[$j], 0, $X, 0, count($V[$j]));
            $X = self::blockMix($X);
        }

        //echo "=>". memory_get_usage() ."\n";
        return $X;
    }

    // Converts Big <-> Little Endian words
    private static function reverse(array &$words) : void
    {
        for ($i = 0; $i < count($words); $i++) {
            $out = $words[$i] &  0xFF;
            $out = ($out << 8) | ($words[$i] >>  8) & 0xFF;
            $out = ($out << 8) | ($words[$i] >> 16) & 0xFF;
            $out = ($out << 8) | ($words[$i] >> 24) & 0xFF;

            $words[$i] = $out;
        }
    }

    // Copy from source to destination array
    private static function blockcopy(array $S, int $Si, array &$D, int $Di) : void
    {
        $len = count($S) - $Si;

        for ($i = 0; $i < $len; $i++) $D[$Di + $i] = $S[$Si + $i] | 0;
    }

    // Xor source and destination array
    private static function blockxor(array $S, int $Si, array &$D, int $Di, int $len) : void
    {
        for ($i = 0; $i < $len; $i++) $D[$Di + $i] = ($D[$Di + $i] ^ $S[$Si + $i]) | 0;
    }

    // Convert binary string to int 64 array
    private static function binstringtoarray(string $binary_string) : array
    {
        $blocks = str_split($binary_string, 4);
        for($i=0; $i<count($blocks); $i++) {
            $blocks[$i] = hexdec(bin2hex($blocks[$i]));
        }
        return $blocks;
    }

    // Convert int 64 array to binary string
    private static function arraytobinstring(array $blocks) : string
    {
        for($i=0; $i<count($blocks); $i++) {
            $hex = dechex($blocks[$i]);
            $hex = str_pad($hex, 8, '0', STR_PAD_LEFT);
            $blocks[$i] = hex2bin($hex);
        }
        return implode('', $blocks);
    }
}
