<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrganizationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'uid' => 'required|string|size:12|alpha_num|uppercase|unique:organizations,uid',
            'website' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'roles' => 'nullable|array',
            'roles.*.name' => 'required|string|max:255',
            'roles.*.permissions' => 'nullable|array',
            'roles.*.permissions.*' => 'required|string|exists:permissions,key',
        ];
    }
}
