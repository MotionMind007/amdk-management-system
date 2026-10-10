document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.menuToggle);

            if (target) {
                target.classList.toggle('hidden');
                button.setAttribute('aria-expanded', String(! target.classList.contains('hidden')));
            }
        });
    });

    document.querySelectorAll('[data-repeater]').forEach((repeater) => {
        const container = repeater.querySelector('[data-repeater-items]');
        const template = repeater.querySelector('template');
        const addButton = repeater.querySelector('[data-repeater-add]');
        let nextIndex = container.children.length;

        addButton?.addEventListener('click', () => {
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        });

        repeater.addEventListener('click', (event) => {
            const removeButton = event.target.closest('[data-repeater-remove]');
            if (removeButton && container.children.length > 1) {
                removeButton.closest('[data-repeater-row]').remove();
            }
        });
    });

    document.querySelectorAll('[data-payment-form]').forEach((form) => {
        const paymentType = form.querySelector('[data-payment-type]');
        const creditFields = form.querySelector('[data-credit-fields]');
        const cashFields = form.querySelector('[data-cash-fields]');
        const dueDate = form.querySelector('[data-due-date]');
        const cashAccount = form.querySelector('[data-cash-account]');

        const updatePaymentFields = () => {
            const isCredit = paymentType?.value === 'credit';

            creditFields?.classList.toggle('hidden', ! isCredit);
            cashFields?.classList.toggle('hidden', isCredit);

            if (dueDate) {
                dueDate.disabled = ! isCredit;
                dueDate.required = isCredit;
            }

            if (cashAccount) {
                cashAccount.disabled = isCredit;
                cashAccount.required = ! isCredit;
            }
        };

        paymentType?.addEventListener('change', updatePaymentFields);
        updatePaymentFields();
    });

    document.querySelectorAll('[data-cash-transaction-form]').forEach((form) => {
        const direction = form.querySelector('[data-cash-direction]');
        const categoryFields = form.querySelector('[data-expense-category-fields]');
        const category = form.querySelector('[data-expense-category]');

        const updateExpenseCategory = () => {
            const isExpense = direction?.value === 'out';

            categoryFields?.classList.toggle('hidden', ! isExpense);

            if (category) {
                category.disabled = ! isExpense;
                category.required = isExpense;
            }
        };

        direction?.addEventListener('change', updateExpenseCategory);
        updateExpenseCategory();
    });

    document.querySelectorAll('[data-production-form]').forEach((form) => {
        const productionType = form.querySelector('[data-production-type]');
        const description = form.querySelector('[data-production-description]');
        const sections = form.querySelectorAll('[data-production-section]');

        const updateProductionSections = () => {
            const selectedType = productionType?.value ?? 'finished_good';

            sections.forEach((section) => {
                const isActive = section.dataset.productionSection === selectedType;

                section.classList.toggle('hidden', ! isActive);
                section.querySelectorAll('input, select, textarea, button').forEach((control) => {
                    control.disabled = ! isActive;
                });
            });

            if (description) {
                description.textContent = selectedType === 'packaging'
                    ? 'Stok bahan berkurang sesuai pemakaian aktual dan stok kemasan bertambah sesuai hasil baik.'
                    : 'Stok bahan dihitung otomatis dari komposisi setiap produk jadi.';
            }
        };

        productionType?.addEventListener('change', updateProductionSections);
        updateProductionSections();
    });

    document.querySelectorAll('[data-stock-opname-form]').forEach((form) => {
        const warehouse = form.querySelector('[data-opname-warehouse]');
        const balanceData = form.querySelector('[data-opname-balance-map]');
        const balanceMap = balanceData ? JSON.parse(balanceData.textContent) : {};
        const usesFixedSnapshot = form.dataset.snapshotFixed === 'true';
        const quantityFormatter = new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });

        const updateDifference = (row) => {
            const rawSystemQuantity = row.dataset.systemQuantity;
            const physicalInput = row.querySelector('[data-physical-quantity]');
            const differenceLabel = row.querySelector('[data-difference]');

            if (! differenceLabel || ! physicalInput || physicalInput.value === '' || rawSystemQuantity === undefined || rawSystemQuantity === '') {
                if (differenceLabel) {
                    differenceLabel.textContent = '—';
                    differenceLabel.classList.remove('text-emerald-700', 'text-amber-700');
                    differenceLabel.classList.add('text-slate-400');
                }

                return;
            }

            const systemQuantity = Number(rawSystemQuantity);
            const difference = Math.round((Number(physicalInput.value) - systemQuantity) * 1000) / 1000;
            differenceLabel.textContent = `${difference > 0 ? '+' : ''}${quantityFormatter.format(difference)}`;
            differenceLabel.classList.remove('text-slate-400', 'text-emerald-700', 'text-amber-700');
            differenceLabel.classList.add(difference === 0 ? 'text-emerald-700' : 'text-amber-700');
        };

        const updateRows = () => {
            const warehouseId = warehouse?.value;

            form.querySelectorAll('[data-opname-row]').forEach((row) => {
                if (! usesFixedSnapshot) {
                    const systemLabel = row.querySelector('[data-system-quantity]');

                    if (! warehouseId) {
                        row.dataset.systemQuantity = '';
                        if (systemLabel) {
                            systemLabel.textContent = '—';
                        }
                    } else {
                        const productBalances = balanceMap[row.dataset.productId] ?? {};
                        const systemQuantity = Number(productBalances[warehouseId] ?? 0);

                        row.dataset.systemQuantity = String(systemQuantity);
                        if (systemLabel) {
                            systemLabel.textContent = quantityFormatter.format(systemQuantity);
                        }
                    }
                } else if (! row.dataset.systemQuantity) {
                    const systemLabel = row.querySelector('[data-system-quantity]');
                    row.dataset.systemQuantity = systemLabel?.textContent.replaceAll('.', '').replace(',', '.') ?? '0';
                }

                updateDifference(row);
            });
        };

        warehouse?.addEventListener('change', updateRows);
        form.addEventListener('input', (event) => {
            const row = event.target.closest('[data-opname-row]');

            if (row && event.target.matches('[data-physical-quantity]')) {
                updateDifference(row);
            }
        });
        updateRows();
    });
});
