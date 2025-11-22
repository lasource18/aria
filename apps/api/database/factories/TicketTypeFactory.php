<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Common ticket type names in French
        $ticketNames = [
            'Entrée Générale',
            'Accès VIP',
            'Early Bird',
            'Tarif Étudiant',
            'Pass Weekend',
            'Table Réservée',
            'Gratuit',
        ];

        $type = fake()->randomElement(['free', 'paid', 'donation']);
        $price = $type === 'free' ? 0 : fake()->numberBetween(1000, 50000);

        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement($ticketNames),
            'type' => $type,
            'price_xof' => $price,
            'fee_pass_through_pct' => 0,
            'max_qty' => fake()->optional(0.7)->numberBetween(10, 500), // 70% have limit
            'per_order_limit' => 10,
            'sales_start' => null, // Starts immediately
            'sales_end' => null, // Ends at event start
            'refundable' => true,
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the ticket type is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'free',
            'price_xof' => 0,
            'name' => 'Gratuit',
        ]);
    }

    /**
     * Indicate that the ticket type is paid.
     */
    public function paid(?int $price = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'paid',
            'price_xof' => $price ?? fake()->numberBetween(5000, 25000),
        ]);
    }

    /**
     * Indicate that the ticket type is donation-based.
     */
    public function donation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'donation',
            'price_xof' => 0,
            'name' => 'Contribution Volontaire',
        ]);
    }

    /**
     * Indicate that the ticket type has unlimited quantity.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_qty' => null,
        ]);
    }

    /**
     * Indicate that the ticket type has limited quantity.
     */
    public function limited(int $qty): static
    {
        return $this->state(fn (array $attributes) => [
            'max_qty' => $qty,
        ]);
    }

    /**
     * Indicate that the ticket type is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }

    /**
     * Indicate early bird pricing.
     */
    public function earlyBird(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Early Bird',
            'type' => 'paid',
            'price_xof' => fake()->numberBetween(2000, 10000),
            'sales_end' => now()->addDays(7),
        ]);
    }

    /**
     * Indicate VIP pricing.
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Accès VIP',
            'type' => 'paid',
            'price_xof' => fake()->numberBetween(20000, 100000),
            'max_qty' => fake()->numberBetween(10, 50),
        ]);
    }
}
