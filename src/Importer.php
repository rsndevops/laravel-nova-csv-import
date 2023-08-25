<?php

namespace rsndevops\LaravelNovaCsvImport;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Nova\Resource;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithValidation;
use rsndevops\LaravelNovaCsvImport\Concerns\HasModifiers;

class Importer implements ToModel, WithValidation, WithHeadingRow, WithMapping, WithBatchInserts, WithChunkReading, SkipsOnFailure, SkipsOnError, SkipsEmptyRows
{
    use Importable, SkipsFailures, SkipsErrors, HasModifiers;

    protected Resource $resource;

    protected $attribute_map = [];

    protected $rules;

    protected $model_class;

    protected $meta_values = [];

    protected $custom_values = [];

    protected $combined_values = [];

    public function __construct()
    {
        $this->bootHasModifiers();
    }

    public function map($row): array
    {
        if (empty($this->attribute_map)) {
            return $row;
        }

        $data = [];

        foreach ($this->attribute_map as $attribute => $column) {
            if (! $column) {
                continue;
            }

            $data[$attribute] = $this->modifyValue(
                $this->getFieldValue($row, $column, $attribute),
                $this->getModifiers($attribute)
            );
        }

        return $data;
    }

    public function model(array $row): Model
    {
        $model = $this->resource::newModel();

        $model->fill($row);

        return $model;
    }

    public function rules(): array
    {
        return $this->rules;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function getAttributeMap(): array
    {
        return $this->attribute_map;
    }

    public function setAttributeMap(array $map): self
    {
        $this->attribute_map = $map;

        return $this;
    }

    public function getMeta($key = null)
    {
        if ($key && ! empty($this->meta_values[$key])) {
            return $this->meta_values[$key];
        }

        return $this->meta_values;
    }

    public function setMeta(array $meta): self
    {
        $this->meta_values = $meta;

        return $this;
    }

    public function getRandomStringSettings($key = null)
    {
        if ($key) {
            return $this->random_string_settings[$key] ?? '';
        }

        return $this->random_string_settings;
    }

    public function setRandomStringSettings(array $map): self
    {
        $this->random_string_settings = $map;

        return $this;
    }

    public function getCustomValues($key = null)
    {
        if ($key) {
            return $this->custom_values[$key] ?? '';
        }

        return $this->custom_values;
    }

    public function setCustomValues(array $map): self
    {
        $this->custom_values = $map;

        return $this;
    }

    public function getCombinedValues($key = null, $row = null)
    {
        if ($key) {
            $outputs = [];
            $config = $this->combined_values[$key];

            foreach ($config['columns'] as $index => $field) {
                $value = $field['value'] ?? false ?: $this->getFieldValue($row, $field['name'], $key);

                // If some part of the value looks like a field name (e.g. `{{ name }}`), then replace that part for
                // the actual value of that field
                if (is_string($value) && preg_match_all('/{{\s*([a-z0-9_\.]+)\s*}}/i', $value, $matches)) {
                    foreach ($matches[1] as $match) {
                        $value = preg_replace('/{{\s*'.$match.'\s*}}/', $this->getFieldValue($row, $match, $key), $value);
                    }
                }

                if ($field['as'] ?? false) {
                    Arr::set($outputs, $field['as'], $value);
                } else {
                    $outputs[$field['name'].'_'.$index] = $value;
                }
            }

            if ($config['separator']) {
                return implode($this->getSeparator($config['separator']), $outputs);
            }

            return $outputs;
        }

        return $this->combined_values;
    }

    protected function getSeparator(?string $rawSeparator): string
    {
        $separators = [
            '__SPACE__' => ' ',
            '__TAB__' => "\t",
        ];

        $rawSeparator = str($rawSeparator);

        if ($rawSeparator->startsWith('__CUSTOM__.')) {
            return $rawSeparator->replaceFirst('__CUSTOM__.', '');
        }

        return $separators[$rawSeparator->value()];
    }

    public function setCombinedValues(array $map): self
    {
        $this->combined_values = $map;

        return $this;
    }

    public function setRules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function getModelClass(): string
    {
        return $this->model_class;
    }

    public function setModelClass(string $model_class): self
    {
        $this->model_class = $model_class;

        return $this;
    }

    public function setResource(Resource $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    protected function getFieldValue(array $row, string $mapping, string $attribute)
    {
        if (array_key_exists($mapping, $row)) {
            return $row[$mapping];
        } elseif (Str::startsWith($mapping, 'meta')) {
            return $this->getMeta(Str::replaceFirst('meta.', '', $mapping));
        } elseif ($mapping === 'combined') {
            return $this->getCombinedValues($attribute, $row);
        } elseif ($mapping === 'random') {
            return Str::random($this->getRandomStringSettings($attribute));
        } elseif ($mapping === 'custom') {
            return $this->getCustomValues($attribute);
        }
    }
}
