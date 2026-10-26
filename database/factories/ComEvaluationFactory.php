<?php

namespace Database\Factories;

use App\Enums\Comges\EvaluacionTipo;
use App\Models\ComAction;
use App\Models\ComEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComEvaluation>
 */
class ComEvaluationFactory extends Factory
{
    protected $model = ComEvaluation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'com_action_id' => ComAction::factory(),
            'cen_hie_dependency_id' => 1,
            'numerador' => 1,
            'denominador' => 1,
            'resultado' => 100,
            'cumplimiento' => 100,
            'tipo_evaluacion' => EvaluacionTipo::Autoevaluacion,
        ];
    }

    public function forSs(int $ssId): static
    {
        return $this->state(fn (array $attributes) => [
            'cen_hie_dependency_id' => $ssId,
        ]);
    }
}
