<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Discount;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Permissions & system role templates -----------------------------------
        $permissions = [
            ['key' => 'orders.manage', 'label' => 'Manage order status', 'category' => 'orders'],
            ['key' => 'orders.refund', 'label' => 'Issue refunds', 'category' => 'orders'],
            ['key' => 'menu.edit', 'label' => 'Edit menu items', 'category' => 'menu'],
            ['key' => 'menu.86', 'label' => 'Toggle item availability', 'category' => 'menu'],
            ['key' => 'inventory.manage', 'label' => 'Manage inventory & stock', 'category' => 'inventory'],
            ['key' => 'reports.view', 'label' => 'View analytics & reports', 'category' => 'reports'],
            ['key' => 'staff.manage', 'label' => 'Manage staff & devices', 'category' => 'staff'],
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['key' => $p['key']], $p);
        }

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-coffee'],
            ['name' => 'Demo Coffee Co.', 'plan' => 'pro', 'default_currency' => 'USD', 'timezone' => 'America/New_York']
        );
        app()->instance('currentTenantId', $tenant->id);

        $branch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DT01'],
            ['name' => 'Downtown Branch', 'city' => 'Springfield', 'country' => 'USA', 'timezone' => 'America/New_York']
        );

        $roleDefs = [
            'owner' => array_column($permissions, 'key'),
            'manager' => ['orders.manage', 'orders.refund', 'menu.edit', 'menu.86', 'inventory.manage', 'reports.view', 'staff.manage'],
            'cashier' => ['orders.manage', 'menu.86'],
            'waiter' => ['orders.manage'],
            'kitchen' => ['orders.manage', 'menu.86'],
        ];

        $roles = [];
        foreach ($roleDefs as $name => $permKeys) {
            $role = Role::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name], ['is_system' => true]);
            $role->permissions()->sync(Permission::whereIn('key', $permKeys)->pluck('id'));
            $roles[$name] = $role;
        }

        $owner = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'owner@demo-coffee.test'],
            ['name' => 'Alex Owner', 'branch_id' => null, 'password_hash' => Hash::make('password'), 'pin_hash' => Hash::make('1234'), 'status' => 'active']
        );
        $owner->roles()->syncWithoutDetaching([$roles['owner']->id]);

        $cashier = User::firstOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'cashier@demo-coffee.test'],
            ['name' => 'Sam Cashier', 'branch_id' => $branch->id, 'password_hash' => Hash::make('password'), 'pin_hash' => Hash::make('1111'), 'status' => 'active']
        );
        $cashier->roles()->syncWithoutDetaching([$roles['cashier']->id]);

        // --- Demo menu ---------------------------------------------------------------
        $coffee = MenuCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Coffee'], ['sort_order' => 1]);
        $pastries = MenuCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Pastries'], ['sort_order' => 2]);

        $latte = MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'category_id' => $coffee->id, 'name' => 'Latte'],
            ['base_price' => 4.50, 'cost_price' => 1.20, 'prep_time_minutes' => 4]
        );
        $latte->variants()->firstOrCreate(['name' => 'Large'], ['price_delta' => 0.75]);

        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'category_id' => $coffee->id, 'name' => 'Espresso'],
            ['base_price' => 3.00, 'cost_price' => 0.60, 'prep_time_minutes' => 2]
        );

        MenuItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'category_id' => $pastries->id, 'name' => 'Croissant'],
            ['base_price' => 3.75, 'cost_price' => 1.10, 'prep_time_minutes' => 1]
        );

        Discount::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WELCOME10'],
            ['name' => 'Welcome 10% off', 'type' => 'percentage', 'value' => 10, 'is_active' => true]
        );

        $this->command->info("Seeded tenant '{$tenant->slug}'. Login: owner@demo-coffee.test / password (PIN 1234)");
    }
}
