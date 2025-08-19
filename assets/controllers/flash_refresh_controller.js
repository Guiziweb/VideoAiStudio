import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["frame"];

    connect() {
        // Écouter les événements de formulaire pour refresh automatique
        document.addEventListener("turbo:submit-end", this.onFormSubmit.bind(this));
        document.addEventListener("turbo:frame-load", this.onFrameLoad.bind(this));
    }

    disconnect() {
        document.removeEventListener("turbo:submit-end", this.onFormSubmit.bind(this));
        document.removeEventListener("turbo:frame-load", this.onFrameLoad.bind(this));
    }

    onFormSubmit(event) {
        // Après soumission form, refresh le frame des flash
        setTimeout(() => {
            this.refreshFlashFrame();
        }, 100);
    }

    onFrameLoad(event) {
        // Auto-dismiss flash messages après 5 secondes
        setTimeout(() => {
            const alerts = document.querySelectorAll('[data-test-sylius-flash-message]');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            });
        }, 5000);
    }

    refreshFlashFrame() {
        const flashFrame = document.getElementById('flash-messages-frame');
        if (flashFrame) {
            flashFrame.src = flashFrame.src; // Force reload du frame
        }
    }

    // Action manuelle pour refresh
    refresh() {
        this.refreshFlashFrame();
    }
}