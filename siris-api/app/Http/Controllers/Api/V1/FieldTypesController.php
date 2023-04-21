<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFieldTypesRequest;
use App\Http\Requests\UpdateFieldTypesRequest;
use App\Models\FieldTypes;
use App\Http\Controllers\Controller;
use App\Http\Resources\FieldTypeResource;

class FieldTypesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return FieldTypeResource::collection(FieldTypes::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFieldTypesRequest $request)
    {
        $fieldType = FieldTypes::create($request->validated());

        return FieldTypeResource::make($fieldType);
    }

    /**
     * Display the specified resource.
     */
    public function show(FieldTypes $fieldType)
    {
        return FieldTypeResource::make($fieldType);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FieldTypes $fieldTypes)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFieldTypesRequest $request, FieldTypes $fieldTypes)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FieldTypes $fieldTypes)
    {
        //
    }
}
