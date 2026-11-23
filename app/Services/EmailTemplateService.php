<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmailTemplateService
{
    /**
     * Generate a short email template using the AI provider (Groq / OpenAI-compatible API).
     */
    public function generate(array $input): array
    {
        $apiKey = config('ai.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('AI_API_KEY is not configured.');
        }

        $messages = $this->buildMessages($input);

        $start = microtime(true);
        $startedAt = now()->toIso8601String();

        $response = Http::withToken($apiKey)
            ->timeout((int) config('ai.timeout'))
            ->acceptJson()
            ->post(rtrim(config('ai.base_url'), '/') . '/chat/completions', [
                'model' => config('ai.model'),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 700,
            ]);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        // Simple response-time log (timestamp diff), as required by the assignment.
        Log::channel('ai')->info('AI call completed', [
            'provider' => config('ai.base_url'),
            'model' => config('ai.model'),
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'response_time_ms' => $durationMs,
            'status' => $response->status(),
        ]);

        if ($response->failed()) {
            Log::channel('ai')->error('AI call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('AI provider returned an error.');
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return array_merge($this->parseEmail($content), [
            'response_time_ms' => $durationMs,
        ]);
    }

    private function buildMessages(array $input): array
    {
        $system = <<<PROMPT
You are an assistant that writes short, customer-friendly email templates.
Rules:
- Keep the email under 120 words, but always write a complete email.
- Match the requested tone exactly.
- Address the recipient by name.
- Start with a line "Subject: <subject>", then a blank line, then the email body.
- End with a polite closing and the placeholder "[Your Name]" as the signature.
- Output only the email. No explanations, no markdown.
PROMPT;

        $user = sprintf(
            "Write an email.\nPurpose: %s\nRecipient name: %s\nTone: %s",
            $input['purpose'],
            $input['recipient_name'],
            $input['tone']
        );

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function parseEmail(string $content): array
    {
        $content = trim($content);
        $subject = null;
        $body = $content;

        if (preg_match('/^Subject:\s*(.+)\R+/i', $content, $m)) {
            $subject = trim($m[1]);
            $body = trim(substr($content, strlen($m[0])));
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
}