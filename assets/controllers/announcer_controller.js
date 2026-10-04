import { Controller } from '@hotwired/stimulus';

/*
 * Annonce aux lecteurs d'écran le contenu affiché après une mise à jour de la zone Turbo.
 * La zone role="status" est hors de la zone remplacée, sinon elle serait recréée et non annoncée.
 */
export default class extends Controller {
    static targets = ['status', 'message'];

    announce() {
        if (!this.hasMessageTarget) {
            return;
        } const text = this.messageTarget.textContent.replace(/\s+/g, ' ').trim();
        this.statusTarget.textContent = '';
        window.setTimeout(() => {
            this.statusTarget.textContent = text;
        }, 100);
    }
}
