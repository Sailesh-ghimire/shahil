<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/**
 * inbox.view to read; inbox.reply.any or inbox.reply.assigned (own conversations) to reply; inbox.assign to hand over.
 */
class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inbox.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->can('inbox.view');
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        if ($user->can('inbox.reply.any')) {
            return true;
        }

        return $user->can('inbox.reply.assigned') && $conversation->assignee_id === $user->getKey();
    }

    public function assign(User $user, Conversation $conversation): bool
    {
        return $user->can('inbox.assign');
    }

    /**
     * Take an unassigned conversation (or one already yours).
     */
    public function claim(User $user, Conversation $conversation): bool
    {
        return $this->assign($user, $conversation)
            || ($user->can('inbox.reply.assigned') && in_array($conversation->assignee_id, [null, $user->getKey()], true));
    }

    /**
     * Create a lead from the chat, or link it to a contact.
     */
    public function link(User $user, Conversation $conversation): bool
    {
        return $user->can('leads.create') || $user->can('contacts.update.any');
    }
}
