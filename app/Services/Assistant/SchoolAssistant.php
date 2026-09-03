<?php

namespace App\Services\Assistant;

use App\Models\User;

/**
 * The assistant a signed-in person can ask about their own school.
 *
 * Three rules shape this, and none of them is negotiable.
 *
 * **It answers from the school's own data, through the same authorization as
 * every screen.** A question is turned into a query that runs as the person
 * asking; a parent asking about fees gets their own children's fees and a query
 * they hold no permission for is refused. The assistant is a faster way to reach
 * data someone can already see, never a way around the permission system.
 *
 * **Nothing leaves the building unless the school says so.** The default
 * provider runs entirely locally: it recognises what is being asked and answers
 * from the database. A hosted model is a separate provider a school opts into,
 * because sending a child's marks or a family's debts to a third party is the
 * school's decision to make, not a default to inherit.
 *
 * **It says when it does not know.** An assistant that invents a plausible
 * attendance figure is worse than no assistant, because a figure with no source
 * is indistinguishable from one with a source until somebody acts on it.
 */
class SchoolAssistant
{
    public function __construct(private readonly AssistantProvider $provider) {}

    /**
     * Answer one question for one person.
     *
     * @return array{answer: string, sources: array<int, array{label: string, href: ?string}>, handled: bool}
     */
    public function ask(User $user, string $question): array
    {
        $question = trim($question);

        if ($question === '') {
            return $this->cannotAnswer('Ask me something about the school and I will look it up.');
        }

        return $this->provider->answer($user, $question);
    }

    /** @return array{answer: string, sources: array<int, mixed>, handled: bool} */
    protected function cannotAnswer(string $message): array
    {
        return ['answer' => $message, 'sources' => [], 'handled' => false];
    }

    /** What this assistant can be asked, shown to the user as starting points. */
    public function suggestionsFor(User $user): array
    {
        return $this->provider->suggestions($user);
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }
}
