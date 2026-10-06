<?php

final class ClosureWrapper
{
    public static function serialize(Closure $closure): string
    {
        return \Opis\Closure\serialize($closure);
    }

    public static function unserialize(string $serialized): Closure
    {
        $closure = str_starts_with($serialized, 'C:32:"Opis\\Closure\\SerializableClosure":')
            ? \Opis\Closure\v3_unserialize($serialized)
            : \Opis\Closure\unserialize($serialized);
        if (!$closure instanceof Closure) {
            throw new InvalidArgumentException('La tarea asíncrona no contiene una closure válida.');
        }

        return $closure;
    }
}
