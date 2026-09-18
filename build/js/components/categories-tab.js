// Categories Tab Component
(function(global) {
    'use strict';
    
    const { createElement: h, useState, useEffect, useRef } = wp.element;
    const apiFetch = wp.apiFetch;

    function CategoriesTab({ searchTerm, onError, setLoading }) {
        console.log('CategoriesTab component rendered');
        
        const [categories, setCategories] = useState([]);
        const [summary, setSummary] = useState({});
        const loadingRef = useRef(false);
        const mountedRef = useRef(true);

        useEffect(() => {
            console.log('CategoriesTab useEffect triggered');
            loadCategories();
            return () => {
                console.log('CategoriesTab cleanup');
                mountedRef.current = false;
            };
        }, [searchTerm]);

        const loadCategories = async () => {
            if (loadingRef.current) {
                console.log('Categories already loading, skipping...');
                return;
            }
            
            console.log('Starting categories data load...');
            loadingRef.current = true;
            setLoading && setLoading(true);
            
            try {
                const params = new URLSearchParams({
                    per_page: '50'
                });
                
                if (searchTerm) {
                    params.append('search', searchTerm);
                }

                console.log('Loading categories with URL:', `/wc-commission/v1/categories?${params}`);
                
                const response = await apiFetch({
                    path: `/wc-commission/v1/categories?${params}`
                });
                
                console.log('Categories API response:', response);
                
                const categoryList = response.data || [];
                const summaryData = response.summary || {};
                console.log('Categories list extracted:', categoryList);
                
                if (mountedRef.current) {
                    setCategories(categoryList);
                    setSummary(summaryData);
                    console.log('Categories state set, length:', categoryList.length);
                }
            } catch (error) {
                console.error('Categories API error:', error);
                if (mountedRef.current) {
                    onError && onError(error.message || 'Failed to load categories');
                    setCategories([]);
                }
            } finally {
                loadingRef.current = false;
                if (mountedRef.current) {
                    setLoading && setLoading(false);
                }
            }
        };

        const formatCurrency = (amount) => {
            const num = parseFloat(amount) || 0;
            return `KSH ${num.toFixed(2)}`;
        };

        console.log('CategoriesTab rendering with categories:', categories);

        return h('div', { className: 'wc-commission-categories' }, [
            h('div', { key: 'header', style: { marginBottom: '20px' } }, [
                h('h2', { key: 'title' }, 'Product Categories & Commission Rates'),
                h('p', { key: 'desc' }, 'Overview of categories with their commission rates, product counts, and total values.')
            ]),

            // Summary stats
            h('div', { key: 'summary', style: { 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', 
                gap: '15px',
                margin: '20px 0',
                padding: '20px',
                background: '#f8f9fa',
                borderRadius: '8px'
            } }, [
                h('div', { style: { textAlign: 'center' } }, [
                    h('h4', { style: { margin: '0 0 5px 0', color: '#2271b1' } }, 'Total Categories'),
                    h('p', { style: { margin: '0', fontSize: '24px', fontWeight: 'bold' } }, String(summary.total_categories || 0))
                ]),
                h('div', { style: { textAlign: 'center' } }, [
                    h('h4', { style: { margin: '0 0 5px 0', color: '#2271b1' } }, 'Total Product Value'),
                    h('p', { style: { margin: '0', fontSize: '20px', fontWeight: 'bold' } }, formatCurrency(summary.total_value || 0))
                ]),
                h('div', { style: { textAlign: 'center' } }, [
                    h('h4', { style: { margin: '0 0 5px 0', color: '#2271b1' } }, 'Total Commission Value'),
                    h('p', { style: { margin: '0', fontSize: '20px', fontWeight: 'bold' } }, formatCurrency(summary.total_commission || 0))
                ])
            ]),

            // Categories table
            categories.length === 0 ? 
                h('p', { key: 'empty' }, 'No categories found.') :
                h('div', { key: 'table-wrapper', style: { overflowX: 'auto' } }, [
                    h('table', { 
                        key: 'categories-table',
                        className: 'wp-list-table widefat fixed striped',
                        style: { width: '100%', marginTop: '20px' }
                    }, [
                        h('thead', { key: 'thead' }, [
                            h('tr', { key: 'header-row' }, [
                                h('th', { key: 'name', className: 'manage-column' }, 'Category Name'),
                                h('th', { key: 'commission-rate', className: 'manage-column', style: { width: '120px' } }, 'Commission Rate'),
                                h('th', { key: 'products', className: 'manage-column', style: { width: '100px' } }, 'Products'),
                                h('th', { key: 'total-value', className: 'manage-column', style: { width: '150px' } }, 'Total Product Value'),
                                h('th', { key: 'commission-value', className: 'manage-column', style: { width: '150px' } }, 'Commission Value'),
                                h('th', { key: 'actions', className: 'manage-column', style: { width: '120px' } }, 'Actions')
                            ])
                        ]),
                        h('tbody', { key: 'tbody' }, 
                            categories.map((category, index) => 
                                h('tr', { 
                                    key: category.id || index,
                                    style: category.is_uncategorized ? { backgroundColor: '#fff3cd', borderLeft: '4px solid #ffc107' } : {}
                                }, [
                                    h('td', { key: 'name' }, [
                                        h('strong', { style: { display: 'block', marginBottom: '4px' } }, category.name || 'Unnamed Category'),
                                        category.description && h('div', { 
                                            style: { fontSize: '12px', color: '#666', lineHeight: '1.4' } 
                                        }, category.description),
                                        category.id && h('div', { style: { fontSize: '11px', color: '#999' } }, `ID: ${category.id}`),
                                        category.warning && h('div', { 
                                            style: { 
                                                fontSize: '11px', 
                                                color: '#856404', 
                                                backgroundColor: '#fff3cd',
                                                padding: '2px 6px',
                                                borderRadius: '3px',
                                                marginTop: '4px',
                                                display: 'inline-block'
                                            } 
                                        }, '⚠️ ' + category.warning)
                                    ]),
                                    h('td', { key: 'commission-rate', style: { 
                                        fontSize: '14px', 
                                        fontWeight: 'bold', 
                                        color: category.is_uncategorized ? '#dc3232' : '#2271b1',
                                        textAlign: 'center'
                                    } }, [
                                        `${category.commission_rate}%`,
                                        category.is_uncategorized && h('div', { 
                                            style: { fontSize: '10px', color: '#856404', marginTop: '2px', fontWeight: 'normal' } 
                                        }, 'Default rate')
                                    ]),
                                    h('td', { key: 'products', style: { textAlign: 'center', fontSize: '14px' } }, 
                                        String(category.product_count || 0)
                                    ),
                                    h('td', { key: 'total-value', style: { fontSize: '13px' } }, [
                                        h('div', { style: { fontWeight: 'bold' } }, formatCurrency(category.total_value || 0))
                                    ]),
                                    h('td', { key: 'commission-value', style: { fontSize: '13px' } }, [
                                        h('div', { style: { fontWeight: 'bold', color: '#2271b1' } }, 
                                            formatCurrency(category.total_commission || 0)
                                        )
                                    ]),
                                    h('td', { key: 'actions', style: { fontSize: '12px' } }, [
                                        category.category_url && !category.is_uncategorized && h('a', {
                                            href: category.category_url,
                                            target: '_blank',
                                            rel: 'noopener noreferrer',
                                            className: 'button button-small',
                                            style: { fontSize: '11px', padding: '4px 8px' }
                                        }, 'View Category')
                                    ])
                                ])
                            )
                        )
                    ])
                ])
        ]);
    }
    
    // Export to global scope
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.CategoriesTab = CategoriesTab;
    
})(window);