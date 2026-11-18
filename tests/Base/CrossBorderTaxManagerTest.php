<?php

use Aimeos\Shop\Base\Tax\CrossBorderTaxManager;

class CrossBorderTaxManagerTest extends AimeosTestAbstract
{
	public function testIsEuCountry()
	{
		$this->assertTrue( CrossBorderTaxManager::isEuCountry( 'DE' ) );
		$this->assertTrue( CrossBorderTaxManager::isEuCountry( 'FR' ) );
		$this->assertTrue( CrossBorderTaxManager::isEuCountry( 'nl' ) );
		$this->assertFalse( CrossBorderTaxManager::isEuCountry( 'US' ) );
		$this->assertFalse( CrossBorderTaxManager::isEuCountry( 'GB' ) );
		$this->assertFalse( CrossBorderTaxManager::isEuCountry( 'CN' ) );
	}

	public function testValidateVatId()
	{
		// Valid VAT IDs
		$this->assertTrue( CrossBorderTaxManager::validateVatId( 'DE123456789' ) );
		$this->assertTrue( CrossBorderTaxManager::validateVatId( 'FR12345678901' ) );
		$this->assertTrue( CrossBorderTaxManager::validateVatId( 'NL123456789B01' ) );

		// Invalid VAT IDs
		$this->assertFalse( CrossBorderTaxManager::validateVatId( 'DE123' ) ); // too short
		$this->assertFalse( CrossBorderTaxManager::validateVatId( 'US123456789' ) ); // non-EU
		$this->assertFalse( CrossBorderTaxManager::validateVatId( '' ) );
	}

	public function testReverseChargeEligibility()
	{
		// Intra-community B2B between Germany (seller) and France (buyer with valid VAT)
		$this->assertTrue( CrossBorderTaxManager::isEligibleForReverseCharge( 'DE', 'FR', 'FR12345678901' ) );

		// Domestic transaction within same country is not reverse charge
		$this->assertFalse( CrossBorderTaxManager::isEligibleForReverseCharge( 'DE', 'DE', 'DE123456789' ) );

		// Non-EU buyer is export, not EU reverse charge
		$this->assertFalse( CrossBorderTaxManager::isEligibleForReverseCharge( 'DE', 'US', 'US123456789' ) );

		// Missing VAT ID cannot reverse charge
		$this->assertFalse( CrossBorderTaxManager::isEligibleForReverseCharge( 'DE', 'FR', '' ) );
	}

	public function testGetTaxRate()
	{
		// Standard rates
		$this->assertEquals( 19.0, CrossBorderTaxManager::getTaxRate( 'DE' ) );
		$this->assertEquals( 20.0, CrossBorderTaxManager::getTaxRate( 'FR' ) );
		$this->assertEquals( 20.0, CrossBorderTaxManager::getTaxRate( 'GB' ) );
		$this->assertEquals( 10.0, CrossBorderTaxManager::getTaxRate( 'JP' ) );
		$this->assertEquals( 0.0, CrossBorderTaxManager::getTaxRate( 'HK' ) );

		// Reverse charge 0% for qualified B2B
		$b2bOptions = [
			'seller_country' => 'DE',
			'is_b2b' => true,
			'vat_id' => 'FR12345678901',
		];
		$this->assertEquals( 0.0, CrossBorderTaxManager::getTaxRate( 'FR', $b2bOptions ) );
	}

	public function testCalculateTax()
	{
		// 100 EUR gross in Germany (19% VAT) -> ~84.03 net + 15.97 tax
		$res = CrossBorderTaxManager::calculate( 119.0, 'DE', ['price_includes_tax' => true] );
		$this->assertEquals( 100.0, $res['net'] );
		$this->assertEquals( 19.0, $res['tax'] );
		$this->assertEquals( 119.0, $res['gross'] );
		$this->assertEquals( 19.0, $res['rate'] );
		$this->assertFalse( $res['reverse_charge'] );

		// 100 EUR net with reverse charge B2B -> 0% tax
		$b2b = CrossBorderTaxManager::calculate( 100.0, 'FR', [
			'seller_country' => 'DE',
			'is_b2b' => true,
			'vat_id' => 'FR12345678901',
			'price_includes_tax' => false,
		] );
		$this->assertEquals( 100.0, $b2b['net'] );
		$this->assertEquals( 0.0, $b2b['tax'] );
		$this->assertEquals( 100.0, $b2b['gross'] );
		$this->assertTrue( $b2b['reverse_charge'] );
	}

	public function testGetDefaultCurrencyForCountry()
	{
		$this->assertEquals( 'USD', CrossBorderTaxManager::getDefaultCurrencyForCountry( 'US' ) );
		$this->assertEquals( 'GBP', CrossBorderTaxManager::getDefaultCurrencyForCountry( 'GB' ) );
		$this->assertEquals( 'EUR', CrossBorderTaxManager::getDefaultCurrencyForCountry( 'DE' ) );
		$this->assertEquals( 'JPY', CrossBorderTaxManager::getDefaultCurrencyForCountry( 'JP' ) );
		$this->assertEquals( 'CNY', CrossBorderTaxManager::getDefaultCurrencyForCountry( 'CN' ) );
		$this->assertNull( CrossBorderTaxManager::getDefaultCurrencyForCountry( 'ZZ' ) );
	}
}
