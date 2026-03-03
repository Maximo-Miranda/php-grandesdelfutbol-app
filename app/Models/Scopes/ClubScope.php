<?php

namespace App\Models\Scopes;

use App\Services\ClubContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ClubScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $clubId = app(ClubContext::class)->id();

        if ($clubId !== null) {
            $builder->where($model->getTable().'.club_id', $clubId);
        }
    }
}
