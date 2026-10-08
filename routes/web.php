<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OpeningStockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManualCashTransactionController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleHubController;
use App\Http\Controllers\ProductCompositionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/modules');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/profile', ProfileController::class)->name('profile.show');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('account.password.update');
    Route::get('/modules', ModuleHubController::class)->name('modules.index');
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
    Route::get('/modules/{module}', ModuleController::class)
        ->whereIn('module', array_keys(config('modules')))
        ->name('modules.show');

    Route::middleware('permission:customers.view')->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });
    Route::resource('customers', CustomerController::class)->except(['index', 'show'])->middleware('permission:customers.manage');

    Route::middleware('permission:suppliers.view')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    });
    Route::resource('suppliers', SupplierController::class)->except(['index', 'show'])->middleware('permission:suppliers.manage');

    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    });
    Route::resource('products', ProductController::class)->except(['index', 'show'])->middleware('permission:inventory.manage');
    Route::middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/inventory/adjustment/create', [InventoryAdjustmentController::class, 'create'])->name('inventory.adjustment.create');
        Route::post('/inventory/adjustment', [InventoryAdjustmentController::class, 'store'])->name('inventory.adjustment.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('permission:system.manage')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');
        Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');
        Route::resource('opening-stocks', OpeningStockController::class)->except(['show', 'destroy']);
        Route::post('/opening-stocks/{openingStock}/post', [OpeningStockController::class, 'post'])->name('opening-stocks.post');
    });

    Route::resource('employees', EmployeeController::class)
        ->except(['show', 'destroy'])
        ->middleware('permission:system.manage');

    Route::get('/production', [ProductionController::class, 'index'])->middleware('permission:production.view')->name('production.index');
    Route::middleware('permission:production.manage')->group(function (): void {
        Route::get('/production/create', [ProductionController::class, 'create'])->name('production.create');
        Route::post('/production', [ProductionController::class, 'store'])->name('production.store');
        Route::get('/production/{production}/edit', [ProductionController::class, 'edit'])->name('production.edit');
        Route::put('/production/{production}', [ProductionController::class, 'update'])->name('production.update');
        Route::post('/production/{production}/post', [ProductionController::class, 'post'])->name('production.post');
        Route::get('/production/compositions/{product}', [ProductCompositionController::class, 'edit'])->name('production.compositions.edit');
        Route::put('/production/compositions/{product}', [ProductCompositionController::class, 'update'])->name('production.compositions.update');
    });

    Route::get('/purchasing', [PurchaseOrderController::class, 'index'])->middleware('permission:purchasing.view')->name('purchasing.index');
    Route::middleware('permission:purchasing.manage')->group(function (): void {
        Route::get('/purchasing/create', [PurchaseOrderController::class, 'create'])->name('purchasing.create');
        Route::post('/purchasing', [PurchaseOrderController::class, 'store'])->name('purchasing.store');
        Route::post('/purchasing/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchasing.approve');
        Route::get('/purchasing/{purchaseOrder}/receive', [GoodsReceiptController::class, 'create'])->name('purchasing.receive.create');
        Route::post('/purchasing/{purchaseOrder}/receive', [GoodsReceiptController::class, 'store'])->name('purchasing.receive.store');
    });

    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::middleware('permission:sales.manage')->group(function (): void {
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::post('/sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post');
    });

    Route::get('/finance', [FinanceController::class, 'index'])->middleware('permission:finance.view')->name('finance.index');
    Route::middleware('permission:finance.manage')->group(function (): void {
        Route::get('/finance/transactions/create', [ManualCashTransactionController::class, 'create'])->name('finance.transactions.create');
        Route::post('/finance/transactions', [ManualCashTransactionController::class, 'store'])->name('finance.transactions.store');
    });
    Route::middleware('permission:sales.manage,finance.manage')->group(function (): void {
        Route::get('/finance/receivables/{sale}/payment', [CustomerPaymentController::class, 'create'])->name('finance.customer-payment.create');
        Route::post('/finance/receivables/{sale}/payment', [CustomerPaymentController::class, 'store'])->name('finance.customer-payment.store');
    });
    Route::middleware('permission:purchasing.manage,finance.manage')->group(function (): void {
        Route::get('/finance/payables/{purchaseOrder}/payment', [SupplierPaymentController::class, 'create'])->name('finance.supplier-payment.create');
        Route::post('/finance/payables/{purchaseOrder}/payment', [SupplierPaymentController::class, 'store'])->name('finance.supplier-payment.store');
    });
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
});
