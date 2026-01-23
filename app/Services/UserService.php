<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Moderator;

class UserService extends BaseCrudService
{
    protected function getModelClass(): string
    {
        return Moderator::class;
    }

    protected function getCreateRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:moderators,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string',
            'status' => 'boolean',
        ];
    }

    protected function getUpdateRules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:moderators,email,' . request()->route('id'),
            'role' => 'required|string',
            'status' => 'boolean',
        ];
        
        if (request()->has('password') && request()->input('password')) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }
        
        return $rules;
    }

    protected function prepareCreateData(array $data): array
    {
        $data['type'] = 'moderator';
        return $data;
    }
}

