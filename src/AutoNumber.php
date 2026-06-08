<?php

namespace Jobsrey\AutoNumber;

use Jobsrey\AutoNumber\Models\AutoNumber as AutoNumberModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class AutoNumber
{
    /**
     * Generate unique name for autonumber identity.
     *
     * @param array $options
     * @param string|null $group
     * @return string
     */
    private function generateUniqueName(array $options, ?string $group = null): string
    {
        $base = serialize($options);
        if ($group !== null) {
            $base .= serialize(['group' => $group]);
        }
        return md5($base);
    }

    /**
     * Evaluate autonumber configuration.
     *
     * @param array $overrides
     * @return array
     */
    public function evaluateConfiguration(array $overrides = []): array
    {
        $config = array_merge(
            Config::get('autonumber', []),
            $overrides
        );

        if (is_callable($config['format'])) {
            $config['format'] = call_user_func($config['format']);
        }

        foreach ($config as $key => $value) {
            if (is_null($value)) {
                throw new InvalidArgumentException($key . ' param cannot be null');
            }
        }

        return $config;
    }

    /**
     * Return the next auto increment number.
     *
     * @param string $name
     * @param string|null $group
     * @return int
     */
    private function getNextNumber($name, ?string $group = null): int
    {
        $query = AutoNumberModel::where('name', $name);

        if ($group !== null) {
            $query->where('group', $group);
        } else {
            $query->whereNull('group');
        }

        $autoNumber = $query->first();

        if ($autoNumber === null) {
            $autoNumber = new AutoNumberModel([
                'name' => $name,
                'group' => $group,
                'number' => 1,
            ]);
        } else {
            $autoNumber->number += 1;
        }

        $autoNumber->save();

        return $autoNumber->number;
    }

    /**
     * Generate auto number.
     *
     * @param Model $model
     * @return bool
     */
    public function generate(Model $model): bool
    {
        $attributes = [];
        foreach ($model->getAutoNumberOptions() as $attribute => $options) {
            if (is_numeric($attribute)) {
                $attribute = $options;
                $options = [];
            }

            $config = $this->evaluateConfiguration($options);

            // Resolve group value
            $group = null;
            if (isset($config['group'])) {
                $group = is_callable($config['group'])
                    ? call_user_func($config['group'], $model)
                    : $config['group'];
            }

            $uniqueName = $this->generateUniqueName(
                array_merge(
                    ['class' => get_class($model)],
                    Arr::except($config, ['onUpdate', 'group'])
                ),
                $group
            );

            $autoNumber = $this->getNextNumber($uniqueName, $group);

            if ($length = $config['length']) {
                $autoNumber = str_replace('?', str_pad($autoNumber, $length, '0', STR_PAD_LEFT), $config['format']);
            }

            $model->setAttribute($attribute, $autoNumber);

            $attributes[] = $attribute;
        }

        return $model->isDirty($attributes);
    }
}
