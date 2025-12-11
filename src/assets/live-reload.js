(function() {
    'use strict';
    
    let isReloading = false;
    let reloadTimeout = null;
    const DEBOUNCE_TIME = 200; // Wait 200ms after last change before reloading
    
    // Show connection status
    function showStatus(message, type = 'info') {
        const statusEl = document.getElementById('__palm_reload_status__');
        if (statusEl) {
            statusEl.textContent = message;
            statusEl.className = `__palm_status_${type}`;
        }
    }
    
    // Create status indicator
    function createStatusIndicator() {
        const style = document.createElement('style');
        style.textContent = `
            #__palm_reload_status__ {
                position: fixed;
                bottom: 10px;
                right: 10px;
                background: #0d6efd;
                color: white;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 11px;
                font-family: monospace;
                z-index: 99999;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                transition: all 0.3s ease;
            }
            .__palm_status_success { background: #16a34a !important; }
            .__palm_status_error { background: #dc2626 !important; }
            .__palm_status_reloading { background: #f59e0b !important; animation: pulse 1s infinite; }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.7; }
            }
        `;
        document.head.appendChild(style);
        
        const indicator = document.createElement('div');
        indicator.id = '__palm_reload_status__';
        indicator.textContent = '🔄 Hot Reload Active';
        document.body.appendChild(indicator);
    }
    
    // WebSocket connection for hot reload (no HTTP requests!)
    let ws = null;
    let reconnectAttempts = 0;
    const MAX_RECONNECT_ATTEMPTS = 10;
    const RECONNECT_DELAY = 1000;
    
    function getWebSocketPort() {
        // Try to get port from meta tag or default to 9001
        const meta = document.querySelector('meta[name="palm-ws-port"]');
        return meta ? parseInt(meta.content) : 9001;
    }
    
    function connectWebSocket() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            return; // Already connected
        }
        
        const port = getWebSocketPort();
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.hostname}:${port}`;
        
        try {
            ws = new WebSocket(wsUrl);
            
            ws.onopen = function() {
                reconnectAttempts = 0;
                showStatus('✅ Hot Reload Active', 'success');
            };
            
            ws.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    if (data.type === 'reload') {
        if (isReloading) return;
        
                        // Clear any pending reload
                        if (reloadTimeout) {
                            clearTimeout(reloadTimeout);
                        }
                        
                        // Show reloading status
                        showStatus('🔄 Reloading...', 'reloading');
                        
                    // Debounce: wait a bit before reloading
                        reloadTimeout = setTimeout(() => {
                        if (!isReloading) {
                            isReloading = true;
                                const changedFiles = data.files || [];
                                const fileList = changedFiles
                                    .map(f => f.split(/[/\\]/).pop())
                                    .slice(0, 3)
                                    .join(', ');
                                console.log(`[Palm Hot Reload] Changes detected in: ${fileList}${changedFiles.length > 3 ? '...' : ''}`);
                            window.location.reload();
                        }
                    }, DEBOUNCE_TIME);
                }
                } catch (e) {
                    console.error('[Palm Hot Reload] Error parsing message:', e);
                }
            };
            
            ws.onerror = function(error) {
                showStatus('⚠️ Connection Error', 'error');
            };
            
            ws.onclose = function() {
                showStatus('⚠️ Reconnecting...', 'error');
                
                // Attempt to reconnect
                if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
                    reconnectAttempts++;
                    setTimeout(() => {
                        connectWebSocket();
                    }, RECONNECT_DELAY * reconnectAttempts);
                } else {
                    showStatus('⚠️ Hot Reload Unavailable', 'error');
                }
            };
        } catch (error) {
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                showStatus('⚠️ Hot Reload Unavailable', 'error');
            }
        }
    }
    
    // Initialize status indicator
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createStatusIndicator);
    } else {
        createStatusIndicator();
    }
    
    // Connect WebSocket after page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(connectWebSocket, 500);
        });
    } else {
        setTimeout(connectWebSocket, 500);
    }
    
    // Reconnect when tab becomes visible
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && (!ws || ws.readyState !== WebSocket.OPEN)) {
            connectWebSocket();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        if (ws) {
            ws.close();
        }
    });
})();