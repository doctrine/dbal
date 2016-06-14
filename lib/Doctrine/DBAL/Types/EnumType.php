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

namespace Doctrine\DBAL\Types;

use Doctrine\Common\Enum\Factory as EnumFactory;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * Type that maps an SQL Enum to a PHP Enum object.
 *
 * @since 2.6
 */
class EnumType extends Type
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return Type::ENUM;
    }

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        if (!is_array($fieldDeclaration['values'])) {
            $fieldDeclaration['values'] = EnumFactory::getValues($fieldDeclaration['values']);
        }

        return $platform->getEnumTypeDeclarationSQL($fieldDeclaration);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (null === $value) {
            return $value;
        }

        try {
            return EnumFactory::getValue($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform, array $fieldDeclaration = null)
    {
        if ($value === null || !is_scalar($value)) {
            return $value;
        }

        if ($fieldDeclaration === null) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }

        if (is_array($fieldDeclaration['values'])) {
            if (!in_array($value, $fieldDeclaration['values'])) {
                throw ConversionException::conversionFailed($value, $this->getName());
            }

            return $value;
        }

        try {
            return EnumFactory::createByValue($fieldDeclaration['values'], $value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        } catch (\UnexpectedValueException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    }
}
