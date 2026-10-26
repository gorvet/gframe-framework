<?php

namespace GFrame\Config\Contracts;

interface ProjectSettingsProvider
{
    /** Return project overrides grouped as app, seo and analytics. */
    public function resolve(): array;
}
