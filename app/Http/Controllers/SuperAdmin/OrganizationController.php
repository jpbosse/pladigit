<?php
 
namespace App\Http\Controllers\SuperAdmin;
 
use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\TenantProvisioningService;
use Illuminate\Http\Request;
 
/**
 * Gestion des organisations depuis le panneau Super Admin.
 * Accès conditionné par les credentials .env (jamais en base).
 */
class OrganizationController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning
    ) {}
 
    public function index()
    {
        $orgs = Organization::orderBy('name')->paginate(25);
        return view('super-admin.organizations.index', compact('orgs'));
    }
 
    public function create()
    {
        return view('super-admin.organizations.create');
    }
 
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'slug'      => ['required', 'alpha_dash', 'unique:organizations'],
            'plan'      => ['required', 'in:free,starter,standard,enterprise'],
            'max_users' => ['required', 'integer', 'min:1'],
        ]);
 
        $validated['db_name'] = Organization::dbNameFromSlug($validated['slug']);
 
        $org = Organization::create($validated);
 
        // Créer physiquement la base de données du tenant
        $this->provisioning->provisionTenant($org);
 
        return redirect()
            ->route('super-admin.organizations.index')
            ->with('success', "Organisation {$org->name} créée et provisionnée.");
    }
 
    public function suspend(Organization $organization)
    {
        $organization->update(['status' => 'suspended']);
        return back()->with('success', 'Organisation suspendue.');
    }
 
    public function activate(Organization $organization)
    {
        $organization->update(['status' => 'active']);
        return back()->with('success', 'Organisation réactivée.');
    }
}
