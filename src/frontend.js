import metadata from './block.json';
import { select } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

// Global import
const { registerCheckoutBlock, extensionCartUpdate } = wc.blocksCheckout;
const { cartStore } = window.wc.wcBlocksData;

const Block = ({ children, checkoutExtensionData }) => {
    let store = select(cartStore);
    const shippingRates = store.getCartData().shippingRates;

    const selectedPickupPointData = store.getCartData().extensions?.['sendy-pickup-point'] || null;

    if (! shouldBeVisible(selectedShippingRates(shippingRates))) {
        return <></>;
    }

    const selectedPickupPoint = (data) => {
        if (data.name === null) {
            return <></>;
        }

        return <>
            <h3 className="wc-block-components-title sendy-checkout-title">{__('Selected pickup point', 'sendy')}</h3>

            <p>
                {data.name} <br />
                {data.street} {data.number} <br/>
                {data.postal_code} {data.city}
            </p>
        </>
    };

	return <div>
        <h3 className="wc-block-components-title sendy-checkout-title">
            {__('Pick-up point', 'sendy')}
        </h3>

        <div>
            <button onClick={openPickupPointPicker}>
                {selectedPickupPointData.name ? __('Change pick-up-point', 'sendy') : __('Select pick-up-point', 'sendy')}
            </button>
        </div>

        {selectedPickupPoint(selectedPickupPointData)}
    </div>;
};

const options = {
	metadata,
	component: Block,
};

/**
 * @param shippingRates
 * @returns {boolean}
 */
const shouldBeVisible = (shippingRates) => {
    if (shippingRates === null) {
        return false;
    }

    return shippingRates[0].rate_id.startsWith("sendy_pickup_point");
};

const selectedShippingRates = (shippingRates) => {
    if (!shippingRates.length) {
        return null;
    }

    let activeShippingRates = [];

    for (let i = 0; i < shippingRates.length; i++) {
        if (! shippingRates[i].shipping_rates) {
            continue;
        }

        for (let j = 0; j < shippingRates[i].shipping_rates.length; j++) {
            activeShippingRates.push(shippingRates[i].shipping_rates[j]);
        }
    }

    return activeShippingRates.filter((shippingRate) => {
        return shippingRate.selected;
    });
}

const openPickupPointPicker = (event) => {
    event.preventDefault();

    const cartData = select(cartStore).getCartData();

    const carrier = cartData?.extensions?.['sendy-carrier']?.carrier || '';

    const data = {
        country: cartData.shippingAddress.country ?? cartData.billingAddress.country ?? 'NL',
        carriers: [carrier],
        address: cartData.shippingAddress.postcode ?? cartData.billingAddress.postcode,
    };

    window.Sendy.parcelShopPicker.open(
        data,
        (data) => {
            extensionCartUpdate({
                namespace: 'sendy-set-pickup-point',
                data: data,
            });
        },
        (errors) => {
            console.log(errors);
        }
    );
};

registerCheckoutBlock(options);
