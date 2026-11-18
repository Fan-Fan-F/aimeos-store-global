<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 * @copyright Aimeos (aimeos.org), 2017-2023
 */


if( !function_exists( 'airoute' ) )
{
	/**
	 * Generate the URL to a named route.
	 *
	 * @param  array|string  $name
	 * @param  mixed  $parameters
	 * @param  bool  $absolute
	 * @return string
	 */
	function airoute( $name, $parameters = [], $absolute = true )
	{
		$isMultiShop = config( 'shop.multishop' ) ?? config( 'app.shop_multishop', false );
		$site = $isMultiShop ? config( 'shop.mshop.locale.site', 'default' ) : null;

		if( $current = Route::current() )
		{
			$parameters['site'] ??= $current->parameter( 'site', Request::get( 'site', $site ) );
			$parameters['locale'] ??= $current->parameter( 'locale', Request::get( 'locale' ) );
			$parameters['currency'] ??= $current->parameter( 'currency', Request::get( 'currency' ) );
		}
		else
		{
			$parameters['site'] ??= Request::get( 'site', $site );
			$parameters['locale'] ??= Request::get( 'locale', app()->getLocale() );
			$parameters['currency'] ??= Request::get( 'currency', (string) config( 'shop.mshop.locale.currency', '' ) );
		}

		return app( 'url' )->route( $name, array_filter( $parameters ), $absolute );
	}
}


if( !function_exists( 'aiconfig' ) )
{
	/**
	 * Returns the configuration setting for the given key
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if the configuration key isn't found
	 * @return mixed Configuration value
	 */
	function aiconfig( $key, $default = null )
	{
		return app( 'aimeos.config' )->get()->get( $key, $default );
	}
}


if( !function_exists( 'aitrans' ) )
{
	/**
	 * Translates the given message
	 *
	 * @param string $singular Message to translate
	 * @param array $params List of paramters for replacing the placeholders in that order
	 * @param string $domain Translation domain
	 * @param string $locale ISO language code, maybe combine with ISO currency code, e.g. "en_US"
	 * @return string Translated string
	 */
	function aitrans( $singular, array $params = array(), $domain = 'client', $locale = null )
	{
		$i18n = app( 'aimeos.context' )->get()->i18n( $locale );
		$format = $i18n->dt( $domain, $singular );

		try {
			return vsprintf( $format, $params );
		} catch( \Throwable $e ) {
			return $format;
		}
	}
}


if( !function_exists( 'aitransplural' ) )
{
	/**
	 * Translates the given messages based on the number
	 *
	 * @param string $singular Message to translate
	 * @param string $plural Message for plural translations
	 * @param integer $number Count of items to chose the correct plural translation
	 * @param array $params List of paramters for replacing the placeholders in that order
	 * @param string $domain Translation domain
	 * @param string $locale ISO language code, maybe combine with ISO currency code, e.g. "en_US"
	 * @return string Translated string
	 */
	function aitransplural( $singular, $plural, $number, array $params = array(), $domain = 'client', $locale = null )
	{
		$i18n = app( 'aimeos.context' )->get()->i18n( $locale );
		$format = $i18n->dn( $domain, $singular, $plural, $number );

		try {
			return vsprintf( $format, $params );
		} catch( \Throwable $e ) {
			return $format;
		}
	}
}


if( !function_exists( 'aicurrency' ) )
{
	/**
	 * Formats a monetary amount into internationalized currency display string.
	 *
	 * @param float|int|string $amount Numeric amount
	 * @param string|null $currency ISO 4217 currency code (e.g. USD, EUR, GBP)
	 * @param string|null $locale ISO locale string (e.g. en_US, de_DE)
	 * @return string Formatted currency string
	 */
	function aicurrency( $amount, ?string $currency = null, ?string $locale = null ) : string
	{
		$currency = $currency ?: (string) ( config( 'shop.mshop.locale.currency' ) ?: 'USD' );
		$num = (float) $amount;

		$symbols = [
			'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
			'CNY' => '¥', 'CAD' => 'CA$', 'AUD' => 'AU$', 'CHF' => 'CHF',
		];

		$decimals = in_array( strtoupper( $currency ), ['JPY', 'KRW'], true ) ? 0 : 2;
		$formatted = number_format( $num, $decimals, '.', ',' );

		if( isset( $symbols[$currency] ) ) {
			return $symbols[$currency] . ' ' . $formatted;
		}

		return $formatted . ' ' . $currency;
	}
}


if( !function_exists( 'aitax' ) )
{
	/**
	 * Calculates cross-border tax breakdown for a monetary amount and country.
	 *
	 * @param float|int|string $amount Gross or Net amount
	 * @param string|null $country Destination country code (ISO 3166-1 alpha-2)
	 * @param bool $isB2B Whether customer is verified business
	 * @param string|null $vatId Optional customer VAT identification number
	 * @return array{net: float, tax: float, gross: float, rate: float, reverse_charge: bool, country: string}
	 */
	function aitax( $amount, ?string $country = null, bool $isB2B = false, ?string $vatId = null ) : array
	{
		$country = $country ?: (string) config( 'shop.crossborder.seller_country', 'DE' );
		return \Aimeos\Shop\Base\Tax\CrossBorderTaxManager::calculate( (float) $amount, $country, [
			'is_b2b' => $isB2B,
			'vat_id' => $vatId,
		] );
	}
}
