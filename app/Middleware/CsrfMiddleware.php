<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Validates CSRF token on state-changing requests.
 */
class CsrfMiddleware
{
    public function handle(Request $request): bool
    {
        if ($request->isPost()) {
            $token = $request->input('_csrf') ?? $request->header('x-csrf-token');

            if (!Session::validateCsrf($token)) {
                if ($request->isAjax()) {
                    Response::json(['error' => 'CSRF token mismatch or expired. Please refresh the page.'], 403);
                } else {
                    Session::flash('error', 'Security token expired. Please try submitting again.');
                    Response::redirect(url('/'));
                }
                return false;
            }
        }

        return true;
    }
}
