( function () {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const element = window.wp && window.wp.element;

    if ( ! registry || ! element || typeof registry.registerPaymentMethod !== 'function' ) {
        return;
    }

    const { registerPaymentMethod } = registry;
    const { createElement } = element;
    const paygatedottocryptogateways = window.paygatedottocryptogatewayData || [];

    const buildContent = ( paygatedottocryptogateway ) =>
        createElement(
            'div',
            { className: 'paygatedottocryptogateway-method-wrapper' },
            createElement(
                'div',
                { className: 'paygatedottocryptogateway-method-label' },
                '' + ( paygatedottocryptogateway.description || '' )
            ),
            paygatedottocryptogateway.icon_url
                ? createElement( 'img', {
                      src: paygatedottocryptogateway.icon_url,
                      alt: paygatedottocryptogateway.label,
                      className: 'paygatedottocryptogateway-method-icon',
                  } )
                : null
        );

    paygatedottocryptogateways.forEach( ( paygatedottocryptogateway ) => {
        registerPaymentMethod( {
            name: paygatedottocryptogateway.id,
            paymentMethodId: paygatedottocryptogateway.id,
            label: paygatedottocryptogateway.label,
            ariaLabel: paygatedottocryptogateway.label,
            canMakePayment: () => true,
            content: buildContent( paygatedottocryptogateway ),
            edit: buildContent( paygatedottocryptogateway ),
            supports: {
                features: [ 'products' ],
            },
        } );
    } );
} )();
