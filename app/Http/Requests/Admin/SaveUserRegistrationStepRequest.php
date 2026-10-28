<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Registration\SaveRegistrationStepRequest;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class SaveUserRegistrationStepRequest extends SaveRegistrationStepRequest
{
    public function rules(): array
    {
        if ((int) $this->route('step') === 7) {
            return [
                'signature_confirmed' => ['sometimes', 'boolean'],
                'terms_accepted' => ['sometimes', 'boolean'],
            ];
        }

        return parent::rules();
    }

    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->subject();

        if (! $actor || ! $actor->isStaff()) {
            return false;
        }

        if (! $target->isMember() || $target->isStaff()) {
            throw new HttpResponseException(response()->json([
                'message' => 'ثبت‌نام فقط برای کاربران عادی ممکن است.',
            ], 422));
        }

        if ($target->registration_completed_at) {
            throw new HttpResponseException(response()->json([
                'message' => 'ثبت‌نام این کاربر قبلاً تکمیل شده است.',
            ], 422));
        }

        $this->assertStepOrder($target);

        return true;
    }

    protected function subject(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'اجازه تکمیل ثبت‌نام این کاربر را ندارید.',
        ], 403));
    }
}
