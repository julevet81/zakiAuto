<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectCustomerUsers
{
    /**
     * Block legacy customer user accounts from authenticated system routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasRole('customer')) {
            return response()->json([
                'message' => 'العملاء لا يملكون صلاحية الدخول إلى النظام. يرجى استخدام الطلبات العامة فقط.',
            ], 403);
        }

        return $next($request);
    }
}
