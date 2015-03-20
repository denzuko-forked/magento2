<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */
namespace Magento\SalesSequence\Test\Unit\Model;

use Magento\SalesSequence\Model\Sequence;

/**
 * Class SequenceTest
 */
class SequenceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface | \PHPUnit_Framework_MockObject_MockObject
     */
    private $adapter;
    /**
     * @var \Magento\Framework\App\Resource | \PHPUnit_Framework_MockObject_MockObject
     */
    private $resource;

    /**
     * @var \Magento\SalesSequence\Model\Sequence\Profile | \PHPUnit_Framework_MockObject_MockObject
     */
    private $profile;

    /**
     * @var \Magento\SalesSequence\Model\Sequence\Meta | \PHPUnit_Framework_MockObject_MockObject
     */
    private $meta;

    /**
     * @var \Magento\SalesSequence\Model\Sequence
     */
    private $sequence;

    protected function setUp()
    {
        $this->meta = $this->getMock(
            'Magento\SalesSequence\Model\Sequence\Meta',
            ['getSequenceTable'],
            [],
            '',
            false
        );
        $this->profile = $this->getMock(
            'Magento\SalesSequence\Model\Sequence\Profile',
            ['getSuffix', 'getPrefix', 'getStep', 'getStartValue'],
            [],
            '',
            false
        );
        $this->resource = $this->getMock(
            'Magento\Framework\App\Resource',
            ['getConnection'],
            [],
            '',
            false
        );
        $this->adapter = $this->getMockForAbstractClass(
            'Magento\Framework\DB\Adapter\AdapterInterface',
            [],
            '',
            false,
            false,
            true,
            ['insert', 'lastInsertId']
        );
        $this->resource->expects($this->any())->method('getConnection')->willReturn($this->adapter);
        $helper = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
        $this->sequence = $helper->getObject('Magento\SalesSequence\Model\Sequence', [
            'meta' => $this->meta,
            'profile' => $this->profile,
            'resource' => $this->resource,
        ]);
    }

    public function testSequenceInitialNull()
    {
        $this->assertNull($this->sequence->getCurrentValue());
    }

    public function testSequenceNextValue()
    {
        $step = 777;
        $startValue = 3;
        $lastInsertId = 3; //at this step it will represents 777
        $this->profile->expects($this->atLeastOnce())->method('getStartValue')->willReturn($startValue);
        $this->meta->expects($this->atLeastOnce())
            ->method('getSequenceTable')
            ->willReturn(
                $this->sequenceParameters()['testTable']
            );
        $this->adapter->expects($this->exactly(3))->method('insert')->with(
            $this->sequenceParameters()['testTable'],
            []
        );
        $this->profile->expects($this->exactly(3))->method('getSuffix')->willReturn(
            $this->sequenceParameters()['suffix']
        );
        $this->profile->expects($this->exactly(3))->method('getPrefix')->willReturn(
            $this->sequenceParameters()['prefix']
        );
        $this->profile->expects($this->exactly(3))->method('getStep')->willReturn($step);
        $lastInsertId = $this->nextIncrementStep($lastInsertId, 780);
        $lastInsertId = $this->nextIncrementStep($lastInsertId, 1557);
        $this->nextIncrementStep($lastInsertId, 2334);
    }

    /**
     * @param $lastInsertId
     * @param $sequenceNumber
     * @return mixed
     */
    private function nextIncrementStep($lastInsertId, $sequenceNumber)
    {
        $lastInsertId++;
        $this->adapter->expects($this->at(1))->method('lastInsertId')->with(
            $this->sequenceParameters()['testTable']
        )->willReturn(
            $lastInsertId
        );
        $this->assertEquals(
            sprintf(
                Sequence::DEFAULT_PATTERN,
                $this->sequenceParameters()['prefix'],
                $sequenceNumber,
                $this->sequenceParameters()['suffix']
            ),
            $this->sequence->getNextValue()
        );
        return $lastInsertId;
    }

    /**
     * @return array
     */
    private function sequenceParameters()
    {
        return [
            'prefix' => 'AA-',
            'suffix' => '-0',
            'testTable' => 'testSequence'
        ];
    }
}
