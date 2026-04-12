<?php

namespace App\Policies;

use App\Models\NewsArticleComment;
use App\Models\User;

class NewsArticleCommentPolicy
{
    public function delete(User $user, NewsArticleComment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
