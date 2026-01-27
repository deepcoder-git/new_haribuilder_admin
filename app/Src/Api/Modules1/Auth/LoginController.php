<?php

declare(strict_types=1);

namespace App\Src\Api\Modules\Auth;

use App\Http\Controllers\Controller;
use App\Models\Moderator;
use App\Services\AuthService;
use App\Src\Api\Modules\Auth\Resources\LoginResource;
use App\Utility\Enums\RoleEnum;
use App\Utility\Response\ApiErrorResponse;
use App\Utility\Response\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    public function login(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $validator = Validator::make($request->all(),[
                'email' => [
                    'required',
                    function ($attribute, $value, $fail) {
                        $isEmail = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        $isPhone = preg_match('/^[0-9+\-\s()]+$/', $value) && strlen(preg_replace('/[^0-9]/', '', $value)) >= 10;
                        
                        if (!$isEmail && !$isPhone) {
                            $fail('The ' . $attribute . ' must be a valid email address or phone number.');
                        }
                    },
                ],
                // 'password' => ['required', 'max:100', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
                'password' => 'required',
            ]);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'login failed',
                    422
                );
            }


            $result = $this->authService->loginApi(
                $request->email,
                $request->password
            );

            if(isset($result['hasError'])){
                return new ApiErrorResponse(
                    ['errors' => [$result['error']]],
                    'login failed',
                    401
                );
            }

            $user = $result['user'];
            $user->token = $result['token'];
            
            return new ApiResponse(
                isError: false,
                code: 200,
                message:__('auth.logged_in'),
                data: new LoginResource($user),
            );
        } catch(\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'login failed',
                500
            );
        }
        
    }

    public function forgotPassword(Request $request): ApiResponse|ApiErrorResponse
    {
        try{
            $validator = Validator::make($request->all(),[
                'email' => ['required', 'email'],
            ]);

            if ($validator->fails()) {
                return new ApiErrorResponse(
                    ['errors' => $validator->errors()],
                    'forgot password failed',
                    422
                );
            }

            // Determine user role automatically based on the email
            $moderator = Moderator::where('email', $request->input('email'))->first();

            if (!$moderator || !$moderator->getRole()) {
                return new ApiErrorResponse(
                    ['errors' => ['User not found or role not assigned']],
                    'forgot password failed',
                    404
                );
            }

            $userType = $moderator->getRole();

            $resetUrl = app()->runningInConsole() ? null : route('admin.auth.reset-password');
            $result = $this->authService->sendPasswordResetLinkApi($request->email, $userType, $resetUrl);

            if(isset($result['hasError'])){
                return new ApiErrorResponse(
                    ['errors' => [$result['error']]],
                    'forgot password failed',
                    401
                );
            }
            
            return new ApiResponse(data: [
                'message' => 'Reset password link sent successfully.',
            ]);
        } catch(\Exception $e){
            return new ApiErrorResponse(
                ['errors' => [$e->getMessage()]],
                'forgot password failed',
                500
            );
        }
    }
}
