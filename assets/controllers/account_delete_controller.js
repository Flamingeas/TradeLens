import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation de suppression d'un compte : une seule fenêtre (<dialog>) remplie à la demande
 * avec les paramètres du bouton cliqué (data-account-delete-*-param).
 */
export default class extends Controller {
    static targets = ['dialog', 'form', 'token', 'name', 'detail'];

    connect() {
        // Referme la fenêtre avant l'instantané de page de Turbo.
        this.closeBeforeCache = () => this.close();
        document.addEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.closeBeforeCache);
    }

    ask({ params }) {
        this.formTarget.action = params.url;
        this.tokenTarget.value = params.token;
        this.nameTarget.textContent = `« ${params.name} »`;

        const trades = Number(params.trades);
        this.detailTarget.textContent = `${trades} trade${trades > 1 ? 's' : ''} et toutes les exécutions de ce compte seront supprimés définitivement. Cette action est irréversible.`;

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
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
