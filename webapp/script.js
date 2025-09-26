// GitHub Pages এর জন্য configuration
const tg = window.Telegram.WebApp;

// Cloudflare Worker URL - আপনার actual worker URL দিবেন
const API_BASE = 'https://your-bot-api.your-account.workers.dev';

// বাকি code একই থাকবে...
const userId = tg.initDataUnsafe.user.id || 'test-user';

async function loadBalance() {
    try {
        const response = await fetch(`${API_BASE}/api/balance/${userId}`);
        const data = await response.json();
        document.getElementById('balance').textContent = data.balance;
    } catch (error) {
        console.error('Error loading balance:', error);
    }
}

// বাকি functions same...
