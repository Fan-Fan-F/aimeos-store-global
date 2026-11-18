<?php

class ResolveControllerTest extends AimeosTestAbstract
{
	public function testCategory()
	{
		View::addLocation( dirname( __DIR__ ) . '/fixtures/views' );

		$response = $this->action( 'GET', '\Aimeos\Shop\Controller\ResolveController@indexAction', ['site' => 'unittest', 'path' => 'tee'] );

		$this->assertResponseOk();
		$this->assertStringContainsString( '<div class="section aimeos catalog-filter', $response->getContent() );
	}


	public function testProduct()
	{
		View::addLocation( dirname( __DIR__ ) . '/fixtures/views' );

		$response = $this->action( 'GET', '\Aimeos\Shop\Controller\ResolveController@indexAction', ['site' => 'unittest', 'path' => 'Cafe_Noire_Cappuccino'] );

		$this->assertResponseOk();
		$this->assertStringContainsString( '<div class="aimeos catalog-detail', $response->getContent() );
	}


	public function testNotFound()
	{
		$response = $this->action( 'GET', '\Aimeos\Shop\Controller\ResolveController@indexAction', ['site' => 'unittest', 'path' => 'invalid'] );

		$response->assertStatus( 404 );
	}


	public function testCmsRegistration()
	{
		$controller = new \Aimeos\Shop\Controller\ResolveController();
		$ref = new \ReflectionClass( $controller );
		$prop = $ref->getProperty( 'fcn' );
		$prop->setAccessible( true );
		$fcns = $prop->getValue();

		$this->assertArrayHasKey( 'product', $fcns );
		$this->assertArrayHasKey( 'catalog', $fcns );
		$this->assertArrayHasKey( 'cms', $fcns );
	}


	protected function getEnvironmentSetUp( $app )
	{
		parent::getEnvironmentSetUp( $app );

		$app['config']->set( 'shop.client.html.catalog.detail.url.target', 'aimeos_resolve' );
	}
}