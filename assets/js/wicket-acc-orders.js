/**
 * WooCommerce Orders HTMX integration
 */
document.addEventListener('DOMContentLoaded', function() {
    // If we don't find class .woocommerce-MyAccount-orders, we exit
    const el = document.querySelector('.woocommerce-MyAccount-orders');
    const classExists = !!el; // Convert to boolean

    if (!classExists) {
        console.log('class .woocommerce-MyAccount-orders not found, exiting');
        return;
    }

    console.log('running wicket-acc-orders.js');

    // Function to convert my-account URLs to wc-account URLs for HTMX requests
    function convertToWcAccountUrl(url) {
        return url.replace('/my-account/', '/wc-account/');
    }

    // Function to set up HTMX attributes on pagination links
    function setupOrdersPagination() {
        // Find all pagination links within the orders table navigation
        const paginationLinks = document.querySelectorAll('.woocommerce-pagination a');

        if (paginationLinks.length > 0) {
            console.log('Found pagination links');

            paginationLinks.forEach(link => {
                const originalUrl = link.getAttribute('href');
                const wcAccountUrl = convertToWcAccountUrl(originalUrl);

                // Add HTMX attributes
                link.setAttribute('hx-get', wcAccountUrl);
                link.setAttribute('hx-target', '.woocommerce-orders-table');
                link.setAttribute('hx-swap', 'outerHTML');
                link.setAttribute('hx-trigger', 'click');
                link.setAttribute('hx-select', '.woocommerce-orders-table');

                // Add a custom attribute to store the original URL
                link.setAttribute('data-original-url', originalUrl);

                // Prevent default link behavior
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Update the browser URL
                    updateBrowserUrl(originalUrl);

                    // Show loading indicator
                    showLoadingIndicator();

                    // Also fetch the pagination separately
                    fetchAndReplacePagination(wcAccountUrl);
                });
            });

            console.log('HTMX attributes added to orders pagination links');
        }
    }

    // Function to update the browser URL
    function updateBrowserUrl(url) {
        // Ensure the URL always uses /my-account/ even if we're using /wc-account/ for HTMX requests
        if (url.includes('/wc-account/')) {
            url = url.replace('/wc-account/', '/my-account/');
        }

        if (window.history && window.history.pushState) {
            window.history.pushState({ path: url }, '', url);
        }
    }

    // Function to show loading indicator
    function showLoadingIndicator() {
        // Check if loading indicator already exists
        if (document.querySelector('.woocommerce-orders-loading')) {
            return;
        }

        const ordersTable = document.querySelector('.woocommerce-orders-table');
        if (!ordersTable) {
            return;
        }

        // Create loading indicator
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'woocommerce-orders-loading';
        loadingIndicator.innerHTML = '<div class="spinner"></div><p>Loading orders...</p>';
        loadingIndicator.style.cssText = 'position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10;';

        // Add spinner styles if not already added
        if (!document.querySelector('#wicket-acc-orders-spinner-styles')) {
            const spinnerStyle = document.createElement('style');
            spinnerStyle.id = 'wicket-acc-orders-spinner-styles';
            spinnerStyle.textContent = `
                .woocommerce-orders-loading .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid rgba(0, 0, 0, 0.1);
                    border-radius: 50%;
                    border-top-color: #007cba;
                    animation: wicket-acc-spin 1s ease-in-out infinite;
                    margin-bottom: 10px;
                }
                @keyframes wicket-acc-spin {
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(spinnerStyle);
        }

        // Make sure the orders table has position relative
        ordersTable.style.position = 'relative';

        // Add loading indicator to orders table
        ordersTable.appendChild(loadingIndicator);
    }

    // Function to hide loading indicator
    function hideLoadingIndicator() {
        const loadingIndicator = document.querySelector('.woocommerce-orders-loading');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
    }

    // Function to fetch and replace pagination
    function fetchAndReplacePagination(url) {
        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                // Get the pagination from the response
                const newPagination = doc.querySelector('.woocommerce-pagination');

                // Get the current pagination
                const currentPagination = document.querySelector('.woocommerce-pagination');

                // Replace the current pagination with the new one
                if (newPagination && currentPagination) {
                    console.log('Replacing pagination');
                    currentPagination.outerHTML = newPagination.outerHTML;

                    // Re-setup pagination on the new pagination links
                    setupOrdersPagination();
                }

                // Hide loading indicator after both table and pagination are updated
                hideLoadingIndicator();
            })
            .catch(error => {
                console.error('Error fetching pagination:', error);
                hideLoadingIndicator();
            });
    }

    // Listen for HTMX events
    document.body.addEventListener('htmx:beforeRequest', function(event) {
        if (event.detail.target.classList.contains('woocommerce-orders-table')) {
            showLoadingIndicator();
        }
    });

    // Single htmx:afterSwap event listener that handles all our needs
    document.body.addEventListener('htmx:afterSwap', function(event) {
        if (event.detail.target.classList.contains('woocommerce-orders-table')) {
            // Hide loading indicator
            hideLoadingIndicator();

            // Update URL in browser if needed
            if (event.detail.xhr && event.detail.xhr.responseURL) {
                let newUrl = event.detail.xhr.responseURL;
                // Always use /my-account/ in the URL
                if (newUrl.includes('/wc-account/')) {
                    newUrl = newUrl.replace('/wc-account/', '/my-account/');
                }
                updateBrowserUrl(newUrl);
            }

            // Setup pagination again after content is swapped
            setupOrdersPagination();

            // Also update the pagination if it exists outside the swapped content
            const paginationOutside = document.querySelector('.woocommerce-pagination');
            if (paginationOutside && event.detail.xhr.response) {
                const responseDoc = new DOMParser().parseFromString(event.detail.xhr.response, 'text/html');
                const paginationFromResponse = responseDoc.querySelector('.woocommerce-pagination');
                if (paginationFromResponse) {
                    paginationOutside.outerHTML = paginationFromResponse.outerHTML;
                    setupOrdersPagination();
                }
            }
        }
    });

    // Initial setup
    setupOrdersPagination();
});
