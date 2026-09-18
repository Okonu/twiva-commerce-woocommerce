// Commission Setup Tab Component
(function(global) {
    'use strict';
    
    const { createElement: h, useState, useEffect, useRef } = wp.element;
    const apiFetch = wp.apiFetch;

    function CommissionsTab({ onError, setLoading }) {
        console.log('CommissionsTab component rendered');
        
        const [commissions, setCommissions] = useState([]);
        const [summary, setSummary] = useState({});
        const loadingRef = useRef(false);
        const mountedRef = useRef(true);

        useEffect(() => {
            console.log('CommissionsTab useEffect triggered');
            loadCommissions();
            return () => {
                console.log('CommissionsTab cleanup');
                mountedRef.current = false;
            };
        }, []);

        const loadCommissions = async () => {
            if (loadingRef.current) {
                console.log('Commissions already loading, skipping...');
                return;
            }
            
            console.log('Starting commissions data load...');
            loadingRef.current = true;
            setLoading && setLoading(true);
            
            try {
                console.log('Loading commissions with URL:', '/wc-commission/v1/commissions');
                
                const response = await apiFetch({
                    path: '/wc-commission/v1/commissions'
                });
                
                console.log('Commissions API response:', response);
                
                const data = response.data || [];
                const summaryData = response.summary || {};
                console.log('Commissions data extracted:', data);
                
                if (mountedRef.current) {
                    setCommissions(Array.isArray(data) ? data : []);
                    setSummary(summaryData);
                    console.log('Commissions state set, length:', Array.isArray(data) ? data.length : 0);
                }
            } catch (error) {
                console.error('Commissions API error:', error);
                if (mountedRef.current) {
                    onError && onError(error.message || 'Failed to load commissions');
                    setCommissions([]);
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

        console.log('CommissionsTab rendering with commissions:', commissions);

        return h('div', { className: 'wc-commission-commissions' }, [
            h('h2', { key: 'title' }, 'Commission Setup - Category-Based Rates'),
            h('p', { key: 'desc' }, 'Commission rates are applied per category. Each product inherits its category\'s commission rate.'),
            
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
                    h('h4', { style: { margin: '0 0 5px 0', color: '#2271b1' } }, 'Total Products'),
                    h('p', { style: { margin: '0', fontSize: '24px', fontWeight: 'bold' } }, String(summary.total_products || 0))
                ]),
                h('div', { style: { textAlign: 'center' } }, [
                    h('h4', { style: { margin: '0 0 5px 0', color: '#2271b1' } }, 'Total Commission Amount'),
                    h('p', { style: { margin: '0', fontSize: '20px', fontWeight: 'bold' } }, formatCurrency(summary.total_commission_amount || 0)),
                    h('p', { style: { margin: '0', fontSize: '12px', color: '#666' } }, formatCurrency(summary.total_commission_amount || 0, 'KSH'))
                ])
            ]),

            // Commission breakdown table
            commissions.length === 0 ? 
                h('p', { key: 'empty' }, 'No categories with products found.') :
                h('div', { key: 'table-wrapper', style: { overflowX: 'auto' } }, [
                    h('table', { 
                        key: 'commissions-table',
                        className: 'wp-list-table widefat fixed striped',
                        style: { width: '100%', marginTop: '20px' }
                    }, [
                        h('thead', { key: 'thead' }, [
                            h('tr', { key: 'header-row' }, [
                                h('th', { key: 'category', className: 'manage-column' }, 'Category'),
                                h('th', { key: 'rate', className: 'manage-column', style: { width: '120px' } }, 'Commission Rate'),
                                h('th', { key: 'products', className: 'manage-column', style: { width: '100px' } }, 'Products'),
                                h('th', { key: 'amount', className: 'manage-column', style: { width: '180px' } }, 'Total Commission'),
                                h('th', { key: 'warning', className: 'manage-column', style: { width: '200px' } }, 'Notes')
                            ])
                        ]),
                        h('tbody', { key: 'tbody' }, 
                            commissions.map((commission, index) => 
                                h('tr', { 
                                    key: commission.category_id || index,
                                    style: commission.warning ? { backgroundColor: '#fff3cd' } : {}
                                }, [
                                    h('td', { key: 'category' }, [
                                        h('strong', null, commission.category_name || 'Unknown Category'),
                                        commission.category_id && h('div', { style: { fontSize: '11px', color: '#999' } }, `ID: ${commission.category_id}`)
                                    ]),
                                    h('td', { key: 'rate', style: { fontSize: '14px', fontWeight: 'bold', color: '#2271b1' } }, 
                                        `${commission.commission_rate}%`
                                    ),
                                    h('td', { key: 'products', style: { textAlign: 'center' } }, 
                                        String(commission.product_count || 0)
                                    ),
                                    h('td', { key: 'amount' }, [
                                        h('div', { style: { fontWeight: 'bold' } }, formatCurrency(commission.total_commission_amount || 0)),
                                        h('div', { style: { fontSize: '11px', color: '#666' } }, formatCurrency(commission.total_commission_amount || 0, 'KSH'))
                                    ]),
                                    h('td', { key: 'warning', style: { fontSize: '12px' } }, [
                                        commission.warning && h('div', { 
                                            style: { 
                                                color: '#856404',
                                                backgroundColor: '#fff3cd',
                                                padding: '4px 8px',
                                                borderRadius: '3px',
                                                fontSize: '11px'
                                            }
                                        }, '⚠️ ' + commission.warning)
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
    global.WCCommissionManager.CommissionsTab = CommissionsTab;
    
})(window);