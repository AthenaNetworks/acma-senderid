<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Support;

use BackedEnum;
use DateTimeInterface;
use JsonSerializable;

/**
 * Base class for request DTOs: serialises to the API's PascalCase JSON shape,
 * omitting null values so that partial (PATCH) updates only send what was set.
 */
abstract class Payload implements JsonSerializable
{
    /**
     * @return array<string, mixed>
     */
    abstract protected function fields(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return self::normalise($this->fields());
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<array-key, mixed> $fields
     * @return array<array-key, mixed>
     */
    private static function normalise(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $value = match (true) {
                $value instanceof self => $value->toArray(),
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
                is_array($value) => self::normalise($value),
                default => $value,
            };

            if ($value === null || (is_array($value) && $value === [] && is_string($key))) {
                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
