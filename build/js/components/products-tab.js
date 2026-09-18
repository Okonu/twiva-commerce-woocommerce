// Products Tab Component
(function(global) {
    'use strict';
    
    const { createElement: h, useState, useEffect, useRef } = wp.element;
    const apiFetch = wp.apiFetch;

    function ProductsTab({ searchTerm, onError, setLoading }) {
        console.log('ProductsTab component rendered with searchTerm:', searchTerm);
        
        const [products, setProducts] = useState([]);
        const loadingRef = useRef(false);
        const mountedRef = useRef(true);

        useEffect(() => {
            console.log('ProductsTab useEffect triggered');
            loadProducts();
            return () => {
                console.log('ProductsTab cleanup');
                mountedRef.current = false;
            };
        }, [searchTerm]);

        const loadProducts = async () => {
            if (loadingRef.current) {
                console.log('Products already loading, skipping...');
                return;
            }
            
            console.log('Starting products data load...');
            loadingRef.current = true;
            setLoading && setLoading(true);
            
            try {
                const params = new URLSearchParams({
                    page: '1',
                    per_page: '25'
                });
                
                if (searchTerm) {
                    params.append('search', searchTerm);
                }

                console.log('Loading products with URL:', `/wc-commission/v1/products?${params}`);
                
                const response = await apiFetch({
                    path: `/wc-commission/v1/products?${params}`,
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });
                
                console.log('Products API response received');
                
                const productList = response.data || [];
                
                if (mountedRef.current) {
                    setProducts(Array.isArray(productList) ? productList : []);
                    console.log('Products loaded:', productList.length);
                }
            } catch (error) {
                console.error('Products API error:', error);
                if (mountedRef.current) {
                    onError && onError(error.message || 'Failed to load products');
                    setProducts([]);
                }
            } finally {
                loadingRef.current = false;
                if (mountedRef.current) {
                    setLoading && setLoading(false);
                }
            }
        };

        const truncateText = (text, maxLength = 60) => {
            if (!text) return '';
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        };

        const formatCurrency = (amount) => {
            const num = parseFloat(amount) || 0;
            return `KSH ${num.toFixed(2)}`;
        };

        console.log('ProductsTab rendering with products:', products);

        return h('div', { className: 'wc-commission-products' }, [
            // Header
            h('div', { key: 'header', style: { marginBottom: '20px' } }, [
                h('h2', { key: 'title' }, 'Products & Category-Based Commission Rates'),
                h('p', { key: 'desc' }, 'Commission rates are automatically applied based on product categories. Products without categories use the default "Other" rate of 15%.'),
                h('div', { key: 'stats', style: { display: 'flex', gap: '20px', marginTop: '10px' } }, [
                    h('span', { style: { color: '#666' } }, `${products.length} products found`)
                ])
            ]),

            // Loading indicator
            loadingRef.current && h('div', { 
                key: 'loading', 
                style: { 
                    textAlign: 'center', 
                    padding: '40px',
                    background: '#f9f9f9',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                    marginBottom: '20px'
                }
            }, [
                h('div', { style: { fontSize: '16px', color: '#666' } }, 'Loading products...'),
                h('div', { style: { marginTop: '10px' } }, [
                    h('div', { 
                        style: { 
                            width: '20px', 
                            height: '20px', 
                            border: '2px solid #f3f3f3',
                            borderTop: '2px solid #2271b1',
                            borderRadius: '50%',
                            animation: 'spin 1s linear infinite',
                            margin: '0 auto'
                        }
                    })
                ])
            ]),

            // Products table
            !loadingRef.current && products.length === 0 ? 
                h('p', { key: 'empty' }, 'No products found.') :
                h('div', { key: 'table-wrapper', style: { overflowX: 'auto' } }, [
                    h('table', { 
                        key: 'products-table',
                        className: 'wp-list-table widefat fixed striped',
                        style: { width: '100%' }
                    }, [
                        // Table header
                        h('thead', { key: 'thead' }, [
                            h('tr', { key: 'header-row' }, [
                                h('th', { key: 'image', className: 'manage-column', style: { width: '60px' } }, 'Image'),
                                h('th', { key: 'name', className: 'manage-column' }, 'Product Details'),
                                h('th', { key: 'categories', className: 'manage-column', style: { width: '180px' } }, 'Categories'),
                                h('th', { key: 'price', className: 'manage-column', style: { width: '120px' } }, 'Price'),
                                h('th', { key: 'commission-rate', className: 'manage-column', style: { width: '120px' } }, 'Commission Rate'),
                                h('th', { key: 'commission-amount', className: 'manage-column', style: { width: '120px' } }, 'Commission Amount'),
                                h('th', { key: 'actions', className: 'manage-column', style: { width: '100px' } }, 'Actions')
                            ])
                        ]),
                        
                        // Table body
                        h('tbody', { key: 'tbody' }, 
                            products.map((product, index) => {
                                return h('tr', { 
                                    key: product.id || index,
                                    style: product.is_uncategorized ? { backgroundColor: '#fff3cd', borderLeft: '4px solid #ffc107' } : {}
                                }, [
                                    h('td', { key: 'image' }, [
                                        product.image ? 
                                            h('img', { 
                                                src: product.image, 
                                                alt: product.name,
                                                style: { width: '40px', height: '40px', objectFit: 'cover' }
                                            }) :
                                            h('div', { 
                                                style: { 
                                                    width: '40px', 
                                                    height: '40px', 
                                                    backgroundColor: '#f0f0f0',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'center',
                                                    fontSize: '12px'
                                                }
                                            }, 'No Image')
                                    ]),
                                    h('td', { key: 'name' }, [
                                        h('strong', { style: { display: 'block', marginBottom: '4px' } }, product.name || 'Unnamed Product'),
                                        h('div', { style: { fontSize: '12px', color: '#666', marginBottom: '6px', lineHeight: '1.4' } }, 
                                            truncateText(product.description || product.short_description || 'No description available', 80)
                                        ),
                                        h('div', { style: { fontSize: '11px', color: '#999' } }, `ID: ${product.id}`),
                                        product.is_uncategorized && h('div', { 
                                            style: { 
                                                fontSize: '11px', 
                                                color: '#856404', 
                                                backgroundColor: '#fff3cd',
                                                padding: '2px 6px',
                                                borderRadius: '3px',
                                                marginTop: '4px',
                                                display: 'inline-block'
                                            } 
                                        }, '⚠️ Uncategorized - Using default rate')
                                    ]),
                                    h('td', { key: 'categories' }, [
                                        product.categories && product.categories.length > 0 ?
                                            product.categories.map((cat, catIndex) => 
                                                h('span', { 
                                                    key: catIndex,
                                                    style: { 
                                                        display: 'inline-block',
                                                        background: '#e7f3ff',
                                                        color: '#2271b1',
                                                        padding: '2px 6px',
                                                        borderRadius: '3px',
                                                        fontSize: '11px',
                                                        margin: '1px 2px 1px 0'
                                                    }
                                                }, cat)
                                            ) :
                                            h('span', { style: { color: '#dc3232', fontSize: '12px' } }, 'Uncategorized')
                                    ]),
                                    h('td', { key: 'price' }, [
                                        h('strong', null, formatCurrency(product.price || product.regular_price || 0)),
                                        h('div', { style: { fontSize: '11px', color: '#666' } }, formatCurrency(product.price || product.regular_price || 0, 'KSH'))
                                    ]),
                                    h('td', { key: 'commission-rate', style: { fontSize: '13px' } }, [
                                        h('div', { style: { fontWeight: 'bold', color: product.is_uncategorized ? '#dc3232' : '#2271b1' } },
                                            `${product.commission_rate}%`
                                        ),
                                        product.commission_message && h('div', { 
                                            style: { 
                                                fontSize: '10px', 
                                                color: product.calculation_type === 'default' ? '#856404' : '#666',
                                                marginTop: '2px',
                                                lineHeight: '1.3'
                                            },
                                            title: product.commission_message
                                        }, product.calculation_type === 'multiple_categories' ? 
                                            '📊 Multiple categories (hover for details)' :
                                            product.calculation_type === 'default' ? 
                                            '⚠️ Default rate - needs categorization' :
                                            '✓ Category-based rate'
                                        )
                                    ]),
                                    h('td', { key: 'commission-amount', style: { fontSize: '13px' } }, [
                                        h('div', { key: 'amount', style: { fontWeight: 'bold' } }, 
                                            formatCurrency(product.commission_amount || 0)
                                        ),
                                        product.commission_message && h('div', {
                                            style: {
                                                fontSize: '9px',
                                                color: '#666',
                                                marginTop: '2px',
                                                maxWidth: '120px',
                                                wordWrap: 'break-word'
                                            },
                                            title: product.commission_message
                                        }, product.rate_source !== 'Other' ? `From: ${product.rate_source}` : 'Default rate')
                                    ]),
                                    h('td', { key: 'actions', style: { fontSize: '12px' } }, [
                                        product.permalink && h('a', {
                                            href: product.permalink,
                                            target: '_blank',
                                            rel: 'noopener noreferrer',
                                            className: 'button button-small',
                                            style: { fontSize: '11px', padding: '4px 8px' }
                                        }, 'View Product')
                                    ])
                                ]);
                            })
                        )
                    ])
                ])
        ]);
    }
    
    // Export to global scope
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.ProductsTab = ProductsTab;
    
})(window);