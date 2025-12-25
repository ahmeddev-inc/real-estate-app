<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        if (!$user) {
            abort(401, 'غير مصرح لك بالوصول');
        }
        
        if (!$user->hasPermission($permission)) {
            abort(403, 'غير مصرح لك بتنفيذ هذا الإجراء');
        }
        
        return $next($request);
    }
}
