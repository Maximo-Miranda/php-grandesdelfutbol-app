<?php

namespace App\Http\Controllers;

use App\Http\Requests\Field\StoreFieldRequest;
use App\Http\Requests\Field\UpdateFieldRequest;
use App\Models\Club;
use App\Models\Field;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;

class FieldController extends Controller
{
    public function store(StoreFieldRequest $request, Club $club, Venue $venue): RedirectResponse
    {
        $venue->fields()->create($request->validated());

        return redirect()->back()->with('success', 'Field added.');
    }

    public function update(UpdateFieldRequest $request, Club $club, Venue $venue, Field $field): RedirectResponse
    {
        $field->update($request->validated());

        return redirect()->back()->with('success', 'Field updated.');
    }
}
