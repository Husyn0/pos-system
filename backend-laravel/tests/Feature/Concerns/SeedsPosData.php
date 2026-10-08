<?php

namespace Tests\Feature\Concerns;

use App\Models\Branch;
use App\Models\Device;
use App\Models\InventoryItem;
use App\Models\ItemVariant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\RecipeIngredient;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Hand-built fixtures rather than formal Factory classes — deliberate for
 * this test suite: the business rules under test (stock locking, payment
 * idempotency, state transitions) care about *exact* quantities and
 * relationships between rows, which is clearer to read as explicit setup
 * than as randomized factory output that then gets overridden per test.
 */
trait SeedsPosData
{
    protected Tenant $tenant;
    protected Branch $branch;
    protected User $manager;

    protected function bootstrapTenant(): void
    {
        $this->tenant = Tenant::create(['name' => 'Test Coffee Co.', 'slug' => 'test-coffee-' . uniqid()]);
        app()->instance('currentTenantId', $this->tenant->id);

        $this->branch = Branch::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'M1']);

        foreach (['orders.manage', 'orders.refund', 'menu.edit', 'menu.86', 'inventory.manage', 'reports.view', 'staff.manage'] as $key) {
            Permission::firstOrCreate(['key' => $key], ['label' => $key]);
        }
        $role = Role::create(['tenant_id' => $this->tenant->id, 'name' => 'manager']);
        $role->permissions()->sync(Permission::pluck('id'));

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Test Manager',
            'email' => 'manager@test.local',
            'password_hash' => Hash::make('password'),
        ]);
        $this->manager->roles()->attach($role->id);
    }

    protected function makeMenuItemWithRecipe(string $name, float $price, array $ingredients = []): MenuItem
    {
        $category = MenuCategory::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Drinks']);
        $item = MenuItem::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => $name,
            'base_price' => $price,
            'track_inventory' => ! empty($ingredients),
        ]);

        foreach ($ingredients as [$inventoryItem, $qty]) {
            RecipeIngredient::create([
                'menu_item_id' => $item->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity_required' => $qty,
            ]);
        }

        return $item;
    }

    protected function makeInventoryItem(string $name, float $onHand, string $unit = 'g'): InventoryItem
    {
        return InventoryItem::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => $name,
            'unit' => $unit,
            'quantity_on_hand' => $onHand,
            'reorder_level' => 0,
        ]);
    }

    protected function makeDevice(): Device
    {
        return Device::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Test Register',
            'type' => 'pos_terminal',
            'device_token' => bin2hex(random_bytes(16)),
            'is_active' => true,
        ]);
    }
}
