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
	    } catch (\Throwable) {
	        // Pas de tenant — on déconnecte silencieusement pour éviter
	        // que Laravel recharge un User depuis une connexion sans base
	        \Illuminate\Support\Facades\Auth::forgetGuards();
	        config(['auth.defaults.guard' => 'super-admin']);
	    }

	    return $next($request);
	}

}
