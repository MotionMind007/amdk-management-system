<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductComposition;
use App\Models\User;
use App\ProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompositionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_composition_form_displays_controls_to_add_and_remove_materials(): void
    {
        $user = User::factory()->administrator()->create();
        $finishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        Product::factory()->create(['name' => 'Tutup Botol', 'type' => ProductType::PackagingMaterial]);

        $response = $this->actingAs($user)->get(route('production.compositions.edit', $finishedProduct));

        $response->assertSeeText('+ Tambah Bahan');
        $response->assertSeeText('Hapus');
        $response->assertSee('data-repeater', false);
        $response->assertSee('data-repeater-add', false);
        $response->assertSee('data-repeater-remove', false);
    }

    public function test_duplicate_materials_are_rejected_with_a_clear_message(): void
    {
        $user = User::factory()->administrator()->create();
        $finishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $existingMaterial = Product::factory()->create(['type' => ProductType::RawMaterial]);
        $duplicateMaterial = Product::factory()->create(['type' => ProductType::PackagingMaterial]);
        ProductComposition::create([
            'product_id' => $finishedProduct->id,
            'component_product_id' => $existingMaterial->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($user)
            ->from(route('production.compositions.edit', $finishedProduct))
            ->put(route('production.compositions.update', $finishedProduct), [
                'components' => [
                    ['product_id' => $duplicateMaterial->id, 'quantity' => 10],
                    ['product_id' => $duplicateMaterial->id, 'quantity' => 5],
                ],
            ]);

        $response->assertRedirect(route('production.compositions.edit', $finishedProduct));
        $response->assertSessionHasErrors([
            'components.0.product_id' => 'Bahan yang sama tidak boleh ditambahkan lebih dari satu kali.',
            'components.1.product_id' => 'Bahan yang sama tidak boleh ditambahkan lebih dari satu kali.',
        ]);
        $this->assertDatabaseHas('product_compositions', [
            'product_id' => $finishedProduct->id,
            'component_product_id' => $existingMaterial->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseCount('product_compositions', 1);
    }

    public function test_inactive_material_cannot_be_added_to_a_composition(): void
    {
        $user = User::factory()->administrator()->create();
        $finishedProduct = Product::factory()->create(['type' => ProductType::FinishedGood]);
        $inactiveMaterial = Product::factory()->create([
            'type' => ProductType::RawMaterial,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('production.compositions.edit', $finishedProduct))
            ->put(route('production.compositions.update', $finishedProduct), [
                'components' => [
                    ['product_id' => $inactiveMaterial->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('production.compositions.edit', $finishedProduct));
        $response->assertSessionHasErrors([
            'components.0.product_id' => 'Bahan yang dipilih tidak tersedia.',
        ]);
        $this->assertDatabaseCount('product_compositions', 0);
    }
}
