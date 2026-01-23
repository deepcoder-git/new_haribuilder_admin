<?php

declare(strict_types=1);

namespace App\Utility\livewire;

use Livewire\Attributes\Url;
use Livewire\Form;

class TableForm extends Form
{
    #[Url('search')]
    public string $search = '';

    #[Url('page')]
    public int $page = 1;

    #[Url('per-page')]
    public int $perPage = 10;
}
