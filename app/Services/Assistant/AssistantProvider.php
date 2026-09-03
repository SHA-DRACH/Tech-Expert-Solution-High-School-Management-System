<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * The seam a school plugs an assistant into.
 *
 * The same shape as App\Services\Sms\SmsGateway, and for the same reason: the
 * platform ships something that works honestly with no account anywhere, and
 * connecting a paid third party stays a decision a school makes deliberately.
 */
interface AssistantProvider
{
    /**
     * @return array{answer: string, sources: array<int, array{label: string, href: ?string}>, handled: bool}
     */
    public function answer(User $user, string $question): array;

    /** @return array<int, string> */
    public function suggestions(User $user): array;

    public function name(): string;
}
