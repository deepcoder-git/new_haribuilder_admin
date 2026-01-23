<?php

declare(strict_types=1);

namespace App\Utility\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

abstract class BaseViewComponent extends Component
{
    protected $model;

    abstract protected function getModelClass(): string;

    abstract protected function getModelVariableName(): string;

    abstract protected function getModuleName(): string;

    abstract protected function getIndexRouteName(): string;

    abstract protected function getViewRouteName(): string;

    protected function getEditRouteName(): ?string
    {
        $viewRoute = $this->getViewRouteName();
        // Convert 'admin.materials.view' to 'admin.materials.edit'
        $editRoute = str_replace('.view', '.edit', $viewRoute);
        // Check if route exists
        if (\Illuminate\Support\Facades\Route::has($editRoute)) {
            return $editRoute;
        }
        return null;
    }

    protected function getRelations(): array
    {
        return [];
    }

    protected function getIcon(): string
    {
        return 'box';
    }

    public function mount(...$args)
    {
        $modelClass = $this->getModelClass();
        $modelVariableName = $this->getModelVariableName();
        $relations = $this->getRelations();

        foreach ($args as $arg) {
            if (is_object($arg) && is_a($arg, $modelClass)) {
                $this->model = $arg;
                if (!empty($relations)) {
                    $this->model->load($relations);
                }
                return;
            }
        }

        $route = request()->route();
        if ($route) {
            $routeParams = $route->parameters();
            
            if (isset($routeParams[$modelVariableName]) && is_object($routeParams[$modelVariableName]) && is_a($routeParams[$modelVariableName], $modelClass)) {
                $this->model = $routeParams[$modelVariableName];
                if (!empty($relations)) {
                    $this->model->load($relations);
                }
                return;
            }

            foreach ($routeParams as $param) {
                if (is_object($param) && is_a($param, $modelClass)) {
                    $this->model = $param;
                    if (!empty($relations)) {
                        $this->model->load($relations);
                    }
                    return;
                }
            }
        }

        $id = $args[0] ?? null;
        if ($id === null) {
            throw new \InvalidArgumentException('No model or ID provided to mount method');
        }

        if (!empty($relations)) {
            $this->model = $modelClass::with($relations)->where(function($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                } else {
                    $query->where('slug', $id);
                }
            })->firstOrFail();
        } else {
            $this->model = $modelClass::where(function($query) use ($id) {
                if (is_numeric($id)) {
                    $query->where('id', $id);
                } else {
                    $query->where('slug', $id);
                }
            })->firstOrFail();
        }
    }

    public function render(): View
    {
        try {
            $breadcrumbUrl = route($this->getIndexRouteName());
        } catch (\Exception $e) {
            $breadcrumbUrl = '#';
        }

        $editRoute = $this->getEditRouteName();
        $editId = $this->model->id ?? $this->model->slug ?? null;

        $viewData = [
            $this->getModelVariableName() => $this->model,
            'moduleName' => $this->getModuleName(),
            'moduleIcon' => $this->getIcon(),
            'indexRoute' => $this->getIndexRouteName(),
            'editRoute' => $editRoute,
            'editId' => $editId,
        ];

        $additionalData = $this->getAdditionalViewData();
        if (is_array($additionalData)) {
            $viewData = array_merge($viewData, $additionalData);
        }

        return view($this->getViewName(), $viewData)->layout('panel::layout.app', [
            'title' => 'View ' . $this->getModuleName(),
            'breadcrumb' => [
                [$this->getModuleName() . ' Management', $breadcrumbUrl],
                ['View ' . $this->getModuleName(), '#'],
            ],
        ]);
    }

    protected function getViewName(): string
    {
        $moduleName = $this->getModuleName();
        return 'admin::' . $moduleName . '.views.view';
    }

    protected function getAdditionalViewData(): array
    {
        return [];
    }
}

