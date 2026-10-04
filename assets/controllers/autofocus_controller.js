import { Controller } from '@hotwired/stimulus';

/*
 * Donne le focus à l'élément dès son affichage (résumé d'erreurs d'un formulaire).
 */
export default class extends Controller {
    connect() {
        this.element.focus();
    }
}
