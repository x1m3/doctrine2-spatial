<?php
/**
 * Copyright (C) 2014 Derek J. Lambert
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

namespace CrEOF\Spatial;

use CrEOF\Spatial\DBAL\Types\BinaryParser;
use CrEOF\Spatial\DBAL\Types\StringParser;
use CrEOF\Spatial\Exception\InvalidValueException;
use CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Default ValueFactory implementing CrEOF PHP types
 *
 * @author  Derek J. Lambert <dlambert@dereklambert.com>
 * @license http://dlambert.mit-license.org MIT
 */
class ValueFactory implements ValueFactoryInterface
{
    /**
     * @param mixed            $value
     * @param AbstractPlatform $platform
     * @param string           $typeFamily
     *
     * @return GeometryInterface
     * @throws InvalidValueException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform, $typeFamily)
    {
        if (null === $value) {
            return null;
        }

        if (ctype_alpha($value[0])) {
            $parser = new StringParser($value);
        } else {
            $parser = new BinaryParser($value);
        }

        $value     = $parser->parse();
        $constName = 'CrEOF\Spatial\PHP\Types\Geometry\GeometryInterface::' . strtoupper($value['type']);

        if ( ! defined($constName)) {
            throw InvalidValueException::unsupportedType($typeFamily, strtoupper($value['type']));
        }

        $class = sprintf('CrEOF\Spatial\PHP\Types\%s\%s', $typeFamily, constant($constName));

        return new $class($value['value'], $value['srid']);
    }
}
