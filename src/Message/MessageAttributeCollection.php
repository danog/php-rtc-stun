<?php

/**
 * This file is part of the PHP WebRTC package.
 *
 * (c) Amin Yazdanpanah <https://www.aminyazdanpanah.com/#contact>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webrtc\STUN\Message;

use Amp\Socket\InternetAddress;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Webrtc\STUN\Enum\MessageAttribute;

/**
 * Class representing a collection of message attributes.
 *
 * @psalm-type AttributeValue = InternetAddress|array<array-key, mixed>|int|string|null
 * @implements IteratorAggregate<string, InternetAddress|array<array-key, mixed>|int|string|null>
 */
final class MessageAttributeCollection implements Countable, IteratorAggregate
{
    /**
     * Array of attributes.
     *
     * @var array<string, AttributeValue>
     */
    private array $attributes = [];

    /**
     * MessageAttribute constructor.
     *
     * @param array<string, AttributeValue>|null $attributes Optional initial attributes.
     */
    public function __construct(?array $attributes = [])
    {
        if ($attributes !== null) {
            foreach ($attributes as $key => $value) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * Get the first attribute value.
     *
     * @return AttributeValue First attribute value or null if empty.
     */
    public function first(): mixed
    {
        $first = reset($this->attributes);
        return $first === false ? null : $first;
    }

    /**
     * Get the last attribute value.
     *
     * @return AttributeValue Last attribute value or null if empty.
     */
    public function end(): mixed
    {
        $last = end($this->attributes);
        return $last === false ? null : $last;
    }

    /**
     * Add an attribute.
     *
     * @param MessageAttribute $attribute Attribute key.
     * @param AttributeValue $value
     * @return self
     */
    public function add(MessageAttribute $attribute, InternetAddress|array|int|string|null $value = null): self
    {
//        $messageAttrExist = (bool)array_filter(MessageAttribute::cases(), fn($attribute) => $attribute->name === $key);
//
//        if (!$messageAttrExist) {
//            throw new InvalidArgumentException("Invalid attribute name.");
//        }

        $this->attributes[$attribute->name] = $value;
        return $this;
    }

    /**
     * Remove an attribute.
     *
     * @param MessageAttribute $attribute Attribute key to remove.
     * @return self
     */
    public function remove(MessageAttribute $attribute): self
    {
        unset($this->attributes[$attribute->name]);
        return $this;
    }

    /**
     * Get all attributes.
     *
     * @return array<string, AttributeValue>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Get all attribute keys.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->attributes);
    }

    /**
     * Get all attribute values.
     *
     * @return list<AttributeValue>
     */
    public function values(): array
    {
        return array_values($this->attributes);
    }

    /**
     * Merge attributes into the current attributes.
     *
     * @param array<string, AttributeValue> $attributes Attributes to merge.
     * @return void
     */
    public function merge(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Get an attribute by key.
     *
     * @param MessageAttribute|string $attribute Attribute.
     * @param AttributeValue $default Default value if attribute is not found.
     * @return AttributeValue
     */
    public function get(MessageAttribute|string $attribute, InternetAddress|array|int|string|null $default = null): mixed
    {
        return $this->attributes[is_string($attribute) ? $attribute : $attribute->name] ?? $default;
    }

    /**
     * Apply a callback to all attributes.
     *
     * @param callable(AttributeValue): AttributeValue $func Callback function.
     * @return void
     */
    public function map(callable $func): void
    {
        $this->attributes = array_map($func, $this->attributes);
    }

    /**
     * if the attribute exists
     *
     * @param MessageAttribute|string $attribute Attribute.
     * @return bool
     */
    public function has(MessageAttribute|string $attribute): bool
    {
        return array_key_exists(is_string($attribute) ? $attribute : $attribute->name, $this->attributes);
    }

    /**
     * Get the count of attributes.
     *
     * @return int
     */
    #[\Override]
    public function count(): int
    {
        return count($this->attributes);
    }

    /**
     * {@inheritdoc}
     *
     * @return ArrayIterator<string, AttributeValue>
     */
    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }
}
