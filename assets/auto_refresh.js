// Auto-refresh page every 15 seconds unless user is actively typing or uploading
setInterval(() => {
    const active = document.activeElement;
    const isTyping = active && (
        active.tagName === 'INPUT' ||
        active.tagName === 'TEXTAREA' ||
        active.tagName === 'SELECT'
    );
    if (!isTyping) {
        window.location.reload();
    }
}, 15000);