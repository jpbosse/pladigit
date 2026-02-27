<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Platform\Organization;
use App\Services\TenantManager;
use App\Services\TenantProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OrganizationController extends Controller
{
    public function __construct(
        private TenantProvisioningService $provisioning,
        private TenantManager $tenantManager,
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'unique:organizations'],
            'plan' => ['required', 'in:free,starter,standard,enterprise'],
        ]);
        $validated['db_name'] = Organization::dbNameFromSlug($validated['slug']);
        $validated['max_users'] = $this->maxUsersFromPlan($validated['plan']);
        $org = Organization::create($validated);

        $this->provisioning->provisionTenant($org);

        return redirect()
            ->route('super-admin.organizations.show', $org)
            ->with('success', "Organisation {$org->name} créée. Créez maintenant le premier administrateur.");
    }

    public function show(Organization $organization)
    {
        // Compter les utilisateurs du tenant
        $userCount = 0;
        try {
            $this->tenantManager->connectTo($organization);
            $userCount = \DB::connection('tenant')->table('users')->count();
        } catch (\Throwable) {
        }

        return view('super-admin.organizations.show', compact('organization', 'userCount'));
    }

    public function edit(Organization $organization)
    {
        return view('super-admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['required', 'in:free,starter,standard,enterprise'],
            'status' => ['required', 'in:active,suspended,pending'],
        ]);
        $validated['max_users'] = $this->maxUsersFromPlan($validated['plan']);
        $organization->update($validated);

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', 'Organisation mise à jour.');
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

    public function createAdmin(Request $request, Organization $organization)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
        ]);

        $this->tenantManager->connectTo($organization);

        \App\Models\Tenant\User::updateOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password_hash' => Hash::make($request->password),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        return redirect()
            ->route('super-admin.organizations.show', $organization)
            ->with('success', "Administrateur {$request->email} créé.");
    }

    private function maxUsersFromPlan(string $plan): int
    {
        return match ($plan) {
            'free' => 5,
            'starter' => 50,
            'standard' => 200,
            'enterprise' => 9999,
            default => 50,
        };
    }
}
