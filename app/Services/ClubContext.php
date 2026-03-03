<?php

namespace App\Services;

use App\Models\Club;

class ClubContext
{
    private ?Club $club = null;

    public function set(Club $club): void
    {
        $this->club = $club;
    }

    public function get(): ?Club
    {
        return $this->club;
    }

    public function id(): ?int
    {
        return $this->club?->id;
    }

    public function clear(): void
    {
        $this->club = null;
    }
}
