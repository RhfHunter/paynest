// onboarding.js
class Onboarding {
    constructor() {
        this.steps = document.querySelectorAll('.step');
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
        this.currentStep = 0;
        this.totalSteps = this.steps.length;
        
        this.init();
    }

    init() {
        this.showStep(0);
        this.startOnboarding();
    }

    showStep(stepIndex) {
        // Hide all steps
        this.steps.forEach(step => step.classList.remove('active'));
        
        // Show current step
        this.steps[stepIndex].classList.add('active');
        
        // Update progress
        this.updateProgress((stepIndex + 1) / this.totalSteps * 100);
    }

    updateProgress(percentage) {
        this.progressFill.style.width = percentage + '%';
        this.progressText.textContent = Math.round(percentage) + '%';
    }

    async startOnboarding() {
        // Step 1: Account Setup
        await this.delay(2000);
        this.showStep(1);
        
        // Step 2: Referral Bonus
        await this.delay(2000);
        this.showStep(2);
        
        // Step 3: Opening Bonus
        await this.delay(2000);
        this.showStep(3);
        
        // Step 4: Completion
        await this.delay(2000);
        
        // Redirect to main app
        this.completeOnboarding();
    }

    async completeOnboarding() {
        // Show completion animation
        this.progressFill.style.background = '#4CAF50';
        this.progressText.textContent = 'Complete!';
        
        await this.delay(1000);
        
        // Redirect to main app
        window.location.href = 'index.html';
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Telegram WebApp Initialization
const tg = window.Telegram.WebApp;

// Initialize onboarding when page loads
document.addEventListener('DOMContentLoaded', () => {
    tg.expand();
    tg.enableClosingConfirmation();
    
    new Onboarding();
});
