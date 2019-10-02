<?php

require_once(dirname(__DIR__) . "../../vendor/autoload.php");

use SharpSpring\RestApi\Lead;

class LeadTest extends PHPUnit\Framework\TestCase {

	public function testActiveIsBoolean()
	{
		$lead = new Lead;
		$lead->active = true;

		$this->assertInternalType("boolean", $lead->active);
	}

	public function testActiveIsNullable()
	{
		$lead = new Lead;

		// initially ValueObject sets nullable properties as a zero-value string
		$this->assertInternalType("string", $lead->active);

		$lead->active = null;
		$this->assertNull($lead->active);
	}
}
