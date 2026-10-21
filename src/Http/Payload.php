<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the JSON body of a request and validates it field by field.
 *
 * The RealWorld specification distinguishes between a field that is absent and
 * a field that is present but empty: `{"article": {"title": ""}}` has to be
 * rejected while `{"article": {"description": "new"}}` must leave the
 * description untouched. A DTO cannot express that difference, so the payload
 * is validated against the raw body instead.
 *
 * Every method returns `null` when the value is missing or when it could not
 * be read; the collected errors tell the two cases apart.
 */
final class Payload
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * Reads the section of the body holding the resource, e.g. `user`,
     * `article` or `comment`.
     */
    public static function fromRequest(Request $request, string $section): self
    {
        $decoded = json_decode($request->getContent(), true);
        if (!is_array($decoded)) {
            return new self([]);
        }

        $data = $decoded[$section] ?? [];

        return new self(is_array($data) ? $data : []);
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    public function addError(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    /**
     * Tells whether the key is present in the body, whatever its value is.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * A required string. Blank and missing values are both rejected.
     */
    public function requiredString(string $key): string
    {
        $value = $this->rawString($key);

        if (null === $value || '' === trim($value)) {
            $this->addError($key, "can't be blank");

            return '';
        }

        return trim($value);
    }

    /**
     * A required text. Blank and missing values are both rejected, but the
     * value itself is kept verbatim.
     */
    public function requiredText(string $key): string
    {
        $value = $this->rawString($key);

        if (null === $value || '' === trim($value)) {
            $this->addError($key, "can't be blank");

            return '';
        }

        return $value;
    }

    /**
     * A required email address.
     */
    public function requiredEmail(string $key): string
    {
        $email = $this->requiredString($key);

        if ('' === $email) {
            return '';
        }

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($key, 'is invalid');

            return '';
        }

        return $email;
    }

    /**
     * An optional string that may not be blank when it is provided.
     *
     * An absent key returns `null` and means "leave the current value alone".
     */
    public function optionalString(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->rawString($key);

        if (null === $value || '' === trim($value)) {
            $this->addError($key, "can't be blank");

            return null;
        }

        return trim($value);
    }

    /**
     * An optional text that may not be blank when it is provided.
     *
     * An absent key returns `null` and means "leave the current value alone".
     */
    public function optionalText(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->rawString($key);

        if (null === $value || '' === trim($value)) {
            $this->addError($key, "can't be blank");

            return null;
        }

        return $value;
    }

    /**
     * An optional email address that may not be blank when it is provided.
     */
    public function optionalEmail(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $email = $this->optionalString($key);

        if (null === $email) {
            return null;
        }

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($key, 'is invalid');

            return null;
        }

        return $email;
    }

    /**
     * An optional nullable string. An absent key returns `null` and means
     * "leave the current value alone"; an empty value is normalised to `null`.
     */
    public function optionalNullableString(string $key): ?string
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->data[$key];

        if (null === $value) {
            return null;
        }

        if (!is_string($value)) {
            $this->addError($key, 'is invalid');

            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * An optional list of strings. An absent key returns `null` and means
     * "leave the current list alone".
     *
     * @return list<string>|null
     */
    public function optionalStringList(string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        $value = $this->data[$key];

        if (!is_array($value)) {
            $this->addError($key, "can't be blank");

            return null;
        }

        $values = [];
        foreach ($value as $item) {
            if (!is_string($item) || '' === trim($item)) {
                $this->addError($key, 'is invalid');

                return null;
            }

            $values[] = trim($item);
        }

        return $values;
    }

    private function rawString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
