<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFormsRequest;
use App\Http\Requests\UpdateFormsRequest;
use App\Models\Forms;
use App\Http\Controllers\Controller;

class FormsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormsRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Forms $forms)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Forms $forms)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormsRequest $request, Forms $forms)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Forms $forms)
    {
        //
    }
}
