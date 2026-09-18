// Overview Tab Component
(function(global) {
    'use strict';
    
    const { createElement: h, useState, useEffect, useRef } = wp.element;
    const apiFetch = wp.apiFetch;

    function OverviewTab({ onError, setLoading }) {
        console.log('OverviewTab component rendered');
        
        const [stats, setStats] = useState({
            totalCommissions: 0,
            totalCommissionValue: 0,
            totalProducts: 0,
            commissionsSet: 0,
            recentActivity: []
        });
        const loadingRef = useRef(false);
        const mountedRef = useRef(true);

        useEffect(() => {
            console.log('OverviewTab useEffect triggered');
            loadOverviewData();
            return () => {
                console.log('OverviewTab cleanup');
                mountedRef.current = false;
            };
        }, []);

        const formatCurrency = (amount) => {
            const num = parseFloat(amount) || 0;
            return `KSH ${num.toFixed(2)}`;
        };

        const loadOverviewData = async () => {
            if (loadingRef.current) {
                console.log('Overview already loading, skipping...');
                return;
            }
            
            console.log('Starting overview data load...');
            loadingRef.current = true;
            setLoading && setLoading(true);
            
            try {
                const [overviewResponse, productsResponse] = await Promise.all([
                    apiFetch({ path: '/wc-commission/v1/overview' }),
                    apiFetch({ path: '/wc-commission/v1/products?per_page=100' })
                ]);
                
                console.log('Overview API response:', overviewResponse);
                console.log('Products API response for calculation:', productsResponse);
                
                const overviewData = overviewResponse.data || overviewResponse;
                const productsData = productsResponse.data || [];
                
                let totalCommissionValue = 0;
                productsData.forEach(product => {
                    const commissionAmount = parseFloat(product.commission_amount || 0);
                    totalCommissionValue += commissionAmount;
                });
                
                const enhancedStats = {
                    ...overviewData,
                    totalCommissionValue: totalCommissionValue
                };
                
                console.log('Enhanced overview data:', enhancedStats);
                
                if (mountedRef.current) {
                    setStats(enhancedStats);
                    console.log('Overview stats updated with total commission value');
                }
            } catch (error) {
                console.error('Overview API error:', error);
                if (mountedRef.current) {
                    onError && onError(error.message || 'Failed to load overview data');
                }
            } finally {
                loadingRef.current = false;
                if (mountedRef.current) {
                    setLoading && setLoading(false);
                }
            }
        };

        console.log('OverviewTab rendering with stats:', stats);

        return h('div', { className: 'wc-commission-overview' }, [
            h('div', { key: 'header' }, [
                h('h2', { key: 'title' }, 'Commission Overview'),
                h('p', { key: 'desc' }, 'Summary of your commission settings and total values.')
            ]),

            h('div', { key: 'stats', className: 'overview-stats-grid', style: { 
                display: 'grid', 
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', 
                gap: '20px',
                margin: '20px 0'
            } }, [
                h('div', { 
                    key: 'stat1',
                    style: { 
                        background: '#fff',
                        border: '1px solid #ddd',
                        borderRadius: '8px',
                        padding: '20px',
                        textAlign: 'center',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                    }
                }, [
                    h('h3', { style: { margin: '0 0 10px 0', color: '#2271b1', fontSize: '16px' } }, 'Commissions Set'),
                    h('p', { style: { margin: '0', fontSize: '24px', fontWeight: 'bold', color: '#333' } }, String(stats.totalCommissions || 0))
                ]),
                h('div', { 
                    key: 'stat2',
                    style: { 
                        background: '#fff',
                        border: '1px solid #ddd',
                        borderRadius: '8px',
                        padding: '20px',
                        textAlign: 'center',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                    }
                }, [
                    h('h3', { style: { margin: '0 0 10px 0', color: '#2271b1', fontSize: '16px' } }, 'Total Products'), 
                    h('p', { style: { margin: '0', fontSize: '24px', fontWeight: 'bold', color: '#333' } }, String(stats.totalProducts || 0))
                ]),
                h('div', { 
                    key: 'stat3',
                    style: { 
                        background: '#fff',
                        border: '1px solid #ddd',
                        borderRadius: '8px',
                        padding: '20px',
                        textAlign: 'center',
                        boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
                    }
                }, [
                    h('h3', { style: { margin: '0 0 10px 0', color: '#2271b1', fontSize: '16px' } }, 'Total Commission Value'),
                    h('p', { style: { margin: '0', fontSize: '20px', fontWeight: 'bold', color: '#333' } }, formatCurrency(stats.totalCommissionValue || 0))
                ])
            ]),

            h('div', { key: 'refresh', style: { marginTop: '20px' } }, [
                h('button', {
                    className: 'button button-secondary',
                    onClick: loadOverviewData
                }, 'Refresh Data')
            ])
        ]);
    }
    
    // Export to global scope
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.OverviewTab = OverviewTab;
    
})(window);