// Styles and CSS utilities
(function(global) {
    'use strict';
    
    // Add CSS for loading spinner and other styles
    function initializeStyles() {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .wc-commission-manager {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            
            .wc-commission-overview .overview-stats-grid > div {
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            
            .wc-commission-overview .overview-stats-grid > div:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }
        `;
        document.head.appendChild(style);
    }
    
    // Export to global scope
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.initializeStyles = initializeStyles;
    
})(window);