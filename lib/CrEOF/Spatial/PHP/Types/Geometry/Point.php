<?php
/**
 * Copyright (C) 2012, 2014 Derek J. Lambert
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace CrEOF\Spatial\PHP\Types\Geometry;

use CrEOF\Spatial\Exception\InvalidValueException;

/**
 * Point object for POINT geometry type
 *
 * @author     Derek J. Lambert <dlambert@dereklambert.com>
 * @license    http://dlambert.mit-license.org MIT
 * @deprecated Geometry classes have been replaced by creof/geo
 */
class Point extends \CrEOF\Geo\Point implements GeometryInterface
{
    /**
     * @deprecated Geometry classes have been replaced by creof/geo
     */
    public function __construct()
    {
        $argv = func_get_args();
        $argv = $this->getConstructorArgs($argv);

        try {
            call_user_func_array('parent::__construct', $argv);
        } catch (\Exception $e) {
            throw new InvalidValueException($e->getMessage());
        }
    }

    /**
     * @return string
     *
     * @deprecated Geometry classes have been replaced by creof/geo
     */
    public function getType()
    {
        return self::POINT;
    }

    /**
     * Deal with constructor craziness
     *
     * @param array $argv
     *
     * @return array
     * @throws InvalidValueException
     */
    private function getConstructorArgs(array $argv)
    {
        $value = null;
        $srid  = null;
        $argc  = count($argv);

        // Called with (array $value)
        if (1 === $argc && is_array($argv[0])) {
            return array($argv[0]);
        }

        if (2 === $argc) {
            // Called with (array $value, $srid = null)
            if (is_array($argv[0]) && (is_numeric($argv[1]) || is_null($argv[1]))) {
                return array($argv[0], $argv[1]);
            }

            // Called with ($x, $y)
            if ((is_string($argv[0]) || is_numeric($argv[0])) && (is_string($argv[1]) || is_numeric($argv[1]))) {
                return array(array($argv[0], $argv[1]));
            }
        }

        if (3 === $argc) {
            // Called with ($x, $y, $srid = null)
            if ((is_string($argv[0]) || is_numeric($argv[0])) && (is_string($argv[1]) || is_numeric($argv[1])) && (is_numeric($argv[2]) || is_null($argv[2]))) {
                return array(array($argv[0], $argv[1]), $argv[2]);
            }
        }

        throw InvalidValueException::invalidParameters(get_class($this), '__construct', $argv);
    }
}
