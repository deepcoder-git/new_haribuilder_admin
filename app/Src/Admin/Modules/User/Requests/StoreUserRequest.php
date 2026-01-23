<?php

declare(strict_types=1);

namespace App\Src\Admin\Modules\User\Requests;

class StoreUserRequest
{
    protected bool $isEditMode;
    protected ?int $editingId;

    public function __construct(bool $isEditMode = false, ?int $editingId = null)
    {
        $this->isEditMode = $isEditMode;
        $this->editingId = $editingId;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile_number' => 'required|digits_between:10,15',
            'role' => 'required|string',
            'status' => 'boolean',
        ];

        if ($this->isEditMode) {
            $rules['email'] = 'required|email|max:255|unique:moderators,email,' . $this->editingId;
            $rules['mobile_number'] = 'required|digits_between:10,15|unique:moderators,mobile_number,' . $this->editingId;
            $rules['password'] = 'nullable|string|min:8|confirmed';
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
        } else {
            $rules['email'] = 'required|email|max:255|unique:moderators,email';
            $rules['mobile_number'] = 'required|digits_between:10,15|unique:moderators,mobile_number';
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['image'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
        }

        return $rules;
    }
}

