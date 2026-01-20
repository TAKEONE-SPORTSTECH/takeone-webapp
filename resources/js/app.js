import './bootstrap';
import $ from 'jquery';
import 'jquery-cropbox';
import imageCompression from 'browser-image-compression';

// Make jQuery globally available for jquery-cropbox
window.$ = window.jQuery = $;
window.imageCompression = imageCompression;
