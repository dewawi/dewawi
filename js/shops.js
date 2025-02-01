$(document).ready(function () {
    const cartItemsContainer = $('#cart-items');
    const cartTotal = $('#cart-total');
    const cartCount = $('#cart-count');

    // Define the locale and currency
    const userLocale = 'de-DE'; // German locale
    const userCurrency = 'EUR'; // Euro currency

    function formatCurrency(value, locale, currency) {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currency,
        }).format(value);
    }

    // Function to render cart items dynamically
    function renderCartItems(cart) {
        const cartItemsContainer = $('#cart-items'); // Ensure the container is correctly selected
        cartItemsContainer.empty();

        if (Object.keys(cart).length === 0) {
            cartItemsContainer.html('<p>Your cart is empty.</p>');
        } else {
            $.each(cart, function (id, item) {
                cartItemsContainer.append(`
                    <div class="cart-item d-flex justify-content-between align-items-center" data-id="${id}">
                        <div class="product-details" style="flex: 2; display: flex; flex-direction: column; justify-content: center;">
                            <strong class="product-title">${item.title}</strong>
                            <div class="product-sku text-muted" style="font-size: 0.9rem;">SKU: ${item.sku}</div>
                        </div>
                        <div style="flex: 1; text-align: center;">${formatCurrency(item.price, userLocale, userCurrency)}</div>
                        <div style="flex: 1; text-align: center;">
                            <input type="number" class="form-control quantity-input" value="${item.quantity}" min="1" data-id="${id}">
                        </div>
                        <div style="flex: 1; text-align: center;" class="item-total">${formatCurrency(item.price * item.quantity, userLocale, userCurrency)}</div>
                        <div style="flex: 1; text-align: center;">
                            <button class="btn btn-danger btn-sm remove-item" data-id="${id}">Remove</button>
                        </div>
                    </div>
                `);
            });
        }
    }

    // Function to update cart summary
    function updateCartSummary(total) {
        cartTotal.text(formatCurrency(total, userLocale, userCurrency));
    }

    // Function to update cart count
    function updateCartCount(count) {
        cartCount.text(count || 0);
    }

    // Add to Cart
    $('.add-to-cart').on('click', function () {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const sku = $(this).data('sku');
        const price = parseFloat($(this).data('price'));

        if (isNaN(price)) {
            console.error('Invalid price for product:', { id, title, sku, price });
            return;
        }

        $.post('/cart/add', { id, title, sku, price }, function (response) {
            if (response.success) {
                renderCartItems(response.cart);
                updateCartSummary(response.total);
                updateCartCount(response.cartItemCount);
				window.location.href = '/cart';
            } else {
                console.error('Failed to add to cart:', response.message);
            }
        }).fail(function () {
            console.error('Error adding item to cart.');
        });
    });

    // Update Cart Item
    cartItemsContainer.on('click', '.update-item', function () {
        const id = $(this).data('id');
        const quantity = $(this).data('quantity');

        if (quantity <= 0) {
            console.log('Quantity must be at least 1.');
            return;
        }

        $.post('/cart/update', { id, quantity }, function (response) {
            if (response.success) {
                renderCartItems(response.cart);
                updateCartSummary(response.total);
                updateCartCount(response.cartItemCount);
            } else {
                console.log('Failed to update item quantity.');
            }
        });
    });

    // Update Item Quantity
    cartItemsContainer.on('change', '.quantity-input', function () {
        const id = $(this).data('id');
        const quantity = $(this).val();

        if (quantity <= 0) {
            console.error('Quantity must be greater than zero.');
            return;
        }

        $.post('/cart/update', { id, quantity }, function (response) {
            if (response.success) {
                renderCartItems(response.cart);
                updateCartSummary(response.total);
                updateCartCount(response.cartItemCount);
            }
        });
    });

    // Remove from Cart
    cartItemsContainer.on('click', '.remove-item', function () {
        const id = $(this).data('id');

        $.post('/cart/remove', { id }, function (response) {
            if (response.success) {
                renderCartItems(response.cart);
                updateCartSummary(response.total);
                updateCartCount(response.cartItemCount);
            } else {
                console.log('Failed to remove item from the cart.');
            }
        });
    });

    // Clear Cart
    $('#clear-cart').on('click', function () {
        $.post('/cart/clear', function (response) {
            if (response.success) {
                renderCartItems(response.cart);
                updateCartSummary(response.total);
                updateCartCount(0);
                console.log('Cart cleared.');
            } else {
                console.log('Failed to clear the cart.');
            }
        });
    });

	$('#checkout-btn').on('click', function () {
		window.location.href = 'checkout';
		/*$.post('/checkout', function (response) {
		    if (response.success) {
		        console.log('Checkout successful! Order ID: ' + response.orderId);
		        $('#cart-items').html('<p>Your cart is empty.</p>');
		        $('#cart-total').text('0.00');
		        $('#cart-count').text('0');
		    } else {
		        console.log('Checkout failed: ' + response.message);
		    }
		});*/
	});

	$('#edit-btn').on('click', function () {
		window.location.href = 'cart';
	});

    // Add a change event listener to the checkbox
    $('#differentshippingaddress').change(function () {
        toggleShippingFields();
    });

    // Function to show or hide shipping fields
    function toggleShippingFields() {
        if ($('#differentshippingaddress').is(':checked')) {
            // Show shipping fields if checkbox is checked
            $('#shipping-container').slideDown();
        } else {
            // Hide shipping fields if checkbox is unchecked
            $('#shipping-container').slideUp();
        }
    }
});

