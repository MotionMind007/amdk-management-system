<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_supplier_data_is_normalized_before_storage(): void
    {
        $user = User::factory()->administrator()->create();

        $this->actingAs($user)->post(route('suppliers.store'), [
            'supplier_code' => ' sup-001 ',
            'name' => ' Supplier Papua ',
            'email' => ' SUPPLIER@EXAMPLE.COM ',
            'payment_term' => 14,
            'status' => 'active',
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::query()->firstOrFail();
        $this->assertSame('SUP-001', $supplier->supplier_code);
        $this->assertSame('Supplier Papua', $supplier->name);
        $this->assertSame('supplier@example.com', $supplier->email);
    }

    public function test_supplier_code_is_unique_after_normalization(): void
    {
        $user = User::factory()->administrator()->create();
        Supplier::factory()->create(['supplier_code' => 'SUP-001']);

        $this->actingAs($user)
            ->from(route('suppliers.create'))
            ->post(route('suppliers.store'), [
                'supplier_code' => ' sup-001 ',
                'name' => 'Supplier Duplikat',
                'payment_term' => 0,
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['supplier_code' => 'Kode supplier sudah digunakan.']);

        $this->assertDatabaseCount('suppliers', 1);
    }
}
