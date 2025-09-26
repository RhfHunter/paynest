class OnboardingFlow {
    constructor() {
        this.currentStep = 0;
        this.totalSteps = 4;
        this.stepContents = document.querySelectorAll('.step-content');
        this.stepDots = document.querySelectorAll('.step-dot');
        this.progressFill = document.getElementById('progressFill');
        
        this.init();
    }

    init() {
        this.setupTelegram();
        this.startFlow();
    }

    setupTelegram() {
        const tg = window.Telegram?.WebApp;
        if (tg) {
            tg.expand();
            tg.enableClosingConfirmation();
            tg.BackButton.hide();
        }
    }

    async startFlow() {
        // Step 1: Account Setup
        await this.showStep(0);
        await this.delay(1500);

        // Step 2: Referral Bonus
        await this.showStep(1);
        await this.delay(1500);

        // Step 3: Opening Bonus
        await this.showStep(2);
        await this.delay(1500);

        // Step 4: Ready
        await this.showStep(3);
    }

    async showStep(stepIndex) {
        // Hide all steps
        this.stepContents.forEach(content => content.classList.remove('active'));
        this.stepDots.forEach(dot => dot.classList.remove('active'));

        // Show current step
        this.stepContents[stepIndex].classList.add('active');
        this.stepDots[stepIndex].classList.add('active');

        // Update progress bar
        const progress = ((stepIndex + 1) / this.totalSteps) * 100;
        this.progressFill.style.width = progress + '%';

        // Add haptic feedback
        this.hapticFeedback();
    }

    hapticFeedback() {
        const tg = window.Telegram?.WebApp;
        if (tg?.HapticFeedback) {
            tg.HapticFeedback.impactOccurred('light');
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

function completeOnboarding() {
    // Save onboarding completion
    localStorage.setItem('userOnboarded', 'true');
    
    // Redirect to main app
    window.location.href = 'app.html';
}

// Initialize onboarding
document.addEventListener('DOMContentLoaded', () => {
    new OnboardingFlow();
});
