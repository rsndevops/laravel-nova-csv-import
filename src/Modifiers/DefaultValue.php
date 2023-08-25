<?php

namespace rsndevops\LaravelNovaCsvImport\Modifiers;

use rsndevops\LaravelNovaCsvImport\Contracts\Modifier;

class DefaultValue implements Modifier
{
    public function title(): string
    {
        return 'Default Value';
    }

    public function description(): string
    {
        return 'Set a default value for the field which will only be used if the source is a missing or empty value';
    }

    public function settings(): array
    {
        return [
            'string' => [
                'type' => 'string',
                'title' => 'Default Value',
            ],
        ];
    }

    public function handle($value = null, array $settings = []): string
    {
        return $value === null ? $settings['string'] : $value;
    }
}
