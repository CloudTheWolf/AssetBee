<?php

namespace App\Http\Middleware;

use App\Models\OrganizationApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrganizationApiKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextKey = $request->bearerToken() ?: $request->header('X-API-Key');

        if (! is_string($plainTextKey) || $plainTextKey === '') {
            return $this->unauthorized();
        }

        $apiKey = OrganizationApiKey::query()
            ->with('organization')
            ->where('key_hash', hash('sha256', $plainTextKey))
            ->whereNull('revoked_at')
            ->first();

        if ($apiKey === null) {
            return $this->unauthorized();
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('organization', $apiKey->organization);
        $request->attributes->set('organizationApiKey', $apiKey);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'message' => 'A valid organization API key is required.',
        ], 401);
    }
}
