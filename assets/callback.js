'use strict';
if (window.parent !== window) {
    window.parent.postMessage({ type: 'y2k-3ds-return' }, window.location.origin);
}
