<?php

namespace Database\Factories;

use App\Enums\SupportTicketArea;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject' => $this->faker->sentence(4),
            'body' => $this->faker->paragraphs(3, true),
            'status' => SupportTicketStatus::Open,
            'area' => SupportTicketArea::Gestion,
            'cen_hie_dependency_id' => 1,
            'created_by_user_id' => User::factory()->state(['cen_hie_dependency_id' => 1]),
            'assigned_to_user_id' => null,
            'indicator_type_id' => null,
            'year_id' => null,
            'github_issue_number' => null,
            'github_issue_url' => null,
            'github_linked_at' => null,
            'github_linked_by_user_id' => null,
            'resolved_at' => null,
        ];
    }
}
