// Navbar Burger Toggle for Mobile
document.addEventListener('DOMContentLoaded', () => {
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }

    // Add to Cart Functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', () => {
            const product = button.dataset.product;
            alert(`${product} has been added to your cart!`);
            // Update cart count in header
            const cartCount = document.querySelector('.navbar-item .button span:last-child');
            let count = parseInt(cartCount.textContent.match(/\d+/)[0]);
            cartCount.textContent = `Cart (${count + 1})`;
        });
    });
});


// Navbar Burger Toggle for Mobile
document.addEventListener('DOMContentLoaded', () => {
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }

    // Add to Cart Functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', () => {
            const product = button.dataset.product;
            alert(`${product} has been added to your cart!`);
            // Update cart count in header
            const cartCount = document.querySelector('.navbar-item .button span:last-child');
            let count = parseInt(cartCount.textContent.match(/\d+/)[0]);
            cartCount.textContent = `Cart (${count + 1})`;
        });
    });

    // Quantity Selector Functionality
    const quantityButtons = document.querySelectorAll('.quantity-btn');
    quantityButtons.forEach(button => {
        button.addEventListener('click', () => {
            const action = button.dataset.action;
            const input = button.parentElement.parentElement.querySelector('.quantity-input');
            let value = parseInt(input.value);
            
            if (action === 'increase') {
                value++;
            } else if (action === 'decrease' && value > 1) {
                value--;
            }
            input.value = value;
        });
    });
});
// Navbar Burger Toggle for Mobile
document.addEventListener('DOMContentLoaded', () => {
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }

    // Add to Cart Functionality
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.addEventListener('click', () => {
            const product = button.dataset.product;
            alert(`${product} has been added to your cart!`);
            // Update cart count in header
            const cartCount = document.querySelector('.navbar-item .button span:last-child');
            let count = parseInt(cartCount.textContent.match(/\d+/)[0]);
            cartCount.textContent = `Cart (${count + 1})`;
        });
    });

    // Quantity Selector Functionality (for Product Detail Page)
    const quantityButtons = document.querySelectorAll('.quantity-btn');
    quantityButtons.forEach(button => {
        button.addEventListener('click', () => {
            const action = button.dataset.action;
            const input = button.parentElement.parentElement.querySelector('.quantity-input');
            let value = parseInt(input.value);
            
            if (action === 'increase') {
                value++;
            } else if (action === 'decrease' && value > 1) {
                value--;
            }
            input.value = value;
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    // Navbar Burger Toggle
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
    if ($navbarBurgers.length > 0) {
        $navbarBurgers.forEach(el => {
            el.addEventListener('click', () => {
                const target = el.dataset.target;
                const $target = document.getElementById(target);
                el.classList.toggle('is-active');
                $target.classList.toggle('is-active');
            });
        });
    }

    // Basic Form Submission (Placeholder)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Form submitted! This is a placeholder action.');
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    // Basic Form Submission (Placeholder)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Form submitted! This is a placeholder action.');
        });
    });
});