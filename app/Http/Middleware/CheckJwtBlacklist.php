<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckJwtBlacklist
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->header('Authorization', '');
        if ($authorization !== '' && str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);
            $tokenHash = hash('sha256', $token);
            $isBlacklisted = Cache::get('jwt_blacklist_' . $tokenHash, false);
            if ($isBlacklisted) {
                return response()->json(['msg' => 'Token has been revoked'], 401);
            }
        }

        return $next($request);
    }
}


