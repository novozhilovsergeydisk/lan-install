import './bootstrap';
import JSZip from 'jszip';

// Делаем JSZip доступным глобально
window.JSZip = JSZip;

// Отладочный код
console.log('JSZip загружен:', typeof JSZip);
console.log('window.JSZip:', window.JSZip);
