<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard/Index', [
            'lists' => auth()->user()->lists()->orderBy('position')->get(),
        ]);
    }
}
