<?php

use App\Recorders\RequestCounts;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Laravel\Pulse\Facades\Pulse;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Pulse::handleExceptionsUsing(fn ($e) => throw $e);
    Pulse::startRecording();
});

test('it records request counts for valid routes', function () {
    $recorder = app(RequestCounts::class);

    $route = new Route('GET', '/dashboard', fn () => 'ok');
    $route->name('dashboard');

    $request = Request::create('/dashboard', 'GET');
    $request->setRouteResolver(fn () => $route);

    $recorder->record(
        Carbon::now(),
        $request,
        new Response('ok', 200),
    );

    expect(Pulse::wantsIngesting())->toBeTrue();
});

test('it ignores requests without a route', function () {
    $recorder = app(RequestCounts::class);

    $request = Request::create('/missing', 'GET');
    $request->setRouteResolver(fn () => null);

    $recorder->record(
        Carbon::now(),
        $request,
        new Response('not found', 404),
    );

    expect(Pulse::wantsIngesting())->toBeFalse();
});

test('it ignores admin panel requests', function () {
    $recorder = app(RequestCounts::class);

    $route = new Route('GET', '/admin', fn () => 'ok');

    $request = Request::create('/admin', 'GET');
    $request->setRouteResolver(fn () => $route);

    $recorder->record(
        Carbon::now(),
        $request,
        new Response('ok', 200),
    );

    expect(Pulse::wantsIngesting())->toBeFalse();
});

test('it ignores pulse dashboard requests', function () {
    $recorder = app(RequestCounts::class);

    $route = new Route('GET', '/pulse', fn () => 'ok');

    $request = Request::create('/pulse', 'GET');
    $request->setRouteResolver(fn () => $route);

    $recorder->record(
        Carbon::now(),
        $request,
        new Response('ok', 200),
    );

    expect(Pulse::wantsIngesting())->toBeFalse();
});
