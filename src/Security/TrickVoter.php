<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Trick;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Only the author of a trick, or an administrator, can change or delete it.
 *
 * @extends Voter<string, Trick>
 */
final class TrickVoter extends Voter
{
    public const EDIT = 'TRICK_EDIT';
    public const DELETE = 'TRICK_DELETE';

    public function __construct(private readonly AccessDecisionManagerInterface $accessDecisionManager)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof Trick;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        $authorId = $subject->getAuthor()?->getId();

        return null !== $authorId && $authorId === $user->getId();
    }
}
