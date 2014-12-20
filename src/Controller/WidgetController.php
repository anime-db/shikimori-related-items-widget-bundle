<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;

/**
 * Similar items widget
 *
 * @package AnimeDb\Bundle\ShikimoriRelatedItemsWidgetBundle\Controller
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class WidgetController extends Controller
{
    /**
     * API path for get related items
     *
     * @var string
     */
    const PATH_RELATED_ITEMS = '/animes/#ID#/related';

    /**
     * Cache lifetime 1 week
     *
     * @var integer
     */
    const CACHE_LIFETIME = 604800;

    /**
     * New items
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Item $item, Request $request)
    {
        /* @var $response \Symfony\Component\HttpFoundation\Response */
        $response = $this->get('cache_time_keeper')->getResponse($item->getDateUpdate(), self::CACHE_LIFETIME);
        /* @var $widget \AnimeDb\Bundle\ShikimoriWidgetBundle\Service\Widget */
        $widget = $this->get('anime_db.shikimori.widget');

        // get shikimori item id
        if (!($item_id = $widget->getItemId($item))) {
            return $response;
        }

        $list = $this->get('anime_db.shikimori.browser')
            ->get(str_replace('#ID#', $item_id, self::PATH_RELATED_ITEMS));
        $list = $this->filter($list);

        $response->setEtag($this->hash($list));
        // response was not modified for this request
        if ($response->isNotModified($request) || !$list) {
            return $response;
        }

        // build list item entities
        foreach ($list as $key => $item) {
            $list[$key] = $widget->getWidgetItem($widget->getItem($item['anime']['id']));
            // add relation
            if (substr($this->container->getParameter('locale'), 0, 2) == 'ru') {
                $list[$key]->setName($list[$key]->getName().' ('.$item['relation_russian'].')');
            } else {
                $list[$key]->setName($list[$key]->getName().' ('.$item['relation'].')');
            }
        }

        return $this->render(
            'AnimeDbShikimoriRelatedItemsWidgetBundle:Widget:index.html.twig',
            ['items' => $list],
            $response
        );
    }

    /**
     * Filter a list items
     *
     * @param array $list
     *
     * @return array
     */
    protected function filter(array $list)
    {
        $tmp = [];
        foreach ($list as $item) {
            if ($item['anime']) {
                $tmp[] = $item;
            }
        }
        return $tmp;
    }

    /**
     * Get hash of list items
     *
     * @param array $list
     *
     * @return string
     */
    protected function hash(array $list)
    {
        $ids = '';
        foreach ($list as $item) {
            $ids .= ':'.$item['anime']['id'];
        }
        return md5($ids);
    }
}
