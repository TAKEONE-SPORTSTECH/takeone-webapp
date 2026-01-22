import './bootstrap';
import $ from 'jquery';
import 'jquery-cropbox';
import imageCompression from 'browser-image-compression';
import Chart from 'chart.js/auto';

// Make jQuery globally available for jquery-cropbox
window.$ = window.jQuery = $;
window.imageCompression = imageCompression;
window.Chart = Chart;
