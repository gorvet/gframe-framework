<?php

use Opis\Closure\SerializableClosure;

final class ClosureWrapper
{
    public static function serialize(Closure $closure): string
    {
        return serialize(new SerializableClosure($closure));
    }

    public static function unserialize(string $serialized): Closure
    {
        $wrapper = unserialize($serialized);
        if (!$wrapper instanceof SerializableClosure) {
            throw new InvalidArgumentException('La tarea asíncrona no contiene una closure válida.');
        }

        return $wrapper->getClosure();
    }
}
