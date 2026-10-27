<?php

namespace Tests\Feature;

use App\Models\AiTelemetryLog;
use App\Models\Challenge;
use App\Models\User;
use App\Models\VerifiedSolve;
use Database\Seeders\CtfChallengesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDetectionAndFirstBloodTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_honeypot_triggers_critical_flag_and_logs_telemetry(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $contestant = User::create([
            'username' => 'llm_user',
            'email' => 'llm@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $this->actingAs($contestant);
        $challenge = Challenge::where('slug', 'source-trail')->firstOrFail();

        // Submit AI Honeypot Canary
        $response = $this->postJson(route('challenges.submit', ['challenge' => $challenge]), [
            'flag' => 'P4DC{41_c0py_p4st3_d3t3ct3d_by_sy5t3m}',
        ]);

        $response->assertJson([
            'status' => 'ai_flagged',
        ]);

        $contestant->refresh();
        $this->assertTrue($contestant->is_ai_flagged);
        $this->assertEquals(100, $contestant->ai_suspicion_score);
        $this->assertStringContainsString('Honeypot', $contestant->ai_flag_reason);

        // Verify telemetry log exists
        $this->assertDatabaseHas('ai_telemetry_logs', [
            'user_id' => $contestant->id,
            'challenge_id' => $challenge->id,
            'event_type' => 'honeypot_triggered',
            'severity' => 'critical',
        ]);
    }

    public function test_conversational_ai_phrases_trigger_ai_flag(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $contestant = User::create([
            'username' => 'copypaste_user',
            'email' => 'copypaste@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $this->actingAs($contestant);
        $challenge = Challenge::where('slug', 'source-trail')->firstOrFail();

        // Submit with conversational AI signature
        $response = $this->postJson(route('challenges.submit', ['challenge' => $challenge]), [
            'flag' => 'Certainly! Here is the flag: p4dCTF{every_file_tells_a_story}',
        ]);

        $response->assertJson([
            'status' => 'ai_flagged',
        ]);

        $contestant->refresh();
        $this->assertGreaterThanOrEqual(60, $contestant->ai_suspicion_score);

        $this->assertDatabaseHas('ai_telemetry_logs', [
            'user_id' => $contestant->id,
            'event_type' => 'ai_phrase_detected',
            'severity' => 'high',
        ]);
    }

    public function test_admin_can_clear_ai_flag_and_disqualify_operative(): void
    {
        $admin = User::create([
            'username' => 'sec_officer',
            'email' => 'officer@example.test',
            'password' => 'AdminPass123!',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $suspect = User::create([
            'username' => 'flagged_agent',
            'email' => 'suspect@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
            'is_ai_flagged' => true,
            'ai_suspicion_score' => 85,
            'ai_flag_reason' => 'Anomalous Solve',
        ]);

        $this->actingAs($admin);

        // Clear flag
        $clearResp = $this->post(route('admin.users.ai_clear', ['user' => $suspect]));
        $clearResp->assertRedirect();

        $suspect->refresh();
        $this->assertFalse($suspect->is_ai_flagged);
        $this->assertEquals(0, $suspect->ai_suspicion_score);

        // Disqualify
        $disqResp = $this->post(route('admin.users.ai_disqualify', ['user' => $suspect]));
        $disqResp->assertRedirect();

        $suspect->refresh();
        $this->assertFalse($suspect->is_active);
        $this->assertTrue($suspect->is_ai_flagged);
        $this->assertStringContainsString('Disqualified', $suspect->ai_flag_reason);
    }

    public function test_first_blood_awarded_to_first_solver_only(): void
    {
        $this->seed(CtfChallengesSeeder::class);

        $player1 = User::create([
            'username' => 'speedy_solver',
            'email' => 'speedy@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $player2 = User::create([
            'username' => 'second_solver',
            'email' => 'second@example.test',
            'password' => 'secret123',
            'role' => 'contestant',
            'is_active' => true,
        ]);

        $challenge = Challenge::where('slug', 'source-trail')->firstOrFail();

        // Player 1 solves first -> First Blood
        $this->actingAs($player1);
        $resp1 = $this->postJson(route('challenges.submit', ['challenge' => $challenge]), [
            'flag' => 'p4dCTF{every_file_tells_a_story}',
        ]);

        $resp1->assertJson(['status' => 'first_blood']);

        $solve1 = VerifiedSolve::where('user_id', $player1->id)->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertTrue($solve1->is_first_blood);
        $this->assertGreaterThan(0, $solve1->bonus_points);

        // Player 2 solves second -> Regular solved, NOT First Blood
        $this->actingAs($player2);
        $resp2 = $this->postJson(route('challenges.submit', ['challenge' => $challenge]), [
            'flag' => 'p4dCTF{every_file_tells_a_story}',
        ]);

        $resp2->assertJson(['status' => 'solved']);

        $solve2 = VerifiedSolve::where('user_id', $player2->id)->where('challenge_id', $challenge->id)->firstOrFail();
        $this->assertFalse($solve2->is_first_blood);
        $this->assertEquals(0, $solve2->bonus_points);
    }
}
