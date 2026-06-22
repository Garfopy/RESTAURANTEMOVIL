<?php

declare(strict_types=1);

namespace Amare\Api\Helpers;

final class BranchConfigEvents
{
    public static function publish(int $branchId, int $version): void
    {
        $url = trim((string)($_ENV['BRANCH_CONFIG_EVENT_WEBHOOK_URL'] ?? getenv('BRANCH_CONFIG_EVENT_WEBHOOK_URL') ?: ''));
        if ($url === '') {
            return;
        }

        $payload = json_encode([
            'event' => 'branch.config.updated',
            'branch_id' => $branchId,
            'version' => $version,
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false || !function_exists('curl_init')) {
            return;
        }

        $headers = ['Content-Type: application/json'];
        $secret = trim((string)($_ENV['BRANCH_CONFIG_EVENT_SECRET'] ?? getenv('BRANCH_CONFIG_EVENT_SECRET') ?: ''));
        if ($secret !== '') {
            $headers[] = 'Authorization: Bearer ' . $secret;
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 400,
            CURLOPT_TIMEOUT_MS => 900,
        ]);
        curl_exec($curl);
        if (curl_errno($curl)) {
            error_log('BranchConfigEvents::publish ERROR: ' . curl_error($curl));
        }
        curl_close($curl);
    }
}
