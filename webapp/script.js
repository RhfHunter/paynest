// script.js - Updated with onboarding check
const tg = window.Telegram.WebApp;
const API_BASE = 'https://your-worker.workers.dev';

class TapBot {
    constructor() {
        this.userId = tg.initDataUnsafe.user.id;
        this.isNewUser = true; // Check from API later
        
        this.init();
    }

    async init() {
        tg.expand();
        tg.enableClosingConfirmation();

        // Check if user needs onboarding
        if (this.isNewUser) {
            window.location.href = 'onboarding.html';
            return;
        }

        await this.loadBalance();
        this.setupEventListeners();
    }

    // ... rest of your existing tap bot code
}

// Start the app
new TapBot();
