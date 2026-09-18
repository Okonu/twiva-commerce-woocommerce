// Shop Validation Section Component
(function(global) {
    'use strict';

    const { createElement: h, useState, useEffect } = wp.element;

    function ValidationSection() {
        const [validationData, setValidationData] = useState(null);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);
        const [copying, setCopying] = useState(false);
        const [regenerating, setRegenerating] = useState(false);

        // Poll validation status every 30 seconds
        useEffect(() => {
            fetchValidationStatus();
            const interval = setInterval(fetchValidationStatus, 30000);
            return () => clearInterval(interval);
        }, []);

        const fetchValidationStatus = async () => {
            try {
                // Check for admin object with fallback to legacy name
                const adminObj = window.twivco_commission_manager_admin || window.wc_commission_manager_admin;
                if (!adminObj) {
                    throw new Error('Admin configuration object not found');
                }

                const response = await fetch(adminObj.api_url + 'wc-commission/v1/validation/status', {
                    headers: {
                        'X-WP-Nonce': adminObj.nonce
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch validation status');
                }

                const data = await response.json();
                setValidationData(data);
                setError(null);
            } catch (err) {
                console.error('Validation status error:', err);
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        const copyToClipboard = async (text) => {
            if (!text) return;

            setCopying(true);
            try {
                await navigator.clipboard.writeText(text);
                // Show success feedback briefly
                setTimeout(() => setCopying(false), 1000);
            } catch (err) {
                console.error('Copy failed:', err);
                setCopying(false);
                // Fallback method
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
            }
        };

        const regenerateCode = async () => {
            setRegenerating(true);
            try {
                // Check for admin object with fallback to legacy name
                const adminObj = window.twivco_commission_manager_admin || window.wc_commission_manager_admin;
                if (!adminObj) {
                    throw new Error('Admin configuration object not found');
                }

                const response = await fetch(adminObj.api_url + 'wc-commission/v1/validation/regenerate-code', {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': adminObj.nonce,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to regenerate code');
                }

                const data = await response.json();
                setValidationData(prev => ({
                    ...prev,
                    one_time_code: data.one_time_code,
                    code_expires_at: data.code_expires_at,
                    minutes_remaining: 30,
                    code_valid: true
                }));
                setError(null);
            } catch (err) {
                console.error('Regeneration error:', err);
                setError(err.message);
            } finally {
                setRegenerating(false);
            }
        };

        if (loading) {
            return h('div', {
                style: {
                    background: '#fff',
                    border: '1px solid #c3c4c7',
                    padding: '20px',
                    marginBottom: '20px',
                    borderRadius: '4px'
                }
            }, 'Loading validation status...');
        }

        if (error) {
            return h('div', {
                style: {
                    background: '#fff',
                    border: '1px solid #d63638',
                    padding: '20px',
                    marginBottom: '20px',
                    borderRadius: '4px'
                }
            }, [
                h('h3', { style: { color: '#d63638', margin: 0 } }, '🔗 Shop Validation - Error'),
                h('p', { style: { color: '#d63638' } }, `Error: ${error}`),
                h('button', {
                    className: 'button',
                    onClick: () => { setError(null); fetchValidationStatus(); }
                }, 'Retry')
            ]);
        }

        if (!validationData) {
            return null;
        }

        const isValidated = validationData.is_validated;
        const hasValidCode = validationData.code_valid && validationData.one_time_code;
        const minutesRemaining = validationData.minutes_remaining || 0;

        return h('div', {
            style: {
                background: '#fff',
                border: `2px solid ${isValidated ? '#00a32a' : '#dba617'}`,
                padding: '20px',
                marginBottom: '20px',
                borderRadius: '4px'
            }
        }, [
            h('h3', {
                style: {
                    color: isValidated ? '#00a32a' : '#dba617',
                    margin: '0 0 15px 0',
                    display: 'flex',
                    alignItems: 'center'
                }
            }, [
                h('span', null, '🔗 Link Your Shop to Business Dashboard'),
                h('span', {
                    style: {
                        marginLeft: 'auto',
                        fontSize: '16px',
                        fontWeight: 'bold'
                    }
                }, isValidated ? '✅ Validated' : '❌ Not Validated')
            ]),

            isValidated ? (
                h('div', { style: { color: '#00a32a' } }, [
                    h('p', { style: { margin: '10px 0' } }, '🎉 Your shop is successfully linked to your business dashboard!'),
                    h('p', { style: { margin: '10px 0', fontSize: '14px' } },
                        `Validated on: ${new Date(validationData.validated_at).toLocaleString()}`
                    )
                ])
            ) : (
                h('div', null, [
                    hasValidCode ? (
                        h('div', null, [
                            h('div', { style: { marginBottom: '15px' } }, [
                                h('label', {
                                    style: {
                                        display: 'block',
                                        fontWeight: 'bold',
                                        marginBottom: '8px'
                                    }
                                }, 'Your One-Time Code:'),
                                h('div', {
                                    style: {
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '10px'
                                    }
                                }, [
                                    h('code', {
                                        style: {
                                            background: '#f0f0f1',
                                            padding: '10px 15px',
                                            fontSize: '18px',
                                            fontWeight: 'bold',
                                            letterSpacing: '2px',
                                            border: '1px solid #c3c4c7',
                                            borderRadius: '4px'
                                        }
                                    }, validationData.one_time_code),
                                    h('button', {
                                        className: 'button button-secondary',
                                        onClick: () => copyToClipboard(validationData.one_time_code),
                                        disabled: copying
                                    }, copying ? 'Copied!' : 'Copy'),
                                    h('button', {
                                        className: 'button button-secondary',
                                        onClick: regenerateCode,
                                        disabled: regenerating
                                    }, regenerating ? 'Generating...' : 'Generate New')
                                ])
                            ]),
                            h('p', {
                                style: {
                                    color: minutesRemaining > 5 ? '#dba617' : '#d63638',
                                    fontWeight: 'bold',
                                    margin: '10px 0'
                                }
                            }, `⏱️ Expires in: ${minutesRemaining} minutes`)
                        ])
                    ) : (
                        h('div', null, [
                            h('p', { style: { color: '#d63638', margin: '10px 0' } }, '⚠️ Code has expired, please generate a new one.'),
                            h('button', {
                                className: 'button button-primary',
                                onClick: regenerateCode,
                                disabled: regenerating
                            }, regenerating ? 'Generating...' : 'Generate New Code')
                        ])
                    ),

                    h('div', { style: { marginTop: '20px' } }, [
                        h('h4', { style: { margin: '0 0 10px 0' } }, 'Steps to Link Your Shop:'),
                        h('ol', { style: { margin: '0', paddingLeft: '20px' } }, [
                            h('li', null, h('a', {
                                href: 'https://commerce.twiva.com/dashboard',
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                style: { textDecoration: 'none' }
                            }, '🔗 Go to https://commerce.twiva.com/dashboard')),
                            h('li', null, '👤 Log in to your business account'),
                            h('li', null, '🔗 Click "Link Shop" section'),
                            h('li', null, '📝 Enter the code above'),
                            h('li', null, '📊 Your shop metrics will appear in the dashboard')
                        ])
                    ])
                ])
            )
        ]);
    }

    // Export the component
    global.WCCommissionManager = global.WCCommissionManager || {};
    global.WCCommissionManager.ValidationSection = ValidationSection;

})(window);