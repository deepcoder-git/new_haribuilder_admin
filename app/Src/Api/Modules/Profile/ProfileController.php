<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\Profile;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Src\Api\Modules\Profile\Resources\ProfileResource;
use App\Utility\Response\ApiResponse;
use App\Utility\Response\ApiErrorResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Validator;


class ProfileController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    public function me(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            return new ApiResponse(
                isError: false,
                code: 200,
                message:'Profile fetched successfully.',
                data: new ProfileResource($user),
            );
        } catch(\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'get profile failed',
                500
            );
        }
    }

    public function logout(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $user = $request->user();
            $this->authService->logoutApi($user);

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [],
                message: __('auth.logout'),
            );
        } catch(\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'logout failed',
                500
            );
        }
        
    }

    public function editProfile(Request $request): ApiResponse|ApiErrorResponse
    {
        try {
            $request->validate([
                'name' => ['required', 'string'],
                'email' => ['required', 'email', 'unique:moderators,email,' . $request->user()->id],
            ]);

            $user = $request->user();
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            return new ApiResponse(data: [
                'profile' => new ProfileResource($user),
                'message' => 'Profile updated successfully.',
            ]);
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'profile update failed',
                500
            );
        }
    }

    public function changePassword(Request $request): ApiResponse|ApiErrorResponse
    {
        $validator = Validator::make($request->all(),[
            'current_password' => ['required'],
            'password' => ['required', 'max:100', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'same:password'],
        ]);

        if ($validator->fails()) {
            return new ApiErrorResponse(
                ['errors' => $validator->errors()],
                'change password failed',
                422
            );
        }

        try {
            $user = request()->user();

            $result = $this->authService->changePassword(
                $user,
                $request->current_password,
                $request->password
            );

            if(isset($result['hasError'])){
                return new ApiErrorResponse(
                    ['errors' => [$result['error']]],
                    'change password failed',
                    401
                );
            }

            return new ApiResponse(
                isError: false,
                code: 200,
                data: [],
                message:__('auth.password_update'),
            );
        } catch (\Exception $e) {
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'change password failed',
                500
            );
        }
    }
}
