<?php

namespace App\Livewire\Pulse;

use App\Recorders\RequestCounts;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
class TopRoutes extends Card
{
    public function render(): Renderable
    {
        [$topRoutes, $time, $runAt] = $this->remember(
            fn () => $this->aggregate('request_count', ['count'])
                ->map(function ($row) {
                    [$method, $uri, $action] = json_decode($row->key, flags: JSON_THROW_ON_ERROR);

                    return (object) [
                        'method' => $method,
                        'uri' => $uri,
                        'action' => $action,
                        'count' => $row->count,
                    ];
                }),
        );

        return View::make('livewire.pulse.top-routes', [
            'time' => $time,
            'runAt' => $runAt,
            'topRoutes' => $topRoutes,
            'config' => [
                'sample_rate' => Config::get('pulse.recorders.'.RequestCounts::class.'.sample_rate'),
            ],
        ]);
    }
}
