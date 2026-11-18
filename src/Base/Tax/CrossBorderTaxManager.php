<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 * @copyright Aimeos (aimeos.org), 2015-2026
 */

namespace Aimeos\Shop\Base\Tax;


/**
 * Manager for cross-border taxation, VAT OSS, and international currency resolution.
 */
class CrossBorderTaxManager
{
	/**
	 * Default standard VAT / sales tax rates by ISO 3166-1 alpha-2 country code.
	 * Includes EU-27 VAT rates (One-Stop Shop OSS) and major global trade markets.
	 *
	 * @var array<string, float>
	 */
	protected static array $standardRates = [
		// European Union (EU OSS rates)
		'AT' => 20.0, 'BE' => 21.0, 'BG' => 20.0, 'CY' => 19.0, 'CZ' => 21.0,
		'DE' => 19.0, 'DK' => 25.0, 'EE' => 22.0, 'ES' => 21.0, 'FI' => 25.5,
		'FR' => 20.0, 'GR' => 24.0, 'HR' => 25.0, 'HU' => 27.0, 'IE' => 23.0,
		'IT' => 22.0, 'LT' => 21.0, 'LU' => 17.0, 'LV' => 21.0, 'MT' => 18.0,
		'NL' => 21.0, 'PL' => 23.0, 'PT' => 23.0, 'RO' => 19.0, 'SE' => 25.0,
		'SI' => 22.0, 'SK' => 20.0,

		// Non-EU Europe
		'GB' => 20.0, 'CH' => 8.1, 'NO' => 25.0, 'IS' => 24.0,

		// Americas
		'US' => 7.25, // Average combined state & local sales tax
		'CA' => 5.0,  // Federal GST base
		'MX' => 16.0, 'BR' => 17.0,

		// Asia-Pacific & Oceania
		'AU' => 10.0, 'NZ' => 15.0, 'JP' => 10.0, 'SG' => 9.0,
		'KR' => 10.0, 'CN' => 13.0, 'HK' => 0.0,  'TW' => 5.0,
		'MY' => 10.0, 'TH' => 7.0,  'VN' => 10.0, 'IN' => 18.0,

		// Middle East & Africa
		'AE' => 5.0,  'SA' => 15.0, 'ZA' => 15.0,
	];

	/**
	 * Default national currency map by ISO 3166-1 alpha-2 country code.
	 *
	 * @var array<string, string>
	 */
	protected static array $countryCurrencies = [
		'US' => 'USD', 'GB' => 'GBP', 'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD',
		'JP' => 'JPY', 'CN' => 'CNY', 'HK' => 'HKD', 'TW' => 'TWD', 'SG' => 'SGD',
		'CH' => 'CHF', 'NO' => 'NOK', 'SE' => 'SEK', 'DK' => 'DKK', 'PL' => 'PLN',
		'KR' => 'KRW', 'IN' => 'INR', 'BR' => 'BRL', 'MX' => 'MXN', 'AE' => 'AED',
		'SA' => 'SAR', 'ZA' => 'ZAR',
		// Eurozone countries
		'AT' => 'EUR', 'BE' => 'EUR', 'CY' => 'EUR', 'DE' => 'EUR', 'EE' => 'EUR',
		'ES' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR', 'GR' => 'EUR', 'HR' => 'EUR',
		'IE' => 'EUR', 'IT' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'LV' => 'EUR',
		'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SI' => 'EUR', 'SK' => 'EUR',
	];

	/**
	 * List of EU ISO country codes for intra-community reverse-charge checking.
	 *
	 * @var array<string>
	 */
	protected static array $euCountries = [
		'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
		'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
		'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
	];

	/**
	 * Regex syntax rules for validating EU VAT ID format.
	 *
	 * @var array<string, string>
	 */
	protected static array $vatRegex = [
		'AT' => '/^ATU[0-9]{8}$/',
		'BE' => '/^BE(0|1)[0-9]{9}$/',
		'BG' => '/^BG[0-9]{9,10}$/',
		'CY' => '/^CY[0-9]{8}[A-Z]$/',
		'CZ' => '/^CZ[0-9]{8,10}$/',
		'DE' => '/^DE[0-9]{9}$/',
		'DK' => '/^DK[0-9]{8}$/',
		'EE' => '/^EE[0-9]{9}$/',
		'ES' => '/^ES[A-Z0-9][0-9]{7}[A-Z0-9]$/',
		'FI' => '/^FI[0-9]{8}$/',
		'FR' => '/^FR[A-Z0-9]{2}[0-9]{9}$/',
		'GR' => '/^EL[0-9]{9}$/',
		'HR' => '/^HR[0-9]{11}$/',
		'HU' => '/^HU[0-9]{8}$/',
		'IE' => '/^IE([0-9]{7}[A-Z]{1,2}|[0-9][A-Z][0-9]{5}[A-Z])$/',
		'IT' => '/^IT[0-9]{11}$/',
		'LT' => '/^LT([0-9]{9}|[0-9]{12})$/',
		'LU' => '/^LU[0-9]{8}$/',
		'LV' => '/^LV[0-9]{11}$/',
		'MT' => '/^MT[0-9]{8}$/',
		'NL' => '/^NL[0-9]{9}B[0-9]{2}$/',
		'PL' => '/^PL[0-9]{10}$/',
		'PT' => '/^PT[0-9]{9}$/',
		'RO' => '/^RO[0-9]{2,10}$/',
		'SE' => '/^SE[0-9]{12}$/',
		'SI' => '/^SI[0-9]{8}$/',
		'SK' => '/^SK[0-9]{10}$/',
	];


	/**
	 * Returns whether the given country is an EU member state.
	 *
	 * @param string $countryCode ISO 3166-1 alpha-2 code
	 * @return bool
	 */
	public static function isEuCountry( string $countryCode ) : bool
	{
		return in_array( strtoupper( trim( $countryCode ) ), self::$euCountries, true );
	}


	/**
	 * Validates the syntax of an EU VAT identification number.
	 *
	 * @param string $vatId VAT number with country prefix (e.g. DE123456789)
	 * @param string|null $country Optional expected country code
	 * @return bool
	 */
	public static function validateVatId( string $vatId, ?string $country = null ) : bool
	{
		$clean = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', $vatId ) ?? '' );

		if( strlen( $clean ) < 4 ) {
			return false;
		}

		$countryCode = $country ? strtoupper( trim( $country ) ) : substr( $clean, 0, 2 );

		// Greece uses EL for VAT
		if( $countryCode === 'GR' && substr( $clean, 0, 2 ) === 'EL' ) {
			$countryCode = 'GR';
		}

		if( !isset( self::$vatRegex[$countryCode] ) ) {
			return false;
		}

		return (bool) preg_match( self::$vatRegex[$countryCode], $clean );
	}


	/**
	 * Checks if an intra-community B2B transaction is eligible for zero-rate reverse charge VAT.
	 *
	 * @param string $sellerCountry Country of the merchant/store
	 * @param string $buyerCountry Country of the buyer/destination
	 * @param string|null $buyerVatId Buyer's VAT identification number
	 * @return bool
	 */
	public static function isEligibleForReverseCharge( string $sellerCountry, string $buyerCountry, ?string $buyerVatId = null ) : bool
	{
		$seller = strtoupper( trim( $sellerCountry ) );
		$buyer = strtoupper( trim( $buyerCountry ) );

		// Both must be distinct EU countries
		if( $seller === $buyer || !self::isEuCountry( $seller ) || !self::isEuCountry( $buyer ) ) {
			return false;
		}

		if( empty( $buyerVatId ) ) {
			return false;
		}

		return self::validateVatId( $buyerVatId, $buyer );
	}


	/**
	 * Returns the effective tax rate percentage for a given country and context.
	 *
	 * @param string $countryCode Destination country code
	 * @param array $options Additional options: is_b2b, vat_id, seller_country, rate_override
	 * @return float Tax rate percentage (e.g. 19.0 for 19%)
	 */
	public static function getTaxRate( string $countryCode, array $options = [] ) : float
	{
		$country = strtoupper( trim( $countryCode ) );
		$sellerCountry = strtoupper( (string) ( $options['seller_country'] ?? config( 'shop.crossborder.seller_country', 'DE' ) ) );
		$isB2B = (bool) ( $options['is_b2b'] ?? false );
		$vatId = isset( $options['vat_id'] ) ? (string) $options['vat_id'] : null;

		// Check for EU intra-community reverse charge (0% tax)
		if( $isB2B && $vatId && self::isEligibleForReverseCharge( $sellerCountry, $country, $vatId ) ) {
			return 0.0;
		}

		// User configuration overrides
		$customRates = (array) config( 'shop.crossborder.tax_rates', [] );
		if( isset( $customRates[$country] ) ) {
			return (float) $customRates[$country];
		}

		return self::$standardRates[$country] ?? (float) config( 'shop.crossborder.default_tax_rate', 0.0 );
	}


	/**
	 * Calculates tax amount and breakdown for a given amount and country.
	 *
	 * @param float $amount Gross or Net monetary amount
	 * @param string $countryCode Destination country code
	 * @param array $options Calculation options: price_includes_tax (bool), is_b2b (bool), vat_id (string)
	 * @return array{net: float, tax: float, gross: float, rate: float, reverse_charge: bool, country: string}
	 */
	public static function calculate( float $amount, string $countryCode, array $options = [] ) : array
	{
		$country = strtoupper( trim( $countryCode ) );
		$sellerCountry = strtoupper( (string) ( $options['seller_country'] ?? config( 'shop.crossborder.seller_country', 'DE' ) ) );
		$isB2B = (bool) ( $options['is_b2b'] ?? false );
		$vatId = isset( $options['vat_id'] ) ? (string) $options['vat_id'] : null;
		$priceIncludesTax = (bool) ( $options['price_includes_tax'] ?? config( 'shop.crossborder.price_includes_tax', true ) );

		$rate = self::getTaxRate( $country, $options );
		$reverseCharge = ( $isB2B && $vatId && self::isEligibleForReverseCharge( $sellerCountry, $country, $vatId ) );

		if( $rate <= 0.0 )
		{
			$net = round( $amount, 2 );
			return [
				'net' => $net,
				'tax' => 0.0,
				'gross' => $net,
				'rate' => 0.0,
				'reverse_charge' => $reverseCharge,
				'country' => $country,
			];
		}

		if( $priceIncludesTax )
		{
			$gross = round( $amount, 2 );
			$net = round( $gross / ( 1 + ( $rate / 100 ) ), 2 );
			$tax = round( $gross - $net, 2 );
		}
		else
		{
			$net = round( $amount, 2 );
			$tax = round( $net * ( $rate / 100 ), 2 );
			$gross = round( $net + $tax, 2 );
		}

		return [
			'net' => $net,
			'tax' => $tax,
			'gross' => $gross,
			'rate' => $rate,
			'reverse_charge' => $reverseCharge,
			'country' => $country,
		];
	}


	/**
	 * Returns the default ISO currency code for a given country code.
	 *
	 * @param string $countryCode ISO 3166-1 alpha-2 code
	 * @return string|null
	 */
	public static function getDefaultCurrencyForCountry( string $countryCode ) : ?string
	{
		$code = strtoupper( trim( $countryCode ) );
		return self::$countryCurrencies[$code] ?? null;
	}
}
