<?php


class HelpersTest extends AimeosTestAbstract
{
	public function testAiconfig()
	{
		$this->assertEquals( 'notexisting', aiconfig( 'not/exists', 'notexisting' ) );
	}

	public function testAicurrency()
	{
		$this->assertEquals( '$ 99.50', aicurrency( 99.50, 'USD' ) );
		$this->assertEquals( '€ 1,250.00', aicurrency( 1250.00, 'EUR' ) );
		$this->assertEquals( '¥ 5,000', aicurrency( 5000, 'JPY' ) );
		$this->assertEquals( '100.00 SEK', aicurrency( 100, 'SEK' ) );
	}

	public function testAitax()
	{
		$calc = aitax( 119.0, 'DE' );
		$this->assertEquals( 100.0, $calc['net'] );
		$this->assertEquals( 19.0, $calc['tax'] );
		$this->assertEquals( 119.0, $calc['gross'] );
		$this->assertEquals( 'DE', $calc['country'] );

		// B2B reverse charge
		$b2b = aitax( 100.0, 'FR', true, 'FR12345678901' );
		$this->assertEquals( 0.0, $b2b['tax'] );
		$this->assertTrue( $b2b['reverse_charge'] );
	}
}
