<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // В реальном приложении здесь должна быть проверка прав доступа
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|min:2',
            'email' => [
                'required',
                'email',
                'max:190',
                'unique:moonshine_users,email'
            ],
            'password' => 'required|string|min:8|max:255',
            'role_id' => 'required|integer|exists:moonshine_user_roles,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Имя пользователя обязательно для заполнения',
            'name.min' => 'Имя пользователя должно содержать минимум 2 символа',
            'name.max' => 'Имя пользователя не должно превышать 255 символов',
            'email.required' => 'Email обязателен для заполнения',
            'email.email' => 'Введите корректный email адрес',
            'email.unique' => 'Пользователь с таким email уже существует',
            'email.max' => 'Email не должен превышать 190 символов',
            'password.required' => 'Пароль обязателен для заполнения',
            'password.min' => 'Пароль должен содержать минимум 8 символов',
            'password.max' => 'Пароль не должен превышать 255 символов',
            'role_id.required' => 'Роль пользователя обязательна для выбора',
            'role_id.integer' => 'ID роли должен быть числом',
            'role_id.exists' => 'Выбранная роль не существует',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'имя пользователя',
            'email' => 'email',
            'password' => 'пароль',
            'role_id' => 'роль пользователя',
        ];
    }
}
