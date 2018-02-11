<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

/**
 * Conversion Exception is thrown when the database to PHP conversion fails.
 *
 * @link   www.doctrine-project.org
 */
namespace Doctrine\DBAL\Types;

use Doctrine\DBAL\DBALException;
use function get_class;
use function gettype;
use function implode;
use function is_object;
use function is_scalar;
use function sprintf;
use function strlen;
use function substr;

class ConversionException extends DBALException
{
    /**
     * Thrown when a Database to Doctrine Type Conversion fails.
     *
     * @param string $value
     * @param string $toType
     *
     * @return \Doctrine\DBAL\Types\ConversionException
     */
    public static function conversionFailed($value, $toType)
    {
        $value = (strlen($value) > 32) ? substr($value, 0, 20) . '...' : $value;

        return new self('Could not convert database value "' . $value . '" to Doctrine Type ' . $toType);
    }

    /**
     * Thrown when a Database to Doctrine Type Conversion fails and we can make a statement
     * about the expected format.
     *
     * @param string $value
     * @param string $toType
     * @param string $expectedFormat
     *
     * @return \Doctrine\DBAL\Types\ConversionException
     */
    public static function conversionFailedFormat($value, $toType, $expectedFormat, ?\Throwable $previous = null)
    {
        $value = (strlen($value) > 32) ? substr($value, 0, 20) . '...' : $value;

        return new self(
            'Could not convert database value "' . $value . '" to Doctrine Type ' .
            $toType . '. Expected format: ' . $expectedFormat,
            0,
            $previous
        );
    }

    /**
     * Thrown when the PHP value passed to the converter was not of the expected type.
     *
     * @param mixed    $value
     * @param string   $toType
     * @param string[] $possibleTypes
     *
     * @return \Doctrine\DBAL\Types\ConversionException
     */
    public static function conversionFailedInvalidType($value, $toType, array $possibleTypes)
    {
        $actualType = is_object($value) ? get_class($value) : gettype($value);

        if (is_scalar($value)) {
            return new self(sprintf(
                "Could not convert PHP value '%s' of type '%s' to type '%s'. Expected one of the following types: %s",
                $value,
                $actualType,
                $toType,
                implode(', ', $possibleTypes)
            ));
        }

        return new self(sprintf(
            "Could not convert PHP value of type '%s' to type '%s'. Expected one of the following types: %s",
            $actualType,
            $toType,
            implode(', ', $possibleTypes)
        ));
    }

    public static function conversionFailedSerialization($value, $format, $error)
    {
        $actualType = is_object($value) ? get_class($value) : gettype($value);

        return new self(sprintf(
            "Could not convert PHP type '%s' to '%s', as an '%s' error was triggered by the serialization",
            $actualType,
            $format,
            $error
        ));
    }
}
