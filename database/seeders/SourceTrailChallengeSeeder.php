<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

/**
 * Seed the "Source Trail" web exploitation challenge.
 *
 * This seeder is idempotent: it only creates the challenge if a
 * record with the slug "source-trail" does not already exist. It
 * never updates existing records, so an administrator's later edits
 * (including a rotated canonical flag, points, or active status)
 * are always preserved.
 *
 * It only touches the challenges table. It never creates, modifies,
 * or reads user or administrator records.
 */
class SourceTrailChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $flag = (string) env('SOURCE_TRAIL_FLAG', 'p4dCTF{every_file_tells_a_story}');

        Challenge::firstOrCreate(
            ['slug' => 'source-trail'],
            [
                'title' => 'Source Trail',
                'description' => 'A developer left behind a trail while building a webpage. The visible page tells only part of the story. Can you connect the missing pieces and uncover what was never meant to be seen?',
                'category' => 'web-exploitation',
                'difficulty' => 'beginner',
                'points' => 30,
                'target_type' => 'internal',
                'target_url' => null,
                'is_active' => true,
                'flag' => $flag,
                'hints' => [
                    'The page may have more to tell than what appears on your screen.',
                    'A webpage is often made from several pieces working together.',
                    'Follow the page\'s connected files and look for clues left behind by the developer.',
                ],
                'published_at' => now(),
            ]
        );
    }
}
