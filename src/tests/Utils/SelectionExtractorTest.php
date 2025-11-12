<?php

namespace PrestaShop\Module\PrestashopBulkAction\Tests\Utils;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\PrestashopBulkAction\Utils\SelectionExtractor;

class SelectionExtractorTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoParams()
    {
        $this->assertSame([], SelectionExtractor::fromParameters([]));
    }

    public function testExtractsFromSelectedArray()
    {
        $post = ['selected' => [1, '2', 3]];
        $this->assertSame([1, '2', 3], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromSelectionCsv()
    {
        $post = ['selection' => '1, 2,3'];
        $this->assertSame(['1', '2', '3'], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromIdsJsonArray()
    {
        $post = ['ids' => '[1,2,3]'];
        $this->assertSame([1, 2, 3], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromScalar()
    {
        $post = ['selected' => '42'];
        $this->assertSame(['42'], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromFloat()
    {
        $post = ['selected' => 7.0];
        $this->assertSame([7], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromProductBulkIds()
    {
        $post = ['product_bulk' => ['ids' => [10, '11']]];
        $this->assertSame([10, '11'], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromProductBulkSelectedCsv()
    {
        $post = ['product_bulk' => ['selected' => '5,6, 7']];
        $this->assertSame(['5', '6', '7'], SelectionExtractor::fromParameters($post));
    }

    public function testExtractsFromProductBulkNumericArray()
    {
        // Simule product_bulk[]=19&product_bulk[]=18 comme dans la requête fournie
        $post = ['product_bulk' => [19, 18]];
        $this->assertSame([19, 18], SelectionExtractor::fromParameters($post));
    }

    public function testEmptyStringsBecomeEmpty()
    {
        $post = ['selected' => '   '];
        $this->assertSame([], SelectionExtractor::fromParameters($post));
    }
}
