<?php

abstract class Cron {
  protected string $name = '';

  public function getName(): string {
    return trim($this->name);
  }

  abstract public function handle(array $task = []): array;
}
