<?php
 
namespace App\Http\Middleware;
 
use App\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
 
class ResolveTenant
{
    public function __construct(private TenantManager $tenantManager) {}
 
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $this->tenantManager->resolveFromRequest($request->getHost());
        } catch (ModelNotFoundException) {
            // Tenant non trouvé — on continue sans bloquer
            // Le middleware 'tenant' sur les routes bloquera si nécessaire
        } catch (\Throwable) {
            // Pas de base configurée — on continue
        }
 
        return $next($request);
    }
}
