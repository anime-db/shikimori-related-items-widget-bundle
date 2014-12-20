<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Tests\Event\Listener;

use AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Event\Listener\Widget;
use AnimeDb\Bundle\CatalogBundle\Controller\ItemController;

/**
 * Test widget
 *
 * @package AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Tests\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class WidgetTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Widget
     *
     * @var \AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Event\Listener\Widget
     */
    protected $widget;

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        $this->widget = new Widget();
    }

    /**
     * Get places
     *
     * @return array
     */
    public function getPlaces()
    {
        return [
            [ItemController::WIDGET_PALCE_BOTTOM],
            [ItemController::WIDGET_PALCE_IN_CONTENT],
            [ItemController::WIDGET_PALCE_RIGHT]
        ];
    }

    /**
     * Test on get widget
     *
     * @dataProvider getPlaces
     *
     * @param string $place
     */
    public function testOnGetWidget($place)
    {
        $event = $this->getMockBuilder('\AnimeDb\Bundle\AppBundle\Event\Widget\Get')
            ->disableOriginalConstructor()
            ->getMock();
        $event
            ->expects($this->once())
            ->method('getPlace')
            ->willReturn($place);
        if ($place == ItemController::WIDGET_PALCE_BOTTOM) {
            $event
                ->expects($this->once())
                ->method('registr')
                ->with('AnimeDbShikimoriRelatedItemsWidgetBundle:Widget:index');
        } else {
            $event
                ->expects($this->never())
                ->method('registr');
        }

        $this->widget->onGetWidget($event);
    }
}
