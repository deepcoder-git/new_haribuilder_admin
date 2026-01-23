<?php

declare(strict_types=1);

namespace Resources\Panel\Components\Sidebar;

use App\Models\Moderator;
use App\Models\Module as ModuleModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Component;

class Modules extends Component
{
    public ?Moderator $user;

    public function __construct(public $modules = [])
    {
        // Check if modules table exists before querying
        if (Schema::hasTable('modules')) {
            try {
                $user = Auth::guard('moderator')->user();
                $this->modules = ModuleModel::getModulesByUser($user);
            } catch (\Exception $e) {
                $this->modules = collect([]);
            }
        } else {
            $this->modules = collect([]);
        }
        
        $this->user = Auth::guard('moderator')->user() ?: null;
    }

    public function render(): View
    {
        return view('panel::components.sidebar.modules', [
            'user' => $this->user,
            'modules' => $this->modules
        ]);
    }
}
