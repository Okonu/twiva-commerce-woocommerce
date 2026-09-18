// Main Commission Manager App Component
(function(global) {
    'use strict';
    
    const { createElement: h, useState } = wp.element;

    function CommissionManagerApp() {
        console.log('CommissionManagerApp component rendered');
        
        const [activeTab, setActiveTab] = useState('overview');
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState(null);
        const [searchTerm, setSearchTerm] = useState('');

        console.log('App state - activeTab:', activeTab, 'loading:', loading, 'searchTerm:', searchTerm);

        const tabs = [
            { name: 'overview', title: 'Overview' },
            { name: 'products', title: 'Products' },
            { name: 'categories', title: 'Categories' },
            { name: 'commissions', title: 'Commissions' }
        ];

        const handleError = (errorMessage) => {
            console.error('App error:', errorMessage);
            setError(errorMessage);
            setLoading(false);
        };

        const clearError = () => {
            setError(null);
        };

        const handleTabSwitch = (tabName) => {
            console.log('Tab switch to:', tabName);
            setActiveTab(tabName);
            setLoading(false);
            clearError();
        };

        const renderTabContent = (tabName) => {
            console.log('renderTabContent called with:', tabName);
            
            const { OverviewTab, ProductsTab, CategoriesTab, CommissionsTab } = global.WCCommissionManager;
            
            switch (tabName) {
                case 'overview':
                    return h(OverviewTab, {
                        onError: handleError,
                        setLoading: setLoading
                    });
                case 'products':
                    return h(ProductsTab, {
                        searchTerm: searchTerm,
                        onError: handleError,
                        setLoading: setLoading
                    });
                case 'categories':
                    return h(CategoriesTab, {
                        searchTerm: searchTerm,
                        onError: handleError,
                        setLoading: setLoading
                    });
                case 'commissions':
                    return h(CommissionsTab, {
                        onError: handleError,
                        setLoading: setLoading
                    });
                default:
                    console.log('Unknown tab:', tabName);
                    return h('div', null, 'Unknown tab: ' + tabName);
            }
        };

        return h('div', { className: 'wc-commission-manager' }, [

            h('div', { key: 'header' }, [
                h('h1', { key: 'title' }, 'Twiva Commerce'),
                h('p', { key: 'desc' }, 'Manage commission rates for your WooCommerce products and categories.')
            ]),

            // Add validation section
            h(global.WCCommissionManager.ValidationSection, { key: 'validation' }),

            error && h('div', { key: 'error', style: { background: '#f44336', color: 'white', padding: '10px' } }, [
                h('p', null, `Error: ${error}`),
                h('button', { onClick: clearError }, 'Clear Error')
            ]),

            h('div', { key: 'tabs' }, 
                tabs.map(tab => 
                    h('button', {
                        key: tab.name,
                        className: tab.name === activeTab ? 'button button-primary' : 'button button-secondary',
                        onClick: () => {
                            console.log('Tab button clicked:', tab.name);
                            handleTabSwitch(tab.name);
                        },
                        style: { margin: '5px' }
                    }, tab.title)
                )
            ),

            ['products', 'categories'].includes(activeTab) && h('div', { key: 'search', style: { margin: '10px 0' } }, [
                h('input', {
                    type: 'text',
                    placeholder: `Search ${activeTab}...`,
                    value: searchTerm,
                    onChange: (e) => {
                        console.log('Search changed:', e.target.value);
                        setSearchTerm(e.target.value);
                    },
                    style: { width: '300px', padding: '8px' }
                })
            ]),

            h('div', { key: 'content', style: { border: '2px solid #0073aa', padding: '20px', margin: '10px 0' } }, 
                renderTabContent(activeTab)
            )
        ]);
    }
    
    // Export to global scope
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.CommissionManagerApp = CommissionManagerApp;
    
})(window);