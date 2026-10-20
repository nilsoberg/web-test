
import '../styles/hmm_logo.min.css';

import { Controller } from '@hotwired/stimulus';

import '../js/jquery/jquery-3.7.1.min.js';
import '../js/skylign/hmm_logo.js';
import { Scroller, EasyScroller } from '../js/skylign/scroller.js';

window.Scroller = Scroller;
window.EasyScroller = EasyScroller;

export default class extends Controller {
    connect() {
        $(this.element).hmm_logo({height_toggle: true}).toggle_scale("obs");
    }
}
