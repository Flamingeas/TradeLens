import { Controller } from '@hotwired/stimulus';

/*
 * Bouton qui émet un événement sur la fenêtre (window) pour commander un contrôleur situé ailleurs dans la page,
 * ex. le "+" des comptes ouvre la fenêtre d'import (événement "tl-open-import").
 */
export default class extends Controller {
    static values = { name: String };

    fire() {
        window.dispatchEvent(new CustomEvent(this.nameValue));
    }
}
