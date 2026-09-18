// Commission Manager - Main Admin Entry Point
(function() {
    'use strict';
    
    const { render } = wp.element;
    const { createElement: h } = wp.element;
    
    console.log('Commission Manager main script loaded');
    
    // Initialize the application
    function initializeCommissionManager() {
        console.log('Initializing Commission Manager...');
        
        // Check if all required dependencies are loaded
        if (!window.WCCommissionManager) {
            console.error('WCCommissionManager components not loaded');
            return;
        }
        
        const { 
            CommissionManagerApp,
            initializeStyles,
            OverviewTab,
            ProductsTab, 
            CategoriesTab,
            CommissionsTab
        } = window.WCCommissionManager;
        
        // Verify all components are available
        if (!CommissionManagerApp) {
            console.error('CommissionManagerApp component not found');
            return;
        }
        
        if (!OverviewTab || !ProductsTab || !CategoriesTab || !CommissionsTab) {
            console.error('One or more tab components not found');
            return;
        }
        
        // Initialize styles
        if (initializeStyles) {
            initializeStyles();
        }
        
        // Find the container and render the app
        const container = document.getElementById('twiva-commerce-app');
        console.log('Container found:', !!container);

        if (container) {
            console.log('Rendering Twiva Commerce app...');
            render(h(CommissionManagerApp), container);
            console.log('Twiva Commerce app rendered successfully');
        } else {
            console.error('Twiva Commerce container (#twiva-commerce-app) not found');
        }
    }
    
    // Function to load script dependencies
    function loadScript(src, callback) {
        const script = document.createElement('script');
        script.src = src;
        script.onload = callback;
        script.onerror = function() {
            console.error('Failed to load script:', src);
        };
        document.head.appendChild(script);
    }
    
    // Load all component scripts in sequence
    function loadAllComponents() {
        const basePath = twivco_commission_manager_admin.plugin_url + 'build/js/';
        const scripts = [
            'utils/styles.js',
            'components/validation-section.js',
            'components/overview-tab.js',
            'components/products-tab.js',
            'components/categories-tab.js',
            'components/commissions-tab.js',
            'components/app.js'
        ];
        
        let loadedScripts = 0;
        
        function loadNext() {
            if (loadedScripts < scripts.length) {
                const scriptPath = basePath + scripts[loadedScripts];
                console.log('Loading script:', scriptPath);
                
                loadScript(scriptPath, function() {
                    loadedScripts++;
                    console.log('Script loaded:', scripts[loadedScripts - 1]);
                    loadNext();
                });
            } else {
                console.log('All scripts loaded, initializing app...');
                // Small delay to ensure all components are registered
                setTimeout(initializeCommissionManager, 100);
            }
        }
        
        loadNext();
    }
    
    // Initialize when DOM is ready
    function init() {
        console.log('DOM ready, starting script loading...');
        
        // Check if admin script localization is available
        if (typeof twivco_commission_manager_admin === 'undefined') {
            console.error('twivco_commission_manager_admin localization not found');
            console.log('Available globals:', Object.keys(window).filter(k => k.includes('commission')));
            return;
        }
        
        loadAllComponents();
    }
    
    // Start initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    console.log('Commission Manager main script setup complete');

})();