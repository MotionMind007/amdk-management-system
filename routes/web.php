<?php

use App\Http\Controllers\AccountingPeriodController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\DataImportController;
use App\Http\Controllers\Admin\OpeningStockController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CashReconciliationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DeliveryNotePrintController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinanceAgingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\InventoryAdjustmentController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryTransferController;
use App\Http\Controllers\ManualCashTransactionController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ModuleHubController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\ProductCompositionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderPrintController;
use App\Http\Controllers\PurchaseReceiptInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\PurchaseReturnPrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalesInvoiceController;
use App\Http\Controllers\SalesReturnController;
use App\Http\Controllers\SalesReturnPrintController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\SupplierInvoicePrintController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\WarehouseController;
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
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
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
        Route::get('/inventory/opnames', [StockOpnameController::class, 'index'])->name('inventory.opnames.index');
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    });
    Route::resource('products', ProductController::class)->except(['index', 'show'])->middleware('permission:inventory.manage');
    Route::middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/inventory/adjustment/create', [InventoryAdjustmentController::class, 'create'])->name('inventory.adjustment.create');
        Route::post('/inventory/adjustment', [InventoryAdjustmentController::class, 'store'])->name('inventory.adjustment.store');
        Route::get('/inventory/opnames/create', [StockOpnameController::class, 'create'])->name('inventory.opnames.create');
        Route::post('/inventory/opnames', [StockOpnameController::class, 'store'])->name('inventory.opnames.store');
        Route::get('/inventory/opnames/{stockOpname}/edit', [StockOpnameController::class, 'edit'])->name('inventory.opnames.edit');
        Route::put('/inventory/opnames/{stockOpname}', [StockOpnameController::class, 'update'])->name('inventory.opnames.update');
    });
    Route::get('/inventory/opnames/{stockOpname}', [StockOpnameController::class, 'show'])
        ->middleware('permission:inventory.view')
        ->name('inventory.opnames.show');

    Route::post('/inventory/opnames/{stockOpname}/post', [StockOpnameController::class, 'post'])
        ->middleware('permission:system.manage')
        ->name('inventory.opnames.post');
    Route::resource('warehouses', WarehouseController::class)
        ->only(['create', 'store', 'edit', 'update'])
        ->middleware('permission:system.manage');
    Route::middleware('permission:inventory.transfer')->group(function (): void {
        Route::get('/inventory/transfers', [InventoryTransferController::class, 'index'])->name('inventory.transfers.index');
        Route::get('/inventory/transfers/create', [InventoryTransferController::class, 'create'])->name('inventory.transfers.create');
        Route::post('/inventory/transfers', [InventoryTransferController::class, 'store'])->name('inventory.transfers.store');
        Route::post('/inventory/transfers/{inventoryTransfer}/post', [InventoryTransferController::class, 'post'])->name('inventory.transfers.post');
    });

    Route::prefix('admin')->name('admin.')->middleware('permission:system.manage')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');
        Route::get('/audit-logs', AuditLogController::class)->name('audit-logs.index');
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:2,10')->name('backups.store');
        Route::get('/backups/{filename}', [BackupController::class, 'download'])->where('filename', '[a-zA-Z0-9._-]+')->name('backups.download');
        Route::get('/health', SystemHealthController::class)->name('health');
        Route::get('/data-imports', [DataImportController::class, 'index'])->name('data-imports.index');
        Route::get('/data-imports/{type}/template', [DataImportController::class, 'template'])->name('data-imports.template');
        Route::post('/data-imports/preview', [DataImportController::class, 'preview'])->name('data-imports.preview');
        Route::post('/data-imports/{token}/confirm', [DataImportController::class, 'confirm'])->whereUuid('token')->name('data-imports.confirm');
        Route::resource('opening-stocks', OpeningStockController::class)->except(['show', 'destroy']);
        Route::post('/opening-stocks/{openingStock}/post', [OpeningStockController::class, 'post'])->name('opening-stocks.post');
    });

    Route::resource('employees', EmployeeController::class)
        ->except(['show', 'destroy'])
        ->middleware('permission:employees.manage');

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
    Route::get('/purchasing/{purchaseOrder}/print', PurchaseOrderPrintController::class)->middleware('permission:purchasing.view')->name('purchasing.print');
    Route::get('/purchasing/receipts/{goodsReceipt}/invoice', PurchaseReceiptInvoiceController::class)->middleware('permission:purchasing.view')->name('purchasing.receipts.invoice');
    Route::get('/purchasing/receipts/{goodsReceipt}/proof', [GoodsReceiptController::class, 'proof'])->middleware('permission:purchasing.view')->name('purchasing.receipts.proof');
    Route::get('/purchasing/supplier-invoices/{supplierInvoice}/print', SupplierInvoicePrintController::class)->middleware('permission:purchasing.view,finance.view')->name('purchasing.supplier-invoices.print');
    Route::get('/purchasing/supplier-invoices/{supplierInvoice}/proof', [SupplierInvoiceController::class, 'proof'])->middleware('permission:purchasing.view,finance.view')->name('purchasing.supplier-invoices.proof');
    Route::get('/purchasing/returns/{purchaseReturn}/print', PurchaseReturnPrintController::class)->middleware('permission:purchasing.view')->name('purchasing.returns.print');
    Route::middleware('permission:purchasing.manage')->group(function (): void {
        Route::get('/purchasing/create', [PurchaseOrderController::class, 'create'])->name('purchasing.create');
        Route::post('/purchasing', [PurchaseOrderController::class, 'store'])->name('purchasing.store');
        Route::get('/purchasing/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('purchasing.edit');
        Route::put('/purchasing/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchasing.update');
        Route::post('/purchasing/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchasing.cancel');
    });
    Route::middleware('permission:finance.manage')->group(function (): void {
        Route::get('/purchasing/receipts/{goodsReceipt}/supplier-invoice/create', [SupplierInvoiceController::class, 'create'])->name('purchasing.supplier-invoices.create');
        Route::post('/purchasing/receipts/{goodsReceipt}/supplier-invoice', [SupplierInvoiceController::class, 'store'])->name('purchasing.supplier-invoices.store');
    });
    Route::middleware('permission:purchasing.receive')->group(function (): void {
        Route::get('/purchasing/{purchaseOrder}/receive', [GoodsReceiptController::class, 'create'])->name('purchasing.receive.create');
        Route::post('/purchasing/{purchaseOrder}/receive', [GoodsReceiptController::class, 'store'])->name('purchasing.receive.store');
    });
    Route::middleware('permission:purchasing.manage')->group(function (): void {
        Route::get('/purchasing/receipts/{goodsReceipt}/returns/create', [PurchaseReturnController::class, 'create'])->name('purchasing.returns.create');
        Route::post('/purchasing/receipts/{goodsReceipt}/returns', [PurchaseReturnController::class, 'store'])->name('purchasing.returns.store');
    });
    Route::post('/purchasing/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:purchasing.approve')
        ->name('purchasing.approve');
    Route::post('/purchasing/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
        ->middleware('permission:purchasing.approve')
        ->name('purchasing.reject');

    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales.view')->name('sales.index');
    Route::get('/sales/deliveries', [DeliveryNoteController::class, 'index'])->middleware('permission:sales.deliver')->name('sales.deliveries.index');
    Route::get('/sales/deliveries/{deliveryNote}/print', DeliveryNotePrintController::class)->middleware('permission:sales.deliver,sales.view')->name('sales.deliveries.print');
    Route::middleware('permission:sales.deliver')->group(function (): void {
        Route::get('/sales/{sale}/delivery/create', [DeliveryNoteController::class, 'create'])->name('sales.deliveries.create');
        Route::post('/sales/{sale}/delivery', [DeliveryNoteController::class, 'store'])->name('sales.deliveries.store');
    });
    Route::get('/sales/{sale}/invoice', SalesInvoiceController::class)->middleware('permission:sales.view')->name('sales.invoice');
    Route::get('/sales/returns/{salesReturn}/print', SalesReturnPrintController::class)->middleware('permission:sales.view')->name('sales.returns.print');
    Route::middleware('permission:sales.manage')->group(function (): void {
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])->name('sales.edit');
        Route::put('/sales/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::post('/sales/{sale}/post', [SaleController::class, 'post'])->name('sales.post');
        Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
        Route::get('/sales/{sale}/returns/create', [SalesReturnController::class, 'create'])->name('sales.returns.create');
        Route::post('/sales/{sale}/returns', [SalesReturnController::class, 'store'])->name('sales.returns.store');
    });

    Route::get('/finance', [FinanceController::class, 'index'])->middleware('permission:finance.view')->name('finance.index');
    Route::get('/finance/aging', FinanceAgingController::class)->middleware('permission:finance.view')->name('finance.aging');
    Route::middleware('permission:finance.manage')->group(function (): void {
        Route::get('/finance/transactions/create', [ManualCashTransactionController::class, 'create'])->name('finance.transactions.create');
        Route::post('/finance/transactions', [ManualCashTransactionController::class, 'store'])->name('finance.transactions.store');
        Route::get('/finance/reconciliations', [CashReconciliationController::class, 'index'])->name('finance.reconciliations.index');
        Route::post('/finance/reconciliations', [CashReconciliationController::class, 'store'])->name('finance.reconciliations.store');
    });
    Route::middleware('permission:finance.close')->group(function (): void {
        Route::get('/finance/periods', [AccountingPeriodController::class, 'index'])->name('finance.periods.index');
        Route::post('/finance/periods', [AccountingPeriodController::class, 'store'])->name('finance.periods.store');
        Route::post('/finance/periods/{accountingPeriod}/reopen', [AccountingPeriodController::class, 'reopen'])->name('finance.periods.reopen');
    });
    Route::middleware('permission:sales.manage,finance.manage')->group(function (): void {
        Route::get('/finance/receivables/{sale}/payment', [CustomerPaymentController::class, 'create'])->name('finance.customer-payment.create');
        Route::post('/finance/receivables/{sale}/payment', [CustomerPaymentController::class, 'store'])->name('finance.customer-payment.store');
        Route::get('/finance/customer-payments/{customerPayment}/proof', [CustomerPaymentController::class, 'proof'])->name('finance.customer-payment.proof');
    });
    Route::middleware('permission:purchasing.manage,finance.manage')->group(function (): void {
        Route::get('/finance/payables/{supplierInvoice}/payment', [SupplierPaymentController::class, 'create'])->name('finance.supplier-payment.create');
        Route::post('/finance/payables/{supplierInvoice}/payment', [SupplierPaymentController::class, 'store'])->name('finance.supplier-payment.store');
        Route::get('/finance/supplier-payments/{supplierPayment}/proof', [SupplierPaymentController::class, 'proof'])->name('finance.supplier-payment.proof');
    });
    Route::get('/reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/operational/{type}', [OperationalReportController::class, 'show'])->middleware('permission:reports.view')->whereIn('type', ['sales', 'purchases', 'stock', 'production', 'cash', 'receivables', 'payables'])->name('reports.operational.show');
    Route::get('/reports/operational/{type}/export', [OperationalReportController::class, 'export'])->middleware('permission:reports.view')->whereIn('type', ['sales', 'purchases', 'stock', 'production', 'cash', 'receivables', 'payables'])->name('reports.operational.export');
});
