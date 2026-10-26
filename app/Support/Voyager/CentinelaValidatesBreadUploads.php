<?php

namespace App\Support\Voyager;

use App\Support\Upload\UploadSizeLimit;
use Illuminate\Support\Collection;

trait CentinelaValidatesBreadUploads
{
    /**
     * @param  array<string, mixed>  $data
     * @param  iterable<int, object>  $rows
     */
    public function validateBread($data, $rows, $name = null, $id = null)
    {
        $this->injectUploadMaxRulesOnRows($rows);

        return parent::validateBread($data, $rows, $name, $id);
    }

    /**
     * @param  iterable<int, object>  $rows
     */
    private function injectUploadMaxRulesOnRows(iterable $rows): void
    {
        $maxRule = UploadSizeLimit::laravelMaxRule();

        foreach ($rows as $row) {
            if (! is_object($row) || ! isset($row->type)) {
                continue;
            }

            if (! in_array($row->type, ['file', 'image', 'multiple_images'], true)) {
                continue;
            }

            $details = $row->details ?? (object) [];

            if (! is_object($details)) {
                $details = json_decode(json_encode($details), false) ?? (object) [];
            }

            if (! isset($details->validation)) {
                $details->validation = (object) [
                    'rule' => $this->defaultUploadValidationRule($row->type, $maxRule),
                ];
                $row->details = $details;

                continue;
            }

            if (! is_object($details->validation)) {
                $details->validation = (object) ['rule' => (string) $details->validation];
            }

            $details->validation->rule = $this->mergeMaxRule(
                $details->validation->rule ?? '',
                $maxRule,
                $row->type,
            );

            if (isset($details->validation->add)) {
                $details->validation->add->rule = $this->mergeMaxRule(
                    $details->validation->add->rule ?? '',
                    $maxRule,
                    $row->type,
                );
            }

            if (isset($details->validation->edit)) {
                $details->validation->edit->rule = $this->mergeMaxRule(
                    $details->validation->edit->rule ?? '',
                    $maxRule,
                    $row->type,
                );
            }

            $row->details = $details;
        }
    }

    private function defaultUploadValidationRule(string $type, string $maxRule): string
    {
        $base = in_array($type, ['image', 'multiple_images'], true) ? 'nullable|image' : 'nullable|file';

        return "{$base}|{$maxRule}";
    }

    private function mergeMaxRule(mixed $existingRule, string $maxRule, string $type): string
    {
        $rules = $this->normalizeRules($existingRule);

        if ($rules === []) {
            return $this->defaultUploadValidationRule($type, $maxRule);
        }

        $rules = array_values(array_filter(
            $rules,
            static fn (mixed $rule): bool => ! is_string($rule) || ! str_starts_with($rule, 'max:')
        ));

        $rules[] = $maxRule;

        if (! $this->rulesContainFileOrImage($rules)) {
            array_unshift($rules, in_array($type, ['image', 'multiple_images'], true) ? 'image' : 'file');
        }

        return implode('|', $rules);
    }

    /**
     * @return array<int, mixed>
     */
    private function normalizeRules(mixed $rule): array
    {
        if (is_array($rule)) {
            return $rule;
        }

        if (! is_string($rule) || trim($rule) === '') {
            return [];
        }

        return explode('|', $rule);
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function rulesContainFileOrImage(array $rules): bool
    {
        return Collection::make($rules)->contains(function (mixed $rule): bool {
            return is_string($rule) && in_array($rule, ['file', 'image'], true);
        });
    }
}
