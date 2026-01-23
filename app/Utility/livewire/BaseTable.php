<?php

declare(strict_types=1);

namespace App\Utility\livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

abstract class BaseTable extends Component
{
    use WithPagination;

    protected array $resetQueryParams = ['query.search', 'query.perPage'];

    abstract protected function dataSource(): LengthAwarePaginator;

    public function updated($name): void
    {
        if (in_array($name, $this->resetQueryParams)) {
            $this->resetPage();
        }
    }
}
