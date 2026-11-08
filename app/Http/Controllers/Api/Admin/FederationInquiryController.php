<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Rules\IranianNationalCode;
use App\Services\FederationMembershipClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FederationInquiryController extends Controller
{
    public function __invoke(Request $request, FederationMembershipClient $federation): JsonResponse
    {
        $data = $request->validate([
            'national_code' => ['required', 'string', new IranianNationalCode],
        ], [
            'national_code.required' => 'کد ملی را وارد کنید.',
        ]);

        try {
            $result = $federation->inquire($data['national_code']);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        if (! ($result['found'] ?? false)) {
            return response()->json([
                'message' => 'این کد ملی در فدراسیون ثبت نشده است.',
                'found' => false,
            ]);
        }

        return response()->json([
            'message' => 'اطلاعات فدراسیون دریافت شد.',
            'found' => true,
            'inquiry' => $result,
        ]);
    }
}
