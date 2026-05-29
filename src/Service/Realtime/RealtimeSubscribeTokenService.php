<?php

declare(strict_types=1);

namespace App\Service\Realtime;

use App\Entity\User;

/**
 * Issues HMAC subscribe tokens consumed by realtime-ws/server.mjs.
 */
final class RealtimeSubscribeTokenService
{
    private const DEFAULT_TTL = 3600;

    public function __construct(
        private readonly ?string $broadcastKey = null,
    ) {
    }

    /**
     * @param list<string> $extraChannels
     */
    public function issueForUser(User $user, array $extraChannels = [], int $ttlSeconds = self::DEFAULT_TTL): string
    {
        $channels = array_values(array_unique(array_merge(
            $this->channelsForUser($user),
            $extraChannels,
        )));

        return $this->issue($user->getId() ?? 0, $channels, $ttlSeconds);
    }

    /**
     * @param list<string> $channels
     */
    public function issue(int $userId, array $channels, int $ttlSeconds = self::DEFAULT_TTL): string
    {
        $key = $this->broadcastKey ?? '';
        if ($key === '' || $channels === []) {
            throw new \RuntimeException('Realtime is not configured.');
        }

        $payload = json_encode([
            'sub' => $userId,
            'channels' => $channels,
            'exp' => time() + $ttlSeconds,
        ], JSON_THROW_ON_ERROR);

        $payloadB64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $signature = rtrim(strtr(
            base64_encode(hash_hmac('sha256', $payloadB64, $key, true)),
            '+/',
            '-_'
        ), '=');

        return $payloadB64.'.'.$signature;
    }

    /**
     * @return list<string>
     */
    public function channelsForUser(User $user): array
    {
        $channels = [];
        $roles = $user->getRoles();

        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
            $channels[] = 'staff';
            $channels[] = 'catalog';
        } else {
            $channels[] = 'catalog';
        }

        $userId = $user->getId();
        if ($userId !== null) {
            $channels[] = 'user:'.$userId;
        }

        return $channels;
    }
}
