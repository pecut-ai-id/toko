/**
 * Baris item dinamis pada form pembelian dan penjualan.
 *
 * Memakai <template> yang sudah dirender server, lalu mengganti penanda
 * __INDEX__ dengan indeks baris baru. Tanpa dependensi tambahan.
 */
function initItemRows({ container, template, add, remove, row }) {
    const containerElement = document.querySelector(container);
    const templateElement = document.getElementById(template);
    const addButton = document.querySelector(add);

    if (!containerElement || !templateElement || !addButton) {
        return;
    }

    const rows = () => containerElement.querySelectorAll(row);

    addButton.addEventListener('click', () => {
        const index = rows().length;

        containerElement.insertAdjacentHTML(
            'beforeend',
            templateElement.innerHTML.replaceAll('__INDEX__', String(index)),
        );
    });

    containerElement.addEventListener('click', (event) => {
        const removeButton = event.target.closest(remove);

        if (!removeButton) {
            return;
        }

        // Sisakan minimal satu baris agar form tetap bisa dikirim.
        if (rows().length <= 1) {
            return;
        }

        removeButton.closest(row)?.remove();
    });
}

/**
 * Isi otomatis harga jual dari produk yang dipilih, memakai format
 * angka Indonesia. Ini hanya bantuan UI; server tetap menghitung ulang
 * semua total dari kuantitas dan harga satuan.
 */
function initSalePricePrefill() {
    const container = document.querySelector('[data-sale-items]');

    if (!container) {
        return;
    }

    const formatter = new Intl.NumberFormat('id-ID');

    container.addEventListener('change', (event) => {
        const select = event.target.closest('[data-sale-product]');

        if (!select) {
            return;
        }

        const row = select.closest('[data-sale-item]');
        const priceInput = row?.querySelector('[data-sale-unit-price]');
        const price = select.selectedOptions[0]?.dataset.price;

        if (!priceInput || !price) {
            return;
        }

        priceInput.value = formatter.format(Number(price));
    });
}

function init() {
    initItemRows({
        container: '[data-purchase-items]',
        template: 'purchase-item-template',
        add: '[data-add-purchase-item]',
        remove: '[data-remove-purchase-item]',
        row: '[data-purchase-item]',
    });

    initItemRows({
        container: '[data-sale-items]',
        template: 'sale-item-template',
        add: '[data-add-sale-item]',
        remove: '[data-remove-sale-item]',
        row: '[data-sale-item]',
    });

    initSalePricePrefill();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export {};
