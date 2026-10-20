
// Shake on invalid Enter press - prevent submission
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit') {
        e.preventDefault();
        const submitBtn = e.target.form?.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            submitBtn.classList.add('shake-animation');
            setTimeout(() => submitBtn.classList.remove('shake-animation'), 400);
        }
    }
}, true);

// Pulse on actual form submission button click
document.addEventListener('click', function (e) {
    const btn = e.target.closest('button[type="submit"], input[type="submit"]');
    if (btn) {
        btn.classList.add('pulse-success');
        // We don't necessarily need to remove it if the page is redirecting/reloading,
        // but it's good practice for AJAX forms.
        setTimeout(() => btn.classList.remove('pulse-success'), 500);
    }
});

