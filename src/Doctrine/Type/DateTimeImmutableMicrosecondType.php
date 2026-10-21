<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * A date and a time that keeps its microseconds.
 *
 * The stock `datetime_immutable` type of Doctrine DBAL writes the seconds
 * only, which is not enough here: the specification expects two revisions of
 * an article to always be distinguishable, so the timestamps are stored with
 * a microsecond precision.
 */
final class DateTimeImmutableMicrosecondType extends DateTimeImmutableType
{
    public const NAME = 'datetime_immutable_microsecond';

    private const FORMAT = 'Y-m-d H:i:s.u';

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof \DateTimeImmutable) {
            return $value->format(self::FORMAT);
        }

        throw InvalidType::new($value, self::NAME, ['null', \DateTimeImmutable::class]);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        $dateTime = \DateTimeImmutable::createFromFormat(self::FORMAT, $value);

        if (false !== $dateTime) {
            return $dateTime;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw InvalidFormat::new($value, self::FORMAT, $e);
        }
    }
}
