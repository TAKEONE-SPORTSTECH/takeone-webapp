import './bootstrap';
import $ from 'jquery';
import 'jquery-cropbox';
import 'select2';
import imageCompression from 'browser-image-compression';
import Chart from 'chart.js/auto';

// Make jQuery globally available
window.$ = window.jQuery = $;
window.imageCompression = imageCompression;
window.Chart = Chart;

// Re-apply Bootstrap bridge jQuery .modal() compatibility on the Vite jQuery
// (The bridge script set it on the CDN jQuery which was overwritten above)
$.fn.modal = function(action) {
    if (window.bsModal) {
        if (action === 'show') window.bsModal.show(this[0]);
        else if (action === 'hide') window.bsModal.hide(this[0]);
    }
    return this;
};
