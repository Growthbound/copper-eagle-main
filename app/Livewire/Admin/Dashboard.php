<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.admin.dashboard', [
            'newLeads' => Lead::query()->where('status', 'new')->count(),
            'totalLeads' => Lead::query()->count(),
            'mediaCount' => Media::query()->count(),
            'recentLeads' => Lead::query()->latest()->limit(5)->get(),
        ]);
    }
}
