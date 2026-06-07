( function () {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const element  = window.wp && window.wp.element;
    const settings = window.wc && window.wc.wcSettings;

    if ( ! registry || ! element || typeof registry.registerPaymentMethod !== 'function' ) {
        return;
    }

    const { registerPaymentMethod } = registry;
    const { createElement, useState, useEffect } = element;

    // Prefer the per-method settings injected by the server integration; fall
    // back to the localised payload.
    let data = window.paygatedottocryptogatewayDynamicData || {};
    if ( settings && typeof settings.getSetting === 'function' ) {
        const fromSettings = settings.getSetting(
            'paygatedotto-crypto-payment-gateway-dynamic_data',
            null
        );
        if ( fromSettings ) {
            data = fromSettings;
        }
    }

    const coins = Array.isArray( data.coins ) ? data.coins : [];

    // Shared holder for the buyer's coin choice so the processing handler can
    // read it without prop drilling.
    const selectedCoinRef = { current: coins.length ? coins[ 0 ].id : '' };

    const CoinSelector = ( props ) => {
        const { eventRegistration, emitResponse } = props;
        const { onPaymentSetup } = eventRegistration || {};

        const [ selected, setSelected ] = useState( selectedCoinRef.current );

        useEffect( () => {
            selectedCoinRef.current = selected;
        }, [ selected ] );

        // Submit the chosen coin to the server. WooCommerce copies
        // paymentMethodData into $_POST so the gateway's process_payment()
        // reads it as $_POST['paygatedotto_selected_coin'].
        useEffect( () => {
            if ( typeof onPaymentSetup !== 'function' ) {
                return;
            }
            const unsubscribe = onPaymentSetup( () => {
                if ( ! selectedCoinRef.current ) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please select a cryptocurrency to pay with.',
                    };
                }
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            paygatedotto_selected_coin: selectedCoinRef.current,
                        },
                    },
                };
            } );
            return unsubscribe;
        }, [ onPaymentSetup, emitResponse ] );

        const children = [];

        if ( data.description ) {
            children.push(
                createElement( 'div', {
                    key: 'desc',
                    className: 'paygatedottocryptogateway-method-label',
                }, '' + data.description )
            );
        }

        if ( ! coins.length ) {
            children.push(
                createElement( 'p', { key: 'none' },
                    'No cryptocurrencies are currently available.' )
            );
            return createElement( 'div', { className: 'paygatedottocryptogateway-method-wrapper' }, children );
        }

        children.push(
            createElement( 'p', { key: 'prompt' }, 'Select the coin you want to pay with:' )
        );

        coins.forEach( ( coin ) => {
            children.push(
                createElement( 'label', {
                    key: coin.id,
                    className: 'paygatedottocryptogateway-coin-option',
                    style: { display: 'flex', alignItems: 'center', gap: '8px', margin: '4px 0' },
                },
                    createElement( 'input', {
                        type: 'radio',
                        name: 'paygatedotto_selected_coin_block',
                        value: coin.id,
                        checked: selected === coin.id,
                        onChange: () => setSelected( coin.id ),
                    } ),
                    coin.logo
                        ? createElement( 'img', {
                              src: coin.logo,
                              alt: '',
                              style: { width: '20px', height: '20px' },
                          } )
                        : null,
                    createElement( 'span', null, coin.label )
                )
            );
        } );

        return createElement( 'div', { className: 'paygatedottocryptogateway-method-wrapper' }, children );
    };

    const Content = ( props ) => createElement( CoinSelector, props );

    registerPaymentMethod( {
        name: 'paygatedotto-crypto-payment-gateway-dynamic',
        paymentMethodId: 'paygatedotto-crypto-payment-gateway-dynamic',
        label: data.title || 'Cryptocurrency',
        ariaLabel: data.title || 'Cryptocurrency',
        canMakePayment: () => coins.length > 0,
        content: createElement( Content, null ),
        edit: createElement( Content, null ),
        supports: {
            features: Array.isArray( data.supports ) ? data.supports : [ 'products' ],
        },
    } );
} )();
