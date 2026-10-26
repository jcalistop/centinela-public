<?php

namespace App\Support\Schema;

/**
 * Compara dos esquemas normalizados (SDD-219 / ADR-040).
 *
 * Ignorados justificados: orden físico de índices entre sí; comentarios de columna.
 */
final class SchemaDiffComparer
{
    /**
     * @param  array{
     *     tables: array<string, array{engine: string|null}>,
     *     columns: array<string, array{data_type: string, column_type: string, is_nullable: string, column_default: string|null}>,
     *     indexes: array<string, array{unique: bool, columns: list<string>}>,
     *     foreign_keys: array<string, array{column: string, ref_table: string, ref_column: string, update_rule: string, delete_rule: string}>
     * }  $reference
     * @param  array{
     *     tables: array<string, array{engine: string|null}>,
     *     columns: array<string, array{data_type: string, column_type: string, is_nullable: string, column_default: string|null}>,
     *     indexes: array<string, array{unique: bool, columns: list<string>}>,
     *     foreign_keys: array<string, array{column: string, ref_table: string, ref_column: string, update_rule: string, delete_rule: string}>
     * }  $target
     * @return list<string>
     */
    public function diff(array $reference, array $target): array
    {
        $messages = [];

        $messages = array_merge($messages, $this->diffMaps(
            'tabla',
            $reference['tables'],
            $target['tables'],
            fn (array $left, array $right): ?string => ($left['engine'] ?? null) !== ($right['engine'] ?? null)
                ? 'engine '.$this->stringify($left['engine']).' vs '.$this->stringify($right['engine'])
                : null
        ));

        $messages = array_merge($messages, $this->diffMaps(
            'columna',
            $reference['columns'],
            $target['columns'],
            function (array $left, array $right): ?string {
                $parts = [];
                foreach (['data_type', 'column_type', 'is_nullable', 'column_default'] as $field) {
                    if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                        $parts[] = $field.' '.$this->stringify($left[$field] ?? null).' vs '.$this->stringify($right[$field] ?? null);
                    }
                }

                return $parts === [] ? null : implode('; ', $parts);
            }
        ));

        $messages = array_merge($messages, $this->diffMaps(
            'índice',
            $reference['indexes'],
            $target['indexes'],
            function (array $left, array $right): ?string {
                $parts = [];
                if (($left['unique'] ?? false) !== ($right['unique'] ?? false)) {
                    $parts[] = 'unique '.($left['unique'] ? 'true' : 'false').' vs '.($right['unique'] ? 'true' : 'false');
                }
                if (($left['columns'] ?? []) !== ($right['columns'] ?? [])) {
                    $parts[] = 'columns ['.implode(', ', $left['columns'] ?? []).'] vs ['.implode(', ', $right['columns'] ?? []).']';
                }

                return $parts === [] ? null : implode('; ', $parts);
            }
        ));

        $messages = array_merge($messages, $this->diffMaps(
            'FK',
            $reference['foreign_keys'],
            $target['foreign_keys'],
            function (array $left, array $right): ?string {
                $parts = [];
                foreach (['column', 'ref_table', 'ref_column', 'update_rule', 'delete_rule'] as $field) {
                    if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                        $parts[] = $field.' '.$this->stringify($left[$field] ?? null).' vs '.$this->stringify($right[$field] ?? null);
                    }
                }

                return $parts === [] ? null : implode('; ', $parts);
            }
        ));

        return $messages;
    }

    /**
     * @param  array<string, array<string, mixed>>  $reference
     * @param  array<string, array<string, mixed>>  $target
     * @param  callable(array<string, mixed>, array<string, mixed>): (?string)  $attributeDiff
     * @return list<string>
     */
    private function diffMaps(string $label, array $reference, array $target, callable $attributeDiff): array
    {
        $messages = [];
        $refKeys = array_keys($reference);
        $tgtKeys = array_keys($target);

        foreach (array_diff($refKeys, $tgtKeys) as $missing) {
            $messages[] = "Falta {$label} en target: {$missing}";
        }

        foreach (array_diff($tgtKeys, $refKeys) as $extra) {
            $messages[] = "Sobran {$label} en target: {$extra}";
        }

        foreach (array_intersect($refKeys, $tgtKeys) as $key) {
            $detail = $attributeDiff($reference[$key], $target[$key]);
            if ($detail !== null) {
                $messages[] = "Difiere {$label} {$key}: {$detail}";
            }
        }

        return $messages;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
