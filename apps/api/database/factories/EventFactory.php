<?php

namespace Database\Factories;

use App\Models\Org;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Generate a valid date range that satisfies the check_event_dates constraint.
     * Ensures end_at is always after start_at.
     *
     * @return array{start_at: \DateTime, end_at: \DateTime}
     */
    private function generateValidDateRange(): array
    {
        $startAt = fake()->dateTimeBetween('+1 week', '+3 months');
        $endAt = (clone $startAt)->modify('+'.fake()->numberBetween(1, 8).' hours');

        return ['start_at' => $startAt, 'end_at' => $endAt];
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // French/Ivorian event titles
        $eventTitles = [
            'Festival de Jazz d\'Abidjan',
            'Concert de Coupé-Décalé',
            'Soirée Afrobeats Live',
            'Exposition d\'Art Contemporain',
            'Marathon de la Paix',
            'Tournoi de Football Inter-Quartiers',
            'Conférence Tech & Innovation',
            'Atelier Entrepreneuriat Digital',
            'Spectacle de Danse Traditionnelle',
            'Fête de la Gastronomie Ivoirienne',
        ];

        // Abidjan venues
        $venues = [
            ['name' => 'Palais de la Culture', 'address' => 'Boulevard de la République, Abidjan', 'lat' => 5.3196, 'lng' => -4.0156],
            ['name' => 'Hôtel Ivoire', 'address' => 'Boulevard Hassan II, Cocody', 'lat' => 5.3487, 'lng' => -3.9956],
            ['name' => 'Stade Félix Houphouët-Boigny', 'address' => 'Boulevard du Gabon, Abidjan', 'lat' => 5.3362, 'lng' => -4.0197],
            ['name' => 'Espace Latrille Events', 'address' => 'Deux Plateaux, Cocody', 'lat' => 5.3707, 'lng' => -3.9787],
            ['name' => 'Centre Culturel Jacques Aka', 'address' => 'Rue des Jardins, Plateau', 'lat' => 5.3269, 'lng' => -4.0215],
        ];

        $categories = ['music', 'arts', 'sports', 'tech', 'other'];
        ['start_at' => $startAt, 'end_at' => $endAt] = $this->generateValidDateRange();
        $venue = fake()->randomElement($venues);
        $isOnline = fake()->boolean(20); // 20% chance online

        return [
            'org_id' => Org::factory(),
            'title' => fake()->randomElement($eventTitles),
            // Slug auto-generated
            'description_md' => fake()->paragraphs(3, true)."\n\n## Programme\n\n".fake()->paragraph(),
            'category' => fake()->randomElement($categories),
            'venue_name' => $isOnline ? null : $venue['name'],
            'venue_address' => $isOnline ? null : $venue['address'],
            'location' => $isOnline ? null : ['lat' => $venue['lat'], 'lng' => $venue['lng']],
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => 'Africa/Abidjan',
            'status' => 'draft',
            'is_online' => $isOnline,
            'settings' => null,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    /**
     * Indicate that the event is canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
        ]);
    }

    /**
     * Indicate that the event is ended.
     */
    public function ended(): static
    {
        return $this->state(function (array $attributes) {
            $startAt = fake()->dateTimeBetween('-2 months', '-1 week');
            $endAt = (clone $startAt)->modify('+'.fake()->numberBetween(1, 8).' hours');

            return [
                'status' => 'ended',
                'start_at' => $startAt,
                'end_at' => $endAt,
            ];
        });
    }

    /**
     * Indicate that the event is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'venue_name' => null,
            'venue_address' => null,
            'location' => null,
        ]);
    }

    /**
     * Indicate that the event is in-person with specific location.
     */
    public function inPerson(): static
    {
        $venues = [
            ['name' => 'Palais de la Culture', 'address' => 'Boulevard de la République, Abidjan', 'lat' => 5.3196, 'lng' => -4.0156],
            ['name' => 'Hôtel Ivoire', 'address' => 'Boulevard Hassan II, Cocody', 'lat' => 5.3487, 'lng' => -3.9956],
        ];
        $venue = fake()->randomElement($venues);

        return $this->state(fn (array $attributes) => [
            'is_online' => false,
            'venue_name' => $venue['name'],
            'venue_address' => $venue['address'],
            'location' => ['lat' => $venue['lat'], 'lng' => $venue['lng']],
        ]);
    }
}
