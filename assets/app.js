/*
 * Point d'entrée JavaScript, chargé par la fonction Twig importmap() dans base.html.twig.
 * Il importe la feuille de styles Tailwind, compilée par "bin/console tailwind:build",
 * et les comportements JavaScript des écrans.
 */
import './styles/app.css';
import './disponibilites.js';
import './confirmation.js';
import './bandeau-cookies.js';
