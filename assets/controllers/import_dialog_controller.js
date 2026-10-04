import { Controller } from '@hotwired/stimulus';

/*
 * Fenêtre d'import : <dialog> natif ouvert en modale, avec fermeture par clic sur le fond
 * et retour propre quand Turbo met la page en cache.
 */
export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        // Referme la fenêtre avant l'instantané de page de Turbo.
        this.closeBeforeCache = () => this.close();
        document.addEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    open() {
        if (typeof this.dialogTarget.showModal === 'function') {
            this.dialogTarget.showModal();
        } else {
            this.dialogTarget.setAttribute('open', '');
        }
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    // Un clic sur le fond a pour cible le <dialog> lui-même.
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
