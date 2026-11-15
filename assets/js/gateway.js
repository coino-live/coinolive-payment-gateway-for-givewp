/*
 * Coino Live in GiveWP's visual form builder: shows the gateway's message; the donor is then redirected
 * to the Coino Live payment page by the server (no card fields, no build step).
 */
(function () {
    'use strict';

    if (!window.givewp || !window.givewp.gateways || !window.wp || !window.wp.element) {
        return;
    }

    var gateway = {
        id: 'coinolive',
        Fields: function () {
            var message = gateway.settings && gateway.settings.message ? gateway.settings.message : '';
            return window.wp.element.createElement('p', {className: 'coinolive-give-message'}, message);
        }
    };

    window.givewp.gateways.register(gateway);
})();
