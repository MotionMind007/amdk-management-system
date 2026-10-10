<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_form_does_not_display_price_or_carton_conversion_fields(): void
    {
        $user = User::factory()->administrator()->create();
        Unit::factory()->create();

        $response = $this->actingAs($user)->get(route('products.create'));

        $response->assertSeeText('Pengaturan Stok');
        $response->assertDontSeeText('HPP');
        $response->assertDontSeeText('Harga Jual');
        $response->assertDontSee('name="purchase_price"', false);
        $response->assertDontSee('name="selling_price"', false);
        $response->assertDontSee('name="carton_quantity"', false);
    }

    public function test_administrator_can_create_product_without_price_or_carton_conversion(): void
    {
        $user = User::factory()->administrator()->create();
        $unit = Unit::factory()->create();

        $response = $this->actingAs($user)->post(route('products.store'), [
            'sku' => 'FG-330ML',
            'name' => 'Air Mineral 330 ml',
            'unit_id' => $unit->id,
            'type' => 'finished_good',
            'minimum_stock' => 10,
            'maximum_stock' => 100,
            'is_active' => true,
            'notes' => null,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'sku' => 'FG-330ML',
            'purchase_price' => 0,
            'selling_price' => 0,
        ]);
    }

    public function test_administrator_can_remove_product_from_master_without_deleting_history_record(): void
    {
        $user = User::factory()->administrator()->create();
        $product = Product::factory()->create(['name' => 'Produk Salah Input', 'is_active' => true]);

        $response = $this->actingAs($user)->delete(route('products.destroy', $product));

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'DELETE',
            'module' => 'Product',
            'entity_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->get(route('products.index'))
            ->assertDontSeeText('Produk Salah Input');
    }

    public function test_user_without_inventory_management_permission_cannot_delete_product(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);
        $product = Product::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->delete(route('products.destroy', $product))
            ->assertForbidden();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => true,
        ]);
    }
}
