<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Google\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

function stripEmptyValueFromArray(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = stripEmptyValueFromArray($value);
        }
        if (empty($value)) {
            unset($data[$key]);
        }
    }

    return $data;
}

function flashAlert(string $message, string $type = 'success' | 'danger' | 'warning'): void
{
    if (! in_array($type, ['danger', 'success', 'warning'])) {
        throw new Error('Invalid Alert Type Provided.');
    }
    session()->flash('alert', array_merge(session()->get('alert', []), [
        ['type' => $type, 'message' => $message],
    ]));
}

function cacheCallBack($key, callable $callback)
{
    $time = app()->isLocal() ? now()->addSeconds(5) : now()->addMinutes(10);

    return Cache::remember($key, $time, $callback);
}

function echoMoney($amount): string
{
    return '₹' . number_format((float)$amount, 2, '.', ',');
}

function echoDate($date): string
{
    if ($date) {
        return date('d-m-Y', strtotime($date));
    }

    return '';
}

function getSessionId()
{
    return session('session_id');
}

/**
 * @throws Exception
 */
function getPlaceHolderImage($type = 'user'): string
{
    return match ($type) {
        'user' => mix('build/panel/images/user.png')->toHtml(),
    };
}


function getAccessToken()
{
    $client = new Client();
    $client->setAuthConfig(storage_path('app/firebase/service-account.json'));
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithAssertion();
    }

    return $client->getAccessToken()['access_token'];
}

function sendPushNotification($registrationIDs = array(), $fcmMsg = '', $send_payload = NULL)
{

    // Log::info('registrationIDs: ' . $registrationIDs);
    $projectId = env('FIREBASE_PROJECT_ID'); // Replace with your Firebase project ID
    $accessToken = getAccessToken(); // Get OAuth token
    Log::info('Firebase Access Token: ' . $accessToken);
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";


    // $registrationIDs_chunks = array_chunk($registrationIDs, 1000);

    $unregisteredIDs = array(); // Array to store unregistered FCM IDs

    if (!count($registrationIDs)) {
        Log::error('No valid registration IDs found.');
        return false;
    }





    foreach ($registrationIDs as $registrationIDsChunk) {

        if ($send_payload == 1) {

            $fcmFields =   [
                "message" => [
                    "token" => $registrationIDsChunk,
                    "notification" => [
                        "title" => $fcmMsg['title'],
                        "body" => $fcmMsg['body']
                    ],
                    "data" => array_map('strval', $fcmMsg),
                    "android" => [
                        "priority" => "high"
                    ],
                    "apns" => [
                        "headers" => [
                            "apns-priority" => "10"
                        ]
                    ]
                ]
            ];
        } else {

            $fcmFields =   [
                "message" => [
                    "token" => $registrationIDsChunk,
                    "notification" => [
                        "title" => $fcmMsg['title'],
                        "body" => $fcmMsg['body']
                    ],
                    "data" => array_map('strval', $fcmMsg),
                    "android" => [
                        "priority" => "high"
                    ],
                    "apns" => [
                        "headers" => [
                            "apns-priority" => "10"
                        ]
                    ]
                ]
            ];
        }
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];



        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmFields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $get_result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($get_result, true);



        Log::info('Firebase HTTP Response Code: ' . $http_code);
        Log::info('Firebase Response: ' . json_encode($result));

        if ($http_code !== 200) {
            Log::error('FCM push failed with HTTP Code: ' . $http_code);
            return false;
        }

        // Check for unregistered FCM IDs in the response
        if (isset($result['error']) && ($result['error'] == 'NotRegistered' || $result['error'] == 'UNREGISTERED')) {
            $unregisteredIDs[] = $registrationID;
        }
    }

    // if (!empty($unregisteredIDs)) {
    //     Usertokens::whereIn('fcm_id', $unregisteredIDs)->delete();
    // }
    return $result;
}
function sendWhatsappTextMessage($message, $numbers, $sessionName)
{
    // dd($message, $numbers, $sessionName, config('services.whatsapp.url') . '/send');
    try {
        $baseUrl = config('services.whatsapp.url');
        $apiKey  = config('services.whatsapp.key');
        $headers = [
            'x-api-key' => $apiKey,
        ];
        $response = Http::withHeaders($headers)
            ->post($baseUrl . "/send", [
                'session' => $sessionName,
                'type' => 'text',
                'numbers' => $numbers,
                'message' => $message,
            ]);
        if ($response->successful()) {
            $response = $response->json() ?? [];
            $isSuccess = $response['success'] ?? false;
            return $isSuccess ? true : false;
        }
        Log::error('Send Whatsapp message failed: ', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    } catch (Exception $e) {
        Log::error('Exception - Send message failed: ', [
            'message' => $e->getMessage(),
        ]);
        return false;
    }
}

function sendBulkWhatsappMsg($payload)
{
    // dd($payload, config('services.whatsapp.url') . '/send-bulk-msg');
    try {
        $baseUrl = config('services.whatsapp.url');
        $apiKey  = config('services.whatsapp.key');
        $headers = [
            'x-api-key' => $apiKey,
        ];
        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->connectTimeout(30)
            ->post($baseUrl . "/send-bulk-msg", $payload);
        if ($response->successful()) {

            $responseData = $response->json() ?? [];

            $results = $responseData['results'] ?? [];
            $totalSent = collect($results)->where('success', true)->count();
            $totalFailed = collect($results)->where('success', false)->count();

            // Gather failed error messages
            $failedDetails = collect($results)
                ->where('success', false)
                ->map(fn($r) => ($r['error'] ?? 'Unknown error'))
                ->implode(', ');

            if ($totalFailed === 0) {
                flashAlert("All {$totalSent} messages sent successfully!", 'success');
            } elseif ($totalSent > 0) {
                flashAlert("{$totalSent} messages sent successfully. {$totalFailed} failed: {$failedDetails}", 'warning');
            } else {
                flashAlert("All {$totalFailed} messages failed: {$failedDetails}", 'danger');
            }

            return true;
        }
        Log::error('Send Whatsapp message failed: ', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    } catch (Exception $e) {
        Log::error('Exception - Send message failed: ', [
            'message' => $e->getMessage(),
        ]);
        return false;
    }
}

function hasPermission($permissions, $modulePermissions) {
    foreach ($modulePermissions as $permission) {
        if ($permissions[$permission] === 1) {
            return true;
        }
    }
    return false;
}

function maskEmail(string $email): string
{
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    
    $parts = explode('@', $email);
    $localPart = $parts[0];
    $domain = $parts[1] ?? '';
    
    if (strlen($localPart) <= 2) {
        $maskedLocal = str_repeat('*', strlen($localPart));
    } else {
        $maskedLocal = substr($localPart, 0, 1) . str_repeat('*', max(3, strlen($localPart) - 2)) . substr($localPart, -1);
    }
    
    if (empty($domain)) {
        return $maskedLocal;
    }
    
    $domainParts = explode('.', $domain);
    $domainName = $domainParts[0];
    $extension = implode('.', array_slice($domainParts, 1));
    
    if (strlen($domainName) <= 2) {
        $maskedDomain = str_repeat('*', strlen($domainName));
    } else {
        $maskedDomain = substr($domainName, 0, 1) . str_repeat('*', max(2, strlen($domainName) - 2)) . substr($domainName, -1);
    }
    
    return $maskedLocal . '@' . $maskedDomain . ($extension ? '.' . $extension : '');
}

function maskPhone(string $phone): string
{
    if (empty($phone)) {
        return $phone;
    }
    
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    if (strlen($phone) <= 4) {
        return str_repeat('*', strlen($phone));
    }
    
    $visibleStart = 2;
    $visibleEnd = 2;
    $maskedLength = strlen($phone) - $visibleStart - $visibleEnd;
    
    if ($maskedLength <= 0) {
        return str_repeat('*', strlen($phone));
    }
    
    return substr($phone, 0, $visibleStart) . str_repeat('*', $maskedLength) . substr($phone, -$visibleEnd);
}

function maskString(string $value, int $visibleStart = 2, int $visibleEnd = 2): string
{
    if (empty($value)) {
        return $value;
    }
    
    $length = strlen($value);
    
    if ($length <= ($visibleStart + $visibleEnd)) {
        return str_repeat('*', $length);
    }
    
    return substr($value, 0, $visibleStart) . str_repeat('*', $length - $visibleStart - $visibleEnd) . substr($value, -$visibleEnd);
}

function getFileIcon(string $fileName): array
{
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    return match(true) {
        in_array($ext, ['pdf']) => ['class' => 'fa-file-pdf', 'color' => '#ef4444'],
        in_array($ext, ['xls', 'xlsx', 'csv']) => ['class' => 'fa-file-excel', 'color' => '#16a34a'],
        in_array($ext, ['doc', 'docx']) => ['class' => 'fa-file-word', 'color' => '#2563eb'],
        in_array($ext, ['jpg', 'jpeg', 'png']) => ['class' => 'fa-file-image', 'color' => '#7c3aed'],
        default => ['class' => 'fa-file', 'color' => '#6b7280'],
    };
}

function formatQty($value): string
{
    if ($value === null || $value === '') {
        return '0';
    }
    
    $floatValue = (float) $value;
    
    // If it's effectively an integer (10.00, 10.0), display as integer (10)
    if (abs($floatValue - round($floatValue)) < 0.0000001) {
        return (string) (int) round($floatValue);
    }
    
    // Otherwise, trim trailing zeros (e.g., 10.50 -> 10.5, 10.00 -> 10)
    return rtrim(rtrim(number_format($floatValue, 2, '.', ''), '0'), '.');
}

/**
 * Calculate product stock status (low stock and out of stock)
 * 
 * @param int $totalStock Total stock quantity
 * @param int|null $lowStockThreshold Low stock threshold value (null if not set)
 * @return array Returns array with 'low_stock' and 'out_of_stock' keys (1 or 0)
 */
function calculateStockStatus(int $totalStock, ?int $lowStockThreshold): array
{
    $isOutOfStock = $totalStock <= 0;
    $isLowStock = !$isOutOfStock && $lowStockThreshold !== null && $lowStockThreshold > 0 && $totalStock <= $lowStockThreshold;
    
    return [
        'low_stock' => $isLowStock ? 1 : 0,
        'out_of_stock' => $isOutOfStock ? 1 : 0,
    ];
}

/**
 * Format date for API responses with relative labels (Today, Yesterday) or formatted date
 * 
 * @param mixed $date Carbon instance, string, or null
 * @param string $format Date format for non-relative dates (default: 'd/m/Y')
 * @param bool $showRelative Whether to show Today/Yesterday labels (default: true)
 * @return string|null
 */
function formatApiDate($date, string $format = 'd/m/Y', bool $showRelative = true): ?string
{
    if (!$date) {
        return null;
    }

    $dateObj = $date instanceof Carbon 
        ? $date->copy() 
        : Carbon::parse($date);
    
    if ($showRelative) {
        $dateObj->startOfDay();
        $today = Carbon::today()->startOfDay();
        $yesterday = Carbon::yesterday()->startOfDay();

        $dateString = $dateObj->toDateString();

        if ($dateString === $today->toDateString()) {
            return 'Today';
        } elseif ($dateString === $yesterday->toDateString()) {
            return 'Yesterday';
        }
    }

    return $dateObj->format($format);
}

/**
 * Format datetime for API responses
 * 
 * @param mixed $date Carbon instance, string, or null
 * @param string $format DateTime format (default: 'd/m/Y H:i')
 * @return string|null
 */
function formatApiDateTime($date, string $format = 'd/m/Y H:i'): ?string
{
    if (!$date) {
        return null;
    }

    $dateObj = $date instanceof Carbon 
        ? $date->copy() 
        : Carbon::parse($date);

    return $dateObj->format($format);
}
