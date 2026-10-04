import { Controller } from '@hotwired/stimulus';

/*
 * Amène l'élément à l'écran dès son affichage (bilan d'import).
 */
export default class extends Controller {
    connect() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.element.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }
}
